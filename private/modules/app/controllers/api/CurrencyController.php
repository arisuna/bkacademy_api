<?php

namespace SMXD\App\Controllers\API;


use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\App\Models\Currency;

class CurrencyController extends BaseController
{
    public function initialize()
    {
        $this->view->disable();
        $this->checkAcl(AclHelper::ACTION_INDEX, AclHelper::CONTROLLER_ADMIN);
    }

    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAcl(AclHelper::ACTION_INDEX, AclHelper::CONTROLLER_ADMIN);


        $query = Helpers::__getRequestValue('query');
        $param = null;

        if (isset($query) && is_string($query) && $query != '') {
            $param = ['conditions' => 'name LIKE :query: OR code LIKE :query: ',
                'bind' => [
                    'query' => '%' . $query . '%'
                ]];
        }

        $list = Currency::find($param);

        $this->response->setJsonContent([
            'success' => true,
            'data' => count($list) ? $list->toArray() : []
        ]);
        end:
        $this->response->send();
    }

    /**
     * @return Response|ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl(AclHelper::ACTION_INDEX, AclHelper::CONTROLLER_ADMIN);

        $code = Helpers::__getRequestValue('code');

        if (!$code || strlen($code) > 3) {
            $return = ['success' => false,
                'message' => "INVALID_CURRENCY_IOS_CODE_TEXT"];
            goto end;
        }

        $newCurrency = new Currency();
        $newCurrency->setData(Helpers::__getRequestValuesArray());
        $return = $newCurrency->__quickCreate();
        $return['data'] = $newCurrency;
        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @return Response|ResponseInterface
     */
    public function updateAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAcl(AclHelper::ACTION_INDEX, AclHelper::CONTROLLER_ADMIN);

        $return = [
            'success' => false,
            'message' => "DATA_NOT_FOUND_TEXT"
        ];

        $code = Helpers::__getRequestValue('code');

        $currency = Currency::findFirstByCode($code);

        if (!$currency) {
            goto end;
        }

        $currency->setData(Helpers::__getRequestValuesArray());

        $return = $currency->__quickUpdate();

        $return['data'] = $currency;

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @return Response|ResponseInterface
     */
    public function deleteAction($code = '')
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAcl(AclHelper::ACTION_INDEX, AclHelper::CONTROLLER_ADMIN);

        $return = [
            'success' => false,
            'message' => "DATA_NOT_FOUND_TEXT"
        ];

        $currency = Currency::findFirstByCode($code);

        if (!$currency) {
            goto end;
        }

        $return = $currency->__quickRemove();

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
