<?php

namespace SMXD\App\Controllers\API;

use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;
use \SMXD\App\Controllers\ModuleApiController;
use SMXD\App\Models\CompanyType;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\UserGroup;
use SMXD\App\Models\UserInterfaceVersion;
use SMXD\App\Models\UserLogin;
use SMXD\App\Models\UserProfile;
use SMXD\App\Models\MediaAttachment;
use SMXD\App\Models\UserSetting;
use SMXD\App\Models\UserSettingDefault;
use SMXD\App\Models\UserSettingGroup;
use SMXD\App\Module;
use Phalcon\Di;
use SMXD\Application\Aws\AwsCognito\CognitoClient;
use Aws\Exception\AwsException;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ProfileController extends BaseController
{
    /**
     * @Route("/profile", paths={module="gms"}, methods={"GET"}, name="gms-profile-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('index');
        $profile_array = ModuleModel::$user->getParsedArray();
        $login = ModuleModel::$user->getUserLogin();
        $result = [
            'success' => true,
            'profile' => $profile_array,
            // 'avatar' => $profile_array['avatar'],
            // 'avatar_url' => ModuleModel::$user->getAvatarUrl(),
            'login' => $login instanceof UserLogin ? $login->toArray() : []
        ];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function updateAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = [
            'success' => false,
            'message' => 'PROFILE_SAVE_FAIL_TEXT',
        ];

        $dataInput = $this->request->getJsonRawBody(true);
        $user_profile_uuid = isset($dataInput['uuid']) && $dataInput['uuid'] != '' ? $dataInput['uuid'] : "";

        if ($user_profile_uuid != '') {
            $userProfile = ModuleModel::$user;

            if ($userProfile->getUuid() == $user_profile_uuid) {
                $modelResult = $userProfile->saveUserProfile($dataInput);
                if ($modelResult instanceof UserProfile) {
                    $result = [
                        'success' => true,
                        'message' => 'USER_PROFILE_SAVE_SUCCESS_TEXT',
                        'data' => $modelResult,
                    ];
                } else {
                    $result = $modelResult;
                }
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function saveSettingAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');

        $name = Helpers::__getRequestValue('name');
        $value = Helpers::__getRequestValue('value');
        $user_setting_default_id = Helpers::__getRequestValue('id');

        if ($user_setting_default_id > 0) {
            $userConfigDefault = UserSettingDefault::findFirstById($user_setting_default_id);
        } else {
            $userConfigDefault = UserSettingDefault::findFirstByName($name);
        }

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($userConfigDefault) {
            $userSetting = UserSetting::findFirst([
                'conditions' => 'user_profile_id = :user_profile_id: AND user_setting_default_id = :user_setting_default_id:',
                'bind' => [
                    'user_profile_id' => ModuleModel::$user->getId(),
                    'user_setting_default_id' => $userConfigDefault->getId(),
                ]
            ]);

            if (!$userSetting) {
                $userSetting = new UserSetting();
            }

            if ($value != $userSetting->getValue()) {
                $data = [
                    'user_profile_id' => ModuleModel::$user->getId(),
                    'user_setting_default_id' => $userConfigDefault->getId(),
                    'value' => $value,
                    'name' => $userConfigDefault->getName(),
                ];
                $userSetting->setData($data);
                $resultAppSetting = $userSetting->__quickSave();

                if ($resultAppSetting['success'] == true) {
                    $return = ['success' => true, 'message' => 'SAVE_SETTING_SUCCESS_TEXT'];
                } else {
                    $return = ['success' => false, 'message' => 'SAVE_SETTING_FAIL_TEXT'];
                }
            } else {
                $return = ['success' => true, 'message' => 'NO_CHANGES_TEXT'];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function settingsAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $return = ['success' => true, 'message' => 'NO_CHANGES_TEXT'];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return mixed
     */
    public function getSettingGroupAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $settingGroups = UserSettingGroup::find([
            'conditions' => 'visible = :visible: AND company_type_id = :company_type_id:',
            'bind' => [
                'visible' => UserSettingGroup::STATUS_ACTIVE,
                'company_type_id' => CompanyType::TYPE_GMS
            ],
            'order' => 'position ASC'
        ]);
        $settingGroupsArray = [];
        foreach ($settingGroups as $groupItem) {
            $group = $groupItem->toArray();
            $group['settings'] = $groupItem->getSettingList();
            if (count($group['settings']) == 0) {
                $group['visible'] = false;
            } else {
                $group['visible'] = ($groupItem->getVisible() == 1);
            }
            $settingGroupsArray[$groupItem->getName()] = $group;
        }
        $this->response->setJsonContent(['success' => true, 'data' => array_values($settingGroupsArray)], JSON_ERROR_UTF8);
        return $this->response->send();
    }


    /**
     * get pusher variables and AMAZON
     */
    public function getSettingVariablesAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        // $settingAppList = ModuleModel::$user->getUserSetting();
        $variables = [];
        // foreach ($settingAppList as $item) {
        //     $variables[$item->getName()] = $item->getValue();
        // }
        $this->response->setJsonContent(['success' => true, 'data' => $variables], JSON_ERROR_UTF8);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function changePasswordAction()
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'POST']);

        $data = Helpers::__getRequestValues();
        $uuid = Helpers::__getRequestValue('uuid') != '' ? Helpers::__getRequestValue('uuid') : false;
        $user_login_id = Helpers::__getRequestValue('user_login_id') != '' ? Helpers::__getRequestValue('user_login_id') : false;
        $user_login_password = Helpers::__getRequestValue('user_login_password ') != '' ? Helpers::__getRequestValue('user_login_password ') : false;
        $user_login_old_password = Helpers::__getRequestValue('user_login_old_password ') != '' ? Helpers::__getRequestValue('user_login_old_password ') : false;
        $user_login_email = Helpers::__getRequestValue('user_login_email ') != '' ? Helpers::__getRequestValue('user_login_email ') : false;
        $token_key = Helpers::__getHeaderValue(Helpers::TOKEN_KEY);
        $result = ['success' => false, 'message' => 'LOGIN_NOT_FOUND_TEXT'];


        $userProfile = ModuleModel::$user;

        if (!$userProfile) {
            goto end_of_function;
        }

        //EDIT USER PROFILE
        $user_login = $userProfile->getUserLogin();
        if (!$user_login) {
            goto end_of_function;
        }
        $user_login->setData([
            'password' => $user_login_password
        ]);

        $checkUserModel = $user_login->__quickUpdate();
        if ($checkUserModel['success'] == true) {

            $resultCreate = ModuleModel::__startCognitoClient();
            if ($resultCreate['success'] == false) {
                $result = $resultCreate;
                goto end_of_function;
            }
            try {
                $client = ModuleModel::$cognitoClient;
                $authenticationResponse = $client->changePassword($token_key, $user_login_old_password, $user_login_password);
            } catch (AwsException $e) {
                $exceptionType = $e->getAwsErrorCode();
                $result = ['success' => false, 'detail' => $e->getMessage(), 'message' => $exceptionType];
                goto end_of_function;
            }
            $result = ['success' => true, 'response' => $authenticationResponse, 'data' => $checkUserModel];
        } else {
            $result = $checkUserModel;
        }
        end_of_function:
        $result['resultCheck'] = isset($resultCheck) ? $resultCheck : false;
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function switchVersionAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $moduleName = Helpers::__getRequestValue('moduleName');
        if ($moduleName == '') $moduleName = UserInterfaceVersion::MODULE_GENERAL;
        $userProfile = ModuleModel::$user;
        if (!$userProfile) {
            goto end_of_function;
        }

        if (in_array($moduleName, UserInterfaceVersion::$modules)) {
            $result = $userProfile->switchUiVersion($moduleName);
        }
        end_of_function:
        if ($result['success'] == true) {
            $result['message'] = 'UI_CHANGE_SUCCESS_TEXT';
        } else {
            $result['message'] = 'UI_CHANGE_FAIL_TEXT';
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function switchSyncOwnerAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $userProfile = ModuleModel::$user;
        if (!$userProfile) {
            goto end_of_function;
        }

        if($userProfile->getIsSyncOwner() == ModelHelper::YES){
            $userProfile->setIsSyncOwner(ModelHelper::NO);
        }else{
            $userProfile->setIsSyncOwner(ModelHelper::YES);
        }

        $return = $userProfile->__quickUpdate();

        end_of_function:

        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @param $uuid
     * @return mixed
     */
    public function getLoginInfoAction()
    {

        $this->checkAjaxGet();

        $result = [
            'success' => false, 'message' => 'LOGIN_NOT_FOUND_TEXT'
        ];

        $model = ModuleModel::$user;

        if ($model) {
            $login = ModuleModel::$user->getUserLogin();
            if ($login) {
                $result = [
                    'success' => true,
                    'data' => $login
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

}
