<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\HttpStatusCode;
use \Reloday\Gms\Controllers\ModuleApiController;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ErrorController extends ModuleApiController
{
    /**
     * @Route("/error", paths={module="gms"}, methods={"GET"}, name="gms-error-index")
     */
    public function indexAction()
    {
        $this->response->setStatusCode(HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR, 'Error Server');
        $this->view->disable();
        $exception = $this->dispatcher->getParam("exception");
        $this->response->setJsonContent([
            'success' => false,
            'message' =>'Error PHP Method',
            'function' => __METHOD__,
            'exception' => [
                'message' => !is_null($exception) ? $exception->getMessage() : '',
                'code' => !is_null($exception) ? $exception->getCode() : '',
                'file' => !is_null($exception) ? $exception->getFile() : '',
                'line' => !is_null($exception) ? $exception->getLine() : '',
            ]
        ]);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function phpAction()
    {
        $this->view->disable();
        $this->response->setStatusCode(HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR, 'Error PHP Method');
        $exception = $this->dispatcher->getParam("exception");
        $this->response->setJsonContent([
            'success' => false,
            'message' =>'Error PHP Method',
            'function' => __METHOD__,
            'exception' => [
                'message' => !is_null($exception) ? $exception->getMessage() : '',
                'code' => !is_null($exception) ? $exception->getCode() : '',
                'file' => !is_null($exception) ? $exception->getFile() : '',
                'line' => !is_null($exception) ? $exception->getLine() : '',
            ]
        ]);
        return $this->response->send();
    }

    /**
     * @return mixed
     */
    public function restrictAction(){
        $this->view->disable();
        $this->response->setStatusCode(HttpStatusCode::HTTP_INTERNAL_SERVER_ERROR, 'Error Restrict Access');
        $this->response->setJsonContent([
            'success' => false,
            'message' =>'Restrict Access',
        ]);
        return $this->response->send();
    }
}
