<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\TaxRule;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class TaxRuleController extends BaseController
{
    /**
     * @Route("/taxrule", paths={module="gms"}, methods={"GET"}, name="gms-taxrule-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);
        $taxRules = TaxRule::find([
            'company_id=' . ModuleModel::$user_profile->getCompanyId() . " AND status!=" . TaxRule::STATUS_DELETED
        ]);
        $data = [];
        if (count($taxRules)) {
            foreach ($taxRules as $tax) {
                $data[] = array_merge($tax->toArray(), ['country' => $tax->getCountry() ? $tax->getCountry()->getName() : ""]);
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => ($data),
        ]);
        $this->response->send();
    }

    /**
     * @Route("/taxrule", paths={module="gms"}, methods={"GET"}, name="gms-taxrule-index")
     */
    public function getTaxRuleActiveAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $list = TaxRule::find([
            'company_id=' . ModuleModel::$user_profile->getCompanyId() . " AND status =" . TaxRule::STATUS_ACTIVATED
        ]);
        $data = [];
        if (count($list)) {
            foreach ($list as $tax) {
                $data[$tax->getId()] = $tax->toArray();
                $data[$tax->getId()]['country'] = $tax->getCountry() ? $tax->getCountry()->getName() : "";
            }
        }
        $this->response->setJsonContent([
            'success' => true,
            'data' => ($data),
        ]);
        $this->response->send();
    }

    /**
     *
     */
    public function initAction()
    {
        $this->view->disable();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);
    }

    /**
     * @param int $id
     */
    public function detailAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclMultiple([
            ['controller' => $this->router->getControllerName(), 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);
        $return = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        $tax = TaxRule::findFirstById($id);

        if ($tax instanceof TaxRule && $tax->belongsToGms() && $tax->isDeleted() == false) {
            $tax_array = $tax->toArray();
            $tax_array['is_deleted'] = $tax->isDeleted();
            $tax_array['is_editable'] = $tax->isEditable();
            $return = [
                'success' => true,
                'data' => $tax_array
            ];
        };
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * Save tax rule
     */
    public function createAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $model = new TaxRule();
        $model->setData([
            'name' => Helpers::__getRequestValue('name'),
            'country_id' => Helpers::__getRequestValue('country_id'),
            'rate' => Helpers::__getRequestValue('rate') ? Helpers::__getRequestValue('rate') : 0,
            'status' => TaxRule::STATUS_ACTIVATED,
            'company_id' => ModuleModel::$user_profile->getCompanyId()
        ]);
        $result = $model->__quickCreate();

        if ($result['success'] == true) {
            $taxArray = $model->toArray();
            $taxArray['country'] = $model->getCountry() ? $model->getCountry()->getName() : "";
            $result = [
                'success' => true,
                'message' => 'SAVE_TAX_RULE_SUCCESS_TEXT',
                'data' => $taxArray
            ];
        } else {
            $result['message'] = 'SAVE_TAX_RULE_FAIL_TEXT';
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * Save tax rule
     */
    public function updateAction($id)
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        if (!$id > 0) $id = Helpers::__getRequestValue('id');
        $result = ['success' => false, 'message' => 'DATA_NOT_FOUND_TEXT'];
        if (is_numeric($id) && $id > 0) {
            $taxRuleModel = TaxRule::findFirstById($id);
            if ($taxRuleModel && $taxRuleModel->belongsToGms()) {
                $is_editable = $taxRuleModel->isEditable();
                if($is_editable) {
                    $taxRuleModel->setData([
                        'name' => Helpers::__getRequestValue('name'),
                        'country_id' => Helpers::__getRequestValue('country_id'),
                        'rate' => Helpers::__getRequestValue('rate'),
                        'company_id' => ModuleModel::$user_profile->getCompanyId()
                    ]);
                    $taxRuleModel->setCountryId(Helpers::__getRequestValue('country_id'));
                } else {
                    $taxRuleModel->setData([
                        'name' => Helpers::__getRequestValue('name'),
                        'company_id' => ModuleModel::$user_profile->getCompanyId()
                    ]);
                }
                $result = $taxRuleModel->__quickUpdate();
                if ($result['success'] == true) {
                    $taxArray = $taxRuleModel->toArray();
                    $taxArray['country'] = $taxRuleModel->getCountry() ? $taxRuleModel->getCountry()->getName() : null;
                    $result = [
                        'success' => true,
                        'message' => 'SAVE_TAX_RULE_SUCCESS_TEXT',
                        'data' => $taxArray
                    ];
                } else {
                    $result['message'] = 'SAVE_TAX_RULE_FAIL_TEXT';
                }
            }
        }
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $id
     */
    public function delAction($id)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);
        $id = (int)$id;
        $taxRule = TaxRule::findFirst($id);
        $return = [
            'success' => false,
            'message' => 'TAX_RULE_NOT_FOUND_TEXT'
        ];
        if ($taxRule instanceof TaxRule && $taxRule->belongsToGms()) {
            if($taxRule->isEditable()) {
                $return = $taxRule->__quickRemove();
            } else {
                $return = [
                    'success' => false,
                    'message' => 'CAN_NOT_DELETE_USED_TAX_RULE_TEXT'
                ];
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
