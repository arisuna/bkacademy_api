<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\Department;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Office;
use Reloday\Gms\Models\EmployeeInContract;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\MediaAttachment;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class DepartmentController extends BaseController
{
    /**
     * @Route("/department", paths={module="gms"}, methods={"GET"}, name="gms-department-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax(['POST', 'GET']);
        $this->checkAclIndex();

        $company_ids = Helpers::__getRequestValue('company_ids');
        $options = [];
        if($company_ids){
            $options['company_ids'] = $company_ids;
        }

        $statuses = Helpers::__getRequestValue('statuses');
        if(count($statuses) > 0){
            $options['statuses'] = $statuses;
        }else{
            $options['statuses'] = [Department::STATUS_ACTIVATED, Department::STATUS_DRAFT];
        }

        $query = Helpers::__getRequestValue('query');
        if($query){
            $options['query'] = $query;
        }

        $return = Department::__findWithFilterSimple($options);
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function detailAction($uId = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $result = [
            'success' => false, 'message' => 'DEPARTMENT_NOT_FOUND_TEXT'
        ];

        if (Helpers::__isValidUuid($uId)) {
            $department = Department::findFirstByUuid($uId);
        } else if (Helpers::__isValidId($uId)) {
            $department = Department::findFirstById($uId);
        }

        if (isset($department) && $department instanceof Department && $department->belongsToGms()) {
            $data = $department->toArray();
            $data['company_name'] = $department->getCompany() ? $department->getCompany()->getName() : '';
            $data['office_name'] = $department->getOffice() ? $department->getOffice()->getName() : '';
            $data['is_editable'] = $department->getCompany()->isEditable();
            $result = [
                'success' => true,
                'data' => $data
            ];
        }

        $this->response->setJsonContent($result);
        return $this->response->send();

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

        $companyId = Helpers::__getRequestValue('company_id');
        $hrCompany = Company::findFirstByIdCache($companyId);

        $result = [
            'success' => false,
            'message' => 'SAVE_DEPARTMENT_FAIL_TEXT',
        ];

        if ($hrCompany && $hrCompany->belongsToGms()) {
            $this->checkPermissionContract($hrCompany);
            $department = new Department();
            $department->setData(Helpers::__getRequestValuesArray());
            $resultSave = $department->__quickCreate();

            $data = $department->toArray();
            $data['office_name'] = $department->getOfficeName();
            $data['company_name'] = $department->getCompanyName();

            if ($resultSave['success'] == true) {
                $result = [
                    'success' => true,
                    'data' => $data,
                    'message' => 'SAVE_DEPARTMENT_SUCCESS_TEXT'
                ];
            } else {
                $result = $resultSave;
            }
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Create or update method
     * @return string
     */
    public function editAction($id)
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAclEdit();
        $id = $id > 0 ? $id : Helpers::__getRequestValue('id');

        $result = [
            'success' => false, 'message' => 'DEPARTMENT_NOT_FOUND_TEXT'
        ];

        if ($id && $id > 0) {
            $department = Department::findFirstById($id);
            if ($department instanceof Department && $department->belongsToGms()) {

                $this->checkPermissionContract($department->getCompany());

                $data = Helpers::__getRequestValuesArray();
                $department->setData($data);
                $resultUpdate = $department->__quickUpdate();
                if ($resultUpdate['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'SAVE_DEPARTMENT_SUCCESS_TEXT',
                        'data' => $department,
                        'fields' => $department->getFieldsDataStructure()
                    ];
                } else {
                    $result = [
                        'success' => false,
                        'message' => 'SAVE_DEPARTMENT_FAIL_TEXT'
                    ];
                }
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /** delete policy */
    public function deleteAction($department_id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete();
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if (is_numeric($department_id) && $department_id > 0) {
            $department = Department::findFirstById($department_id);
            if ($department && $department instanceof Department && $department->belongsToGms() == true) {
                $this->checkPermissionContract($department->getCompany());
                $return = $department->remove();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/department", paths={module="gms"}, methods={"GET"}, name="gms-department-index")
     */
    public function listAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $department = new Department();
        $name_search = addslashes($this->request->get('name'));
        $result = $department->loadList($name_search);
        echo json_encode($result);
    }

    /**
     * @return mixed
     */
    public function simpleListAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $return = Department::__findWithFilterSimple();
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * @Route("/office", paths={module="gms"}, methods={"GET"}, name="gms-office-index")
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $return = Department::__findWithFilterSimple([
            'is_active' => true,
            'query' => Helpers::__getRequestValue('query'),
            'company_id' => Helpers::__getRequestValue('company_id'),
        ]);
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

}
