<?php

namespace Reloday\Gms\Help;

use Phalcon\Di;
use Phalcon\Security\Random;
use Reloday\Gms\Models\MapField;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\RelocationServiceCompany;

class AutofillEmailTemplateHelper
{
    public static function previewContent($content)
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML(mb_convert_encoding('<?xml encoding="utf-8" ?>'. $content, 'HTML-ENTITIES', 'UTF-8'));
        $map_field_array = [];
        $i = 0;
        foreach ($document->getElementsByTagName('a') as $a) {
            $class = $a->getAttribute("target");
            if(Helpers::__isValidUuid($class)){
                $map_field = MapField::findFirstByUuid($class);
                if($map_field){
                    $substring = $a->nodeValue;
                    $fragment = $document->createDocumentFragment();
                    $fragment->appendXML($substring);
                    $a->parentNode->insertBefore($fragment,$a);
                    // $a->parentNode->removeChild($a);
                    $map_field_array[$i] =$a; 
                    $i ++;
                    // $document->saveHTML();
                }
            }
        }
        foreach($map_field_array as $child){
            $child->parentNode->removeChild($child);
        }
        $content = $document->saveHTML();
        $return = [
            "success" =>  true,
            "data" => $content,
            "map_fields" => $map_field_array
        ];
        return $return;
    }

    public static function fillContent($content, $phpDateFormat = "d/m/Y", $language = '', $access = true)
    {
        if($language == ''){
            $language = ModuleModel::$language;
        }

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML(mb_convert_encoding('<?xml encoding="utf-8" ?>'. $content, 'HTML-ENTITIES', 'UTF-8'));
        $map_field_array = [];
        $empty_fields_array = [];
        $i = 0;
        foreach ($document->getElementsByTagName('a') as $a) {
            $class = $a->getAttribute("target");
            if(Helpers::__isValidUuid($class)){
                $map_field = MapField::findFirstByUuid($class);
                if($map_field){
                    switch ($map_field->getTable()) {
                        case MapField::TABLE_ASSIGNMENT_BASIC:
                        case MapField::TABLE_ASSIGNMENT_DESTINATION:
                        case MapField::TABLE_ASSIGNMENT:
                            $value = $map_field->getFieldValue(ModuleModel::$assignment ? ModuleModel::$assignment->getUuid() : '', $language, $phpDateFormat);
                            break;
                        case MapField::TABLE_ASSIGNMENT_COMPANY_DATA:
                        case MapField::TABLE_RELOCATION:
                            $value = $map_field->getFieldValue(ModuleModel::$relocation ? ModuleModel::$relocation->getUuid() : '', $language, $phpDateFormat);
                            break;
                        case MapField::TABLE_EMPLOYEE: 
                            $value = $map_field->getFieldValue(ModuleModel::$employee ? ModuleModel::$employee->getUuid() : '', $language, $phpDateFormat);
                            break;
                        case MapField::TABLE_COMPANY:
                            $value = $map_field->getFieldValue(ModuleModel::$employee ? ModuleModel::$employee->getCompany()->getUuid() : '', ModuleModel::$language, $phpDateFormat);
                            break;
                        case MapField::TABLE_SERVICE:
                            if(ModuleModel::$relocationServiceCompany){
                                $value = $map_field->getFieldValue(ModuleModel::$relocationServiceCompany->getUuid(), ModuleModel::$language, $phpDateFormat);
                            }else{
                                $value = ['success' => true, 'data_text' => ''];
                            }
                            break;
                    }
                    if(!$value["success"]){
                        return $value;
                    }
                    if($access == true){
                        $substring = $value["data_text"];
                    }
                    if(!$value["data_text"] || !$access){
                        $substring = "[" . $map_field->getCode() . "]";
                        $empty_fields_array[] = "[" . $map_field->getCode() . "]";
                    }
                    $fragment = $document->createDocumentFragment();
                    $fragment->appendXML($substring);
                    $a->parentNode->insertBefore($fragment,$a);
                    // $a->parentNode->removeChild($a);
                    $map_field_array[$i] =$a; 
                    $i ++;
                    // $document->saveHTML();
                }
            }
        }
        foreach($map_field_array as $child){
            $child->parentNode->removeChild($child);
        }

//        $content = $document->saveHTML();
        $content = preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $document->saveHTML());

        $return = [
            "success" =>  true,
            "data" => $content,
            "map_fields" => $map_field_array,
            "empty_fields_array" => $empty_fields_array
        ];
        return $return;
    }

    /**
     * @param $content
     * @return bool
     */
    public static function isExistedField($content)
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML(mb_convert_encoding('<?xml encoding="utf-8" ?>'. $content, 'HTML-ENTITIES', 'UTF-8'));
        $map_field_array = [];
        $i = 0;
        foreach ($document->getElementsByTagName('a') as $a) {
            $class = $a->getAttribute("target");
            if(Helpers::__isValidUuid($class)){
                return true;
            }
        }

        return false;
    }
}