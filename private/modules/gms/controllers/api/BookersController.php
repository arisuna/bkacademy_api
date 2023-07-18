<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanyType;
use Reloday\Gms\Models\ModuleModel;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class BookersController extends BaseController
{
    /**
     * @Route("/bookers", paths={module="gms"}, methods={"GET"}, name="gms-bookers-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclIndex();
        $data = Company::__loadListBookers();
        end_of_function:
        $this->response->setJsonContent(['success' => true, 'data' => $data]);
        $this->response->send();
    }

    /**
     * @Route("/bookers", paths={module="gms"}, methods={"GET"}, name="gms-bookers-index")
     */
    public function simpleAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $data = Company::__loadListBookers();
        end_of_function:
        $this->response->setJsonContent(['success' => true, 'data' => $data]);
        $this->response->send();
    }

    /**
     * @Route("/bookers", paths={module="gms"}, methods={"GET"}, name="gms-bookers-index")
     */
    public function loadBookersByIdsAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $ids = Helpers::__getRequestValue('ids');
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }

        $queryBuilder = new \Phalcon\Mvc\Model\Query\Builder();
        $queryBuilder->addFrom('\Reloday\Gms\Models\Company', 'Company');
        $queryBuilder->distinct(true);
        $queryBuilder->columns([
            'Company.id',
            'Company.uuid',
            'Company.name'
        ]);
        $queryBuilder->where('Company.company_type_id = :company_type_id:', [
            'company_type_id' => Company::TYPE_BOOKER,
        ]);

        $queryBuilder->andwhere('Company.created_by_company_id = :created_by_company_id:', [
            'created_by_company_id' => ModuleModel::$company->getId(),
        ]);

        $queryBuilder->andwhere('Company.status = :status:', [
            'status' => Company::STATUS_ACTIVATED
        ]);

        $queryBuilder->andwhere('Company.id IN ({ids:array})', [
            'ids' => $ids
        ]);


        $countries = $queryBuilder->getQuery()->execute();
        $this->response->setJsonContent([
            'success' => true,
            'data' => $countries,
//            'query' => $queryBuilder->getQuery()->getSql()
        ]);
        return $this->response->send();
    }


    /**
     * @Route("/bookers", paths={module="gms"}, methods={"GET"}, name="gms-bookers-index")
     */
    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $booker = Company::__findBooker($uuid);
            if ($booker && $booker->belongsToGms()) {
                $companyArray = $booker->toArray();
                $financial = $booker->getCompanyFinancialDetail();
                $companyArray['financial'] = $financial ? $financial->toArray() : [];
                $return = ['success' => true, 'data' => $companyArray, 'fields' => $booker->getBookerFieldsDataStructure()];
            }
        }

        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * API load list all companies (hr and bookers)
     * @Route("/company", paths={module="gms"}, methods={"GET"}, name="gms-company-index")
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $options["query"] = Helpers::__getRequestValue("query");
        $dataBookers = Company::__loadListBookers($options);
        $this->response->setJsonContent([
            'success' => true,
            'total_items' => count($dataBookers),
            'total_rest_items' => null,
            'data' => $dataBookers
        ]);
        return $this->response->send();
    }

    public function createAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('create', $this->router->getControllerName());


        $custom_data = Helpers::__getRequestValuesArray();
        $custom_data['company_type_id'] = CompanyType::TYPE_BOOKER;
        $custom_data['created_by_company_id'] = intval(ModuleModel::$company->getId());

        $company = new Company();
        $company->setData($custom_data);
        $company->setStatus(Company::STATUS_ACTIVATED);
        $resultCompany = $company->__quickCreate();

        if ($resultCompany['success'] == true) {
            $return = [
                'success' => true,
                'message' => 'CREATE_BOOKER_SUCCESS_TEXT'
            ];
        } else {
            $return = $resultCompany;
            $return['message'] = 'CREATE_BOOKER_FAIL_TEXT';
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *
     */
    public function editAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('edit', $this->router->getControllerName());

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if ($uuid == '' || !(Helpers::__isValidUuid($uuid))) {
            goto end_of_function;
        }

        $custom_data = Helpers::__getRequestValuesArray();
        $custom_data['company_type_id'] = CompanyType::TYPE_BOOKER;
        $custom_data['created_by_company_id'] = intval(ModuleModel::$company->getId());

        $company = Company::findFirstByUuid($uuid);
        if (!$company || $company->isBooker() == false || $company->belongsToGms() == false) {
            goto end_of_function;
        }
        $company->setData($custom_data);
        $resultCompany = $company->__quickUpdate();

        if ($resultCompany['success'] == true) {
            $return = [
                'success' => true,
                'message' => 'SAVE_BOOKER_SUCCESS_TEXT'
            ];
        } else {
            $return = $resultCompany;
            $return['message'] = 'SAVE_BOOKER_FAIL_TEXT';
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     *
     */
    public function editFinancialDataAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAcl('edit', $this->router->getControllerName());

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $dataCompanyFinancial = (array)Helpers::__getRequestValuesArray();
        if (!Helpers::__isValidId($dataCompanyFinancial['company_id'])) {
            goto end_of_function;
        }

        $company = Company::findFirstByIdCache($dataCompanyFinancial['company_id']);

        if (!$company || $company->isBooker() == false || $company->belongsToGms() == false) {
            goto end_of_function;
        }

        $this->db->begin();
        $reference = Helpers::__getRequestValue("reference");
        $company->setReference($reference);
        $return = $company->__quickUpdate();
        if ($return['success'] == false) {
            $this->db->rollback();
            goto end_of_function;
        }

        $resultCompany = $company->__saveFinancialInfo($dataCompanyFinancial);

        if ($resultCompany['success'] == true) {
            $this->db->commit();
            $return = [
                'success' => true,
                'message' => 'SAVE_BOOKER_SUCCESS_TEXT'
            ];
        } else {
            $this->db->rollback();
            $return = $resultCompany;
            $return['message'] = 'SAVE_BOOKER_FAIL_TEXT';
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }


    /**
     * @Route("/bookers", paths={module="gms"}, methods={"GET"}, name="gms-bookers-index")
     */
    public function deleteAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $booker = Company::__findBooker($uuid);
            if ($booker && $booker->belongsToGms()) {
                $return = $booker->__quickRemove();
            }
        }

        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * API load list company
     * @Route("/company", paths={module="gms"}, methods={"GET"}, name="gms-company-index")
     */
    public function searchBookerAccountsAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclIndex();

        $params = [];
        $params['query'] = Helpers::__getRequestValue('query');

        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['start'] = Helpers::__getRequestValue('start');

        /***** new filter ******/
        $params['hasPagination'] = Helpers::__getRequestValue('hasPagination');

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

        $result = Company::__findBookerWithFilter($params, $ordersConfig);

        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
