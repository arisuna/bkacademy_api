<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Test\Mvc\Model\Behavior\Helper;
use \Reloday\Gms\Controllers\ModuleApiController;

use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Guide;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class GuideController extends BaseController
{
    /**
     * @Route("/guide", paths={module="gms"}, methods={"GET"}, name="gms-guide-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $result = ['success' => true, 'data' => []];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @Route("/guide", paths={module="gms"}, methods={"GET"}, name="gms-guide-index")
     */
    public function initializeAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $result = ['success' => true, 'data' => []];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $this->checkAclIndex();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');
        $params['company_ids'] = Helpers::__getRequestValueAsArray('companies');
        $params['country_ids'] = Helpers::__getRequestValueAsArray('countries');
        $params['query'] = Helpers::__getRequestValue('query');

        $params['limit'] = Helpers::__getRequestValue('limit');;
        $params['length'] = Helpers::__getRequestValue('length');
        $params['start'] = Helpers::__getRequestValue('start');
        $params['simple'] = Helpers::__getRequestValue('simple');
        $params['active'] = Helpers::__getRequestValue('active');
        $params['page'] = Helpers::__getRequestValue('page');
        /****** destination ****/

        /****** origin ****/
        $languages = Helpers::__getRequestValue('languages');
        $languagesSearch = [];

        if (is_array($languages) && count($languages) > 0) {
            foreach ($languages as $item) {
                $item = (array)$item;
                $languagesSearch[] = $item;
            }
        }
        $params['languages'] = $languagesSearch;
//        $order = Helpers::__getRequestValueAsArray('sort');
//        $ordersConfig = Helpers::__getApiOrderConfig([$order]);

        /***** new filter ******/
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');

        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => isset($orders->descending) && $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }



        $return = Guide::__findWithFilter($params, $ordersConfig);
        if ($return['success'] == true) {
            $return['recordsFiltered'] = ($return['total_items']);
            $return['recordsTotal'] = ($return['total_items']);
            $return['length'] = ($return['total_items']);
            $return['draw'] = Helpers::__getRequestValue('draw');
        }

        $return['params'] = $params;

        if ($return['success'] == true) {
            $this->response->setJsonContent($return);
            $this->response->send();
        } else {
            $this->response->setJsonContent($return);
            $this->response->send();
        }
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate();

        $data = Helpers::__getRequestValuesArray();
        $guide = new Guide();
        $data['company_id'] = ModuleModel::$company->getId();
        $guide->setData($data);
        $result = $guide->__quickCreate();
        if ($result['success'] == false) {
            $result['message'] = 'CREATE_GUIDE_FAIL_TEXT';
        } else {
            $result['message'] = 'CREATE_GUIDE_SUCCESS_TEXT';
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function detailAction($id)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }

        $guide = Guide::findFirstById($id);
        if ($guide && $guide->belongsToCompany() && $guide->isArchived() == false) {
            $guideArray = $guide->toArray();
            $guideArray['tagsList'] = $guide->getTagsObjectList();
            $result = ['success' => true, 'data' => $guideArray];
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getContentAction($id)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }

        $guide = Guide::findFirstById($id);
        if ($guide && $guide->belongsToCompany() && $guide->isArchived() == false) {
            $content = $guide->getContent();
            $result = ['success' => true, 'data' => $content];
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function deleteAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }

        $guide = Guide::findFirstById($id);
        if ($guide && $guide->belongsToCompany()) {
            $result = $guide->__quickRemove();
            if ($result['success'] == false) {
                $result['message'] = 'REMOVE_GUIDE_FAIL_TEXT';
            } else {
                $result['message'] = 'REMOVE_GUIDE_SUCCESS_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function editAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclUpdate();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }
        $guide = Guide::findFirstById($id);
        if ($guide && $guide->belongsToCompany()) {

            $data = Helpers::__getRequestValuesArray();
            $data['company_id'] = ModuleModel::$company->getId();
            if(isset($data['contentText']) && $data['contentText'] != null && $data['contentText'] != ''){
                $data['contentText'] = rawurldecode(base64_decode($data['contentText']));
            }
            $guide->setData($data);
            $guide->setCity($data["city"]);
            $guide->setSummary($data["summary"]);
            $guide->setCountryId($data["country_id"]);
            $guide->setTargetCompanyId($data["target_company_id"]);

            $result = $guide->__quickUpdate();
            if ($result['success'] == false) {
                $result['message'] = 'UPDATE_GUIDE_FAIL_TEXT';
            } else {
                $result['message'] = 'UPDATE_GUIDE_SUCCESS_TEXT';
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function editContentAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclUpdate();
        $id = Helpers::__getRequestValue('id');
        $content = Helpers::__getRequestValue('content');
        if(isset($content) && $content != null && $content != ''){
            $content = rawurldecode(base64_decode($content));
        }
        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (Helpers::__isValidId($id) == false) {
            goto end_of_function;
        }
        $guide = Guide::findFirstById($id);
        if ($guide && $guide->belongsToCompany()) {
            $result = $guide->updateContent($content);
            if ($result['success'] == false) {
                $result['message'] = 'UPDATE_GUIDE_FAIL_TEXT';
            } else {
                $result['message'] = 'UPDATE_GUIDE_SUCCESS_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
