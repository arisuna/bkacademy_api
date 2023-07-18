<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\MapFieldExt;
use Reloday\Gms\Models\DocumentTemplate;
use Reloday\Gms\Models\DocumentTemplateField;
use Reloday\Gms\Models\DocumentTemplateFieldCondition;
use Reloday\Gms\Models\DocumentTemplateFile;
use Reloday\Gms\Models\MapField;
use Reloday\Gms\Models\Media;
use Reloday\Gms\Models\ModuleModel;
use Phalcon\Utils\Slug as PhpSlug;
use Phalcon\Security\Random;
use Reloday\Gms\Models\ServiceCompany;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class DocumentTemplateFieldController extends BaseController
{
    /**
     * get list object type
     */
    public function listObjectTypesAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $listObjectTypes = [];

        $resultServiceCompanies = ServiceCompany::getListActiveOfMyCompanyWithMapField([]);

        $valueType = Helpers::__getRequestValue('value_type');

        //TABLE OBJECT
        foreach (MapField::OBJECT_TYPES as $item) {
            $listObjectTypes[] = $item;
        }

        //SERVICE OBJECT

        if($resultServiceCompanies['success'] && count($resultServiceCompanies['data']) > 0){
            foreach ($resultServiceCompanies['data'] as $service){
                $item = $service->toArray();
                $item['object_type'] = array_search(MapFieldExt::TABLE_SERVICE, MapFieldExt::MAP_OBJECT_TYPE_TO_TABLE_NAME);
                $listObjectTypes[] = $item;
            }
        }


        //OTHER OBJECT
        if(isset($valueType) && $valueType != DocumentTemplateField::VALUE_TYPE_CHECKBOX){
            $listObjectTypes[] = DocumentTemplateField::OBJECT_OTHER_TYPE;
        }

        $result = ['success' => true, 'data' => $listObjectTypes];

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return void
     */
    public function detailAction($uuid){
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $item = DocumentTemplateField::findFirstByUuid($uuid);

        if(!$item){
            goto end_of_function;
        }

        $return['data'] =  $item->parsedDataToArray();
        $return['success'] = true;

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Save field
     */
    public function saveDocumentTemplateFieldAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        //TODO save Field

        $arrayData = Helpers::__getRequestValuesArray();

        $documentTemplateId = Helpers::__getRequestValue("document_template_id");
        $documentTemplate = DocumentTemplate::findFirstById($documentTemplateId);
        if (!$documentTemplate) {
            goto end_of_function;
        }

        $id = Helpers::__getRequestValue("id");
        $this->db->begin();
        $isNew = false;
        if ($id) {
            $item = DocumentTemplateField::findFirstById($id);
            $old_map_field = $item->getMapField();
        } else {
            $isNew = true;
            $item = new DocumentTemplateField();
            $old_map_field = null;
        }

        $item->setDocumentTemplateId($documentTemplate->getId());
        if (!$item->getId()) {
            $item->setUuid(Helpers::__uuid());
        }

        $item->setPageNumber($arrayData['page_number']);
        $item->setWidth($arrayData['width']);
        $item->setHeight($arrayData['height']);
        $item->setXAxis($arrayData['x_axis']);
        $item->setYAxis($arrayData['y_axis']);

        if (isset($arrayData['value_type'])) {
            $item->setValueType($arrayData['value_type']);
        }

        if (isset($arrayData['document_field_type'])) {
            if ($arrayData['document_field_type'] === DocumentTemplateField::FIELD_TYPE_RELOTALENT_FIELD) {
                $item->setDocumentFieldType($arrayData['document_field_type']);
                $item->setObjectType($arrayData['object_type']);

                if ($arrayData['object_type'] == array_search(MapFieldExt::TABLE_SERVICE, MapFieldExt::MAP_OBJECT_TYPE_TO_TABLE_NAME)) {
                    $serviceCompany = $arrayData['serviceCompany'];
                    if (is_array($serviceCompany)) {
                        $service_company_id = $serviceCompany['id'];
                    } else {
                        $service_company_id = $serviceCompany->id;
                    }
                    $item->setServiceCompanyId($service_company_id);
                } else {
                    $item->setServiceCompanyId(null);
                }

                //Check object type current date (10)
                if($arrayData['object_type'] == DocumentTemplateField::OTHER_TYPE){
                    $item->setDefaultValue($arrayData['map_field_id'] ?? null);
                    $item->setMapFieldId(0);
                }else{
                    $item->setMapFieldId($arrayData['map_field_id'] ?? null);
                    if($item->getValueType() === DocumentTemplateField::VALUE_TYPE_TEXT_NUMBER){
                        $item->setDefaultValue(null);
                    }
                }

                if(isset($arrayData['default_value'])){
                    $item->setDefaultValue($arrayData['default_value']);
                }
            } else {
                $item->setDocumentFieldType($arrayData['document_field_type']);
                $item->setObjectType(0);
                $item->setMapFieldId(0);
                $item->setDefaultValue($arrayData['default_value'] ?? null);
                $item->setServiceCompanyId(null);
            }
        }

        //update number of dependant fields of document template

        $map_field = $item->getMapField();

        if($map_field instanceof MapField && $map_field->getTable() == MapField::TABLE_DEPENDANT){
            if ($old_map_field instanceof MapField && $old_map_field->getTable() == MapField::TABLE_DEPENDANT) {

            } else {
                $documentTemplate->setNumberOfDependantField($documentTemplate->getNumberOfDependantField() + 1);
            }
        } else {
            if ($old_map_field instanceof MapField && $old_map_field->getTable() == MapField::TABLE_DEPENDANT) {
                $documentTemplate->setNumberOfDependantField($documentTemplate->getNumberOfDependantField() - 1);
            }
        }
        if($map_field instanceof MapField && $map_field->getTable() == MapField::TABLE_ENTITY_DOCUMENT){
            if ($old_map_field instanceof MapField && $old_map_field->getTable() == MapField::TABLE_ENTITY_DOCUMENT) {

            } else {
                $documentTemplate->setNumberOfEntityDocumentField($documentTemplate->getNumberOfEntityDocumentField() + 1);
            }
        } else {
            if ($old_map_field instanceof MapField && $old_map_field->getTable() == MapField::TABLE_ENTITY_DOCUMENT) {
                $documentTemplate->setNumberOfEntityDocumentField($documentTemplate->getNumberOfEntityDocumentField() - 1);
            }
        }
        $return = $documentTemplate->__quickUpdate();
        if (!$return['success']) {
            $this->db->rollback();
            goto end_of_function;
        }

        //update-create field
        if ($isNew) {
            $return = $item->__quickCreate();
        } else {
            $return = $item->__quickSave();
        }

        if (!$return['success']) {
            $this->db->rollback();
            goto end_of_function;
        }

        //Remove all condition if not the match relotalent field type
        if($item->getValueType() == DocumentTemplateField::VALUE_TYPE_TEXT_NUMBER && $item->getDocumentFieldType() != DocumentTemplateField::FIELD_TYPE_RELOTALENT_FIELD){
            $fieldConditionRemoves = $item->getDocumentTemplateFieldConditions()->delete();
        }

        $this->db->commit();
        $arrayData = $item->parsedDataToArray();

        $return['data'] = $arrayData;

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function saveStyleAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $arrayData = Helpers::__getRequestValuesArray();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $documentTemplateId = Helpers::__getRequestValue("document_template_id");
        $documentTemplate = DocumentTemplate::findFirstById($documentTemplateId);
        if (!$documentTemplate) {
            goto end_of_function;
        }

        $id = Helpers::__getRequestValue("id");
        $item = DocumentTemplateField::findFirstById($id);
        if (!$item) {
            goto end_of_function;
        }

        if (isset($arrayData['characters_spacing'])) {
            $item->setCharactersSpacing($arrayData['characters_spacing']);
        }

        if (isset($arrayData['text_size'])) {
            $item->setTextSize($arrayData['text_size']);
        }

        if (isset($arrayData['reset_style']) && $arrayData['reset_style']) {
            $item->setTextSize(null);
            $item->setCharactersSpacing(null);
        }

        $return = $item->__quickSave();
        if (!$return['success']) {
            goto end_of_function;
        }

        $return['data'] = $item->parsedDataToArray();;

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();

    }

    /**
     * Save condition alternative case
     */
    public function saveFieldConditionAction(){
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', AclHelper::CONTROLLER_FORM_TEMPLATE);

        $arrayData = Helpers::__getRequestValuesArray();
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $this->db->begin();

        $documentTemplateFieldId = Helpers::__getRequestValue("document_template_field_id");
        $documentTemplateField = DocumentTemplateField::findFirstById($documentTemplateFieldId);
        if (!$documentTemplateField) {
            goto end_of_function;
        }

        $documentTemplate = $documentTemplateField->getDocumentTemplate();

        $id = Helpers::__getRequestValue("id");
        $item = DocumentTemplateFieldCondition::findFirstById($id);
        if (!$item) {
            $item = new DocumentTemplateFieldCondition();
            $item->setUuid(Helpers::__uuid());
            $old_map_field = null;
        }else{
            $old_map_field = $item->getMapField();
        }

        $item->setDocumentTemplateFieldId($documentTemplateFieldId);
        $item->setObjectType(isset($arrayData['object_type']) ? $arrayData['object_type'] : null);
        $item->setConditionType(isset($arrayData['condition_type']) ? $arrayData['condition_type'] : DocumentTemplateFieldCondition::OR);

        if ($arrayData['object_type'] == array_search(MapFieldExt::TABLE_SERVICE, MapFieldExt::MAP_OBJECT_TYPE_TO_TABLE_NAME)) {
            $serviceCompany = $arrayData['serviceCompany'];
            if (is_array($serviceCompany)) {
                $service_company_id = $serviceCompany['id'];
            } else {
                $service_company_id = $serviceCompany->id;
            }
            $item->setServiceCompanyId($service_company_id);
        } else {
            $item->setServiceCompanyId(null);
        }

        //Check object type current date (10)
        if($arrayData['object_type'] == DocumentTemplateField::OTHER_TYPE){
            $item->setDefaultValue($arrayData['map_field_id'] ?? null);
            $item->setMapFieldId(0);
        }else{
            $item->setMapFieldId(isset($arrayData['map_field_id']) ? $arrayData['map_field_id'] : null);
            $item->setDefaultValue(null);
        }


        $return = $item->__quickSave();
        if (!$return['success']) {
            $this->db->rollback();
            goto end_of_function;
        }


        $map_field = $item->getMapField();
        if($map_field instanceof MapFieldExt && $map_field->getTable() == MapField::TABLE_DEPENDANT){
            if ($old_map_field instanceof MapFieldExt && $old_map_field->getTable() == MapField::TABLE_DEPENDANT) {

            } else {
                $documentTemplate->setNumberOfDependantField($documentTemplate->getNumberOfDependantField() + 1);
            }
        } else {
            if ($old_map_field instanceof MapFieldExt && $old_map_field->getTable() == MapField::TABLE_DEPENDANT) {
                $documentTemplate->setNumberOfDependantField($documentTemplate->getNumberOfEntityDocumentField() > 0 ? $documentTemplate->getNumberOfDependantField() - 1 : 0);
            }
        }
        if($map_field instanceof MapFieldExt && $map_field->getTable() == MapField::TABLE_ENTITY_DOCUMENT){
            if ($old_map_field instanceof MapFieldExt && $old_map_field->getTable() == MapField::TABLE_ENTITY_DOCUMENT) {

            } else {
                $documentTemplate->setNumberOfEntityDocumentField($documentTemplate->getNumberOfEntityDocumentField() + 1);
            }
        } else {
            if ($old_map_field instanceof MapFieldExt && $old_map_field->getTable() == MapField::TABLE_ENTITY_DOCUMENT) {
                $documentTemplate->setNumberOfEntityDocumentField($documentTemplate->getNumberOfEntityDocumentField() > 0 ? $documentTemplate->getNumberOfEntityDocumentField() - 1 : 0);
            }
        }

        $returnDocumentTemplate = $documentTemplate->__quickUpdate();
        if(!$returnDocumentTemplate['success']){
            $return = $returnDocumentTemplate;
            $this->db->rollback();
            goto end_of_function;
        }

        $this->db->commit();
        $return['data'] = $item->parsedDataToArray();

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     * @return \Phalcon\Http\ResponseInterface
     */
    public function removeFieldConditionAction($uuid){
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAcl('index', AclHelper::CONTROLLER_FORM_TEMPLATE);
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $this->db->begin();
        $item = DocumentTemplateFieldCondition::findFirstByUuid($uuid);
        if(!$item){
            goto end_of_function;
        }

        $return = $item->__quickRemove();

        if(!$return['success']){
            $this->db->rollback();
            goto end_of_function;
        }
        $this->db->commit();
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}