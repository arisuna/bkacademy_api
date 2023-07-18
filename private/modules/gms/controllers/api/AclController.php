<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserGroup;
use Reloday\Gms\Models\UserLogin;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AclController extends BaseController
{
	/**
     * @Route("/acl", paths={module="gms"}, methods={"GET"}, name="gms-acl-index")
     */
    public function indexAction()
    {

    }

    /**
     * Load list permission of user
     * @return string
     */
    public function listAction() {
        $this->view->disable();
        $model = new UserLogin();
        $list = $model->loadUserMenu($token_key, UserGroup::GMS_ADMIN);

        //echo json_encode($list['acl_list']);
        $this->response->setJsonContent($list);
        $this->response->send();
    }

    /**
     *
     */
    public function checkValidationAction(){
        $this->view->disable();
        $this->checkAjaxPut();

        $controller = Helpers::__getRequestValue('controller');
        $action = Helpers::__getRequestValue('action');

        AclHelper::__setUserProfile( ModuleModel::$user_profile);
        $return = AclHelper::__checkPermissionDetailGms( $controller, $action );

        $this->response->setJsonContent($return);
        $this->response->send();
    }
}
