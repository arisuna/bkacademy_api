<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\Helpers;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\CompanyBusinessDetail;
use Reloday\Gms\Models\CompanyFinancialDetail;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\MediaAttachment;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class GmsCompanyController extends BaseController
{
    /**
     * @Route("/gmscompany", paths={module="gms"}, methods={"GET"}, name="gms-gmscompany-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');

        // Load owner company
        $id = ModuleModel::$user_profile->getCompanyId();
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
        }else{
            $data['timezone_offset'] = null;
            $data['timezone_utc'] = null;
            $data['timezone_name'] = null;
        }

        $data['business'] = $company->getCompanyBusinessDetail();
        $data['financial'] = $company->getCompanyFinancialDetail();
        // Found owner information
        $this->response->setJsonContent([
            'success' => true,
            'data' => $data,
        ]);
        return $this->response->send();
    }

    public function initAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $gms_members = UserProfile::find([
            'conditions' => 'company_id=' . ModuleModel::$user_profile->getCompanyId()
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
        $this->checkAjax('PUT');
        $this->checkAcl('edit', 'gms_company');


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
        } else {
            $return = $companyResult;
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
        $this->checkAjax('PUT');
        $this->checkAcl('edit', 'gms_company');


        $return = [
            'success' => false,
            'message' => 'SAVE_COMPANY_FAIL_TEXT'
        ];

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
        }

        $this->response->setJsonContent($return);
        return $this->response->send();
    }

    /**
     *
     */
    public function getConfigurationListAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');

        $result = [
            'success' => false,
            'message' => 'DATA_NOT_FOUND_TEXT'
        ];
        $app = ModuleModel::$app;
        if ($app) {
            $appSettings = $app->getAppSetting();
            $appSettingsArray = [];
            if ($appSettings->count() > 0) {
                foreach ($appSettings as $appSettingItem) {
                    $appSettingsArray[$appSettingItem->getName()] = $appSettingItem;
                }
            }
            $result = [
                'success' => true,
                'data' => $appSettingsArray
            ];
        }

        $this->response->setJsonContent($result);
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
        $data = $business->toArray();
        $data['skills_array'] = implode(",", $business->getSkills());
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
        $data = $financial->toArray();

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
        $this->checkAjax('PUT');
        $this->checkAcl('edit', $this->router->getControllerName());


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
        $this->checkAjax('PUT');
        $this->checkAcl('edit', $this->router->getControllerName());

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
}
