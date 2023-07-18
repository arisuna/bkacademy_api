<?php

namespace Reloday\Gms\Controllers\API;

use Aws\Exception\AwsException;
use Phalcon\Security;
use Reloday\Application\Aws\AwsCognito\CognitoClient;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Models\ApplicationModel;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Help\EmployeeHelper;
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
class EmployeeLoginController extends BaseController
{
    /**
     *
     */
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
            $model = Employee::findFirstByUuid($uuid);
            if ($model && $model->manageByGms() == true) {
                $login = $model->getUserLogin();
                if ($login) {
                    $data = $login->toArray();
                    $data['hasAwsCognito'] = $model->hasLogin();
                    $result = [
                        'success' => true,
                        'data' => $data
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

            $employeeProfileModel = Employee::findFirstByUuid($uuid);

            if ($employeeProfileModel && $employeeProfileModel->manageByGms()) {
                $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);
            }

            if (!$employeeProfileModel) {
                goto end_of_function;
            }

            $this->db->begin();

            $result = EmployeeHelper::__createLogin($employeeProfileModel, $user_login_email, $user_login_password, true, false);

            if ($result['success'] == false) {
                $this->db->rollback();
                goto end_of_function;
            }

            $this->db->commit();

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

            $employeeProfileModel = Employee::findFirstByUuid($uuid);

            if ($employeeProfileModel && $employeeProfileModel->manageByGms()) {
                $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);
            }

            if (!$employeeProfileModel) {
                goto end_of_function;
            }

            $this->db->begin();

            $result = EmployeeHelper::__updateLogin($employeeProfileModel, $user_login_email, $user_login_password);

            if ($result['success'] == false) {
                $this->db->rollback();
            }

            $this->db->commit();
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * save login from all api call
     * @return mixed
     */
    public function reInvitationAssigneeLoginAction()
    {
        $this->checkAjaxPutPost();
        $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);
        $uuid = Helpers::__getRequestValue('uuid');

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $employeeProfileModel = Employee::findFirstByUuid($uuid);
            if ($employeeProfileModel && $employeeProfileModel->manageByGms()) {
                if ($employeeProfileModel->isEditable()) {
                    $result = EmployeeHelper::__reInvite($employeeProfileModel, EmailTemplateDefault::ASSIGNEE_LOGIN);
                } else {
                    $result = ['success' => false, 'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'];
                    goto end_of_function;
                }
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
