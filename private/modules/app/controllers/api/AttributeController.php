<?php

namespace SMXD\App\Controllers\API;

use SMXD\App\Models\Attributes;
use SMXD\App\Models\AttributesValue;
use SMXD\App\Models\AttributesValueTranslation;
use SMXD\App\Models\Company;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\SupportedLanguage;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;

class AttributeController extends BaseController
{
    public function initialize()
    {
        $this->view->disable();
    }

    /**
     * @Route("/attribute", paths={module="backend"}, methods={"GET"}, name="backend-attribute-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $list = Attributes::find();
        $this->response->setJsonContent([
            'success' => true,
            'data' => count($list) ? $list->toArray() : []
        ]);

        end:
        $this->response->send();
    }

    public function searchAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPutGet();
        $params = [];
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['order'] = Helpers::__getRequestValue('order');
        $params['page'] = Helpers::__getRequestValue('page');
        $params['search'] = Helpers::__getRequestValue('query');
        $result = Attributes::__findWithFilters($params);
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function initializeAction()
    {
        $languages = SupportedLanguage::find();
        if (count($languages)) {
            $_tmp = [];
            foreach ($languages as $language) {
                $_tmp[$language->getIso()] = $language->getName();
            }
            $languages = $_tmp;
        } else {
            $languages = [];
        }

        $this->response->setJsonContent([
            'success' => true,
            'languages' => $languages
        ]);
        $this->response->send();
    }

    public function detailAction($id)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();

        $data = Attributes::findFirst('id=' . (int)$id);
        $data = $data instanceof Attributes ? $data->toArray() : [];
        $_tmp = [];
        if (count($data)) {
            // Find value
            $attribute_values = AttributesValue::find("attributes_id={$data['id']} AND company_id IS NULL AND archived = " . AttributesValue::STATUS_ARCHIVED_NO);
            if (count($attribute_values)) {
                foreach ($attribute_values as $key => $attribute_value) {
                    $_tmp[$key] = $attribute_value->toArray();
                    // Find translate value
                    $value_translations = AttributesValueTranslation::find("attributes_value_id={$attribute_value->getId()}");
                    if (count($value_translations)) {
                        foreach ($value_translations as $value_translation) {
                            $_tmp[$key]['data_translation'][$value_translation->getLanguage()] = $value_translation->toArray();
                        }
                    }
                }
            }
        }
        $data['data_value'] = $_tmp;

        $this->response->setJsonContent([
            'success' => true,
            'data' => $data
        ]);

        end:
        $this->response->send();
    }

    /**
     *
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();
        $this->response->setJsonContent($this->__save());
        end:
        $this->response->send();
    }

    /**
     *
     */
    public function updateAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPut();

        //Change Archived status of attribute value
        if (Helpers::__getRequestValue('arrayHasDelete') && count(Helpers::__getRequestValue('arrayHasDelete')) > 0) {
            $arrAttributeValue = Helpers::__getRequestValue('arrayHasDelete');
            foreach ($arrAttributeValue as $key => $value) {
                $attributeValueVModel = AttributesValue::findFirst('id=' . (int)$value);
                $attributeValueVModel->setArchived(AttributesValue::STATUS_ARCHIVED_YES);
                if ($attributeValueVModel->save()) {
                    $this->response->setJsonContent([
                        'success' => true,
                        'message' => 'ATTRIBUTE_CHANGE_ARCHIVED_TEXT'
                    ]);
                } else {
                    $this->response->setJsonContent([
                        'success' => false,
                        'message' => 'DATA_NOT_FOUND_TEXT'
                    ]);
                    goto end;
                }
            }
        }
        $this->response->setJsonContent($this->__save());
        end:
        $this->response->send();
    }

    /**
     * @return array
     */
    private function __save()
    {
        $model = new Attributes();
        if ((int)Helpers::__getRequestValue('id')) {
            $model = Attributes::findFirst((int)Helpers::__getRequestValue('id'));
            if (!$model instanceof Attributes) {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
                goto end;
            }
        }
        $model->setName(Helpers::__getRequestValue('name'));
        $model->setCode(Helpers::__getRequestValue('code'));
        $model->setDescription(Helpers::__getRequestValue('description'));

        $this->db->begin();
        $resultSave = $model->__quickSave();
        if ($resultSave['success'] == true) {
            $data_value = Helpers::__getRequestValueAsArray('data_value');
            $current_data_value = AttributesValue::find("attributes_id={$model->getId()}");
            if (empty($data_value) || !is_array($data_value)) {
                if (count($current_data_value))
                    foreach ($current_data_value as $item) {
                        if (!$item->delete()) {
                            $this->db->rollback();
                            $result = ([
                                'result' => false,
                                'message' => "DATA_DELETE_FAIL_TEXT"
                            ]);
                            goto end;
                        } // STOP
                    }
            } else {
                // Check value has been removed
                if (count($current_data_value)) {
                    foreach ($current_data_value as $item) {
                        $existed = false;
                        foreach ($data_value as $value) {
                            if (isset($value['id']) && $item->getId() == $value['id']) {
                                $existed = true;
                                break;
                            }
                        }
                        if (!$existed) {
                            if (!$item->delete()) {
                                $this->db->rollback();
                                $result = ([
                                    'result' => false,
                                    'message' => "DATA_DELETE_FAIL_TEXT"
                                ]);
                                goto end;
                            } // STOP
                        }
                    }
                }
            }

            // Add new or update value
            if (is_array($data_value) && count($data_value)) {
                foreach ($data_value as $item) {
                    $result = $this->__saveValue($model, $item);
                    if (!$result['success']) {
                        $this->db->rollback();
                        goto end;
                    } // STOP
                }
            }

            $this->db->commit();
            $result = ([
                'success' => true,
                'message' => 'DATA_SAVE_SUCCESS_TEXT'
            ]);
        } else {
            $this->db->rollback();
            $result = ([
                'success' => false,
                'message' => $resultSave['message']
            ]);
        }

        end:
        return $result;
    }

    /**
     * Old function
     */
    public function saveAction()
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();

        $model = new Attributes();
        if ((int)Helpers::__getRequestValue('id') > 0) {
            $model = Attributes::findFirst((int)Helpers::__getRequestValue('id'));
            if (!$model instanceof Attributes) {
                exit(json_encode([
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ]));
            }
        }
        $model->setName(Helpers::__getRequestValue('name'));
        $model->setCode(Helpers::__getRequestValue('code'));
        $model->setDescription(Helpers::__getRequestValue('description'));

        $this->db->begin();
        if ($model->save()) {

            $data_value = Helpers::__getRequestValue('data_value');
            $current_data_value = AttributesValue::find("attributes_id={$model->getId()}");
            if (empty($data_value) || !is_array($data_value)) {
                if (count($current_data_value))
                    foreach ($current_data_value as $item) {
                        if (!$item->delete()) {
                            $this->db->rollback();
                            $this->response->setJsonContent([
                                'result' => false,
                                'message' => "DATA_DELETE_FAIL_TEXT"
                            ]);
                            goto end;
                        } // STOP
                    }
            } else {
                // Check value has been removed
                if (count($current_data_value)) {
                    foreach ($current_data_value as $item) {
                        $existed = false;
                        foreach ($data_value as $value) {
                            if (isset($value['id']) && $item->getId() == $value['id']) {
                                $existed = true;
                                break;
                            }
                        }
                        if (!$existed) {
                            if (!$item->delete()) {
                                $this->db->rollback();
                                $this->response->setJsonContent([
                                    'result' => false,
                                    'message' => "DATA_DELETE_FAIL_TEXT"
                                ]);
                                goto end;
                            } // STOP
                        }
                    }
                }
            }

            // Add new or update value
            if (is_array($data_value) && count($data_value)) {
                foreach ($data_value as $item) {
                    $result = $this->__saveValue($model, $item);
                    if (!$result['success']) {
                        $this->db->rollback();
                        exit(json_encode($result));
                    } // STOP
                }
            }

            $this->db->commit();
            $this->response->setJsonContent([
                'success' => true,
                'message' => 'DATA_SAVE_SUCCESS_TEXT',
            ]);
        } else {
            $this->db->rollback();
            $msg = [];
            foreach ($model->getMessages() as $message) {
                $msg[] = $message->getMessage();
            }
            $this->response->setJsonContent([
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
                'detail' => $msg
            ]);
        }

        end:
        $this->response->send();
    }

    /**
     * @param Attributes $attribute
     * @param array $data
     * @return array
     */
    private function __saveValue(Attributes $attribute, $data = [])
    {
        $value = new AttributesValue();
        if (isset($data['id']) && (int)$data['id'] > 0) {
            $value = AttributesValue::findFirst((int)$data['id']);
            if (!$value instanceof AttributesValue) {
                return [
                    'success' => false,
                    'message' => "ATTRIBUTE_VALUE_NOT_FOUND_TEXT"
                ];
            }
        }
        // Save value
        $value->setValue($data['value']);
        $value->setStandard((int)$data['standard']);
        $value->setAttributesId($attribute->getId());

        if (!$value->save()) {
            return [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];
        } else { // Save value success
            $value_translation = isset($data['data_translation']) ?
                $data['data_translation'] : [];
            // Update or add new translate value
            if (is_array($value_translation) && count($value_translation)) {
                foreach ($value_translation as $item) {
                    $result = $this->__saveValueTranslate($value, $item);
                    if (!$result['success']) {
                        return $result;
                    } // STOP
                }
            }
        }
        return [
            'success' => true,
            'message' => 'DATA_SAVE_SUCCESS_TEXT'
        ];
    }

    private function __saveValueTranslate(AttributesValue $value, $data = [])
    {
        $translation = new AttributesValueTranslation();
        if (isset($data['id']) && (int)$data['id']) {
            $translation = AttributesValueTranslation::findFirst("id={$data['id']}");
            if (!$translation instanceof AttributesValueTranslation) {
                return [
                    'success' => false,
                    'message' => "DATA_NOT_FOUND_TEXT"
                ];
            } // STOP
        }

        $translation->setValue(isset($data['value']) ? $data['value'] : '');
        $translation->setLanguage(isset($data['language']) ? $data['language'] : '');
        $translation->setAttributesValueId($value->getId());

        if (!$translation->save()) {
            return [
                'success' => false,
                'message' => 'DATA_SAVE_FAIL_TEXT',
            ];
        } // STOP

        return ['success' => true, 'message' => 'DATA_SAVE_SUCCESS_TEXT'];
    }

    /**
     * @param $id
     */
    public function deleteAction($id)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxDelete();

        $result = [
            'success' => false,
            'message' => 'YOU_DO_NOT_HAVE_PERMISSION_TEXT'
        ];


        if (Helpers::__isValidId($id)) {
            $model = Attributes::findFirst('id=' . (int)$id);
            if ($model instanceof Attributes) {
                $result = $model->__quickRemove();
                if ($result['success'] == false) {
                    $result = [
                        'success' => false,
                        'message' => 'DATA_DELETE_FAIL_TEXT'
                    ];
                }
            } else {
                $result = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        }

        $this->response->setJsonContent($result);
        end:
        return $this->response->send();
    }


    // ---------------------
    // API function --------
    // ---------------------

    /**
     * @param int $company_id
     * @param string $name
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function itemAction($name = '', int $company_id = 0)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();

        $attribute = Attributes::findFirst(['name="' . addslashes($name) . '"']);
        $company = ModuleModel::$company;
        $language = ModuleModel::$language;

        $return = ['success' => false];
        if ($attribute) {
            $values = [];

            $companyId = $company ? $company->getId() : 0;

            foreach ($attribute->getListValuesOfCompany($companyId, $language) as $attribute_value) {
                $values[] = $attribute_value;
            }

            $return = [
                'success' => true,
                'data' => [
                    'id' => $attribute->getId(),
                    'name' => $attribute->getName(),
                    'code' => $attribute->getCode(),
                    'values' => $values,
                    'company_id' => $company_id,
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
    public function addNewValueAction($company_id = 0)
    {
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxPost();

        $return = ['success' => false];
        $company_id = Helpers::__getRequestValue('company_id');
        if (!$company_id) {
            goto end;
        }

        $name = Helpers::__getRequestValue('name');
        $value = Helpers::__sanitizeText(Helpers::__getRequestValue('value'));

        $attribute = Attributes::findFirstByName($name);
        $company = Company::findFirstById($company_id);

        $language = $company->getLanguage() | 'en';

        if ($attribute) {
            $return = $attribute->saveSimpleValue($value, $company_id, $language);
        }

        end:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    function getAttributeValuesByIdAction($attrId = ''){
        $this->view->disable();
        $this->checkAclIndex(AclHelper::CONTROLLER_ADMIN);
        $this->checkAjaxGet();

        $attributeValueList = [];

        $handlerAttrId = explode('_', trim($attrId)); // 4_13
        $attributeId = $handlerAttrId[0]; // 4
        $attributeValueId = $handlerAttrId[1]; // 13

        $attributeValue = AttributesValue::findFirstById($attributeValueId);
        if($attributeValue){
            $attributeValueList['value_name'] = $attributeValue->getValue();
        }


        $this->response->setJsonContent(['success' => true, 'data' => $attributeValueList]);
        return $this->response->send();
    }


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
}