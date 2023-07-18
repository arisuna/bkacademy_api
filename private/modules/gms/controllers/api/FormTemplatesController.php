<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\ModelHelper;
use Reloday\Application\Models\DocumentTemplateFieldExt;
use Reloday\Application\Models\MapFieldExt;
use Reloday\Gms\Models\DocumentTemplate;
use Reloday\Gms\Models\DocumentTemplateFieldCondition;
use Reloday\Gms\Models\DocumentTemplateFile;
use Reloday\Gms\Models\Media;
use Reloday\Gms\Models\ModuleModel;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;
use Reloday\Gms\Models\DocumentTemplateField;
use Reloday\Gms\Models\DocumentPdf;
use Reloday\Application\Lib\RelodayQueue;
use Reloday\Application\Lib\RelodayS3Helper;
use Reloday\Application\Models\DependantExt;
use Reloday\Application\Models\EmployeeExt;
use Reloday\Application\Lib\Utils;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class FormTemplatesController extends BaseController
{
    /**
     * @Route("/form-templates", paths={module="gms"}, methods={"POST"}, name="gms-form-templates-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_FORM_TEMPLATE);

        $options = [];
        $options['query'] = Helpers::__getRequestValue('query');
        $options['page'] = Helpers::__getRequestValue('page');
        $options['limit'] = Helpers::__getRequestValue('limit');

        $result = DocumentTemplate::__findWithFilter($options, []);

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Pre create form
     */
    public function preCreateAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate(AclHelper::CONTROLLER_FORM_TEMPLATE);
        $uuid = Helpers::__uuid();
        $upload_url_pdf = RelodayS3Helper::__getPresignedUrlToUpload(DocumentTemplateFile::PATH_PREFIX . "/" . ModuleModel::$company->getUuid() . '/' . $uuid . '.pdf', "application/pdf");
        if (!$upload_url_pdf['success']) {
            $result = $upload_url_pdf;
            goto end_of_function;
        }
       
        $result = [
            'success' => true, 'data' => ['uuid' => $uuid, 'upload_url_pdf' => $upload_url_pdf["data"]]
        ];
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Create Action
     * @throws \Phalcon\Security\Exception
     * @throws \Exception
     */
    public function preUploadFileAction()
    {

        $uuid = Helpers::__getRequestValue('uuid');

        if (!Helpers::__isValidUuid($uuid)) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        /** REMOVE IF EXISTED */
        $existedFile = DocumentTemplateFile::__getFileByObjectUuid($uuid);
        if ($existedFile) {
            $delete = $existedFile->__quickRemove();
            if ($delete['success'] == false) {
                $return = ['success' => false, 'message' => 'UPLOAD_FAIL_TEXT'];
                goto end_of_function;
            }
        }

        $company = ModuleModel::$company;
        $name = Helpers::__getRequestValue('name');
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $type = Helpers::__getRequestValue('type');
        $size = Helpers::__getRequestValue('size');

        $this->db->begin();
        $documentTemplateFile = new DocumentTemplateFile();

        $fileUuid = Helpers::__uuid();
        $documentTemplateFile->setUuid($fileUuid);
        $documentTemplateFile->setFileName($uuid . '.' . $extension);
        $documentTemplateFile->setFileExtension($extension);
        $documentTemplateFile->setFileType(Media::__getFileType($extension));
        $documentTemplateFile->setMimeType($type);
        $documentTemplateFile->setName(pathinfo($name)['filename']);
        $documentTemplateFile->setObjectUuid($uuid);
        $documentTemplateFile->setCompanyUuid(ModuleModel::$company->getUuid());
        $documentTemplateFile->addDefaultFilePath();

        $resultFileCreate = $documentTemplateFile->__quickCreate();

        if (!$resultFileCreate['success']) {
            $return = $resultFileCreate;
            $this->db->rollback();
            goto end_of_function;
        }

        $this->db->commit();

        $return = [
            'success' => true,
            'data' => $documentTemplateFile->getTemporaryFileUrlS3(),
            'message' => 'FILE_UPLOADED_TEXT',
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Create Action
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclCreate(AclHelper::CONTROLLER_FORM_TEMPLATE);

        $name = Helpers::__getRequestValue('name');
        $languageId = Helpers::__getRequestValue('supported_language_id');
        $canvasFiles = Helpers::__getRequestValue('canvasFiles');
        $fileName = Helpers::__getRequestValue('fileName');
        $content = Helpers::__getRequestValue('content');
        $uuid = Helpers::__getRequestValue('uuid');

        if (!Helpers::__isValidUuid($uuid)) {
            $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
            goto end_of_function;
        }

        $documentTemplate = new DocumentTemplate();
        $documentTemplate->setUuid($uuid);
        $documentTemplate->setName($name);
        $documentTemplate->setCharactersSpacing(0);
        $documentTemplate->setTextSize(11);
        $documentTemplate->setCompanyId(ModuleModel::$company->getId());
        $documentTemplate->setIsInUsed(DocumentTemplate::NOT_IN_USED);
        $documentTemplate->setStatus(DocumentTemplate::STATUS_DRAFT);
        $documentTemplate->setSupportedLanguageId($languageId);

        $resultCreate = $documentTemplate->__quickCreate();
        if (!$resultCreate['success']) {
            $return = $resultCreate;
            goto end_of_function;
        }

        $return = [
            'success' => true,
            'message' => 'FILE_UPLOADED_TEXT',
            'data' => $documentTemplate->toArray()
        ];

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * detail Action
     */
    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (!Helpers::__isValidUuid($uuid)) {
            goto end_of_function;
        }
        $document = DocumentTemplate::findFirstByUuid($uuid);
        $this->db->begin();

        if ($document) {
            if ($document->getStatus() == DocumentTemplate::STATUS_ACTIVE) {
                $documentTemplateFieldsActive = $document->getDocumentTemplateFields(['conditions' => 'is_deleted = :is_deleted: and document_field_type = 0', 'bind' => ['is_deleted' => ModelHelper::NO]]);

                if (count($documentTemplateFieldsActive) > 0) {
                    $resultDel = $documentTemplateFieldsActive->delete();

                    if (!$resultDel) {
                        $this->db->rollback();
                        goto end_of_function;
                    }
                }
            }

            $data = $document->toArray();
            $data['document_template_file'] = $document->getDocumentTemplateFile() ? $document->getDocumentTemplateFile()->toArray() : [];
            $data['document_template_file']['pdf_file'] = $document->getDocumentTemplateFile() ? $document->getDocumentTemplateFile()->getTemporaryFileUrlS3() : '';

            if( $document->getDocumentTemplateFile() ){
                $document->getDocumentTemplateFile()->checkPageNumber();
            }

            $document_template_fields = $document->getDocumentTemplateFields(['conditions' => 'is_deleted = :is_deleted:', 'bind' => ['is_deleted' => ModelHelper::NO]]);

            $fields = [];
            foreach ($document_template_fields as $document_template_field) {
                $item = $document_template_field->parsedDataToArray();
//                if($document_template_field->getMapFieldId()){
//                    $item['mapField'] = $document_template_field->getMapField()->toArray();
//                    $item['mapField']['map_field_type_name'] = $document_template_field->getMapField()->getMapFieldType()->getName();
//                    if($document_template_field->getMapField()->getAttributeId()){
//                        $item['mapField']['attribute'] = $document_template_field->getMapField()->getAttribute();
//                    }
//                }

                $fields[] = $item;

            }
            $result = ['success' => true, 'data' => $data, 'documentTemplateFields' => $fields, 'isSetupField' => $document->isSetupField()];
        }

        if($result['success'] == true){
            $this->db->commit();
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * detail Action
     */
    public function deleteAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclDelete(AclHelper::CONTROLLER_FORM_TEMPLATE);

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($id <= 0) {
            goto end_of_function;
        }

        $this->db->begin();

        $document = DocumentTemplate::findFirstById($id);
        if (!$document) {
            goto end_of_function;
        }

        $resultDel = $document->__quickRemove();

        $result = ['success' => true];

        if (!$resultDel['success']) {
            $this->db->rollback();
            $result = $resultDel;
            goto end_of_function;
        }

        // start delete all

        $this->db->commit();

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * update Action
     */
    public function updateAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_FORM_TEMPLATE);

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (!Helpers::__checkId($id) || $id <= 0) {
            goto end_of_function;
        }

        $name = Helpers::__getRequestValue('name');
        $status = Helpers::__getRequestValue('status');
        $text_size = Helpers::__getRequestValue('text_size');
        $characters_spacing = Helpers::__getRequestValue('characters_spacing');
        $updateType = Helpers::__getRequestValue('update_type');
        $language_id = Helpers::__getRequestValue('supported_language_id');

        $document = DocumentTemplate::findFirstById($id);
        if (!$document) {
            goto end_of_function;
        }

        switch ($updateType) {
            case DocumentTemplate::NAME_TYPE :
                if ($name) {
                    $document->setName($name);
                }
                goto save_function;
            case DocumentTemplate::STATUS_TYPE :
                if (is_int($status)) {
                    $document->setStatus($status);
                }
                goto save_function;
            case DocumentTemplate::STYLE_TYPE:
                if (is_int($text_size) && is_int($characters_spacing)) {
                    $document->setTextSize($text_size);
                    $document->setCharactersSpacing($characters_spacing);
                }

                goto save_function;
            case DocumentTemplate::LANGUAGE_TYPE :
                $document->setSupportedLanguageId($language_id);
                goto save_function;
            default:
                goto save_function;
        }

        save_function:
        $result = $document->__quickSave();

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function removeMatchFieldAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclEdit(AclHelper::CONTROLLER_FORM_TEMPLATE);

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if (!$uuid) {
            goto end_of_function;
        }

        $docFiled = DocumentTemplateField::findFirst(['conditions' => 'uuid = :uuid:', 'bind' => ['uuid' => $uuid]]);
        if (!$docFiled instanceof DocumentTemplateField) {
            goto end_of_function;
        }
        $documentTemplate = $docFiled->getDocumentTemplate();
        if (!$documentTemplate instanceof DocumentTemplate) {
            goto end_of_function;
        }

        $this->db->begin();
        $map_field = $docFiled->getMapField();

        if($map_field instanceof MapField && $map_field->getTable() == MapField::TABLE_DEPENDANT){
            $documentTemplate->setNumberOfDependantField($documentTemplate->getNumberOfDependantField() - 1);
        }
        if($map_field instanceof MapField && $map_field->getTable() == MapField::TABLE_ENTITY_DOCUMENT){
            $documentTemplate->setNumberOfEntityDocumentField($documentTemplate->getNumberOfEntityDocumentField() - 1);
        }
        $result = $documentTemplate->__quickUpdate();
        if (!$result['success']) {
            $this->db->rollback();
            goto end_of_function;
        }

        $result = $docFiled->__quickremove();
        if (!$result['success']) {
            $this->db->rollback();
            goto end_of_function;
        }
        $this->db->commit();

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function updateDocumentTemplateFileAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclEdit(AclHelper::CONTROLLER_FORM_TEMPLATE);

        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        if ($id <= 0) {
            goto end_of_function;
        }

        $pageSize = Helpers::__getRequestValue('page_size');
        $docFile = DocumentTemplateFile::findFirstById($id);

        if ($docFile && $pageSize) {
            $docFile->setPageSize($pageSize);
        }

        $result = $docFile->__quickSave();

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function getPdfAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $this->checkAcl('index', AclHelper::CONTROLLER_FORM_TEMPLATE);
        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $target_uuid = Helpers::__getRequestValue('target_uuid');
        $target_type = Helpers::__getRequestValue('target_type');
        $relocation_id = Helpers::__getRequestValue('relocation_id');
        $pdf_uuid = Helpers::__getRequestValue('pdf_uuid');
        if (Helpers::__isValidUuid($uuid)) {
            $document = DocumentTemplate::findFirstByUuid($uuid);
            if (!$document) {
                goto end_of_function;
            }
            if ($document instanceof DocumentTemplate) {
                try {
                    $pdfObject = null;
                    if (Helpers::__isValidUuid($pdf_uuid)) {
                        $pdfObject = DocumentPdf::findFirstByUuid($pdf_uuid);
                    }
                    if ($pdfObject instanceof DocumentPdf) {
                        $employee = $pdfObject->getEmployee();
                        $target = $pdfObject->getTarget();
                        $content_file = $document->getName() . ".pdf";
                        if($target instanceof DependantExt){
                            $content_file = Utils::nomalize($target->getFullname()). " - " .Utils::nomalize($document->getName()) . ".pdf";
                        } else if($employee instanceof EmployeeExt){
                            $content_file = Utils::nomalize($employee->getFullname()). " - " .Utils::nomalize($document->getName()) . ".pdf";
                        }
                        $url = RelodayS3Helper::__getPresignedUrl("document/" .$document->getUuid() ."/" . $pdfObject->getUuid() . '-content.pdf', "", $content_file, "application/pdf");
                        if ($url) {
//                        if($pdfObjects[0]->getStatus() == DynamoPdfObject::STATUS_COMPLETED && $pdfObjects[0]->getUpdatedAt() >= strtotime($salaryCalculator->getUpdatedAt())){
                            $result['url'] = $url;
                            $result['success'] = true;
                            $result['detail'] = $pdfObject->toArray();
                            $result['message'] = "";
                            $result['time_elapsed'] = (time() - $pdfObject->getCreatedAt()) * 1000;
//                        }
                        }  else {
                            $result['success'] = false;
                            $result['detail'] = $pdfObject->toArray();
                            $result['message'] = "";
                            $result['time_elapsed'] = (time() - $pdfObject->getCreatedAt()) * 1000;
                        }
                        $result['data'] = $pdfObject->toArray();
                    } else {
                        $pdfObject = new DocumentPdf();
                        $pdf_uuid = Helpers::__uuid();
                        $pdfObject->setUuid($pdf_uuid);
                        $pdfObject->setDocumentTemplateId($document->getId());
                        $pdfObject->setType($target_type);
                        $pdfObject->setRelocationId($relocation_id);
                        $pdfObject->setTargetUuid($target_uuid);
                        $pdfObject->setStatus(DocumentPdf::STATUS_ON_LOADING);
                        $pdfObject->setCreatedAt(time());
                        $pdfObject->setUpdatedAt(time());
                        $result = $pdfObject->__quickCreate();
                        if (!$result["success"]) {
                            goto end_of_function;
                        }
                        $queue = new RelodayQueue(getenv('QUEUE_GENERATE_DOCUMENT_PDF'));
                        $result["addQueue"] = $queue->addQueue([
                            'uuid' => $document->getUuid(),
                            'action' => "generatePdf",
                            'params' => [
                                'uuid' => $document->getUuid(),
                                'pdf_uuid' => $pdf_uuid,
                                'company_uuid' => ModuleModel::$company->getUuid()
                            ],
                        ]);
                        $result["success"] = false;
                    }
                } catch (\Exception $e) {
                    $result = ['success' => false, 'detail' => $e->getMessage()];
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Clone form template
     * Form, File, Field
     */
    public function cloneAction(){
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', AclHelper::CONTROLLER_FORM_TEMPLATE);
        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $uuid = Helpers::__getRequestValue('uuid');
        $document = DocumentTemplate::findFirstByUuid($uuid);
        if(!$document){
            goto end_of_function;
        }
        $this->db->begin();
        //Clone Document template
        $formArr = $document->toArray();
        unset($formArr['uuid']);
        unset($formArr['id']);
        unset($formArr['name']);
        unset($formArr['created_at']);
        unset($formArr['updated_at']);
        unset($formArr['status']);

        $newDocument = new DocumentTemplate();
        $newDocument->setData($formArr);
        $newDocument->setUuid(Helpers::__uuid());
        $newDocument->setName('Clone of ' . $document->getName());
        $newDocument->setStatus(DocumentTemplate::STATUS_DRAFT);

        $resultCreate = $newDocument->__quickCreate();

        if(!$resultCreate['success']){
            $result = $resultCreate;
            $this->db->rollback();
            goto end_of_function;
        }
        //Clone File
        $copyFile = $document->getDocumentTemplateFile();

        $fileUuid = Helpers::__uuid();
        $documentTemplateFile = new DocumentTemplateFile();

        $documentTemplateFile->setUuid($fileUuid);
        $documentTemplateFile->setFileName($newDocument->getUuid() . '.' . $copyFile->getFileExtension());
        $documentTemplateFile->setFileExtension($copyFile->getFileExtension());
        $documentTemplateFile->setFileType($copyFile->getFileType());
        $documentTemplateFile->setMimeType($copyFile->getMimeType());
        $documentTemplateFile->setName($copyFile->getName());
        $documentTemplateFile->setObjectUuid($newDocument->getUuid());
        $documentTemplateFile->setCompanyUuid(ModuleModel::$company->getUuid());
        $documentTemplateFile->addDefaultFilePath();

        $resultFileCreate = $documentTemplateFile->__quickCreate();
        if(!$resultCreate['success']){
            $result = $resultFileCreate;
            $this->db->rollback();
            goto end_of_function;
        }
        // Copy file s3
        $fromFilePath = $copyFile->getFilePath();
        $toFilePath = $documentTemplateFile->getFilePath();

        $resultCopyFile = RelodayS3Helper::__copyMedia($fromFilePath, $toFilePath);

        if ($resultCopyFile['success'] == false) {
            $return = $resultCopyFile;
            $return['success'] = false;
            $return['message'] = "FILE_COPY_TO_S3_FAIL_TEXT";
            $this->db->rollback();
            goto end_of_function;
        }

        //Clone Fields
        $oldFields = $document->getDocumentTemplateFields(['conditions' => 'is_deleted != :is_deleted:', 'bind' => ['is_deleted' => ModelHelper::YES]]);
        foreach ($oldFields as $oldField){
            $field = $oldField->toArray();
            unset($field['id']);
            unset($field['uuid']);
            unset($field['created_at']);
            unset($field['updated_at']);
            unset($field['document_template_id']);
            $newField = new DocumentTemplateField();
            $newField->setData($field);
            $newField->setUuid(Helpers::__uuid());
            $newField->setDocumentTemplateId($newDocument->getId());
            $resultField = $newField->__quickCreate();

            if(!$resultField['success']){
                $result = $resultField;
                $this->db->rollback();
                goto end_of_function;
            }

            //Clone field conditions
            $fieldConditions = $oldField->getDocumentTemplateFieldConditions();
            foreach ($fieldConditions as $fieldCondition){
                $arrFieldCondition = $fieldCondition->toArray();
                unset($arrFieldCondition['id']);
                unset($arrFieldCondition['uuid']);
                unset($arrFieldCondition['created_at']);
                unset($arrFieldCondition['updated_at']);
                unset($arrFieldCondition['document_template_field_id']);

                $newFieldCondition = new DocumentTemplateFieldCondition();
                $newFieldCondition->setData($arrFieldCondition);
                $newFieldCondition->setUuid(Helpers::__uuid());
                $newFieldCondition->setDocumentTemplateFieldId($newField->getId());
                $resultFieldCondition = $newFieldCondition->__quickCreate();

                if(!$resultFieldCondition['success']){
                    $result = $resultFieldCondition;
                    $this->db->rollback();
                    goto end_of_function;
                }

            }
        }

        $this->db->commit();
        $result = [
            'success' => true,
            'data' => $newDocument,
            'message' => 'FORM_TEMPLATE_CLONE_SUCCESS_TEXT',
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }
}