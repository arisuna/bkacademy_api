<?php

namespace Reloday\Gms\Controllers\API;

use Elasticsearch\Endpoints\Cat\Help;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Office;
use Reloday\Gms\Models\EmployeeInContract;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\ZoneLang;
use Reloday\Gms\Models\Currency;
use Reloday\Gms\Models\MediaAttachment;
use Reloday\Gms\Models\Employee;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class OfficeController extends BaseController
{
    /**
     * @Route("/office", paths={module="gms"}, methods={"GET"}, name="gms-office-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        return $this->searchAction();
    }


    /**
     * @Route("/office", paths={module="gms"}, methods={"GET"}, name="gms-office-index")
     */
    public function listAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        return $this->searchAction();
    }

    /**
     * @Route("/office", paths={module="gms"}, methods={"GET"}, name="gms-office-index")
     */
    public function simpleAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        return $this->searchAction();
    }

    /**
     * @Route("/office", paths={module="gms"}, methods={"GET"}, name="gms-office-index")
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

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

        $return = Office::__findWithFilterSimple([
            'is_active' => true,
            'page' => Helpers::__getRequestValue('page'),
            'limit' => Helpers::__getRequestValue('limit'),
            'query' => Helpers::__getRequestValue('query'),
            'company_id' => Helpers::__getRequestValue('company_id'),
        ], $ordersConfig);
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * detail of office
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function detailAction($uniqueId = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();
        $result = [
            'success' => false, 'message' => 'OFFICE_NOT_FOUND_TEXT'
        ];


        if (Helpers::__isValidUuid($uniqueId) || Helpers::__isValidId($uniqueId)) {
            if (Helpers::__isValidUuid($uniqueId)) {
                $office = Office::findFirstByUuid($uniqueId);
            }
            if (Helpers::__isValidId($uniqueId)) {
                $office = Office::findFirstById($uniqueId);
            }
            if (isset($office) && $office && $office->belongsToGms() == true) {
                $data = $office->toArray();
                $data['company_name'] = $office->getCompany() ? $office->getCompany()->getName() : '';
                $data['is_editable'] = $office->getCompany() && $office->getCompany()->isEditable();
                $result = [
                    'success' => true,
                    'data' => $data,
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send($result);
    }

    /**
     * Create or update method
     * @return string
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclCreate();

        $dataArray = Helpers::__getRequestValuesArray();
        $companyId = Helpers::__getRequestValue('company_id');
        $hrCompany = Company::findFirstByIdCache($companyId);

        $result = [
            'success' => false,
            'message' => 'SAVE_OFFICE_FAIL_TEXT',
        ];

        if ($hrCompany && $hrCompany->belongsToGms()) {

            $this->checkPermissionOffice($hrCompany);

            $office = new Office();
            $office->setData($dataArray);
            $resultSave = $office->__quickCreate();
            $data = $office->toArray();
            $data['country_name'] = $office->getCountryName();
            $data['company_name'] = $office->getCompanyName();
            if ($resultSave['success'] == true) {
                $result = [
                    'success' => true,
                    'message' => 'SAVE_OFFICE_SUCCESS_TEXT',
                    'data' => $office
                ];
            } else {
                $result = [
                    'success' => false,
                    'message' => 'SAVE_OFFICE_FAIL_TEXT',
                    'detail' => $resultSave
                ];
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Create or update method
     * @return string
     */
    public function updateAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclEdit();

        $result = [
            'success' => false, 'message' => 'OFFICE_NOT_FOUND_TEXT'
        ];

        $id = $id ? $id : Helpers::__getRequestValue('id');
        if ($id) {
            $office = Office::findFirstById($id);
            if ($office instanceof Office && $office->belongsToGms() == true) {

                if ($office->getCompany() && $office->getCompany()->belongsToGms()) {
                    $this->checkPermissionOffice($office->getCompany());
                }
                // Find company name
                $dataArray = Helpers::__getRequestValuesArray();
                $office->setData($dataArray);
                $resultSave = $office->__quickUpdate();
                if ($resultSave['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'SAVE_OFFICE_SUCCESS_TEXT',
                        'data' => $office
                    ];
                } else {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_OFFICE_FAIL_TEXT',
                        'detail' => $office['detail']
                    ];
                }

            }
        }
        $this->response->setJsonContent($result);
        $this->response->send($result);
    }

    /**
     * @return string
     */
    public function findEmployeeAction()
    {
        $this->view->disable();

        $access = $this->canAccessResource($this->router->getControllerName(), 'index');
        if (!$access['success']) {
            exit(json_encode($access));
        }

        $company_id = $this->request->get('company_id');

        $gms_company = ModuleModel::$user_profile->getCompanyId();
        if (!$gms_company) {
            exit(json_encode([
                'result' => false,
                'message' => 'COMPANY_OF_LOGGER_NOT_SET'
            ]));
        }

        // 1. Find all contract of this company
        $contract = Contract::findFirst(['from_company_id=' . ($company_id ? $company_id : 0) . ' AND to_company_id=' . $gms_company]);

        if ($contract instanceof Contract) {
            // 2. Find all employee in list contract
            $user_in_contract = UserInContact::find(['contract_id=' . $contract->getId()]);

            if (count($user_in_contract)) {
                $user_ids = [];
                foreach ($user_in_contract as $item) {
                    $user_ids[] = $item->getUserId();
                }
                // 3. Find first name and last name of users in contract
                $users = UserProfile::find('id IN (' . implode(',', $user_ids) . ')');
                if (count($users)) {
                    $result = [];
                    foreach ($users as $user) {
                        $result[] = [
                            'key' => $user->getFirstname() . ' ' . $user->getLastname(),
                            'value' => $user->getId()
                        ];
                    }

                    // Response to data
                    exit(json_encode([
                        'success' => true,
                        'data' => $result
                    ]));
                }
            }
        }

        echo json_encode([
            'success' => true,
            'data' => json_encode([])
        ]);

    }


    /**
     * delete an office
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function deleteAction($id = '')
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();

        $id = $id ? $id : $this->request->get('id');
        $result = [
            'success' => false, 'message' => 'OFFICE_NOT_FOUND_TEXT'
        ];
        if (Helpers::__isValidId($id)) {
            $office = Office::findFirstById($id);
            if ($office && $office->belongsToGms()) {
                $this->checkPermissionOffice($office->getCompany());
                $resultDelete = $office->__quickRemove();
                if ($resultDelete['success'] == false) {
                    $result = ['success' => false, 'message' => 'REMOVE_FAILED_TEXT'];
                } else {
                    $result = ['success' => true, 'message' => 'OFFICE_REMOVE_SUCCESS_TEXT'];
                }
            }
        }
        $this->response->setJsonContent($result);
        $this->response->send($result);
    }


    /**
     * @param $hrCompany
     */
    public function checkPermissionOffice($hrCompany)
    {
        if ($hrCompany->isEditable() == false) {
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_IN_CONTRACT_TEXT',
            ]);
            $this->response->send();
            exit();
        }
    }
}
