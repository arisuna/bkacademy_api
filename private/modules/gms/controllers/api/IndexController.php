<?php

namespace Reloday\Gms\Controllers\API;

use \Phalcon\Mvc\View;
use Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Help\Reminder;
use Reloday\Gms\Models\Constant;
use Reloday\Gms\Models\ConstantTranslation;
use Reloday\Gms\Models\TaskTemplateCompany;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class IndexController extends ModuleApiController
{
    /**
     * @Route("/index", paths={module="gms"}, methods={"GET"}, name="gms-index-index")
     */

    protected $app_script;

    public function initialize()
    {

    }

    /**
     *
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->response->setJsonContent(['success' => false, 'message' => 'ERROR_SESSION_EXPIRED_TEXT']);
        return $this->response->send();
    }

    /**
     *
     */
    public function maintenanceAction()
    {
        $this->view->disable();
        $this->response->setJsonContent(['success' => false, 'message' => 'MAINTENANCE_IN_PROGRESS_TEXT']);
        return $this->response->send();
    }

    /**
     *
     */
    public function loginAction()
    {
        $this->view->disable();
        $this->response->setJsonContent(['success' => false, 'message' => 'ERROR_SESSION_EXPIRED_TEXT']);
        return $this->response->send();
    }

    /**
     * check controller
     */
    public function checkAction()
    {
        $this->view->disable();
        $this->response->setJsonContent(['success' => false, 'message' => 'ERROR_SESSION_EXPIRED_TEXT']);
        return $this->response->send();
    }

    /**
     *
     */
    public function needformAction(){
        $this->view->setLayout("app-angular-layout");
        $this->view->is_page = true;
        $this->view->base_href = "/gms/";
        if($this->di->get('appConfig')->application->environment != "LOCAL"){
            $releaseNumber = getenv('RELEASE_NUMBER');
        }else{
            $releaseNumber = date('Ymd');
        }
        $this->app_script->addJs("/resources/js/base.js"."?version=".$releaseNumber);
        $this->app_script->addJs("/libraries/angular-material/angular-material.min.js"."?version=".$releaseNumber);
        $this->app_script->addJs("/resources/js/gms.js"."?version=".$releaseNumber);
        $this->app_script->addJs('/apps/gms/js/needform.js');
    }


    /**
     * Get Module JS Files
     */
    private function getModulesJs()
    {
        $js_dir = $this->config->base_dir->gms . 'js';

        $js_files = array_diff(scandir($js_dir), [
            '..',
            '.',
            '.gitignore',
            'base-90e1edffea.js',
            'rev-manifest.json',
            '*.json',
        ]);

        return $js_files;
    }

    /**
     * Get Menu
     */

    public function leftMenuAction()
    {
        $this->view->disable();

        $menu_dir = $this->config->base_dir->gms . 'menu';

        $menu_files = array_diff(scandir($menu_dir), ['..', '.']);

        $menu_content = [];

        foreach ($menu_files as $file) {
            $menu_content = $this->getMenuContent($menu_content, $menu_dir . '/' . $file);
        }

        $this->response->setJsonContent($menu_content);
        $this->response->send();

        return;
    }

    private function getMenuContent($menu, $module)
    {
        $content = file_get_contents($module);

        $arr = json_decode($content);

        array_push($menu, $arr[0]);

        return $menu;
    }

    /**
     * @param string $lang
     * @return string
     */
    public function translateLoginKeyAction($lang = '')
    {

        $constant_keys = [
            'LOGIN_TEXT',
            'ENTER_EMAIL_TEXT',
            'ENTER_PASSWORD_TEXT',
            'REMEMBER_ME_TEXT',
            'FORGOT_PASSWORD_TEXT',
            'LOGIN_BTN_TEXT',
            'REGISTER_NOW_TEXT',
            'NEED_TO_SIGN_UP_TEXT',
            'RESET_PASSWORD_TEXT'
        ];

        $constants = Constant::find([
            'conditions' => 'name IN ({constant_names:array})',
            'bind' => [
                'constant_names' => $constant_keys,
            ]
        ]);

        $keys = [];
        $result = [];
        if (count($constants) > 0) {
            foreach ($constants as $constant) {
                $keys[$constant->getName()] = $constant->getId();
            }
            $constants_translate = ConstantTranslation::find([
                'conditions' => 'constant_id IN (' . implode(',', $keys) . ') AND ' .
                    'language="' . (empty($lang) ? $lang : 'en') . '"'
            ]);
            if (count($constants_translate)) {
                foreach ($constants_translate as $tran) {
                    if (($key = array_search($tran->getConstantId(), $keys)) !== false) {
                        $result[$key] = $tran->getValue();
                    }
                }
            }
        }

        return json_encode([
            'success' => true,
            'keys' => $result
        ]);
    }

    /**
     * @param string $lang
     */
    public function forgotPasswordKeyAction($lang = '')
    {
        $this->view->disable();

        $constant_keys = ['RESET_PASSWORD_BTN_TEXT', 'ENTER_EMAIL_TEXT', 'FORGOT_PASSWORD_TEXT','RESET_PASSWORD_TEXT'];
        $constants = Constant::find([
            'conditions' => 'name IN ("' . implode('","', $constant_keys) . '")'
        ]);
        $keys = [];
        $result = [];
        if (count($constants) > 0) {
            foreach ($constants as $constant) {
                $keys[$constant->getName()] = $constant->getId();
            }
            $constants_translate = ConstantTranslation::find([
                'conditions' => 'constant_id IN (' . implode(',', $keys) . ') AND ' .
                    'language="' . (empty($lang) ? $lang : 'en') . '"'
            ]);
            if (count($constants_translate)) {
                foreach ($constants_translate as $tran) {
                    if (($key = array_search($tran->getConstantId(), $keys)) !== false) {
                        $result[$key] = $tran->getValue();
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'keys' => $result
        ]);
    }

    /**
     *
     *
     */
    public function route404Action() {
        die(__FUNCTION__);
    }

    /**
     *
     */
    public function testAction(){
        print_r(get_loaded_extensions());
        die;
    }
}
