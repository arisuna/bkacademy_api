<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Models\DocumentTemplateFieldExt;
use Reloday\Application\Models\MapFieldExt;
use Reloday\Application\Models\SupportedLanguageExt;
use Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\City;
use Reloday\Gms\Models\DocumentTemplateField;
use Reloday\Gms\Models\MapField;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ServiceCompany;

class MapFieldController extends BaseController
{
    /**
     * get list fields from object type
     */
    public function searchAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $objectType = Helpers::__getRequestValue('object_type');

        $params = [];

        if($objectType && $objectType > 0){
            if(empty(MapFieldExt::MAP_OBJECT_TYPE_TO_TABLE_NAME[$objectType])){
                if($objectType != DocumentTemplateField::OTHER_TYPE){
                    $return['data'] = [];
                    goto end;
                }else{
                    $return = [
                        'success' => true,
                        'data' => [
                            [
                                'id' => DocumentTemplateField::DEFAULT_VALUE_CURRENT_DATE,
                                'label' => 'CURRENT_DATE_TEXT'
                            ]
                        ]
                    ];
                    goto end;
                }

            }else{
                $tableName = MapFieldExt::MAP_OBJECT_TYPE_TO_TABLE_NAME[$objectType];

                $params['table'] = $tableName;
                if(MapFieldExt::MAP_OBJECT_TYPE_TO_TABLE_NAME[$objectType] == MapFieldExt::TABLE_SERVICE){
                    $serviceCompany = Helpers::__getRequestValue('serviceCompany');
                    $svCompany = ServiceCompany::findFirstByUuid($serviceCompany->uuid);
                    if($svCompany && $svCompany instanceof ServiceCompany){
                        $params['service_id'] = $svCompany->getServiceId();
                    }
                }

            }
        }

        $valueType = Helpers::__getRequestValue('value_type');
        if($valueType === DocumentTemplateFieldExt::VALUE_TYPE_CHECKBOX){
            $typeNames = DocumentTemplateFieldExt::MAP_FIELD_TYPE_CHECKBOX;
            $params['typeNames'] = $typeNames;
        }

        $tables = Helpers::__getRequestValue('tables');
        $params['tables'] = $tables;

        $limit = Helpers::__getRequestValue('limit');
        $params['limit'] = $limit;

        $is_suggested = Helpers::__getRequestValue('is_suggested');
        $params['is_suggested'] = $is_suggested;

        $isGroupTable = Helpers::__getRequestValue('isGroupTable');
        $params['isGroupTable'] = $isGroupTable;

        $translated_language_id = Helpers::__getRequestValue('translated_language_id');
        $supportedLang = SupportedLanguageExt::findFirstById($translated_language_id);
        $language = $supportedLang ? $supportedLang->getIso() : 'en' ;


        $mapFields = MapField::__findWithFilter($params, $language);


        if(!$mapFields){
            goto end;
        }

        $return = ['success' => true, 'data' => $mapFields];

        end:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * Get detail map field item
     */
    public function detailAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $mapField = MapField::findFirstByUuid($uuid);

        if(!$mapField){
            $mapField = MapField::findFirstById($uuid);
        }

        if(!$mapField){
            goto end;
        }

        $data = $mapField->toArray();

        $data['map_field_type_name'] = $mapField->getMapFieldType()->getName();
        if($mapField->getAttributeId() > 0){
            $data['attribute'] = $mapField->getAttribute()->toArray();
        }

        $return = ['success' => true, 'data' => $data];

        end:
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * get list fields from object type
     */
    public function getListFromObjectTypeAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();

        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];

        $objectType = Helpers::__getRequestValue('object_type');

        if($objectType && $objectType > 0){
            if(empty(MapFieldExt::MAP_OBJECT_TYPE_TO_TABLE_NAME[$objectType])){
                if($objectType != DocumentTemplateField::OTHER_TYPE){
                    $return['data'] = [];
                    goto end;
                }else{
                    $return = [
                        'success' => true,
                        'data' => [
                            [
                                'id' => DocumentTemplateField::DEFAULT_VALUE_CURRENT_DATE,
                                'label' => 'CURRENT_DATE_TEXT'
                            ]
                        ]
                    ];
                    goto end;
                }

            }else{
                $tableName = MapFieldExt::MAP_OBJECT_TYPE_TO_TABLE_NAME[$objectType];

                $params = [
                    'table' => $tableName
                ];

                $valueType = Helpers::__getRequestValue('value_type');
                if($valueType === DocumentTemplateFieldExt::VALUE_TYPE_CHECKBOX){
                    $typeNames = DocumentTemplateFieldExt::MAP_FIELD_TYPE_CHECKBOX;
                    $params['typeNames'] = $typeNames;
                }

                if(MapFieldExt::MAP_OBJECT_TYPE_TO_TABLE_NAME[$objectType] == MapFieldExt::TABLE_SERVICE){
                    $serviceCompany = Helpers::__getRequestValue('serviceCompany');
                    $svCompany = ServiceCompany::findFirstByUuid($serviceCompany->uuid);
                    if($svCompany && $svCompany instanceof ServiceCompany){
                        $params['service_id'] = $svCompany->getServiceId();
                    }
                }


                $mapFields = MapField::__findWithFilter($params);


                if(!$mapFields){
                    goto end;
                }

                $return = ['success' => true, 'data' => $mapFields];
            }

        }

        end:
        $this->response->setJsonContent($return);
        $this->response->send();
    }
}

