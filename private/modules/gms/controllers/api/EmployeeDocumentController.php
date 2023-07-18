<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Mvc\Controller\Base;
use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\EmployeeDocument;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class EmployeeDocumentController extends BaseController
{
    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkPermissionCreateEdit(AclHelper::CONTROLLER_EMPLOYEE);

        $employeeId = Helpers::__getRequestValue('employee_id');
        $documentTypeId = Helpers::__getRequestValue('document_type_id');
        $uuid = Helpers::__getRequestValue('uuid');

        $dataInput = Helpers::__getRequestValuesArray();

        $result = [
            'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
        ];


        if ($employeeId && Helpers::__isValidId($employeeId)) {
            $employee = Employee::findFirstByIdCache($employeeId);
            if (!$employee instanceof Employee && !$employee->manageByGms()) {
                $result = [
                    'success' => false, 'message' => 'PARAMETER_INVALID_TEXT'
                ];
                goto end_of_function;
            }
        }

        $document = new EmployeeDocument();
        $dataInput['employee_id'] = $employee->getId();
        $document->setData($dataInput);
        if (Helpers::__isValidUuid($uuid)) {
            $document->setUuid($uuid);
        }
        $resultSave = $document->__quickCreate();
        if ($resultSave['success'] == true) {
            $result = [
                'success' => true,
                'message' => 'SAVE_DOCUMENT_SUCCESS_TEXT',
                'data' => $document,
            ];
        } else {
            $result = [
                'success' => false, 'message' => 'SAVE_DOCUMENT_FAIL_TEXT'
            ];
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param String $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function updateAction(String $uuid)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkPermissionCreateEdit();

        $dataInput = Helpers::__getRequestValuesArray();

        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $document = EmployeeDocument::findFirstByUuid($uuid);
            if ($document instanceof EmployeeDocument && $document->getEmployee() && $document->getEmployee()->manageByGms()) {
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
     * @param String $uuid
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface\
     */
    public function deleteAction(String $uuid)
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'DELETE']);
        $this->checkPermissionCreateEdit();

        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $document = EmployeeDocument::findFirstByUuid($uuid);
            if ($document instanceof EmployeeDocument && $document->getEmployee() && $document->getEmployee()->manageByGms()) {
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
    public function listAction(String $uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        if ($uuid && Helpers::__isValidUuid($uuid)) {
            $employee = Employee::findFirstByUuidCache($uuid);
            if ($employee instanceof Employee && $employee->belongsToGms()) {
                $result = [
                    'success' => true,
                    'data' => $employee->getEmployeeDocuments(),
                ];
            } else {
                $result = [
                    'success' => false, 'message' => 'EMPLOYEE_NOT_FOUND_TEXT'
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
}
