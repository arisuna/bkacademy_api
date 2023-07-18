<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Models\Attributes;
use \Reloday\Gms\Models\AttributesValue;
use \Reloday\Gms\Models\AttributesValueTranslation;
use \Reloday\Gms\Models\ModuleModel;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class AttributesController extends BaseController
{
    /**
     * @Route("/assignment", paths={module="gms"}, methods={"GET"}, name="gms-assignment-index")
     */
    public function listAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $company = ModuleModel::$company;
        $language = ModuleModel::$language;
        $attributes = Attributes::__getAllAttributes();

        //load assignment list
        $results = [];
        foreach ($attributes as $attribute) {
            $results[] = [
                'id' => $attribute->getId(),
                'name' => $attribute->getName(),
                'code' => $attribute->getCode(),
                'values' => $attribute->getListValuesOfCompany($company->getId(), $language),
            ];
        }
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $results]);
        $this->response->send();
    }

    /**
     * [reloadAction description]
     * @return [type] [description]
     */
    public function reloadAction()
    {
        $this->view->disable();
        $this->response->setJsonContent(['success' => true, 'message' => 'NOT_NEED_RELOAD_TEXT']);
        $this->response->send();
    }

    /**
     * detail of an attribute
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function detailAction($id = '')
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $company = ModuleModel::$company;
        $user_profile = ModuleModel::$user_profile;
        $attribute = Attributes::findFirstById($id);
        $language = ModuleModel::$language;

        $values = [];
        foreach ($attribute->getListValuesOfCompany($company->getId(), $language) as $attribute_value) {
            $values[] = $attribute_value;
        }

        $return = [
            'success' => true,
            'data' => [
                'id' => $attribute->getId(),
                'name' => $attribute->getName(),
                'code' => $attribute->getCode(),
                'values' => $values,
                'values_archived' => $attribute->listValuesArchivedOfCompany($company->getId(), $language),
                'company_id' => $company->getId(),
            ]
        ];
        $this->response->setJsonContent($return);
        $this->response->send();
    }


    /**
     * detail of an attribute
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function editAction($params = '')
    {
        $this->view->disable();
        $this->checkAjax(['PUT', 'POST']);
        $this->checkAclEdit();


        $company = ModuleModel::$company;
        $language = ModuleModel::$language;

        //@TODO add attribute validation form here @TODO
        $attribute_data = Helpers::__getRequestValuesArray();
        $id = Helpers::__getRequestValue('id');
        $name = Helpers::__getRequestValue('name');
        $values = Helpers::__getRequestValue('values');
        $code = Helpers::__getRequestValue('code');
        $values_to_remove = Helpers::__getRequestValue('values_to_remove');


        $attribute = Attributes::findFirstById($id);
        if ($attribute) {
            $results = $attribute->__save(['values' => $values, 'values_to_remove' => $values_to_remove]);
        }
        $return = ['success' => true, 'message' => 'ATTRIBUTES_UPDATED_SUCCESS_TEXT', 'data' => []];
        $this->response->setJsonContent($return);
        $this->response->send();
    }

    /**
     * detail of an attribute
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function itemAction($name = '')
    {

        $this->view->disable();
        $company = ModuleModel::$company;
        $attribute = Attributes::findFirstByName($name);
        $language = ModuleModel::$language;
        $return = ['success' => false];
        if ($attribute) {
            $values = [];
            foreach ($attribute->getListValuesOfCompany($company->getId(), $language) as $attribute_value) {
                $values[] = $attribute_value;
            }
            $return = [
                'success' => true,
                'data' => [
                    'id' => $attribute->getId(),
                    'name' => $attribute->getName(),
                    'code' => $attribute->getCode(),
                    'values' => $values,
                    'company_id' => $company->getId(),
                ]
            ];
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * detail of an attribute
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function getByCodeAction($code = '')
    {
        $this->view->disable();
        $code = Helpers::__getQuery('code');
        $return = ['success' => false];
        if (is_string($code) && $code != '') {

            $explodes = explode('_', $code);
            $attributeId = $explodes[0];
            $attributeValueId = isset($explodes[1]) && $explodes[1] != '' ? $explodes[1] : null;

            if (Helpers::__isValidId($attributeId) && Helpers::__isValidId($attributeValueId)) {
                $attribute = Attributes::__findFirstByIdWithCache($attributeId);
                if ($attribute) {
                    $attributeValue = Attributes::__getTranslateValue($code, ModuleModel::$language);
                    if ($attributeValue) {
                        $return = [
                            'success' => true,
                            'data' => [
                                'id' => $attribute->getId(),
                                'value' => $attributeValue,
                                'code' => $code
                            ]
                        ];
                    }
                }
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     * detail of an attribute
     * @param string $id [description]
     * @return [type]     [description]
     */
    public function addNewValueAction()
    {
        $this->view->disable();

        $this->checkAjaxPost();

        $action = "index";
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        $name = Helpers::__getRequestValue('name');
        $value = Helpers::__sanitizeText(Helpers::__getRequestValue('value'));

        $attribute = Attributes::findFirstByName($name);
        $language = ModuleModel::$language;

        $return = ['success' => false];
        if ($attribute) {
            $return = $attribute->saveSimpleValue($value, ModuleModel::$company->getId(), $language);
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
