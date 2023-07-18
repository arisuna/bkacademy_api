<?php

namespace Reloday\Gms\Controllers\API;

use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use \Reloday\Gms\Models\AssignmentType;
use \Reloday\Gms\Models\ServicePack;
use \Reloday\Gms\Models\ModuleModel;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AssignmenttypesController extends BaseController
{
    /**
     * @Route("/assignment-type", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function listAction()
    {

        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index',$this->router->getControllerName());
        $assignment_types = AssignmentType::getAllCurrentAssignmentType();
        //assignment type in contract
        $results = [];

        if (count($assignment_types)) {
            foreach ($assignment_types as $assignment_type) {
                $results[] = [
                    'id' => $assignment_type->getId(),
                    'name' => $assignment_type->name,
                    'reference' => $assignment_type->reference,
                    'company_name' => $assignment_type->company->name,
                    'status' => $assignment_type->getStatus(),
                    'service_packs' => $assignment_type->service_packs,
                ];
            }
        }
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $results]);
        $this->response->send();
    }


    /**
     * list assigmnet type by company
     * @param  string $company_id [description]
     * @return [type]             [description]
     */
    public function companyAction($company_id = '')
    {

        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index',$this->router->getControllerName());
        $assignment_types = AssignmentType::getAllCurrentAssignmentType($company_id);
        //assignment type in contract
        $results = [];

        if (count($assignment_types)) {
            foreach ($assignment_types as $assignment_type) {
                $results[] = [
                    'id' => $assignment_type->getId(),
                    'name' => $assignment_type->name,
                    'reference' => $assignment_type->reference,
                    'company_name' => $assignment_type->company->name,
                    'service_packs' => $assignment_type->service_packs,
                ];
            }
        }

        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $results]);
        $this->response->send();
    }

    /**
     * @Route("/assignment-type/create", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('create',$this->router->getControllerName());


        if ($this->request->isPost()) {

            //@TODO add attribute validation form here @TODO

            $assignment_type_data = $this->request->getJsonRawBody();
            $name = $assignment_type_data->name;
            $service_packs = $assignment_type_data->service_packs;
            $reference = $assignment_type_data->reference;
            $status = $assignment_type_data->status;
            $company_id = $assignment_type_data->company_id;

            //var_dump( $assignment_type_data );die();

            //check if status exist
            $this->db->begin();


            //create attribute template
            $assignmentType = new AssignmentType();
            $assignmentType->setName($name);
            $assignmentType->setReference($reference);
            $assignmentType->setCompanyId($company_id);
            $assignmentType->setCreatedAt(date('Y-m-d H:i:s'));
            $assignmentType->setUpdatedAt(date('Y-m-d H:i:s'));
            $assignmentType->setStatus(intval($status));
            try {
                if ($assignmentType->save() === false) {
                    $this->db->rollback();
                    $error_message = $assignmentType->getMessages();
                    $return = ['success' => false, 'message' => implode('. ', $error_message)];
                    goto end_create_function;
                }
            } catch (\PDOException $e) {
                $return = ['success' => false, 'message' => $e->getMessage()];
                $this->db->rollback();
                goto end_create_function;
            }

            $service_pack_to_save = [];
            foreach ($service_packs as $service_pack_item) {
                if (is_numeric($service_pack_item) && $service_pack_item > 0) {
                    $servicePack = ServicePack::findFirstById($service_pack_item);
                    if ($servicePack) {
                        $service_pack_to_save[] = $servicePack;
                    }
                } elseif (is_object($service_pack_item) && property_exists($service_pack_item, "id")) {
                    $servicePack = ServicePack::findFirstById($service_pack_item->id);
                    if ($servicePack) {
                        $service_pack_to_save[] = $servicePack;
                    }
                }
            }
            $assignmentType->service_packs = $service_pack_to_save;

            try {
                if ($assignmentType->save() === false) {
                    $this->db->rollback();
                    $error_message = $assignmentType->getMessages();
                    $return = ['success' => false, 'message' => implode('. ', $error_message)];
                    goto end_create_function;
                }
            } catch (\PDOException $e) {
                $return = ['success' => false, 'message' => $e->getMessage()];
                $this->db->rollback();
                goto end_create_function;
            }

            $this->db->commit();

            $return = ['success' => true, 'message' => 'ASSIGNMENT_TYPE_CREATE_SUCCESS_TEXT', 'data' => []];
            goto end_create_function;
        } else {
            $return = ['success' => false, 'message' => 'POST_REQUEST_ONLY_TEXT'];
            goto end_create_function;
        }


        end_create_function:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * detail of an attribute
     * @param  string $id [description]
     * @return [type]     [description]
     */
    public function detailAction($id = '')
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index',$this->router->getControllerName());

        if ($id == '' || $id == 0) {
            $return = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
            $this->response->setJsonContent($return);
            return $this->response->send();
        }


        $company = ModuleModel::$company;
        $user_profile = ModuleModel::$user_profile;
        $assignment_type = AssignmentType::findFirstById($id);
        $language = ModuleModel::$language;

        $service_packs = $assignment_type->service_packs;

        $assignment_array = $assignment_type->toArray();
        foreach ($service_packs as $item) {
            $assignment_array['service_packs'][] = $item->getId();
        }
        //@TODO recheck this part
        //$assignment_array['service_packs'] = $service_packs;
        $return = [
            'success' => true,
            'data' => $assignment_array
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [editAction description]
     * @return [type] [description]
     */
    public function editAction()
    {
        $this->view->disable();

        $action = "edit";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        $custom_data = [];

        $assignmentType = new AssignmentType();
        $this->db->begin();
        $result = $assignmentType->__save($custom_data);

        if ($result instanceof AssignmentType) {
            $this->db->commit();
            $msg = ['success' => true, 'message' => 'ASSIGNMENT_UPDATED_SUCCESS_TEXT'];
        } else {
            $this->db->rollback();
            $msg = $result;
        }

        $this->response->setJsonContent($msg);
        return $this->response->send();
    }

    /**
     * [editAction description]
     * @return [type] [description]
     */
    public function deleteAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        if ($id == '' || $id == 0) {
            $return = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
            $this->response->setJsonContent($return);
            return $this->response->send();
        }

        $assignment_type = AssignmentType::findFirstById($id);

        if ($assignment_type && $assignment_type->belongsToGms()) {
            $result = $assignment_type->archive();
            if ($result !== true) {
                $return = $result;
            } else {
                $return = [
                    'success' => true,
                    'data' => 'DATA_DELETED_SUCCESS_TEXT'
                ];
            }
        } else {
            $return = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
