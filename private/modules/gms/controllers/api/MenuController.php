<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\UserGroup;
use Reloday\Gms\Models\UserLogin;
/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class MenuController extends ModuleApiController
{
	/**
     * @Route("/menu", paths={module="gms"}, methods={"GET"}, name="gms-menu-index")
     */
    public function indexAction()
    {
    	$this->view->disable();

        $headers = array_change_key_case($this->request->getHeaders());
        $token_key = '';
        if (array_key_exists('token-key', $headers))
            $token_key = $headers['token-key'];

        $model = new UserLogin();
        $list = $model->loadUserMenu($token_key, UserGroup::GMS_ADMIN);

        //echo json_encode($list['acl_list']);
        $this->response->setJsonContent($list->data);
        $this->response->send();
    }

    /**
     * Load list permission of user
     * @return string
     */
    public function homeAction() {
        $this->view->disable();

        $headers = array_change_key_case($this->request->getHeaders());
        $token_key = '';
        if (array_key_exists('token-key', $headers))
            $token_key = $headers['token-key'];

        $model = new UserLogin();
        $list = $model->loadUserMenu($token_key, UserGroup::GMS_ADMIN);

        //echo json_encode($list['acl_list']);
        $this->response->setJsonContent($list['data']);
        $this->response->send();
    }

    /**
     * Load list permission of user
     * @return string
     */
    public function dashboardAction() {
        $this->view->disable();

        $headers = array_change_key_case($this->request->getHeaders());
        $token_key = '';
        if (array_key_exists('token-key', $headers))
            $token_key = $headers['token-key'];

        $model = new UserLogin();
        $list = $model->loadUserMenu($token_key, UserGroup::GMS_ADMIN);

        //echo json_encode($list['acl_list']);
        $this->response->setJsonContent($list);
        $this->response->send();
    }
}
