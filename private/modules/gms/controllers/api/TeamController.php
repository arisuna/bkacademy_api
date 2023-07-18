<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\Department;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Office;
use Reloday\Gms\Models\Team;
use Reloday\Gms\Models\EmployeeInContract;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\MediaAttachment;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class TeamController extends BaseController
{
    /**
     * Load list team
     * @Route("/team", paths={module="gms"}, methods={"GET"}, name="gms-team-index")
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $items = Team::__findWithFilter([
            'company_id' => Helpers::__getRequestValue('company_id'),
            'query' => Helpers::__getRequestValue('query'),
            'status' => [Team::STATUS_ACTIVATED, Team::STATUS_DRAFT]
        ]);
        $dataArray = [];
        if(count($items) > 0){
            foreach($items as $item){
                $data = $item->toArray();
                $data['id'] = intval($item->id);
                $dataArray[] = $data;
            }
        }
        $return = ['success' => true, 'data' => $dataArray, 'total_items' => count($dataArray)];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Load list team
     * @Route("/team", paths={module="gms"}, methods={"GET"}, name="gms-team-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax(['POST', 'GET']);
        $this->checkAcl('index');

        $company_ids = Helpers::__getRequestValue('company_ids');
        $options = [];
        if($company_ids){
            $options['company_ids'] = $company_ids;
        }

        $statuses = Helpers::__getRequestValue('statuses');
        if(count($statuses) > 0){
            $options['status'] = $statuses;
        }else{
            $options['status'] = [Team::STATUS_ACTIVATED, Team::STATUS_DRAFT];
        }

        $items = Team::__findWithFilter($options);
        $dataArray = [];
        if(count($items) > 0){
            foreach($items as $item){
                $data = $item->toArray();
                $data['id'] = intval($item->id);
                $dataArray[] = $data;
            }
        }
        $return = ['success' => true, 'data' => $dataArray];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function detailAction($uuid = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());
        $result = [
            'success' => false, 'message' => 'TEAM_NOT_FOUND_TEXT'
        ];
        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $teamObject = Team::findFirstByUuid($uuid);
            if ($teamObject instanceof Team && $teamObject->belongsToGms()) {
                $data = $teamObject->toArray();
                $data['company_name'] = ($teamObject->getCompany() ? $teamObject->getCompany()->getName() : '');
                $data['office_name'] = $teamObject->getOffice() ? $teamObject->getOffice()->getName() : '';
                $data['department_name'] = $teamObject->getDepartment() ? $teamObject->getDepartment()->getName() : '';
                $data['is_editable'] = $teamObject->getCompany() ? $teamObject->getCompany()->isEditable() : false;
                $result = [
                    'success' => true, 'data' => $data, 'fields' => $teamObject->getFieldsDataStructure()
                ];
            }
        }
        if ($uuid && Helpers::__isValidId($uuid)) {
            $teamObject = Team::findFirstById($uuid);
            if ($teamObject instanceof Team && $teamObject->belongsToGms()) {
                $data = $teamObject->toArray();
                $data['company_name'] = ($teamObject->getCompany() ? $teamObject->getCompany()->getName() : '');
                $data['office_name'] = $teamObject->getOffice() ? $teamObject->getOffice()->getName() : '';
                $data['department_name'] = $teamObject->getDepartment() ? $teamObject->getDepartment()->getName() : '';
                $data['is_editable'] = $teamObject->getCompany() ? $teamObject->getCompany()->isEditable() : false;
                $result = [
                    'success' => true, 'data' => $data, 'fields' => $teamObject->getFieldsDataStructure()
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
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $companyId = Helpers::__getRequestValue('company_id');
        $hrCompany = Company::findFirstByIdCache($companyId);

        $result = [
            'success' => false,
            'message' => 'SAVE_TEAM_FAIL_TEXT',
        ];

        if ($hrCompany && $hrCompany->belongsToGms()) {
            $this->checkPermissionContract($hrCompany);

            $team = new Team();
            $team->setData(Helpers::__getRequestValuesArray());
            $resultSave = $team->__quickCreate();
            if ($resultSave['success'] == true) {
                $data = $team->toArray();
                $data['company_name'] = $team->getCompanyName();
                $data['office_name'] = $team->getOfficeName();
                $data['department_name'] = $team->getDepartmentName();

                $result = [
                    'success' => true,
                    'data' => $data,
                    'message' => 'CREATE_TEAM_SUCCESS_TEXT'
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
        $this->checkAjax(['PUT']);
        $this->checkAcl('edit', $this->router->getControllerName());
        $id = $id ? $id : $this->request->get('id');

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (Helpers::__checkId($id)) {
            $teamObject = Team::findFirst($id);
            if ($teamObject instanceof Team && $teamObject->belongsToGms()) {

                $this->checkPermissionContract($teamObject->getCompany());
                $dataInput = Helpers::__getRequestValuesArray();
                $teamObject->setData($dataInput);
                $result = $teamObject->__quickUpdate();

                if ($result['success'] == true) {
                    $result['message'] = 'SAVE_TEAM_SUCCESS_TEXT';
                } else {
                    $result['message'] = 'SAVE_TEAM_FAIL_TEXT';
                }
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $team_uuid
     * @return mixed
     */
    public function deleteAction($team_uuid)
    {
        $this->view->disable();
        $this->checkAjax('DELETE');
        $this->checkAcl('delete', $this->router->getControllerName());
        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        if ($team_uuid != '') {
            $team = Team::findFirstByUuid($team_uuid);
            if ($team && $team instanceof Team && $team->belongsToGms() == true) {
                $this->checkPermissionContract($team->getCompany());
                $return = $team->remove();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Load list team
     * @Route("/team", paths={module="gms"}, methods={"GET"}, name="gms-team-index")
     */
    public function listAction()
    {
        // Find list company they have permission manage
        $this->view->disable();
        $hasPermission = $this->canAccessResource();
        if (!$hasPermission) {
            return;
        }

        $team = new Team();
        $name_search = addslashes($this->request->get('name'));
        $result = $team->loadList($name_search);

        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
