<?php


namespace Reloday\Gms\Controllers\API;

use Exception;
use PDOException;
use Reloday\Application\Models\FilterConfigExt;
use Reloday\Application\Models\FilterConfigItemExt;
use Reloday\Application\Models\FilterConfigItemValueExt;
use Reloday\Gms\Models\FilterField;
use Reloday\Gms\Models\FilterConfig;
use Reloday\Gms\Models\FilterConfigItem;
use Reloday\Gms\Models\FilterConfigItemValue;
use Reloday\Gms\Models\ModuleModel;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class FilterConfigController extends BaseController
{
    private $_aModuleType = FilterConfig::MODULE_TYPE;

    /**
     * Action allowing create new filter config
     */
    public function saveFilterConfigAction()
    {
        $dataInput = $this->request->getJsonRawBody(true);

        if (isset ($dataInput['filter_config_id'])) {
            $filterConfig = FilterConfig::findFirst((int)$dataInput['filter_config_id']);
            if($filterConfig){
                $this->_editFilterConfig($dataInput);
            }else{
                $this->_createFilterConfig($dataInput);
            }
        } else {
            $this->_createFilterConfig($dataInput);
        }

    }

    /**
     * Temporally save filter config
     */
    public function tmpSaveFilterConfigAction()
    {
        $result = ['success' => false, 'message' => 'TMP_FILTER_CONFIG_SAVE_FAILED_TEXT'];

        $dataInput = $this->request->getJsonRawBody(true);

        if (!isset($dataInput["filter_config_id"]) || empty($dataInput["items"])) {
            goto end_of_function;
        } else {
            try {
                $this->cache->save(
                    'TMP_ITEMS_FILTER_' . $dataInput['filter_config_id'],
                    $dataInput["items"],
                    86400
                );
                $result = ['success' => true, 'message' => 'TMP_FILTER_CONFIG_SAVE_SUCCESS_TEXT'];

            } catch (PDOException $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            } catch (Exception $e) {
                $result = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Allow create new filter config and items
     * @param $dataInput
     * @return mixed
     */
    private function _createFilterConfig($dataInput)
    {
        $this->db->begin();

        $foundByName = FilterConfigExt::__findByName($dataInput['name'], $dataInput['creator_user_profile_id'], $this->_aModuleType[$dataInput['target']]);

        if ($foundByName) {
            $result = [
                'success' => false,
                'message' => 'FILTER_CONFIG_NAME_EXISTED'
            ];

            $this->db->rollback();
            goto end_of_function;
        }

        $filterConfig = new FilterConfig();
        if (!isset($dataInput['is_filter_public']) || !$dataInput['is_filter_public']) {
            $filterConfig->setUserProfileId($dataInput['user_profile_id']);
        }
        $filterConfig->setCreatorUserProfileId($dataInput['creator_user_profile_id']);
        $filterConfig->setName($dataInput['name']);
        $filterConfig->setTarget($this->_aModuleType[$dataInput['target']]);
        $filterConfig->setTargetActive($dataInput['target_active']);
        $filterConfig->setCompanyId($dataInput['company_id']);

        if (!$filterConfig->save()) {
            $msg = [];
            foreach ($filterConfig->getMessages() as $message) {
                $msg[$message->getField()] = $message->getMessage();
            }

            $result = [
                'success' => false,
                'message' => 'SAVE_FILTER_CONFIG_FAIL_TEXT',
                'detail' => $msg
            ];

            $this->db->rollback();
            goto end_of_function;
        }

        foreach ($dataInput['items'] as $item) {
            $filterConfigItem = new FilterConfigItem();
            $filterConfigItem->setFilterConfigId($filterConfig->getId());
            $filterConfigItem->setFilterFieldId($item['fieldId']);
            $filterConfigItem->setFieldName($item['fieldName']);
            $filterConfigItem->setCreatedAt(date('Y-m-d H:i:s'));

            if (!$filterConfigItem->save()) {
                $msg = [];
                foreach ($filterConfigItem->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'SAVE_FILTER_ITEM_FAIL_TEXT',
                    'detail' => $msg
                ];

                $this->db->rollback();
                goto end_of_function;
            }

            $filterConfigItemValue = new FilterConfigItemValue();
            $filterConfigItemValue->setFilterConfigItemId($filterConfigItem->getId());
            $filterConfigItemValue->setOperator($item['operator']);
            $itemValue = is_array($item['value']) ? json_encode($item['value']) : $item['value'];
            $filterConfigItemValue->setValue($itemValue);
            $filterConfigItemValue->setCreatedAt(date('Y-m-d H:i:s'));

            if (!$filterConfigItemValue->save()) {
                $msg = [];
                foreach ($filterConfigItemValue->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'SAVE_FILTER_ITEM_VALUE_FAIL_TEXT',
                    'detail' => $msg
                ];

                $this->db->rollback();
                goto end_of_function;
            }
        }

        $this->db->commit();

        $result = [
            'success' => true,
            'message' => 'SAVE_FILTER_SUCCESS_TEXT',
            'filterName' => $filterConfig->getName(),
            'isClone' => true,
            'filterConfigId' => $filterConfig->getId()
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Allow edit new filter config and items
     * @param $dataInput
     * @return mixed
     */
    private function _editFilterConfig($dataInput)
    {
        $this->db->begin();
        $isClone = false;
        $filterConfig = FilterConfig::findFirst((int)$dataInput['filter_config_id']);
        if ($filterConfig->getCreatorUserProfileId() == null && $filterConfig->getUserProfileId() == null){
            $filterConfig = new FilterConfig();
            $isClone = true;
        }
        if ($filterConfig && $filterConfig->getName() != $dataInput['name']){
            $foundByName = FilterConfigExt::__findByName($dataInput['name'], $dataInput['creator_user_profile_id'], $this->_aModuleType[$dataInput['target']]);
            if ($foundByName) {
                $result = [
                    'success' => false,
                    'message' => 'FILTER_CONFIG_NAME_EXISTED'
                ];

                $this->db->rollback();
                goto end_of_function;
            }
        }

        if ($filterConfig->getCreatorUserProfileId() != null &&
            $filterConfig->isPublic() &&
            (!ModuleModel::$user_profile->isAdmin() && $filterConfig->getCreatorUserProfileId() != ModuleModel::$user_profile->getId())){
            $result = [
                'success' => false,
                'message' => 'YOU_DO_NOT_HAVE_PERMISSION_ACCESSED_TEXT'
            ];

            $this->db->rollback();
            goto end_of_function;
        }

        if (isset($dataInput['is_filter_public']) && $dataInput['is_filter_public'] == true){
            $filterConfig->setUserProfileId(null);
        }

        if (isset($dataInput['is_filter_public']) && $dataInput['is_filter_public'] == false){
            $filterConfig->setUserProfileId($dataInput['user_profile_id']);
        }

        if ($isClone == true || (!isset($dataInput['is_filter_public']) &&
            $filterConfig->getUserProfileId() != null &&
            $filterConfig->getCreatorUserProfileId() == $dataInput['creator_user_profile_id'])){
            $filterConfig->setUserProfileId($dataInput['user_profile_id']);
        }
        if (!$filterConfig->getCreatorUserProfileId()){
            $filterConfig->setCreatorUserProfileId($dataInput['creator_user_profile_id']);
        }
        $filterConfig->setName($dataInput['name']);
        $filterConfig->setTarget($this->_aModuleType[$dataInput['target']]);
        $filterConfig->setCompanyId($dataInput['company_id']);
        $filterConfig->setTargetActive($dataInput['target_active']);

        if (!$filterConfig->save()) {
            $msg = [];
            foreach ($filterConfig->getMessages() as $message) {
                $msg[$message->getField()] = $message->getMessage();
            }

            $result = [
                'success' => false,
                'message' => 'SAVE_FILTER_CONFIG_FAIL_TEXT',
                'detail' => $msg
            ];

            $this->db->rollback();
            goto end_of_function;
        }

        $aItemOrig = FilterConfigItem::find([
            'columns' => 'id',
            'conditions' => 'filter_config_id = :filter_config_id:',
            'bind' => [
                'filter_config_id' => $filterConfig->getId()
            ]
        ])->toArray();

        $aItemToRm = [];
        foreach ($aItemOrig as $item) {
            $aItemToRm[] = intval($item['id']);
        }

        foreach ($dataInput['items'] as $item) {

            if (isset ($item['filter_config_item_id']) && !$isClone) {
                $filterConfigItem = FilterConfigItem::findFirst((int)$item['filter_config_item_id']);
                // Exclude this in delete set
                $key = array_search($item["filter_config_item_id"], $aItemToRm);
                if ($key !== false) {
                    unset($aItemToRm[$key]);
                }
            } else {
                $filterConfigItem = new FilterConfigItem();
            }

            $filterConfigItem->setFilterConfigId($filterConfig->getId());
            $filterConfigItem->setFilterFieldId($item['fieldId']);
            $filterConfigItem->setFieldName($item['fieldName']);
            $filterConfigItem->setCreatedAt(date('Y-m-d H:i:s'));

            if (!$filterConfigItem->save()) {
                $msg = [];
                foreach ($filterConfigItem->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'SAVE_FILTER_ITEM_FAIL_TEXT',
                    'detail' => $msg
                ];

                $this->db->rollback();
                goto end_of_function;
            }

            if (isset ($item['filter_config_item_id']) && !$isClone) {
                $filterConfigItemValue = FilterConfigItemValue::findFirst(
                    ['filter_config_item_id=' . (int)$item['filter_config_item_id']]
                );

            } else {
                $filterConfigItemValue = new FilterConfigItemValue();
            }

            $filterConfigItemValue->setFilterConfigItemId($filterConfigItem->getId());
            $filterConfigItemValue->setOperator($item['operator']);
            $itemValue = is_array($item['value']) ? json_encode($item['value']) : $item['value'];
            $filterConfigItemValue->setValue($itemValue);
            $filterConfigItemValue->setCreatedAt(date('Y-m-d H:i:s'));

            if (!$filterConfigItemValue->save()) {
                $msg = [];
                foreach ($filterConfigItemValue->getMessages() as $message) {
                    $msg[$message->getField()] = $message->getMessage();
                }
                $result = [
                    'success' => false,
                    'message' => 'SAVE_FILTER_ITEM_VALUE_FAIL_TEXT',
                    'detail' => $msg
                ];

                $this->db->rollback();
                goto end_of_function;
            }
        }
        // Delete no chosen items
        $aItemToRm = array_values($aItemToRm);
        if (!empty($aItemToRm)) {
            if (!FilterConfigItemValue::find([
                "conditions" => "filter_config_item_id IN ({to_rm_ids:array})",
                "bind" => [
                    "to_rm_ids" => $aItemToRm
                ]
            ])->delete()) {
                $this->db->rollback();
                $result = [
                    'success' => false,
                    'message' => 'SAVE_FILTER_ITEM_VALUE_FAIL_TEXT',
                ];
                goto end_of_function;
            }

            if (!FilterConfigItem::find([
                "conditions" => "id IN ({to_rm_ids:array})",
                "bind" => [
                    "to_rm_ids" => $aItemToRm
                ]
            ])->delete()) {
                $this->db->rollback();
                $result = [
                    'success' => false,
                    'message' => 'SAVE_FILTER_ITEM_VALUE_FAIL_TEXT',
                ];
                goto end_of_function;
            }
        }

        $this->db->commit();

        $result = [
            'success' => true,
            'message' => 'SAVE_FILTER_SUCCESS_TEXT',
            'filterName' => $filterConfig->getName(),
            'isClone' => $isClone,
            'filterConfigId' => $filterConfig->getId()
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Allow retrieving rows from filter_config table
     * @return mixed
     */
    public function listFilterConfigAction()
    {
        $result = [
            'success' => false,
            'message' => 'LIST_FILTER_CONFIG_FAIL_TEXT',
            'data' => []
        ];

        $dataInput = $this->request->getJsonRawBody(true);

        $filterConfig = new FilterConfig();

        $aOptions = [
            'target' => $this->_aModuleType[$dataInput['moduleName']],
            'target_active' => isset($dataInput['target_active']) ? $dataInput['target_active'] : 1,
            'user_profile_id' => isset($dataInput['user_profile_id']) ? $dataInput['user_profile_id'] : null,
            'creator_user_profile_id' => isset($dataInput['creator_user_profile_id']) ? $dataInput['creator_user_profile_id'] : null,
            'company_id' => isset($dataInput['company_id']) ? $dataInput['company_id'] : null,
            'is_admin' => isset($dataInput['is_admin']) ? $dataInput['is_admin'] : null,
            'is_manager' => isset($dataInput['is_manager']) ? $dataInput['is_manager'] : false,
        ];

        $aFilterConfig = FilterConfig::listByCriteria($aOptions);

        if ($aFilterConfig['success'] && !empty($aFilterConfig['data'])) {

            $result = [
                'success' => true,
                'message' => 'LIST_FILTER_CONFIG_SUCCESS_TEXT',
                'data' => $aFilterConfig['data']
            ];
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Allow retrieving rows from filter_config table
     * @return mixed
     */
    public function filterConfigAction()
    {
        $result = [
            'success' => false,
            'message' => 'FILTER_CONFIG_FAIL_TEXT',
            'data' => []
        ];

        $dataInput = $this->request->getJsonRawBody(true);

        $aOptions = [
            'target' => $this->_aModuleType[$dataInput['moduleName']],
            'target_active' => isset($dataInput['target_active']) ? $dataInput['target_active'] : 1,
            'user_profile_id' => isset($dataInput['user_profile_id']) ? $dataInput['user_profile_id'] : null,
            'creator_user_profile_id' => isset($dataInput['creator_user_profile_id']) ? $dataInput['creator_user_profile_id'] : null,
            'company_id' => isset($dataInput['company_id']) ? $dataInput['company_id'] : null,
            'is_admin' => isset($dataInput['is_admin']) ? $dataInput['is_admin'] : null,
            'is_manager' => isset($dataInput['is_manager']) ? $dataInput['is_manager'] : false,
            'filter' => isset($dataInput['filter']) ? $dataInput['filter'] : null,
        ];

        $aFilterConfig = FilterConfig::filterItemByCriteria($aOptions);

        if ($aFilterConfig['success']) {

            $result = [
                'success' => true,
                'message' => 'FILTER_CONFIG_SUCCESS_TEXT',
                'data' => $aFilterConfig['data']
            ];
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Allow retrieving return items for a filter config
     * @return mixed
     */
    public function listItemsByFilterAction()
    {
        $result = [
            'success' => false,
            'message' => 'LIST_FILTER_CONFIG_FAIL_TEXT',
            'data' => []
        ];

        $dataInput = $this->request->getJsonRawBody(true);

        $filterConfigItem = new FilterConfigItem();

        $aOptions = [
            'target' => $this->_aModuleType[$dataInput['target']],
            'filter_config_id' => $dataInput['filter_config_id']
        ];

        $aItem = $filterConfigItem::listJoinByCriteria($aOptions);

        if ($aItem['success'] && !empty($aItem['data'])) {
            $result = [
                'success' => true,
                'message' => 'LIST_FILTER_CONFIG_ITEMS_SUCCESS_TEXT',
                'data' => $aItem['data']
            ];
        }

        $this->response->setJsonContent($result);
        return $this->response->send();

    }

    /**
     * Allow retrieve rows from filter_field table
     */
    public function listFilterFieldAction()
    {
        $result = [
            'success' => false,
            'message' => 'LIST_FILTER_FIELD_FAIL_TEXT',
            'data' => []
        ];

        $dataInput = $this->request->getJsonRawBody(true);

        $aCriteria = [
            'target' => $this->_aModuleType[$dataInput['target']]
        ];

        $aFilterField = FilterField::listByCriteria($aCriteria);

        if ($aFilterField['success'] && !empty($aFilterField['data'])) {
            $result = [
                'success' => true,
                'message' => 'LIST_FILTER_FIELD_SUCCESS_TEXT',
                'data' => $aFilterField['data']
            ];
        }

        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * Allow delete a filter config
     * @param $id
     * @return mixed
     */
    public function deleteFilterConfigAction($id)
    {
        $this->view->disable();
        $this->checkAjax('DELETE');

        $result = ['success' => false, 'message' => 'FILTER_CONFIG_NOT_FOUND_TEXT'];

        $filterConfig = FilterConfig::findFirst($id);

        if (!$filterConfig) {
            goto end_of_function;
        }

        $aFilterConfigItem = FilterConfigItemExt::find([
            "conditions" => "filter_config_id =:filter_config_id:",
            "bind" => [
                "filter_config_id" => $id
            ]
        ])->toArray();

        $this->db->begin();
        foreach ($aFilterConfigItem as $item) {
            $oFilterConfigItemValue = FilterConfigItemValueExt::findFirst([
                "conditions" => "filter_config_item_id =:filter_config_item_id:",
                "bind" => [
                    "filter_config_item_id" => $item['id']
                ]
            ]);
            if (!$oFilterConfigItemValue->__quickRemove()) {
                $this->db->rollback();
                goto end_of_function;
            }

            $oFilterConfigItem = FilterConfigItem::findFirst($item['id']);
            if (!$oFilterConfigItem->__quickRemove()) {
                $this->db->rollback();
                goto end_of_function;
            }
        }

        if (!$filterConfig->__quickRemove()) {
            $this->db->rollback();
            goto end_of_function;
        }

        $this->db->commit();

        $result = [
            'success' => true,
            'message' => 'DELETE_FILTER_SUCCESS_TEXT',
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

}
