<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use \Reloday\Gms\Controllers\API\BaseController;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanyType;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserProfile;

use Reloday\Gms\Models\Policy;
use Reloday\Gms\Models\PolicyItem;
use Reloday\Gms\Models\AllowanceTitle;
use Reloday\Gms\Models\AllowanceType;
use Reloday\Gms\Models\Attributes;
use Reloday\Gms\Models\AttributesValue;
use Reloday\Gms\Models\AttributesValueTranslation;
use Reloday\Gms\Models\MediaAttachment;


/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class PolicyController extends BaseController
{
    /**
     * @Route("/policy", paths={module="gms"}, methods={"GET"}, name="gms-policy-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        $search = Policy::loadList();
        //load assignment list
        $results = [];

        if ($search['success'] == true) {
            $policies = $search['data'];
            if (count($policies)) {

                foreach ($policies as $policy) {
                    $item = $policy->toArray();
                    $item['assignment_type_name'] = $policy->getAssignmentType()->getName();
                    $results[] = $item;
                }
            } else {
                $results = [];
            }
        }
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $results]);
        return $this->response->send();
    }

    /**
     * Init data for company form
     * @method GET
     * @route /company/init
     * @return string
     */
    public function initAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();

        // Get init information: country, user in current company (empty for first time - create)
        $countries = Country::find(['order' => 'name']);
        $countries_arr = [];
        if (count($countries)) {
            foreach ($countries as $country) {
                $countries_arr[] = [
                    'text' => $country->getName(),
                    'value' => $country->getId()
                ];
            }
        }

        $employees = [];
        if (($id = $this->request->get('id'))) {
            $employees_list = UserProfile::find(['company_id=' . $id]);
            if (count($employees_list)) {
                foreach ($employees_list as $item) {
                    $employees[$item->getId()] = [
                        'value' => $item->getId(),
                        'text' => $item->getFirstname() . ' ' . $item->getLastname()
                    ];
                }
            }
        }

        echo json_encode([
            'success' => true,
            'countries' => $countries_arr,
            'employees' => $employees
        ]);

    }

    /**
     * [companyAction description]
     * @return [type] [description]
     */
    public function companyAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', $this->router->getControllerName());

        $search = Policy::loadList();
        //load assignment list
        $results = [];


        if ($search['success'] == true) {
            $policies = $search['data'];
            if (count($policies)) {
                foreach ($policies as $policy) {
                    $results[] = [
                        'id' => $policy->getId(),
                        'uuid' => $policy->getUuid(),
                        'name' => $policy->getName(),
                        'status' => $policy->getStatus()
                    ];
                }
            }
        }
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $results]);
        return $this->response->send();

    }

    /**
     * [detail action]
     * @param  [type] $policy_id [description]
     * @return [type]            [description]
     */
    public function detailAction($policy_id)
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        /** check policy id */
        $return = ['success' => false, 'message' => 'POLICY_NOT_FOUND_TEXT'];

        if (Helpers::__isValidId($policy_id)) {
            $policy = Policy::findFirstById($policy_id);
            if ($policy && $policy->belongsToGms()) {
                $policy_items = $policy->getPolicyItem();
                $policy_items_array = [];
                foreach ($policy_items as $policy_item) {
                    $policy_items_array[$policy_item->getId()] = $policy_item->toArray();
                    $policy_items_array[$policy_item->getId()]['allowance_type_name'] = $policy_item->getAllowanceType()->getName();
                    $policy_items_array[$policy_item->getId()]['allowance_title_name'] = $policy_item->getAllowanceTitle()->getName();
                }
            }
            $return = ['success' => true, 'message' => 'POLICY_SUCCESS_TEXT', 'data' => $policy, 'policy_items' => array_values($policy_items_array)];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * [detail action]
     * @param  [type] $policy_id [description]
     * @return [type]            [description]
     */
    public function detail_itemAction($policy_item_id)
    {
        $this->view->disable();
        $action = 'index';
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }
        /** check policy id */
        if ($policy_item_id > 0) {
            $policy_item = PolicyItem::findFirstId($policy_item_id);
            $policy_item_array['allowance_type_name'] = $policy_item->getAllowanceType()->getName();
            $policy_item_array['allowance_title_name'] = $policy_item->getAllowanceTitle()->getName();
            $policy_item_array = $policy_item->toArray();

            $this->response->setJsonContent(['success' => true, 'message' => 'POLICY_ITEM_SUCCESS_TEXT', 'data' => $policy_item]);
            return $this->response->send();
        } else {
            $this->response->setJsonContent(['success' => false, 'message' => 'POLICY_ITEM_NOT_FOUND_TEXT']);
            return $this->response->send();
        }
    }

    /**
     * Create or update method
     * @return string
     */
    public function saveAction()
    {
        $this->view->disable();

        if (!in_array($this->request->getMethod(), ['POST', 'PUT'])) {
            exit(json_encode([
                'success' => false,
                'message' => 'ACCESS_DENIED_TEXT'
            ]));
        }

        $action = $this->request->isPost() ? 'create' : 'edit';
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        /** define custom data */
        $custom_data = [];
        if ($this->request->isPut() && $this->request->getPut('uuid') != '' && $this->request->getPut('id')) {
            $custom_data['policy_id'] = $this->request->getPut('id');
            $custom_data['policy_uuid'] = $this->request->getPut('uuid');
        }


        $policyManager = new Policy();
        $policyModel = $policyManager->__save($custom_data);


        if ($policyModel instanceof Policy) {
            // Find current contract
            $return = [
                'success' => true,
                'message' => 'POLICY_SAVE_SUCCESS_TEXT',
                'data' => $policyModel
            ];
        } else {
            $return = $policyModel;
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Create or update method
     * @return string
     */
    public function save_policy_itemAction()
    {
        $this->view->disable();

        if (!in_array($this->request->getMethod(), ['POST', 'PUT'])) {
            exit(json_encode([
                'success' => false,
                'message' => 'ACCESS_DENIED_TEXT'
            ]));
        }

        $action = $this->request->isPost() ? 'create' : 'edit';
        $access = $this->canAccessResource($this->router->getControllerName(), $action);
        if (!$access['success']) {
            exit(json_encode($access));
        }

        /** define custom data */
        $custom_data = [];
        if ($this->request->isPut() && $this->request->getPut('uuid') != '' && $this->request->getPut('id')) {
            $custom_data['policy_item_id'] = $this->request->getPut('id');
            $custom_data['policy_item_uuid'] = $this->request->getPut('uuid');
            $custom_data['policy_id'] = $policy_id = $this->request->getPut('policy_id');
        }
        if ($this->request->isPost() && $this->request->getPost('policy_id') != '') {
            $custom_data['policy_id'] = $policy_id = $this->request->getPost('policy_id');
        }

        if ($policy_id > 0) {
            $policy = Policy::findFirstById($policy_id);
            if (!$policy) {
                $return = [
                    'success' => false,
                    'message' => 'POLICY_ITEM_SAVE_FAILED_TEXT',
                ];
                goto end_of_function;
            } else {
                $custom_data['company_id'] = $policy->getCompanyId();
            }
        } else {
            $return = [
                'success' => false,
                'message' => 'POLICY_ITEM_SAVE_FAILED_TEXT',
            ];
            goto end_of_function;
        }

        $policyItemManager = new PolicyItem();
        $policyItemModel = $policyItemManager->__save($custom_data);


        if ($policyItemModel instanceof PolicyItem) {
            // Find current contract
            if ($this->request->isPut()) {
                $data = $this->request->getPut();
            }
            if ($this->request->isPost()) {
                $data = $this->request->getPost();
            }

            if (isset($data['attachments']) && count($data['attachments']) > 0) {
                $attachments = $data['attachments'];
                $resultAttachment = MediaAttachment::__create_attachments($policyItemModel, $attachments, "policy_item");
            }


            $return = [
                'success' => true,
                'message' => 'POLICY_ITEM_SAVE_SUCCESS_TEXT',
                'data' => $policyItemModel
            ];
        } else {
            $return = [
                'success' => false,
                'message' => 'POLICY_ITEM_SAVE_FAILED_TEXT',
            ];
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /** delete policy */
    public function deleteAction($policy_uuid)
    {
        $this->view->disable();
        if (!$this->request->isDelete()) {
            exit('Restrict access');
        }
        $access = $this->canAccessResource($this->router->getControllerName(), 'delete');
        if (!$access['success']) {
            exit(json_encode($access));
        }

        if ($policy_uuid != '') {
            $policy = Policy::findFirstByUuid($policy_uuid);
            if ($policy && $policy instanceof Policy && $policy->belongsToGms() == true) {
                $return = $policy->remove();
            } else {
                $return = [
                    'success' => false,
                    'message' => 'DATA_NOT_FOUND_TEXT'
                ];
            }
        } else {
            $return = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /** delete policy item  */
    public function deleteitemAction()
    {

        $this->view->disable();
        if (!$this->request->isDelete()) {
            exit('Restrict access');
        }
        $access = $this->canAccessResource($this->router->getControllerName(), 'delete');
        if (!$access['success']) {
            exit(json_encode($access));
        }
        $data = $this->request->getJsonRawBody();
        $policy_id = isset($data->policy_id) ? $data->policy_id : null;
        $policy_item_uuid = isset($data->policy_item_uuid) ? $data->policy_item_uuid : null;


        if (is_numeric($policy_id) && $policy_id > 0 && $policy_item_uuid != '') {

            $policy = Policy::findFirstById($policy_id);

            if ($policy && $policy instanceof Policy && $policy->belongsToGms() == true) {
                $policyItem = PolicyItem::findFirstByUuid($policy_item_uuid);
                if ($policyItem && $policyItem->getPolicyId() == $policy->getId()) {
                    $return = $policyItem->remove();
                } else {
                    $return = [
                        'success' => false,
                        'message' => 'POLICY_ITEM_DATA_NOT_FOUND_TEXT'
                    ];
                }
            } else {
                $return = [
                    'success' => false,
                    'message' => 'POLICY_DATA_NOT_FOUND_TEXT'
                ];
            }
        } else {
            $return = [
                'success' => false,
                'message' => 'DATA_NOT_FOUND_TEXT'
            ];
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/policy", paths={module="gms"}, methods={"GET"}, name="gms-policy-index")
     */
    public function getSimpleListAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();
        $company_id = Helpers::__getRequestValue('company_id');
        $policies = [];
        if ($company_id > 0) {
            $policies = Policy::find([
                'conditions' => 'company_id = :company_id:',
                'bind' => [
                    'company_id' => $company_id
                ],
                'columns' => 'id, number, name, uuid'
            ]);
        }
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $policies]);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function searchAction(){
        $this->view->disable();
        $this->view->disable();
        $this->checkAjaxPutGet();
        $return = Policy::__findWithFilters([
            'company_id' => Helpers::__getRequestValue('company_id'),
            'query' => Helpers::__getRequestValue('query')
        ]);
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/policy", paths={module="gms"}, methods={"GET"}, name="gms-policy-index")
     */
    public function getListAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $ids = Helpers::__getRequestValue('ids');
        if ($ids && !is_array($ids)) {
            $ids = explode(',', $ids);
        }
        $params = [];

        if ($ids) {
            $params['ids'] = $ids;
        }

        $search = Policy::loadList($params);

        //load assignment list
        $results = [];

        if ($search['success'] == true) {
            $results = $search['data'];
        }
        $this->response->setJsonContent(['success' => true, 'message' => '', 'data' => $results]);
        return $this->response->send();
    }
}
