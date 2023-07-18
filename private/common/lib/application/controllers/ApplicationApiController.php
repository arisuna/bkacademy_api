<?php

namespace Reloday\Application\Controllers;

use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\HttpStatusCode;
use Reloday\Application\Lib\RequestHeaderHelper;
use Reloday\Application\Models\ApplicationModel;


/**
 * Controller base class for all application API controllers
 */
class ApplicationApiController extends ApplicationController
{
    /**
     * @var
     */
    public $timezone_offset;
    /**
     * @var
     */
    public $timezone_name;

    /**
     * @param $dispatcher
     */
    public function beforeExecuteRoute(\Phalcon\Mvc\Dispatcher $dispatcher)
    {
        $this->checkPrelightRequest();
        $headers = array_change_key_case($this->request->getHeaders());
        if (array_key_exists('timezone-offset', $headers)) {
            $this->timezone_offset = $headers['timezone-offset'];
        }
    }

    /**
     *
     */
    public function checkAjax($method)
    {
        if (!$this->request->isAjax() && !$this->request->isOptions()) {
            $return = [
                'success' => false,
                'method' => $method,
                'isAjax' => $this->request->isAjax(),
                'isSoap' => $this->request->isSoap(),
                'isOptions' => $this->request->isOptions(),
                'message' => 'Restrict access for Non Ajax Method'
            ];
            goto end_of_function;
        }

        if (is_string($method)) {
            if ($method == '') $method = 'GET';

            if ($method == 'GET' && !$this->request->isGet()) {
                $return = ['success' => false, 'message' => 'Restrict access for Get Method'];
            } elseif ($method == 'POST' && !$this->request->isPost()) {
                $return = ['success' => false, 'message' => 'Restrict access for Post Method'];
            } elseif ($method == 'DELETE' && !$this->request->isDelete()) {
                $return = ['success' => false, 'message' => 'Restrict access for Delete Method'];
            } elseif ($method == 'PUT' && !$this->request->isPut()) {
                $return = ['success' => false, 'message' => 'Restrict access for Put Method'];
            } else {
                $return = ['success' => true, 'message' => 'Open access for Other Method'];
            }

        } elseif (is_array($method)) {
            if (!in_array($this->request->getMethod(), $method)) {
                $return = ['success' => false, 'message' => 'Access denied'];
            } else {
                $return = ['success' => true, 'message' => 'Access OK'];
            }
        } else {
            $return = ['success' => false, 'message' => 'Restrict access Undefined Method'];
        }


        $return['isOptions'] = $this->request->isOptions();
        $return['isSoap'] = $this->request->isSoap();
        $return['isDelete'] = $this->request->isDelete();
        $return['isPut'] = $this->request->isPut();
        $return['isPost'] = $this->request->isPost();
        $return['isGet'] = $this->request->isGet();

        end_of_function:
        $this->applyCrossDomainHeader();

        if ($return['success'] == false) {

            $return = $this->buildResponse();
            $this->applyCrossDomainHeader();
            $this->response->setJsonContent($return);
            $this->response->send();

            exit();
        }
    }

    /**
     *
     */
    public function buildResponse()
    {
        $return = [];
        $return['request_headers'] = $this->request->getHeaders();
        $return['reponse_headers'] = $this->response->getHeaders();
        $return['params'] = Helpers::__getRequestValues();
        $return['method'] = $this->request->getMethod();
        $return['token'] = $this->getTokenKey();
        $return['isOptions'] = $this->request->isOptions();
        $return['isSoap'] = $this->request->isSoap();
        $return['isDelete'] = $this->request->isDelete();
        $return['isPut'] = $this->request->isPut();
        $return['isPost'] = $this->request->isPost();
        $return['isGet'] = $this->request->isGet();
        if ($this->request->isOptions() == false) {
            $return['prelight'] = false;
            $this->response->setStatusCode(HttpStatusCode::HTTP_FORBIDDEN, HttpStatusCode::getMessageForCode(HttpStatusCode::HTTP_FORBIDDEN));
        } else {
            $return['prelight'] = true;
            $return['success'] = true;
        }

        return $return;
    }

    /**
     *
     */
    public function checkAjaxPut()
    {
        return $this->checkAjax('PUT');
    }

    /**
     *
     */
    public function checkAjaxPost()
    {
        return $this->checkAjax('POST');
    }

    /**
     *
     */
    public function checkPost()
    {
        if (!$this->request->isPost()) {
            $return = [
                'success' => false,
                'message' => 'Restrict access for Non Post Method'
            ];

            if ($return['success'] == false) {
                $return = $this->buildResponse();
                $this->response->setJsonContent($return);
                $this->response->send();
                exit();
            }
        }
    }

    /**
     *
     */
    public function checkAjaxPutPost()
    {
        return $this->checkAjax(['POST', 'PUT']);
    }

    public function checkAjaxPutGet()
    {
        return $this->checkAjax(['PUT', 'GET']);
    }

    /**
     *
     */
    public function checkAjaxDelete()
    {
        return $this->checkAjax('DELETE');
    }

    /**
     *
     */
    public function checkAjaxGet()
    {
        return $this->checkAjax('GET');
    }

    /**
     * @return mixed
     */
    public function getTokenKey()
    {
        $token_key = Helpers::__getHeaderValue(RequestHeaderHelper::TOKEN);
        if ($token_key == '') $token_key = Helpers::__getHeaderValue(RequestHeaderHelper::TOKEN_KEY);
        if ($token_key == '') $token_key = Helpers::__getHeaderValue(RequestHeaderHelper::EMPLOYEE_TOKEN_KEY);
        return $token_key;
    }

    /**
     *
     */
    public function checkPrelightRequest()
    {
        $this->applyCrossDomainHeader();
        if ($this->request->isOptions()) {
            $return = $this->buildResponse();
            $this->response->setJsonContent($return);
            $this->response->send();
            exit();
        }
    }

    /**
     * @return mixed
     */
    public function applyCrossDomainHeader()
    {
        $origin = $this->request->getHeader(RequestHeaderHelper::ORIGIN) ? $this->request->getHeader(RequestHeaderHelper::ORIGIN) : '*';
        $origin = "*";
        $this->response->setHeader('Access-Control-Expose-Headers', RequestHeaderHelper::TOKEN_KEY . "," . RequestHeaderHelper::REFRESH_TOKEN);
        $this->response->setHeader('Access-Control-Allow-Origin', "*");
        $this->response->setHeader("Access-Control-Allow-Headers", RequestHeaderHelper::__getAll());
        $this->response->setHeader("Access-Control-Allow-Methods", 'PUT,GET,POST,DELETE');
        $this->response->sendHeaders();
        return true;
    }

    /**
     * @param $result
     */
    public function setJsonContent($result)
    {
        if ($result['success'] == false && is_array($result['detail']) && is_string(reset($result['detail']))) {
            $result['message'] = reset($result['detail']);
        }
        $this->response->setJsonContent($result);
    }

    /**
     * @param String $method
     */
    public function checkRequestType(String $method)
    {
        if (is_string($method)) {
            if ($method == '') $method = 'GET';

            if ($method == 'GET' && !$this->request->isGet()) {
                $return = ['success' => false, 'message' => 'Restrict access for Get Method'];
            } elseif ($method == 'POST' && !$this->request->isPost()) {
                $return = ['success' => false, 'message' => 'Restrict access for Post Method'];
            } elseif ($method == 'DELETE' && !$this->request->isDelete()) {
                $return = ['success' => false, 'message' => 'Restrict access for Delete Method'];
            } elseif ($method == 'PUT' && !$this->request->isPut()) {
                $return = ['success' => false, 'message' => 'Restrict access for Put Method'];
            } else {
                $return = ['success' => true, 'message' => 'Open access for Other Method'];
            }

        } elseif (is_array($method)) {
            if (!in_array($this->request->getMethod(), $method)) {
                $return = ['success' => false, 'message' => 'Access denied'];
            } else {
                $return = ['success' => true, 'message' => 'Access OK'];
            }
        } else {
            $return = ['success' => false, 'message' => 'Restrict access Undefined Method'];
        }


        $return['isOptions'] = $this->request->isOptions();
        $return['isSoap'] = $this->request->isSoap();
        $return['isDelete'] = $this->request->isDelete();
        $return['isPut'] = $this->request->isPut();
        $return['isPost'] = $this->request->isPost();
        $return['isGet'] = $this->request->isGet();

        end_of_function:

        if ($return['success'] == false) {
            $return = $this->buildResponse();
            $this->response->setJsonContent($return);
            $this->response->send();
            exit();
        }
    }
}
