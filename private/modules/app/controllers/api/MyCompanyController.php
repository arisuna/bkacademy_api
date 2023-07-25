<?php

namespace SMXD\App\Controllers\API;

use Phalcon\Test\Mvc\Model\Behavior\Helper;
use SMXD\Application\Lib\AclHelper;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Lib\ModelHelper;
use SMXD\App\Models\Company;
use SMXD\App\Models\CompanyBusinessDetail;
use SMXD\App\Models\CompanyFinancialDetail;
use SMXD\App\Models\Country;
use SMXD\App\Models\Currency;
use SMXD\App\Models\FinancialAccount;
use SMXD\App\Models\InvoiceTemplate;
use SMXD\App\Models\ModuleModel;
use SMXD\App\Models\Plan;
use SMXD\App\Models\ObjectAvatar;
use SMXD\App\Models\Subscription;
use SMXD\App\Models\UserProfile;
use SMXD\App\Models\MediaAttachment;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class MyCompanyController extends BaseController
{
    /**
     * @Route("/gmscompany", paths={module="gms"}, methods={"GET"}, name="gms-gmscompany-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');

        // Load owner company
        $id = ModuleModel::$user->getCompanyId();
        $company = Company::findFirst($id ? $id : 0);

        if (!$company instanceof Company) {
            exit(json_encode([
                'success' => false,
                'message' => 'COMPANY_NOT_FOUND'
            ]));
        }
        $data = $company->toArray();
        $country = Country::findFirst($data['country_id'] ? $data['country_id'] : 0);
        $country_name = '';
        if ($country instanceof Country) {
            $country_name = $country->getName();
        }
        $data['country_name'] = $country_name;

        if ($company->getTimezone()) {
            $data['timezone_offset'] = intval($company->getTimezone()->getOffset());
            $data['timezone_utc'] = $company->getTimezone()->getUtc();
            $data['timezone_name'] = $company->getTimezone()->getName();
        } else {
            $data['timezone_offset'] = null;
            $data['timezone_utc'] = null;
            $data['timezone_name'] = null;
        }

        $data['business'] = $company->getCompanyBusinessDetail();
        $data['financial'] = $company->getCompanyFinancialDetail();
        $data['app'] = $company->getApp();

        $theme = $company->getTheme();
        if ($theme) {
            try {
                $data['theme'] = $theme->toArray();
                try {
                    $logo_img =  ObjectAvatar::__getImageByUuidAndType($theme->getUuid(), 'theme_logo');

                    if ($logo_img) {
                        $data['theme']['logo_url'] = $logo_img->getUrlThumb();
                    } else {
                        $data['theme']['logo_url'] = '';
                    }
                } catch (\Exception $exception) {
                    \Sentry\captureException($exception);
                    $data['theme']['logo_url'] = "";
                }
                try {
                    $icon_img = ObjectAvatar::__getImageByUuidAndType($theme->getUuid(), 'theme_icon');
                    if ($icon_img) {
                        $data['theme']['icon_url'] = $icon_img->getUrlThumb();
                    } else {
                        $data['theme']['icon_url'] = '';
                    }
                } catch (\Exception $exception) {
                    \Sentry\captureException($exception);
                    $data['theme']['logo_url'] = "";
                }
                try {
                    $logo_login_img =  ObjectAvatar::__getImageByUuidAndType($theme->getUuid(), 'theme_logo_login');
                    if ($logo_login_img) {
                        $data['theme']['logo_login_url'] = $logo_login_img->getUrlThumb();
                    } else {
                        $data['theme']['logo_login_url'] = '';
                    }
                } catch (\Exception $exception) {
                    \Sentry\captureException($exception);
                    $data['theme']['logo_login_url'] = "";
                }
            } catch (\Exception $exception) {
                // do nothing on theme
            }
        }

        $subscription = Subscription::findFirstByCompanyId($company->getId());

        if (!$subscription instanceof Subscription) {
            $return = ['success' => false, 'message' => 'SUBSCRIPTION_DOES_NOT_EXIST_TEXT', 'company' => $company];
            goto end_of_function;
        }
        $subscription_array = $subscription->toArray();
        $is_highest = true;
        $plan = $subscription->getPlan();
        $plans = Plan::__getList();
        if(count($plans) > 0){
            foreach ($plans as $plan_item){
                if($plan_item->getPrice() > $plan->getPrice()){
                    $is_highest = false;
                }
            }
        }
        $subscription_array['is_highest'] = $is_highest;
        $subscription_array["trial_end"] = 0;
        if ($subscription->getIsTrial() == 1) {
            $now = time();
            $subscription_array["trial_end"] = round(($subscription->getExpiredDate() - $now) / (60 * 60 * 24));
        }
        $subscription_array['has_payment_required'] = $subscription->hasPaymentRequired();

        $modules = $subscription->getModules();
        $limites = $subscription->getLimits();
        // Found owner information
        $return = [
            'success' => true,
            'data' => $data,
            'subscription' => $subscription_array,
            'modules' => $modules,
            'limites' => $limites,
        ];
        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    public function initAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $gms_members = UserProfile::find([
            'conditions' => 'company_id=' . ModuleModel::$user->getCompanyId()
                . ' AND status <> ' . UserProfile::STATUS_DRAFT
                . ' AND active = ' . UserProfile::STATUS_ACTIVE
        ]);

        $this->response->setJsonContent([
            'success' => true,
            'members' => count($gms_members) ? $gms_members->toArray() : []
        ]);
        return $this->response->send();
    }


    /**
     * @param int $id
     */
    public function detailAction($id = 0)
    {
        $this->view->disable();
        $this->checkAjax('GET');
        // 1. Load company details
        $company = Company::findFirst($id ? $id : 0);
        if (!$company instanceof Company) {
            exit(json_encode([
                'success' => false,
                'message' => 'COMPANY_NOT_FOUND_TEXT'
            ]));
        }
        // 2. Load business information
        $business = CompanyBusinessDetail::findFirst('company_id=' . $id);

        // 3. Load financial details
        $financial = CompanyFinancialDetail::findFirst('company_id=' . $id);

        $info = $company->toArray();
        $info['logo'] = $company->getLogo();
        // Found owner information
        echo json_encode([
            'success' => true,
            'info' => $info,
            'business' => $business ? $business->toArray() : [],
            'financial' => $financial ? $financial->toArray() : []
        ]);
    }

    /**
     * [saveAction description]
     * @return [type] [description]
     */
    public function saveAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclUpdate(AclHelper::CONTROLLER_MY_COMPANY);


        $return = [
            'success' => false,
            'message' => 'SAVE_COMPANY_FAIL_TEXT'
        ];

        $this->db->begin();
        // 1. save general details
        $company = ModuleModel::$company;
        $dataCompany = Helpers::__getRequestValues();


        $companyResult = $company->__update(
            Company::prepareDataFromArray((array)$dataCompany)
        );

        if ($companyResult instanceof Company && $companyResult->isCurrentGms()) {
            $return = [
                'success' => true,
                'message' => 'SAVE_COMPANY_SUCCESS_TEXT'
            ];

            $dataCompanyBusiness = CompanyBusinessDetail::prepareDataFromArray((array)Helpers::__getRequestValueAsArray('business'));
            $businessInfoResult = $companyResult->__saveBusinessInfo($dataCompanyBusiness);

            if ($businessInfoResult['success'] == false) {
                $this->db->rollback();
                $return = $businessInfoResult;
                goto end_of_function;
            }

            $dataCompanyFinancial = (array)Helpers::__getRequestValue('financial');

            $financialResultInfo = $companyResult->__saveFinancialInfo($dataCompanyFinancial);
            if ($financialResultInfo['success'] == false) {
                $this->db->rollback();
                $return = $financialResultInfo;
                goto end_of_function;
            }

            $this->db->commit();
            goto end_of_function;
        }


        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }


    /**
     *
     */
    public function saveBasicInfoAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclUpdate(AclHelper::CONTROLLER_MY_COMPANY);


        $return = [
            'success' => false,
            'message' => 'SAVE_COMPANY_FAIL_TEXT'
        ];

        $company = ModuleModel::$company;
        $dataCompany = Helpers::__getRequestValues();
        $dataCompanyArr = Helpers::__getRequestValuesArray();

        $companyResult = $company->__update(
            Company::prepareDataFromArray((array)$dataCompany)
        );

        if ($companyResult instanceof Company && $companyResult->isCurrentGms()) {
            $return = [
                'success' => true,
                'message' => 'SAVE_COMPANY_SUCCESS_TEXT'
            ];
        }

        if(isset($dataCompanyArr['isFirstSetting']) && isset($dataCompanyArr['currency_code']) && $dataCompanyArr['isFirstSetting'] == true){

            $financical_account = FinancialAccount::findFirst([
                "conditions" => "currency = :currency: and company_id = :company_id: and is_deleted = 0",
                "bind" => [
                    "currency" => $dataCompanyArr['currency_code'],
                    "company_id" => ModuleModel::$company->getId()
                ]
            ]);
            if (!$financical_account){
                $currency = Currency::findFirstByCode($dataCompanyArr['currency_code']);
                $financialAccount = new FinancialAccount();
                $financialAccount->setUuid(Helpers::__uuid());
                $financialAccount->setCompanyId($company->getId());
                $financialAccount->setCurrency($dataCompanyArr['currency_code']);
                $financialAccount->setName($currency->getName());
                $financialAccount->setAmount(0);

                if(count($company->getFinancialAccounts()) == 0){
                    $financialAccount->setIsDefault(ModelHelper::YES);
                }
                $resultCreateFinancialAccount = $financialAccount->__quickCreate();
            }
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/gmscompany", paths={module="gms"}, methods={"GET"}, name="gms-gmscompany-index")
     */
    public function getBasicInfoAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');

        // Load owner company
        $company = ModuleModel::$company;
        $data = $company->toArray();
        $data['country_name'] = $company->getCountry() ? $company->getCountry()->getName() : '';
        $return = [
            'success' => true,
            'data' => $data,
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/gmscompany", paths={module="gms"}, methods={"GET"}, name="gms-gmscompany-index")
     */
    public function getBusinessInfoAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');

        // Load owner company
        $company = ModuleModel::$company;
        $business = $company->getCompanyBusinessDetail();
        if ($business) {
            $data = $business->toArray();
            $skills = $business->parseSkills();
            $data['skills'] = $business->parseSkills();
            $data['skills_array'] = is_array($skills) && count($skills) > 0 ? implode(",", $skills) : null;
        } else {
            $data = [];
        }

        $return = [
            'success' => true,
            'data' => $data,
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/gmscompany", paths={module="gms"}, methods={"GET"}, name="gms-gmscompany-index")
     */
    public function getFinancialInfoAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');

        // Load owner company
        $company = ModuleModel::$company;
        $financial = $company->getCompanyFinancialDetail();
        if ($financial) {
            $data = $financial->toArray();
        } else {
            $data = [];
        }

        $return = [
            'success' => true,
            'data' => $data,
        ];
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function saveBusinessDataAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclUpdate(AclHelper::CONTROLLER_MY_COMPANY);

        // Load owner company
        $company = ModuleModel::$company;
        $dataCompanyBusiness = CompanyBusinessDetail::prepareDataFromArray((array)Helpers::__getRequestValuesArray());
        $businessInfoResult = $company->__saveBusinessInfo($dataCompanyBusiness);

        if ($businessInfoResult['success'] == false) {
            $return = $businessInfoResult;
            goto end_of_function;
        } else {
            $return = [
                'success' => true,
                'message' => 'SAVE_COMPANY_SUCCESS_TEXT'
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();

    }


    /**
     *
     */
    public function saveFinancialDataAction()
    {
        $this->view->disable();
        $this->checkAjaxPut();
        $this->checkAclUpdate(AclHelper::CONTROLLER_MY_COMPANY);

        // Load owner company
        $company = ModuleModel::$company;

        $dataCompanyFinancial = (array)Helpers::__getRequestValuesArray();
        $this->db->begin();

        $financialResultInfo = $company->__saveFinancialInfo($dataCompanyFinancial);
        if ($financialResultInfo['success'] == false) {
            $return = $financialResultInfo;
            $this->db->rollback();
            goto end_of_function;
        } else {
            $this->db->commit();
            $return = [
                'success' => true,
                'message' => 'SAVE_COMPANY_SUCCESS_TEXT'
            ];
        }

        end_of_function:
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getApplicationAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        // Load owner company
        $company = ModuleModel::$company;

        $return = [
            'success' => true,
            'data' => $company->getApp()
        ];

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @param $id
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function updateApplicationAction()
    {
        $this->view->disable();
        $this->checkAjax('PUT');
        $this->checkAclEdit();

        $return = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT',
        ];
        $application = ModuleModel::$company->getApp();
        $application->getName(Helpers::__getRequestValue('name'));
        $return = $application->__quickUpdate();
        if ($return['success'] == true) {
            $return['message'] = 'APPLICATION_UPDATE_SUCCESS_TEXT';
        } else {
            $return['message'] = 'APPLICATION_UPDATE_FAIL_TEXT';
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     * @Route("/product", paths={module="gms"}, methods={"GET"}, name="get-list-product")
     */
    public function getListInvoiceTemplateAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAclIndex();

        $templates = InvoiceTemplate::find([
            "conditions" => "is_deleted = 0 and company_id = :company_id:",
            "bind" => [
                "company_id" => ModuleModel::$company->getId()
            ]
        ]);

        $this->response->setJsonContent([
            'success' => true,
            'data' => $templates,
        ]);
        $this->response->send();

    }


    public function saveInvoiceTemplateAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAclUpdate(AclHelper::CONTROLLER_MY_COMPANY);

        $uuid = Helpers::__getRequestValue("uuid");

        $result = [
            'success' => false,
            'message' => 'INVOICE_TEMPLATE_NOT_FOUND_TEXT'
        ];

        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $template = InvoiceTemplate::findFirstByUuid($uuid);

            if ($template instanceof InvoiceTemplate && $template->belongsToGms()) {

                $template->setData(Helpers::__getRequestValuesArray());
                $templateIfExist = InvoiceTemplate::findFirst([
                    "conditions" => "name = :name: and company_id = :company_id: and id != :id: and is_deleted = 0 ",
                    "bind" => [
                        "name" => Helpers::__getRequestValue("name"),
                        "company_id" => ModuleModel::$company->getId(),
                        "id" => $template->getId()
                    ]
                ]);
                if($templateIfExist instanceof InvoiceTemplate){
                    $result['message'] = 'INVOICE_TEMPLATE_NAME_MUST_BE_UNIQUE_TEXT';
                    $result['success'] = false;
                    goto end_of_function;
                }

                $result = $template->__quickUpdate();

                if ($result['success'] == false) {
                    $result['message'] = 'SAVE_INVOICE_TEMPLATE_FAIL_TEXT';
                }
                else $result['message'] = 'SAVE_INVOICE_TEMPLATE_SUCCESS_TEXT';

            }
        } else {
            $template = new InvoiceTemplate();
            $data['company_id'] = ModuleModel::$company->getId();

            $template->setData($data);
            $templateIfExist = InvoiceTemplate::findFirst([
                "conditions" => "name = :name: and company_id = :company_id: and is_deleted = 0 ",
                "bind" => [
                    "name" => Helpers::__getRequestValue("name"),
                    "company_id" => ModuleModel::$company->getId()
                ]
            ]);
            if($templateIfExist instanceof InvoiceTemplate){
                $template->setName($template->getName().$template->getUuid());
            }
            $result = $template->__quickCreate();
            if ($result['success'] == false) {
                $result['message'] = 'SAVE_INVOICE_TEMPLATE_FAIL_TEXT';
            }
            else {
                $result['message'] = 'SAVE_INVOICE_TEMPLATE_SUCCESS_TEXT';
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $uuid
     */
    public function removeInvoiceTemplateAction($uuid)
    {
        $this->view->disable();
        $this->checkAjaxDelete();
        $this->checkAclUpdate(AclHelper::CONTROLLER_MY_COMPANY);

        $return = [
            'success' => false,
            'message' => 'INVOICE_TEMPLATE_NOT_FOUND_TEXT'
        ];
        if ($uuid != '' && Helpers::__isValidUuid($uuid)) {

            $template = InvoiceTemplate::findFirstByUuid($uuid);

            if ($template instanceof InvoiceTemplate && $template->belongsToGms()) {

                $return = $template->__quickRemove();
            }
        }
        $this->response->setJsonContent($return);
        return $this->response->send();
    }
}
