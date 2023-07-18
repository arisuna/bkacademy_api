<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\EntityDocument;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class EntityDocumentController extends BaseController
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
    public function createDocumentAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate();

        $uuid = Helpers::__getRequestValue('uuid');
        $type = Helpers::__getRequestValue('type');
        $dataInput = Helpers::__getRequestValuesArray();

        $result = [
            'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
        ];

        if ($uuid && Helpers::__isValidUuid($uuid)) {
            if ($type == "employee") {
                $entity = Employee::findFirstByUuidCache($uuid);
                if (!$entity instanceof Employee && !$entity->manageByGms()) {
                    $result = [
                        'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
                    ];

                    goto end_of_function;
                }
            }
        } else {
            $result = [
                'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
            ];
            goto end_of_function;
        }

        $document = new EntityDocument();
        $dataInput['entity_id'] = $entity->getId();
        $dataInput['entity_uuid'] = $entity->getUuid();
        $dataInput['entity_name'] = $entity->getSource();
        $document->setData($dataInput);
        $resultSave = $document->__quickCreate();
        if ($resultSave['success'] == true) {
            $result = [
                'success' => true,
                'message' => 'SAVE_DOCUMENT_SUCCESS_TEXT',
                'data' => $document,
            ];
        } else {
            $result = $resultSave;
            $result = [
                'success' => false, 'message' => 'SAVE_DOCUMENT_FAIL_TEXT'
            ];
        }

        end_of_function:
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
        $this->checkPermissionCreateEdit();

        $dataInput = Helpers::__getRequestValuesArray();

        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $document = EntityDocument::findFirstByUuid($uuid);
            if ($document instanceof EntityDocument && $document->belongsToHr()) {

                $dataInput['entity_id'] = $document->getEntityId();
                $document->setData($dataInput);

                $resultSave = $document->__quickUpdate();
                if ($resultSave['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'SAVE_DOCUMENT_SUCCESS_TEXT',
                        'data' => $document,
                    ];
                } else {
                    $result = $resultSave;
                    $result = [
                        'success' => false, 'message' => 'SAVE_DOCUMENT_FAIL_TEXT'
                    ];
                }
            } else {
                $result = [
                    'success' => false, 'message' => 'SAVE_DOCUMENT_FAIL_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
            ];
        }
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
        $this->checkPermissionCreateEdit();

        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $document = EntityDocument::findFirstByUuid($uuid);
            if ($document instanceof EntityDocument && $document->belongsToHr()) {
                $resultDelete = $document->__quickRemove();
                if ($resultDelete['success'] == true) {
                    $result = [
                        'success' => true,
                        'message' => 'DELETE_DOCUMENT_SUCCESS_TEXT',
                        'data' => $document,
                    ];
                } else {
                    $result = $resultDelete;
                    $result = [
                        'success' => false, 'message' => 'DELETE_DOCUMENT_FAIL_TEXT'
                    ];
                }
            } else {
                $result = [
                    'success' => false, 'message' => 'DELETE_DOCUMENT_FAIL_TEXT'
                ];
            }
        } else {
            $result = [
                'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
            ];
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
        goto end_of_function;

        //@TODO check Entity Documents
        if ($uuid && Helpers::__isValidUuid($uuid)) {
            if ($type == "employee") {
                $entity = Employee::findFirstByUuidCache($uuid);
                if (!$entity instanceof Employee && !$entity->manageByGms()) {
                    $result = [
                        'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
                    ];

                    goto end_of_function;
                }
            }
        } else {
            $result = [
                'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
            ];
            goto end_of_function;
        }


        if (isset($entity) && $entity instanceof Employee && $entity->belongsToHr()) {
            $result = [
                'success' => true,
                'message' => 'SAVE_DOCUMENT_SUCCESS_TEXT',
                'data' => $entity->getEntityDocuments(),
            ];
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
