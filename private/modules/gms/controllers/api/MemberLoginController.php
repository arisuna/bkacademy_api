<?php

namespace Reloday\Gms\Controllers\API;

use Aws\Exception\AwsException;
use Phalcon\Security;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayQueue;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Help\UserHelper;
use Reloday\Gms\Models\EmailTemplateDefault;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserLogin;
use Reloday\Gms\Models\UserProfile;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class MemberLoginController extends BaseController
{
    public function initialize()
    {
        $this->view->disable();
    }

    /**
     * @param $uuid
     * @return mixed
     */
    public function getAction($uuid)
    {

        $this->checkAjaxGet();
        $uuid = $uuid ? $uuid : Helpers::__getRequestValue('uuid');

        $result = [
            'success' => false, 'message' => 'LOGIN_NOT_FOUND_TEXT'
        ];

        if ($uuid != '') {

            $model = UserProfile::findFirstByUuid($uuid);

            if ($model && $model->belongsToGms() == true) {
                $login = $model->getUserLogin();
                if ($login) {
                    $result = [
                        'success' => true,
                        'data' => $login
                    ];
                }
            } elseif ($model && method_exists($model, 'manageByGms') && $model->manageByGms()) {
                $login = $model->getUserLogin();
                if ($login) {
                    $result = [
                        'success' => true,
                        'data' => $login
                    ];
                }
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * save login from all api call
     * @return mixed
     */
    public function createAction()
    {
        $this->checkAjaxPutPost();
        $uuid = Helpers::__getRequestValue('uuid') != '' ? Helpers::__getRequestValue('uuid') : false;
        $user_login_password = Helpers::__getRequestValue('user_login_password ') != '' ? Helpers::__getRequestValue('user_login_password ') : false;
        $user_login_email = Helpers::__getRequestValue('user_login_email ') != '' ? Helpers::__getRequestValue('user_login_email ') : false;

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $userProfileModel = UserProfile::findFirstByUuid($uuid);

            if ($userProfileModel && $userProfileModel->isGms() && $userProfileModel->belongsToGms()) {
                $this->checkAclEdit(AclHelper::CONTROLLER_COMPANY_MEMBER);
            }

            if ($userProfileModel && $userProfileModel->isHr() && $userProfileModel->manageByGms()) {
                $this->checkAclEdit(AclHelper::CONTROLLER_HR_MEMBER);
            }

            if (!$userProfileModel) {
                goto end_of_function;
            }

            $this->db->begin();

            if ($userProfileModel && $userProfileModel->isGms() && $userProfileModel->belongsToGms()) {
                $result = UserHelper::__createLogin($userProfileModel, $user_login_email, $user_login_password, false);
            } else if ($userProfileModel && $userProfileModel->isHr() && $userProfileModel->manageByGms()) {
                $result = UserHelper::__createLogin($userProfileModel, $user_login_email, $user_login_password, false);
            }

            if ($result['success'] == false) {
                $this->db->rollback();
                goto end_of_function;
            }

            $userProfileModel->setPendingStatus();
            $resultUpdate = $userProfileModel->__quickUpdate();

            if ($resultUpdate['success'] == false) {
                $result = $resultUpdate;
                $this->db->rollback();
                goto end_of_function;
            }

            $this->db->commit();

            if ($result['success'] == true){
                $result['user_profile'] = $userProfileModel->parsedDataToArray();
            }
        }

        end_of_function:

        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * save login from all api call
     * @return mixed
     */
    public function updateAction()
    {
        $this->checkAjaxPutPost();
        $uuid = Helpers::__getRequestValue('uuid') != '' ? Helpers::__getRequestValue('uuid') : false;
        $user_login_password = Helpers::__getRequestValue('user_login_password ') != '' ? Helpers::__getRequestValue('user_login_password ') : false;
        $user_login_email = Helpers::__getRequestValue('user_login_email ') != '' ? Helpers::__getRequestValue('user_login_email ') : false;

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $userProfileModel = UserProfile::findFirstByUuid($uuid);

            if ($userProfileModel && $userProfileModel->isGms() && $userProfileModel->belongsToGms()) {
                $this->checkAclEdit(AclHelper::CONTROLLER_COMPANY_MEMBER);
            }

            if ($userProfileModel && $userProfileModel->isHr() && $userProfileModel->manageByGms()) {
                $this->checkAclEdit(AclHelper::CONTROLLER_HR_MEMBER);
            }

            if (!$userProfileModel) {
                goto end_of_function;
            }

            $this->db->begin();

            if ($userProfileModel && $userProfileModel->isGms() && $userProfileModel->belongsToGms()) {
                $result = UserHelper::__updateLogin($userProfileModel, $user_login_email, $user_login_password);
            } else if ($userProfileModel && $userProfileModel->isHr() && $userProfileModel->manageByGms()) {
                $result = UserHelper::__updateLogin($userProfileModel, $user_login_email, $user_login_password);
            }

            if ($result['success'] == false) {
                $this->db->rollback();
            }

            $this->db->commit();
        }

        end_of_function:
        if ($result['success'] == true && !isset($result['message'])){
            $result['message'] = 'CHANGE_PASSWORD_SUCCESS_TEXT';
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


}
