<?php

namespace Reloday\App\Controllers\API;

use \Reloday\App\Controllers\ModuleApiController;
use Reloday\App\Models\App;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class AppController extends ModuleApiController
{
    /**
     * @Route("/app", paths={module="app"}, methods={"GET"}, name="app-app-index")
     */
    public function indexAction()
    {

    }

    /**
     *
     */
    public function checkAppAction()
    {
        $this->view->disable();
        $app_name = addslashes($this->request->get('app_name'));
        $app = App::findFirst("name='$app_name'");
        if ($app instanceof App) {
            echo json_encode([
                'success' => true,
                'message' => $this->_getTranslation()->_('APP_EXISTED_TEXT')
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => $this->_getTranslation()->_('APP_NOT_FOUND_TEXT')
            ]);
        }
        return;
    }
}
