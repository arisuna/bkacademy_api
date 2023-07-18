<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AclHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\FinancialAccount;
use Reloday\Gms\Models\ModuleModel;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class FinancialAccountController extends BaseController
{

    /**
     * @Route("/bill", paths={module="gms"}, methods={"GET"}
     */
    public function getListFinancialAccountAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclMultiple([
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $financial_accounts = FinancialAccount::find([
            "conditions" => "is_deleted = 0 and company_id = :company_id:",
            "bind" => [
                "company_id" => ModuleModel::$company->getId()
            ]
        ]);

        $this->response->setJsonContent([
            'success' => true,
            'data' => $financial_accounts,
        ]);
        $this->response->send();
    }

    /**
     * @return mixed
     */
    public function createFinancialAccountAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $data = Helpers::__getRequestValuesArray();
        $financical_account = FinancialAccount::findFirst([
            "conditions" => "currency = :currency: and company_id = :company_id: and is_deleted = 0",
            "bind" => [
                "currency" => Helpers::__getRequestValue("currency"),
                "company_id" => ModuleModel::$company->getId()
            ]
        ]);
        if ($financical_account instanceof FinancialAccount) {
            $result['message'] = 'CURRENCY_MUST_BE_UNIQUE_TEXT';
            $result['success'] = false;
            goto end_of_function;
        }
        $financical_account = new FinancialAccount();

        $financical_account->setData($data);
        $financical_account->setCompanyId(ModuleModel::$company->getId());
        $result = $financical_account->__quickCreate();
        if ($result['success'] == false) {
            $result['message'] = 'SAVE_FINANCIAL_ACCOUNT_FAIL_TEXT';
        } else {
            $result['message'] = 'SAVE_FINANCIAL_ACCOUNT_SUCCESS_TEXT';
        }

        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function detailFinancialAccountAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxGet();

        $this->checkAclMultiple([
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $result = [
            'success' => false,
            'message' => 'FINANCIAL_ACCOUNT_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {
            $financical_account = FinancialAccount::findFirstByUuid($uuid);
        } elseif ($uuid != '' && Helpers::__isValidId($uuid)) {
            $financical_account = FinancialAccount::findFirstById($uuid);
        }


        if (isset($financical_account) && $financical_account instanceof FinancialAccount && $financical_account->belongsToGms()) {
            $result = [
                'success' => true,
                'data' => $financical_account
            ];
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Save service action
     */
    public function editFinancialAccountAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $uuid = Helpers::__getRequestValue("uuid");

        $result = [
            'success' => false,
            'message' => 'FINANCIAL_ACCOUNT_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $financical_account = FinancialAccount::findFirstByUuid($uuid);

            if ($financical_account instanceof FinancialAccount && $financical_account->belongsToGms()) {

                $old_currency = $financical_account->getCurrency();
                $new_currency = Helpers::__getRequestValue("currency");
                $old_amount = $financical_account->getAmount();
                $new_amount = Helpers::__getRequestValue("amount");


                $checkIfExist = FinancialAccount::findFirst([
                    "conditions" => "currency = :currency: and company_id = :company_id: and id != :id: and is_deleted = 0",
                    "bind" => [
                        "currency" => Helpers::__getRequestValue("currency"),
                        "company_id" => ModuleModel::$company->getId(),
                        "id" => $financical_account->getId()
                    ]
                ]);
                if ($checkIfExist instanceof FinancialAccount) {
                    $result['message'] = 'CURRENCY_MUST_BE_UNIQUE_TEXT';
                    $result['success'] = false;
                    goto end_of_function;
                }

                if (($old_currency != $new_currency || $old_amount != $new_amount) && !$financical_account->isChangeable()) {
                    $result = [
                        "success" => false,
                        "message" => "FINANCIAL_ACCOUNT_CAN_NOT_REMOVE_TEXT"
                    ];
                    goto end_of_function;
                }

                $financical_account->setData(Helpers::__getRequestValuesArray());

                $result = $financical_account->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'SAVE_FINANCIAL_ACCOUNT_FAIL_TEXT';
                    goto end_of_function;
                }
                $result['message'] = 'SAVE_FINANCIAL_ACCOUNT_SUCCESS_TEXT';

            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeFinancialAccountAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $return = [
            'success' => false,
            'message' => 'FINANCIAL_ACCOUNT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $financical_account = FinancialAccount::findFirstByUuid($uuid);

            if ($financical_account instanceof FinancialAccount && $financical_account->belongsToGms()) {

                if (!$financical_account->isChangeable()) {
                    $return = [
                        "success" => false,
                        "message" => "FINANCIAL_ACCOUNT_CAN_NOT_REMOVE_TEXT"
                    ];
                    goto end_of_function;
                }

                $return = $financical_account->__quickRemove();
            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function setDefaultFinancialAccountAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclIndex(AclHelper::CONTROLLER_FINANCE_SETTING);

        $return = [
            'success' => false,
            'message' => 'FINANCIAL_ACCOUNT_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $financical_account = FinancialAccount::findFirstByUuid($uuid);

            if ($financical_account instanceof FinancialAccount && $financical_account->belongsToGms()) {
                $this->db->begin();
                $default_financial_account = FinancialAccount::findFirst([
                    "conditions" => "company_id = :company_id: and is_deleted = 0 and is_default =1",
                    "bind" => [
                        "company_id" => ModuleModel::$company->getId()
                    ]
                ]);
                if ($default_financial_account instanceof FinancialAccount and $default_financial_account->getUuid() != $uuid) {
                    $default_financial_account->setIsDefault(0);
                    $return = $default_financial_account->__quickUpdate();

                    if ($return['success'] == false) {
                        $return['message'] = 'SAVE_FINANCIAL_ACCOUNT_FAIL_TEXT';
                        $this->db->rollback();
                        goto end_of_function;
                    }

                }
                $financical_account->setIsDefault(1);
                $return = $financical_account->__quickUpdate();

                if ($return['success'] == false) {
                    $return['message'] = 'SAVE_FINANCIAL_ACCOUNT_FAIL_TEXT';
                    $this->db->rollback();
                    goto end_of_function;
                }

                $this->db->commit();
                $return = [
                    "success" => true,
                    "message" => 'SAVE_FINANCIAL_ACCOUNT_SUCCESS_TEXT'
                ];

            }
        }
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function getDetailsByCurrencyAction()
    {
        $this->view->disable();
        $this->checkAjaxPutGet();

        $this->checkAclMultiple([
            ['controller' => AclHelper::CONTROLLER_FINANCE, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_INVOICING, 'action' => AclHelper::ACTION_INDEX],
            ['controller' => AclHelper::CONTROLLER_FINANCE_SETTING, 'action' => AclHelper::ACTION_INDEX],
        ]);

        $result = [
            'success' => false,
            'message' => 'FINANCIAL_ACCOUNT_NOT_FOUND_TEXT'
        ];

        $currency = Helpers::__getRequestValue('currency');

        if (Helpers::__isCurrency($currency)) {
            $financical_account = FinancialAccount::__findFirstByCurrency($currency);
        }

        if (isset($financical_account) && $financical_account && $financical_account->belongsToGms()) {
            $result = [
                'success' => true,
                'data' => $financical_account
            ];
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }
}
