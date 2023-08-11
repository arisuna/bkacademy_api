<?php

namespace SMXD\App\Controllers\API;

use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;
use \SMXD\App\Controllers\ModuleApiController;
use SMXD\App\Models\CompanyType;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\StaffUserGroup;
use SMXD\App\Models\UserInterfaceVersion;
use SMXD\App\Models\User;
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
        $result = [
            'success' => true,
            'profile' => $profile_array
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
        $user_uuid = isset($dataInput['uuid']) && $dataInput['uuid'] != '' ? $dataInput['uuid'] : "";

        if ($user_uuid != '') {
            $user = ModuleModel::$user;

            if ($user->getUuid() == $user_uuid) {
                $modelResult = $user->saveUser($dataInput);
                if ($modelResult instanceof User) {
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
                'conditions' => 'user_id = :user_id: AND user_setting_default_id = :user_setting_default_id:',
                'bind' => [
                    'user_id' => ModuleModel::$user->getId(),
                    'user_setting_default_id' => $userConfigDefault->getId(),
                ]
            ]);

            if (!$userSetting) {
                $userSetting = new UserSetting();
            }

            if ($value != $userSetting->getValue()) {
                $data = [
                    'user_id' => ModuleModel::$user->getId(),
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
     * @return mixed
     */
    public function changeLanguageAction($value)
    {
        $this->view->disable();
        $this->checkAjax('PUT');

        $userConfigDefault = UserSettingDefault::findFirstByName(UserSettingDefault::DISPLAY_LANGUAGE);

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($userConfigDefault) {
            $userSetting = UserSetting::findFirst([
                'conditions' => 'user_id = :user_id: AND user_setting_default_id = :user_setting_default_id:',
                'bind' => [
                    'user_id' => ModuleModel::$user->getId(),
                    'user_setting_default_id' => $userConfigDefault->getId(),
                ]
            ]);

            if (!$userSetting) {
                $userSetting = new UserSetting();
            }

            if ($value != $userSetting->getValue()) {
                $data = [
                    'user_id' => ModuleModel::$user->getId(),
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


        $user = ModuleModel::$user;

        if (!$user) {
            goto end_of_function;
        }

        //EDIT USER PROFILE
        $user_login = $user->getUser();
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
        $user = ModuleModel::$user;
        if (!$user) {
            goto end_of_function;
        }

        if (in_array($moduleName, UserInterfaceVersion::$modules)) {
            $result = $user->switchUiVersion($moduleName);
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

        $user = ModuleModel::$user;
        if (!$user) {
            goto end_of_function;
        }

        if($user->getIsSyncOwner() == ModelHelper::YES){
            $user->setIsSyncOwner(ModelHelper::NO);
        }else{
            $user->setIsSyncOwner(ModelHelper::YES);
        }

        $return = $user->__quickUpdate();

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
            $login = ModuleModel::$user->getUser();
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
