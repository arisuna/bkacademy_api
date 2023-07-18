<?php

namespace Reloday\Gms\Controllers\API;

use Microsoft\Graph\Model\Entity;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\EntityDocument;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\ModuleModel;

/**
 * Concrete implementation of GMS module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AssigneeDocumentController extends BaseController
{
    /**
     * @Route("/entitydocument", paths={module="gms"}, methods={"GET"}, name="gms-entitydocument-index")
     */
    public function indexAction()
    {

    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function preCreateDocumentAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);
        $result = [
            'success' => true, 'data' => ['uuid' => Helpers::__uuid()]
        ];
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createDocumentAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);

        $entityUuid = Helpers::__getRequestValue('entity_uuid');
        $uuid = Helpers::__getRequestValue('uuid');
        $type = Helpers::__getRequestValue('type');
        $dataInput = Helpers::__getRequestValuesArray();
        $result = [
            'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
        ];

        $document = null;

        if ($entityUuid && Helpers::__isValidUuid($entityUuid)) {
            $employee = Employee::findFirstByUuidCache($entityUuid);
            if ($employee && $employee->belongsToGms()) {
                $document = new EntityDocument();
                $dataInput['uuid'] = $uuid;
                $dataInput['entity_id'] = $employee->getId();
                $dataInput['entity_uuid'] = $employee->getUuid();
                $dataInput['entity_name'] = $employee->getSource();
                $document->getCompanyId($employee->getCompanyId());
                $document->setData($dataInput);
                $document->setIsActive(ModelHelper::YES);
                $document->setIsShared(ModelHelper::YES);
                $document->setUuid($uuid);
                $resultSave = $document->__quickCreate();
                if ($resultSave['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'SAVE_DOCUMENT_SUCCESS_TEXT',
                        'data' => $document,
                    ];
                } else {
                    $result = $resultSave;
                    $result['message'] = 'SAVE_DOCUMENT_FAIL_TEXT';
                }
                goto end_of_function;
            } else {
                $dependant = Dependant::findFirstByUuidCache($entityUuid);
                if ($dependant && $dependant->belongsToGms()) {
                    $document = new EntityDocument();
                    $dataInput['uuid'] = $uuid;
                    $dataInput['entity_id'] = $dependant->getId();
                    $dataInput['entity_uuid'] = $dependant->getUuid();
                    $dataInput['entity_name'] = $dependant->getSource();
                    if ($dependant->getEmployee()) {
                        $document->getCompanyId($dependant->getEmployee()->getCompanyId());
                    }
                    $document->setUuid($uuid);
                    $document->setData($dataInput);
                    $document->setIsActive(ModelHelper::YES);
                    $document->setIsShared(ModelHelper::YES);
                    $resultSave = $document->__quickCreate();
                    if ($resultSave['success'] == true) {
                        $result = [
                            'success' => true,
                            'message' => 'SAVE_DOCUMENT_SUCCESS_TEXT',
                            'data' => $document,
                        ];
                    } else {
                        $result = $resultSave;
                        $result['message'] = 'SAVE_DOCUMENT_FAIL_TEXT';
                    }

                    goto end_of_function;
                }
            }
        }

        end_of_function:
        if ($result['success'] == true) {
            ModuleModel::$assigneeDocument = isset($document) ? $document : null;
            ModuleModel::$employee = isset($document) && $document->belongsToEmployee() ? $document->getEmployee() : null;
            ModuleModel::$dependant = isset($document) && $document->belongsToDependant() ? $document->getDependant() : null;
            // if(isset($document) && $document->belongsToDependant()){
            //     ModuleModel::$employee = ModuleModel::$dependant->getEmployee();
            // }
            $this->dispatcher->setParam('return', $result);
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     *
     */
    public function updateDocumentAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);

        $dataInput = Helpers::__getRequestValuesArray();
        $result = [
            'success' => false, 'message' => 'SAVE_DOCUMENT_FAIL_TEXT'
        ];
        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $document = EntityDocument::findFirstByUuid($uuid);
            if ($document instanceof EntityDocument && $document->belongsToGms()) {
                $document->setData($dataInput);
                $resultSave = $document->__quickUpdate();
                if ($resultSave['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'SAVE_DOCUMENT_SUCCESS_TEXT',
                        'data' => $document,
                    ];
                }
            }
        }
        if ($result['success'] == true) {
            ModuleModel::$assigneeDocument = isset($document) ? $document : null;
            ModuleModel::$employee = isset($document) && $document->belongsToEmployee() ? $document->getEmployee() : null;
            ModuleModel::$dependant = isset($document) && $document->belongsToDependant() ? $document->getDependant() : null;
            // if(isset($document) && $document->belongsToDependant()){
            //     ModuleModel::$employee = ModuleModel::$dependant->getEmployee();
            // }
            $this->dispatcher->setParam('return', $result);
        }

        $result['oldValue'] = ModuleModel::$assigneeDocument->getOldFieldValue('expiry_date');
        $result['hasChanged'] = ModuleModel::$assigneeDocument->hasChanged('expiry_date');
        $result['changedFields'] = ModuleModel::$assigneeDocument->getChangedFields();
        $result['hasUpdated'] = ModuleModel::$assigneeDocument->hasUpdated('expiry_date');
        $result['updatedFields'] = ModuleModel::$assigneeDocument->getUpdatedFields();
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     *
     */
    public function deleteDocumentAction($uuid)
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'DELETE']);
        $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);
        $result = [
            'success' => false, 'message' => 'DELETE_DOCUMENT_FAIL_TEXT'
        ];
        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $document = EntityDocument::findFirstByUuid($uuid);
            if ($document instanceof EntityDocument && $document->belongsToGms()) {
                $resultDelete = $document->__quickRemove();
                if ($resultDelete['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'DELETE_DOCUMENT_SUCCESS_TEXT',
                        'data' => $document,
                    ];
                }
            }
        }
        if ($result['success'] == true) {
            ModuleModel::$assigneeDocument = isset($document) ? $document : null;
            ModuleModel::$employee = isset($document) && $document->belongsToEmployee() ? $document->getEmployee() : null;
            ModuleModel::$dependant = isset($document) && $document->belongsToDependant() ? $document->getDependant() : null;
            // if(isset($document) && $document->belongsToDependant()){
            //     ModuleModel::$employee = ModuleModel::$dependant->getEmployee();
            // }
            $this->dispatcher->setParam('return', $result);
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getDocumentsAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $type = Helpers::__getRequestValue('type');
        $result = ['success' => true, 'data' => []];

        //@TODO check Entity Documents
        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $entity = Employee::findFirstByUuidCache($uuid);
            if (!$entity) {
                $entity = Dependant::findFirstByUuidCache($uuid);
            }
            if (isset($entity) && $entity->belongsToGms()) {
                $documents = EntityDocument::__getDocumentsByEntityUuid($entity->getUuid());
                $result = [
                    'success' => true,
                    'data' => $documents,
                ];
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @param $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getDocumentAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclEdit(AclHelper::CONTROLLER_EMPLOYEE);
        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $document = EntityDocument::findFirstByUuid($uuid);
            $data = $document->toArray();
            $data['document_type_label'] = $document->getDocumentType()->getLabel();
            if ($document instanceof EntityDocument && $document->belongsToGms()) {
                $result = [
                    'success' => true,
                    'data' => $data,
                ];
            } else {
                $result = [
                    'success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'
            ];
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
