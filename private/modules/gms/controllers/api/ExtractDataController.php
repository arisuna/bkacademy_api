<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Mvc\Model\Query\Builder;
use Phalcon\Security\Random;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\AttributeHelper;
use Reloday\Application\Lib\CacheHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\LanguageCode;
use Reloday\Application\Lib\RelodayS3Helper;
use Reloday\Application\Models\ServiceFieldExt;
use Reloday\Gms\Models\Assignment;
use Reloday\Gms\Models\Attributes;
use Reloday\Gms\Models\Company;
use Reloday\Gms\Models\Contract;
use Reloday\Gms\Models\Country;
use Reloday\Gms\Models\DataUserMember;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\EmployeeInContract;
use Reloday\Gms\Models\ExtractDataSetting;
use Reloday\Gms\Models\FilterConfig;
use Reloday\Gms\Models\FilterConfigItem;
use Reloday\Gms\Models\InvoiceQuote;
use Reloday\Gms\Models\InvoiceQuoteItem;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Nationality;
use Reloday\Gms\Models\Office;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;
use Reloday\Gms\Models\ReportLog;
use Reloday\Gms\Models\ServiceCompany;
use Reloday\Gms\Models\ServiceField;
use Reloday\Gms\Models\TaxRule;
use Reloday\Gms\Models\UserProfile;
use Reloday\Gms\Models\ServiceFieldType;
use Reloday\Gms\Models\EtlHistory;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ExtractDataController extends BaseController
{
    /**
     * @Route("/extract-data", paths={module="gms"}, methods={"GET"}, name="extract-data-index")
     */
    public function indexAction()
    {
        $etl_history = EtlHistory::findFirst([
            "conditions" => "status = :done_status:",
            "bind" => [
                "done_status" => EtlHistory::STATUS_DONE
            ],
            'order' => 'id DESC'
        ]);
        if($etl_history){
            $data = $etl_history->toArray();
            $data['timestamp'] = date('d/m/Y H:i', strtotime($etl_history->getTimestamp()));
            $return = [
                "success" => true,
                "data" => $data
            ];
        } else {
            $return = [
                "success" => false,
            ];
        }
        end_of_function:
        $this->response->setJsonContent($return);
        $this->response->send();

    }

    /**
     *
     */
    public function employeeAnalyticsAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', 'report');


        $result = [];

        switch ($this->request->get('opt')) {
            case 'country':
                $result = [
                    'success' => true,
                    'data' => [] // country_code => total_number_of_employee
                ];
                $contract_ids = [];
                $countries = [];
                // Load all employee has contract with current GMS logged
                // 1. Get all contract with current GMS
                $gms_company_id = ModuleModel::$user_profile->getCompanyId();
                $contracts = Contract::find([
                    'from_company_id=' . $gms_company_id . ' OR to_company_id=' . $gms_company_id
                ]);
                if (count($contracts)) {
                    foreach ($contracts as $contract) {
                        $contract_ids[$contract->getId()] = $contract->getId();
                    }
                    $employee_contract_list = EmployeeInContract::find([
                        'contract_id IN(' . implode(',', $contract_ids) . ')'
                    ]);
                    if (count($employee_contract_list)) {
                        $employee_ids = [];
                        foreach ($employee_contract_list as $employee) {
                            $employee_ids[] = $employee->getId();
                        }
                        $employees = Employee::find(['id IN (' . implode(',', $employee_ids) . ')']);
                        if (count($employees)) {
                            foreach ($employees as $employee) {

                            }
                        }
                    }
                }
                break;
            default:
                break;
        }

        echo json_encode($result);
    }

    /**
     * Relocation
     */
    public function relocationAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $result = [
            'success' => false,
            'list' => []
        ];
        $isChange = false;
        /***** new filter ******/
        $params = Helpers::__getRequestValuesArray();

        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');
        $etl_history = EtlHistory::findFirst([
            'order' => 'id DESC'
        ]);
  
        if($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_IN_PROGRESS){
            $old_report_log = ReportLog::__checkLastResult(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_RELOCATION, $params);
            if($old_report_log instanceof ReportLog){
                $result = [
                    "success" => true,
                    'executionId' => $old_report_log->getExecutionId(),
                    'url' => $old_report_log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            } else {
                $result = [
                    "success" => false,
                    'message' => "REFRESHING_REPORT_DATA_TEXT",
                ];
                goto end_of_function;
            }
        } else if ($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_DONE){
            $log = ReportLog::__checkResultAfterTimestamp(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_RELOCATION, $params, $etl_history->getTimestamp());
            if ($log) {
                if($params['is_tmp'] == true){
                    if($log->getParams()){
                        $logParams = json_decode($log->getParams(), true);
                        $logParamItems = [];
                        if(is_array($logParams) &&  isset($logParams['items']) && $logParams['items']){
                            $logParamItems = json_decode(json_encode($logParams['items']), true);
                        }

                        if(is_object($logParams) && isset($logParams->items) && $logParams->items){
                            $logParamItems = json_decode(json_encode($logParams->items), true);
                        }

                        $isChange = FilterConfig::__checkFilterCached($params['filter_config_id'], $logParamItems);
                        if($isChange){
                            goto go_to_filter;
                        }

                    }
                }

                $result = [
                    "success" => true,
                    'executionId' => $log->getExecutionId(),
                    'url' => $log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            }
        }
        go_to_filter:
        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }

        $result = Relocation::__executeReport($params, $ordersConfig);
        $result['isChangeFilter'] = $isChange;

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Relocation
     */
    public function relocationResultAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $params = Helpers::__getRequestValuesArray();
        $result = AthenaHelper::getExecutionInfo($executionId);
        $next_token = Helpers::__getRequestValue('nextToken');
        if ($result['success'] && $result['status'] == 'SUCCEEDED') {
            $queryResult = AthenaHelper::getQueryResult($executionId, Helpers::__getRequestValue('nextToken'));
            if (!$queryResult['success']) {
                $result['result'] = $queryResult;
                goto end_of_function;
            }
            $result['nextToken'] = $queryResult['nextToken'];
            $data = [];
            $i = 0;
            foreach ($queryResult['data'] as $item) {
                if ($i > 0 || ($i == 0 && $next_token != null && $next_token != '')) {
                    $data[] = [
                        'identify' => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        'assignee_name' => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        'assignee_email' => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        'assignee_phone' => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : "",
                        'assignment_reference' => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        'assignee_id' => isset($item['Data'][5]['VarCharValue']) ? $item['Data'][5]['VarCharValue'] : "",
                        'start_date' => isset($item['Data'][6]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), strtotime($item['Data'][6]['VarCharValue'])) : "",
                        'end_date' => isset($item['Data'][7]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), strtotime($item['Data'][7]['VarCharValue'])) : "",
                        'hr_company_name' => isset($item['Data'][8]['VarCharValue']) ? $item['Data'][8]['VarCharValue'] : "",
                        'booker_company_name' => isset($item['Data'][9]['VarCharValue']) ? $item['Data'][9]['VarCharValue'] : "",
                        'dsp_reporter_name' => isset($item['Data'][10]['VarCharValue']) ? $item['Data'][10]['VarCharValue'] : "",
                        'dsp_owner_name' => isset($item['Data'][11]['VarCharValue']) ? $item['Data'][11]['VarCharValue'] : "",
                        'dsp_viewers_name' => isset($item['Data'][12]['VarCharValue']) ? $item['Data'][12]['VarCharValue'] : "",
                        'status' => isset($item['Data'][13]['VarCharValue']) ? $item['Data'][13]['VarCharValue'] : "",
                        'services_short_name' => isset($item['Data'][14]['VarCharValue']) ? $item['Data'][14]['VarCharValue'] : "",
                        'services_name' => isset($item['Data'][15]['VarCharValue']) ? $item['Data'][15]['VarCharValue'] : "",
//                        'relocation_created_date' => isset($item['Data'][16]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), strtotime($item['Data'][16]['VarCharValue'] )) : "",
                        'relocation_created_date' => isset($item['Data'][16]['VarCharValue']) ? $item['Data'][16]['VarCharValue'] : "",
                        'cancel_services_name' => isset($item['Data'][17]['VarCharValue']) ? $item['Data'][17]['VarCharValue'] : "",
                        'home_country_name' => isset($item['Data'][18]['VarCharValue']) ? $item['Data'][18]['VarCharValue'] : "",
                        'destination_country_name' => isset($item['Data'][19]['VarCharValue']) ? $item['Data'][19]['VarCharValue'] : "",
                        'archived' => isset($item['Data'][20]['VarCharValue']) ? $item['Data'][20]['VarCharValue'] : "",
                        'ee_reference' => isset($item['Data'][21]['VarCharValue']) ? $item['Data'][21]['VarCharValue'] : "",
                        'origin_office' => isset($item['Data'][22]['VarCharValue']) ? $item['Data'][22]['VarCharValue'] : "",
                        'destination_office' => isset($item['Data'][23]['VarCharValue']) ? $item['Data'][23]['VarCharValue'] : "",
                    ];
                }
                $i++;
            }
            $result['data'] = $data;
            $result['completion_date_time'] = date(ModuleModel::$company->getPhpDateFormat(true), strtotime($result["queryExecution"]["Status"]["CompletionDateTime"]));
            $result['i'] = $i;
            $result['url'] = RelodayS3Helper::__getPresignedUrl("report/" . ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog ) {
                if(isset($params['filter_config_id'])){
                    $aOptions = [
                        'filter_config_id' => $params['filter_config_id'],
                    ];
                    $aItem = FilterConfigItem::listJoinByCriteria($aOptions, false);
                    if($aItem['success']){
                        $params['items'] = $aItem['data'];
                    }
                }
                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_DSP_EXTRACT_RELOCATION);
                $report_log->setCompanyId(ModuleModel::$company->getId());
                $report_log->setExecutionId($executionId);
                $report_log->setParams(json_encode($params));
                $report_log->setUrl($result['url']);
                $etl_history =  EtlHistory::findFirst([
                    "conditions" => "status = :done_status:",
                    "bind" => [
                        "done_status" => EtlHistory::STATUS_DONE
                    ],
                    'order' => 'id DESC'
                ]);
                if($etl_history){
                    $report_log->setLastUpdate(date('Y-m-d H:i:s', strtotime($etl_history->getTimestamp())));
                }
                $create_report_log = $report_log->__quickCreate();
                $result["create_log"] = $create_report_log;
            } else {
                $result['completion_date_time'] = $report_log->getLastUpdate();
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     *
     */
    public function relocationInitializationAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', 'report');
        if (empty($this->request->get('opt'))) {
            $result = [
                'success' => false,
                'status' => [
                    0 => 'ALL_TEXT',
                    Relocation::STATUS_INITIAL => 'NOT_STARTED_TEXT',
                    //Relocation::STATUS_STARTING_SOON => 'STATUS_STARTING_SOON_TEXT',
                    Relocation::STATUS_ONGOING => 'ONGOING_TEXT',
                    //Relocation::STATUS_ENDING_SOON => 'STATUS_ENDING_SOON_TEXT',
                    Relocation::STATUS_TERMINATED => 'TERMINATED_STATUS_TEXT'
                ],
                'assignees' => [],
                'data_members' => [],
                'accounts' => []
            ];
            $profile = ModuleModel::$user_profile;
            if ($profile instanceof UserProfile) {

                $result['success'] = true;

                // Find all HR company have contract with current company
                $contracts = Contract::find(['to_company_id=' . $profile->getCompanyId()]);
                $contract_ids = [];
                if (count($contracts)) {
                    $contract_ids = [];
                    $hr_company_ids = [];
                    foreach ($contracts as $contract) {
                        $contract_ids[] = $contract->getId();
                        $hr_company_ids[] = $contract->getFromCompanyId();
                    }

                    // Get list HR
                    $companies = Company::find("id IN (" . implode(',', $hr_company_ids) . ")");
                    if (count($companies)) {
                        foreach ($companies as $company) {
                            $result['accounts'][$company->getId()] = $company->getName();
                        }
                    }

                    $employee_contracts = EmployeeInContract::find(['contract_id IN (' . implode(',', $contract_ids) . ')']);
                    if (count($employee_contracts)) {
                        $_ids = [];
                        foreach ($employee_contracts as $employee_contract) {
                            $_ids[] = $employee_contract->getEmployeeId();
                        }
                        $employees = Employee::find(['id IN (' . implode(',', $_ids) . ')']);
                        if (count($employees)) {
                            foreach ($employees as $employee) {
                                $result['assignees'][$employee->getId()] = $employee->toArray();
                            }
                        }
                    }
                }

                // Data members
                $data_members = DataUserMember::find(["object_name='relocation'"]);
                if (count($data_members)) {
                    $users = [];
                    foreach ($data_members as $d) {
                        if (!isset($result['data_members'][$d->getMemberTypeId()]))
                            $result['data_members'][$d->getMemberTypeId()] = [];
                        if (!isset($users[$d->getUserProfileId()])) {
                            $user = UserProfile::findFirst($d->getUserProfileId());
                            if ($user instanceof UserProfile) {
                                $users[$user->getId()] = $user->getFirstname() . ' ' . $user->getLastname();
                            } else {
                                $users[$d->getUserProfileId()] = '';
                            }
                        }
                        if (!isset($result['data_members'][$d->getMemberTypeId()][$d->getObjectUuid()])) {
                            $result['data_members'][$d->getMemberTypeId()][$d->getObjectUuid()] = [];
                        }
                        $result['data_members'][$d->getMemberTypeId()][$d->getObjectUuid()][] = $users[$d->getUserProfileId()];
                    }
                }

                // Assignment
                if (count($contract_ids)) {
                    $assignments = Assignment::find(['contract_id IN (' . implode(',', $contract_ids) . ')']);
                    if (count($assignments)) {
                        foreach ($assignments as $assignment) {
                            $result['assignments'][$assignment->getId()] = $assignment->getReference();
                        }
                    }
                }

            } else {
                $result['message'] = 'ERROR_SESSION_EXPIRED_TEXT';
            }
        } else {
            $result = [
                'success' => true,
                'services' => []
            ];
            // Load service of relocation
            $services = RelocationServiceCompany::find(['status=' . RelocationServiceCompany::STATUS_ACTIVE]);
            if (count($services)) {
                foreach ($services as $service) {
                    if (!isset($result['services'][$service->getRelocationId()])) {
                        $result['services'][$service->getRelocationId()] = [];
                    }
                    $result['services'][$service->getRelocationId()][] = $service->getName();
                }
            }
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Assignment
     */
    public function assignmentAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('index', 'report');
        $result = [
            'success' => true,
            'list' => []
        ];

        $params = Helpers::__getRequestValuesArray();

        /***** new filter ******/
        $isChange = false;
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');

        $etl_history = EtlHistory::findFirst([
            'order' => 'id DESC'
        ]);
        if($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_IN_PROGRESS){
            $old_report_log = ReportLog::__checkLastResult(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_ASSIGNMENT, $params);
            if($old_report_log instanceof ReportLog){
                $result = [
                    "success" => true,
                    'executionId' => $old_report_log->getExecutionId(),
                    'url' => $old_report_log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            } else {
                $result = [
                    "success" => false,
                    'message' => "REFRESHING_REPORT_DATA_TEXT",
                ];
                goto end_of_function;
            }
        } else if ($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_DONE){
            $log = ReportLog::__checkResultAfterTimestamp(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_ASSIGNMENT, $params, $etl_history->getTimestamp());
            if ($log) {
                if($params['is_tmp'] == true){
                    if($log->getParams()){
                        $logParams = json_decode($log->getParams(), true);
                        $logParamItems = [];
                        if(is_array($logParams) &&  isset($logParams['items']) && $logParams['items']){
                            $logParamItems = json_decode(json_encode($logParams['items']), true);
                        }

                        if(is_object($logParams) && isset($logParams->items) && $logParams->items){
                            $logParamItems = json_decode(json_encode($logParams->items), true);
                        }
                        if(isset($params['filter_config_id'])){
                            $isChange = FilterConfig::__checkFilterCached($params['filter_config_id'], $logParamItems);
                            if($isChange){
                                goto go_to_filter;
                            }
                        }else{
                            goto go_to_filter;
                        }
                    }
                }

                $result = [
                    "success" => true,
                    'executionId' => $log->getExecutionId(),
                    'url' => $log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            }
        }

        /***** new filter ******/
        go_to_filter:
        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }

        $result = Assignment::__executeReport($params, $ordersConfig);

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Relocation
     */
    public function assignmentResultAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $params = Helpers::__getRequestValuesArray();
        $result = AthenaHelper::getExecutionInfo($executionId);

        $next_token = Helpers::__getRequestValue('nextToken');
        if ($result['success'] && $result['status'] == 'SUCCEEDED') {
            $queryResult = AthenaHelper::getQueryResult($executionId, $next_token);
            if (!$queryResult['success']) {
                $result['result'] = $queryResult;
                goto end_of_function;
            }
            $result['nextToken'] = $queryResult['nextToken'];
            $data = [];
            $i = 0;
            $companyPhpDateFormat = ModuleModel::$company->getPhpDateFormat();
            foreach ($queryResult['data'] as $item) {
                if ($i > 0 || ($i == 0 && $next_token != null && $next_token != '')) {
                    $data[] = [
                        'asgt_id' => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        'hr_company_name' => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : "",
                        'booker_company_name' => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        'asgt_reference' => isset($item['Data'][5]['VarCharValue']) ? $item['Data'][5]['VarCharValue'] : "",
                        'marital_status' => isset($item['Data'][6]['VarCharValue']) ? AttributeHelper::__translate($item['Data'][6]['VarCharValue'], ModuleModel::$language) : "",
                        'partner_name' => isset($item['Data'][7]['VarCharValue']) ? $item['Data'][7]['VarCharValue'] : "",
                        'partner_citizenship' => isset($item['Data'][8]['VarCharValue']) ? $item['Data'][8]['VarCharValue'] : "",
                        'estimated_start_date' => isset($item['Data'][9]['VarCharValue']) ? date($companyPhpDateFormat, strtotime($item['Data'][9]['VarCharValue'])) : "",
                        'estimated_end_date' => isset($item['Data'][10]['VarCharValue']) ? date($companyPhpDateFormat, strtotime($item['Data'][10]['VarCharValue'])) : "",
                        'effective_start_date' => isset($item['Data'][11]['VarCharValue']) ? date($companyPhpDateFormat, strtotime($item['Data'][11]['VarCharValue'])) : "",
                        'effective_end_date' => isset($item['Data'][12]['VarCharValue']) ? date($companyPhpDateFormat, strtotime($item['Data'][12]['VarCharValue'])) : "",
                        'home_country' => isset($item['Data'][13]['VarCharValue']) ? $item['Data'][13]['VarCharValue'] : "",
                        'home_city' => isset($item['Data'][14]['VarCharValue']) ? $item['Data'][14]['VarCharValue'] : "",
                        'destination_country' => isset($item['Data'][15]['VarCharValue']) ? $item['Data'][15]['VarCharValue'] : "",
                        'destination_city' => isset($item['Data'][16]['VarCharValue']) ? $item['Data'][16]['VarCharValue'] : "",
                        'destination_office' => isset($item['Data'][17]['VarCharValue']) ? $item['Data'][17]['VarCharValue'] : "",
                        'destination_job_title' => isset($item['Data'][18]['VarCharValue']) ? $item['Data'][18]['VarCharValue'] : "",
                        'home_hr_office' => isset($item['Data'][19]['VarCharValue']) ? $item['Data'][19]['VarCharValue'] : "",
                        'home_hr_name' => isset($item['Data'][20]['VarCharValue']) ? $item['Data'][20]['VarCharValue'] : "",
                        'home_hr_email' => isset($item['Data'][21]['VarCharValue']) ? $item['Data'][21]['VarCharValue'] : "",
                        'home_hr_phone' => isset($item['Data'][22]['VarCharValue']) ? $item['Data'][22]['VarCharValue'] : "",
                        'destination_hr_office' => isset($item['Data'][23]['VarCharValue']) ? $item['Data'][23]['VarCharValue'] : "",
                        'destination_hr_name' => isset($item['Data'][24]['VarCharValue']) ? $item['Data'][24]['VarCharValue'] : "",
                        'destination_hr_email' => isset($item['Data'][25]['VarCharValue']) ? $item['Data'][25]['VarCharValue'] : "",
                        'destination_hr_phone' => isset($item['Data'][26]['VarCharValue']) ? $item['Data'][26]['VarCharValue'] : "",
                        'assignee_name' => isset($item['Data'][27]['VarCharValue']) ? $item['Data'][27]['VarCharValue'] : "",
                        'assignee_email' => isset($item['Data'][28]['VarCharValue']) ? $item['Data'][28]['VarCharValue'] : "",
                        'assignee_phone' => isset($item['Data'][29]['VarCharValue']) ? $item['Data'][29]['VarCharValue'] : "",
                        'home_country_name' => isset($item['Data'][30]['VarCharValue']) ? $item['Data'][30]['VarCharValue'] : "",
                        'destination_country_name' => isset($item['Data'][31]['VarCharValue']) ? $item['Data'][31]['VarCharValue'] : "",
                        'hr_assignment_owner_name' => isset($item['Data'][32]['VarCharValue']) ? $item['Data'][32]['VarCharValue'] : "",
                        'home_hr_office_name' => isset($item['Data'][33]['VarCharValue']) ? $item['Data'][33]['VarCharValue'] : "",
                        'destination_office_name' => isset($item['Data'][34]['VarCharValue']) ? $item['Data'][34]['VarCharValue'] : "",
                        'dsp_reporter_name' => isset($item['Data'][35]['VarCharValue']) ? $item['Data'][35]['VarCharValue'] : "",
                        'dsp_owner_name' => isset($item['Data'][36]['VarCharValue']) ? $item['Data'][36]['VarCharValue'] : "",
                        'dsp_viewers_name' => isset($item['Data'][37]['VarCharValue']) ? $item['Data'][37]['VarCharValue'] : "",
                        'relocation_number' => isset($item['Data'][38]['VarCharValue']) ? $item['Data'][38]['VarCharValue'] : "",
                        'order_number' => isset($item['Data'][39]['VarCharValue']) ? $item['Data'][39]['VarCharValue'] : "",
//                        'assignment_created_date' => isset($item['Data'][40]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), strtotime($item['Data'][40]['VarCharValue'])) : "",
                        'assignment_created_date' => isset($item['Data'][40]['VarCharValue']) ? $item['Data'][40]['VarCharValue'] : "",
                        'status' => isset($item['Data'][41]['VarCharValue']) ? $item['Data'][41]['VarCharValue'] : "",
                        'destination_job_grade' => isset($item['Data'][42]['VarCharValue']) ? $item['Data'][42]['VarCharValue'] : "",
                        'ee_reference' => isset($item['Data'][43]['VarCharValue']) ? $item['Data'][43]['VarCharValue'] : "",
                    ];
                }
                $i++;
            }
            $result['data'] = $data;
            $result['completion_date_time'] = date(ModuleModel::$company->getPhpDateFormat(true), strtotime($result["queryExecution"]["Status"]["CompletionDateTime"]));
            $result['url'] = RelodayS3Helper::__getPresignedUrl("report/" . ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog) {
                if(isset($params['filter_config_id'])){
                    $aOptions = [
                        'filter_config_id' => $params['filter_config_id'],
                    ];
                    $aItem = FilterConfigItem::listJoinByCriteria($aOptions, false);
                    if($aItem['success']){
                        $params['items'] = $aItem['data'];
                    }
                }

                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_DSP_EXTRACT_ASSIGNMENT);
                $report_log->setCompanyId(ModuleModel::$company->getId());
                $report_log->setExecutionId($executionId);
                $report_log->setParams(json_encode($params));
                $report_log->setUrl($result['url']);
                $etl_history =  EtlHistory::findFirst([
                    "conditions" => "status = :done_status:",
                    "bind" => [
                        "done_status" => EtlHistory::STATUS_DONE
                    ],
                    'order' => 'id DESC'
                ]);
                if($etl_history){
                    $report_log->setLastUpdate(date('Y-m-d H:i:s', strtotime($etl_history->getTimestamp())));
                }
                $create_report_log = $report_log->__quickCreate();
                $result["create_log"] = $create_report_log;
            } else {
                $result['completion_date_time'] = $report_log->getLastUpdate();
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     *
     */
    public function assignmentInitializationAction()
    {
        $this->view->disable();
        $this->checkAjax('GET');
        $this->checkAcl('index', 'report');

        $result = [
            'success' => false,
            'assignees' => [],
            'data_members' => [],
            'accounts' => [],
            'offices' => [],
            'marital_status' => [],
            'hr_users' => []
        ];
        $profile = ModuleModel::$user_profile;
        if ($profile instanceof UserProfile) {

            $result['success'] = true;

            // Find all HR company have contract with current company
            $company_builder = new Builder();
            $company_builder->addFrom('Reloday\Gms\Models\Contract', 'c')
                ->columns('com.*')
                ->innerJoin('Reloday\Gms\Models\Company', 'c.from_company_id=com.id', 'com')
                ->where('c.status=' . Contract::STATUS_ACTIVATED)
                ->andWhere('c.to_company_id=' . $profile->getCompanyId());
            $companies = $company_builder->getQuery()->execute();
            $company_ids = [];
            if (count($companies)) {
                foreach ($companies as $company) {
                    $result['accounts'][$company->getId()] = $company->getName();
                    $company_ids[] = $company->getId();
                }
            }

            // Load assignees
            $assignee_builder = new Builder();
            $assignee_builder->addFrom('Reloday\Gms\Models\Contract', 'c')
                ->columns('e.*')
                ->innerJoin('Reloday\Gms\Models\EmployeeInContract', 'c.id=ei.contract_id', 'ei')
                ->innerJoin('Reloday\Gms\Models\Employee', 'e.id=einv.employee_id', 'e')
                ->where('c.status=' . Contract::STATUS_ACTIVATED)
                ->andWhere('c.to_company_id=' . $profile->getCompanyId());
            $assignees = $assignee_builder->getQuery()->execute();
            if (count($assignees)) {
                foreach ($assignees as $item) {
                    $result['assignees'][$item->getId()] = $item->toArray();
                }
            }

            $countries = Country::find();
            if (count($countries)) {
                foreach ($countries as $country) {
                    $result['countries'][$country->getId()] = $country->getName();
                }
            }

            // Data members
            $data_members = DataUserMember::find(["object_name='assignment'"]);
            if (count($data_members)) {
                $users = [];
                foreach ($data_members as $d) {
                    if (!isset($result['data_members'][$d->getMemberTypeId()]))
                        $result['data_members'][$d->getMemberTypeId()] = [];
                    if (!isset($users[$d->getUserProfileId()])) {
                        $user = UserProfile::findFirst($d->getUserProfileId());
                        if ($user instanceof UserProfile) {
                            $users[$user->getId()] = $user->getFirstname() . ' ' . $user->getLastname();
                        } else {
                            $users[$d->getUserProfileId()] = '';
                        }
                    }
                    if (!isset($result['data_members'][$d->getMemberTypeId()][$d->getObjectUuid()])) {
                        $result['data_members'][$d->getMemberTypeId()][$d->getObjectUuid()] = [];
                    }
                    $result['data_members'][$d->getMemberTypeId()][$d->getObjectUuid()][] = $users[$d->getUserProfileId()];
                }
            }

            // Find list office
            if (count($company_ids)) {
                $offices = Office::find(['company_id IN (' . implode(',', $company_ids) . ')']);
                if (count($offices)) {
                    foreach ($offices as $office) {
                        if (!isset($result['offices'][$office->getCompanyId()]))
                            $result['offices'][$office->getCompanyId()] = [];
                        $result['offices'][$office->getCompanyId()][$office->getId()] = $office->getName();
                    }
                }
                $users = UserProfile::find(["company_id IN (" . implode(',', $company_ids) . ")"]);
                if (count($users)) {
                    foreach ($users as $user) {
                        if (!isset($result['hr_users'][$user->getCompanyId()]))
                            $result['hr_users'][$user->getCompanyId()] = [];
                        $result['hr_users'][$user->getCompanyId()][$user->getId()] = $user->toArray();
                    }
                }
            }
            // Attribute marital status
            $marital_status = Attributes::findFirst(["code='" . Attributes::MARITAL_STATUS . "'"]);
            if ($marital_status instanceof Attributes) {
                $list = $marital_status->getValues([
                    "standard = 1 OR ( company_id = :company_id: AND archived = 0 )",
                    "bind" => [
                        "company_id" => $profile->getCompanyId()
                    ]
                ]);
                if (count($list)) {
                    foreach ($list as $item) {
                        $result['marital_status'][$item->getAttributesId() . '_' . $item->getId()] = $item->getValue();
                    }
                }
            }

        } else {
            $result['message'] = 'ERROR_SESSION_EXPIRED_TEXT';
        }

        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Invoices
     */
    public function invoiceAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('index', 'report');
        $this->checkAcl('index', 'invoice');

        $params = Helpers::__getRequestValuesArray();
        /***** new filter ******/
        $isChange = false;
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');

        $etl_history = EtlHistory::findFirst([
            'order' => 'id DESC'
        ]);
        if($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_IN_PROGRESS){
            $old_report_log = ReportLog::__checkLastResult(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_INVOICE, $params);
            if($old_report_log instanceof ReportLog){
                $result = [
                    "success" => true,
                    'executionId' => $old_report_log->getExecutionId(),
                    'url' => $old_report_log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            } else {
                $result = [
                    "success" => false,
                    'message' => "REFRESHING_REPORT_DATA_TEXT",
                ];
                goto end_of_function;
            }
        } else if ($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_DONE){
            $log = ReportLog::__checkResultAfterTimestamp(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_INVOICE, $params, $etl_history->getTimestamp());
            if ($log) {
                if($params['is_tmp'] == true){
                    if($log->getParams()){
                        $logParams = json_decode($log->getParams(), true);
                        $logParamItems = [];
                        if(is_array($logParams) &&  isset($logParams['items']) && $logParams['items']){
                            $logParamItems = json_decode(json_encode($logParams['items']), true);
                        }

                        if(is_object($logParams) && isset($logParams->items) && $logParams->items){
                            $logParamItems = json_decode(json_encode($logParams->items), true);
                        }
                        if(isset($params['filter_config_id'])){
                            $isChange = FilterConfig::__checkFilterCached($params['filter_config_id'], $logParamItems);
                            if($isChange){
                                goto go_to_filter;
                            }
                        }else{
                            goto go_to_filter;
                        }

                    }
                }

                $result = [
                    "success" => true,
                    'executionId' => $log->getExecutionId(),
                    'url' => $log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            }
        }

        /***** new filter ******/
        go_to_filter:
        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }

        $result = InvoiceQuote::__executeReport($params, $ordersConfig);

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Relocation
     */
    public function invoiceResultAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $params = Helpers::__getRequestValuesArray();
        $next_token = Helpers::__getRequestValue('nextToken');
        $result = AthenaHelper::getExecutionInfo($executionId);
        if ($result['success'] && $result['status'] == 'SUCCEEDED') {
            $queryResult = AthenaHelper::getQueryResult($executionId, $next_token);
            if (!$queryResult['success']) {
                $result['result'] = $queryResult;
                goto end_of_function;
            }
            $result['nextToken'] = $queryResult['nextToken'];
            $data = [];
            $i = 0;
            foreach ($queryResult['data'] as $item) {
                if ($i > 0 || ($i == 0 && $next_token != null && $next_token != '')) {
                    $currency = isset($item['Data'][16]['VarCharValue']) ? $item['Data'][16]['VarCharValue'] : "";
                    $data[] = [
                        'id' => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        'number' => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        'reference' => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        'date' => isset($item['Data'][3]['VarCharValue']) && $item['Data'][3]['VarCharValue'] > 0 ? date(ModuleModel::$company->getPhpDateFormat(), $item['Data'][3]['VarCharValue']) : "",
                        'due_date' => isset($item['Data'][4]['VarCharValue']) && $item['Data'][4]['VarCharValue'] > 0 ? date(ModuleModel::$company->getPhpDateFormat(), $item['Data'][4]['VarCharValue']) : "",
                        'account_name' => isset($item['Data'][5]['VarCharValue']) ? $item['Data'][5]['VarCharValue'] : "",
                        'biller_address' => isset($item['Data'][6]['VarCharValue']) ? $item['Data'][6]['VarCharValue'] : "",
                        'biller_town' => isset($item['Data'][7]['VarCharValue']) ? $item['Data'][7]['VarCharValue'] : "",
                        'biller_country_name' => isset($item['Data'][8]['VarCharValue']) ? $item['Data'][8]['VarCharValue'] : "",
                        'biller_email' => isset($item['Data'][9]['VarCharValue']) ? $item['Data'][9]['VarCharValue'] : "",
                        'biller_contact' => isset($item['Data'][10]['VarCharValue']) ? $item['Data'][10]['VarCharValue'] : "",
                        'total' => isset($item['Data'][11]['VarCharValue']) ? number_format((float)$item['Data'][11]['VarCharValue'], 2, '.', '') : "",
                        'sub_total' => isset($item['Data'][12]['VarCharValue']) ? number_format((float)$item['Data'][12]['VarCharValue'], 2, '.', '')  : "",
                        'discount' => isset($item['Data'][13]['VarCharValue']) ? number_format((float)$item['Data'][13]['VarCharValue'], 2, '.', '') . " %" : "",
                        'total_paid' => isset($item['Data'][14]['VarCharValue']) ? number_format((float)$item['Data'][14]['VarCharValue'], 2, '.', '')  : "",
                        'status' => isset($item['Data'][15]['VarCharValue']) ? $item['Data'][15]['VarCharValue'] : "",
                        'currency' => isset($item['Data'][16]['VarCharValue']) ? $item['Data'][16]['VarCharValue'] : "",
                        'vat_number' => isset($item['Data'][17]['VarCharValue']) ? $item['Data'][17]['VarCharValue'] : "",
                        'invoice_template_name' => isset($item['Data'][18]['VarCharValue']) ? $item['Data'][18]['VarCharValue'] : "",
                        'relocation' => isset($item['Data'][19]['VarCharValue']) ? $item['Data'][19]['VarCharValue'] : "",
                        'employee_name' => isset($item['Data'][20]['VarCharValue']) ? $item['Data'][20]['VarCharValue'] : "",
                        'total_before_tax' => isset($item['Data'][21]['VarCharValue']) ? number_format((float)$item['Data'][21]['VarCharValue'], 2, '.', '') : "",
                        'total_tax' => isset($item['Data'][22]['VarCharValue']) ? number_format((float)$item['Data'][22]['VarCharValue'], 2, '.', '') : "",
                        'items' => isset($item['Data'][23]['VarCharValue']) ? $item['Data'][23]['VarCharValue'] : "",
                        'account_invoicing_reference' => isset($item['Data'][24]['VarCharValue']) ? $item['Data'][24]['VarCharValue'] : "",
                        'office_name' => isset($item['Data'][25]['VarCharValue']) ? $item['Data'][25]['VarCharValue'] : "",
                        'office_invoicing_reference' => isset($item['Data'][26]['VarCharValue']) ? $item['Data'][26]['VarCharValue'] : "",
                        'biller_for' => isset($item['Data'][27]['VarCharValue']) ? $item['Data'][27]['VarCharValue'] : "",
                    ];
                }
                $i++;
            }
            $result['data'] = $data;
            $result['completion_date_time'] = date(ModuleModel::$company->getPhpDateFormat(true), strtotime($result["queryExecution"]["Status"]["CompletionDateTime"]));
            $result['url'] = RelodayS3Helper::__getPresignedUrl("report/" . ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog) {
                if(isset($params['filter_config_id'])){
                    $aOptions = [
                        'filter_config_id' => $params['filter_config_id'],
                    ];
                    $aItem = FilterConfigItem::listJoinByCriteria($aOptions, false);
                    if($aItem['success']){
                        $params['items'] = $aItem['data'];
                    }
                }

                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_DSP_EXTRACT_INVOICE);
                $report_log->setCompanyId(ModuleModel::$company->getId());
                $report_log->setExecutionId($executionId);
                $report_log->setParams(json_encode($params));
                $report_log->setUrl($result['url']);
                $etl_history =  EtlHistory::findFirst([
                    "conditions" => "status = :done_status:",
                    "bind" => [
                        "done_status" => EtlHistory::STATUS_DONE
                    ],
                    'order' => 'id DESC'
                ]);
                if($etl_history){
                    $report_log->setLastUpdate(date('Y-m-d H:i:s', strtotime($etl_history->getTimestamp())));
                }
                $create_report_log = $report_log->__quickCreate();
                $result["create_log"] = $create_report_log;
            } else {
                $result['completion_date_time'] = $report_log->getLastUpdate();
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Relocation
     */
    public function assigneeAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('index', 'report');
        $result = [
            'success' => false,
            'list' => []
        ];

        $params = Helpers::__getRequestValuesArray();
        /***** new filter ******/
        $isChange = false;
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');
       
        $etl_history = EtlHistory::findFirst([
            'order' => 'id DESC'
        ]);
        if($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_IN_PROGRESS){
            $old_report_log = ReportLog::__checkLastResult(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_ASSIGNEE, $params);
            if($old_report_log instanceof ReportLog){
                $result = [
                    "success" => true,
                    'executionId' => $old_report_log->getExecutionId(),
                    'url' => $old_report_log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            } else {
                $result = [
                    "success" => false,
                    'message' => "REFRESHING_REPORT_DATA_TEXT",
                ];
                goto end_of_function;
            }
        } else if ($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_DONE){
            $log = ReportLog::__checkResultAfterTimestamp(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_ASSIGNEE, $params, $etl_history->getTimestamp());
            if ($log) {
                if($params['is_tmp'] == true){
                    if($log->getParams()){
                        $logParams = json_decode($log->getParams(), true);
                        $logParamItems = [];
                        if(is_array($logParams) &&  isset($logParams['items']) && $logParams['items']){
                            $logParamItems = json_decode(json_encode($logParams['items']), true);
                        }

                        if(is_object($logParams) && isset($logParams->items) && $logParams->items){
                            $logParamItems = json_decode(json_encode($logParams->items), true);
                        }
                        if(isset($params['filter_config_id'])){
                            $isChange = FilterConfig::__checkFilterCached($params['filter_config_id'], $logParamItems);
                            if($isChange){
                                goto go_to_filter;
                            }
                        }else{
                            goto go_to_filter;
                        }

                    }
                }

                $result = [
                    "success" => true,
                    'executionId' => $log->getExecutionId(),
                    'url' => $log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            }
        }
        /***** new filter ******/
        go_to_filter:
        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }

        $result = Employee::__executeReport($params, $ordersConfig);

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();

//                    $item->dependents = [];
//                    $employee = Employee::findFirstById($item->id);
//                    $dependents = $employee->getDependants();
//                    if (is_array($dependents) && count($dependents) > 0) {
//                        $i = 0;
//                        foreach ($dependents as $dependent) {
//                            switch ($dependent->getRelation()) {
//                                case Dependant::DEPENDANT_CHILD:
//                                    $relation = "CHILD_TEXT";
//                                    break;
//                                case Dependant::DEPENDANT_SPOUSE:
//                                    $relation = "SPOUSE_TEXT";
//                                    break;
//                                case Dependant::DEPENDANT_COMMON_LAW_PARTNER:
//                                    $relation = "COMMON_LAW_PARTNER_TEXT";
//                                    break;
//                                case Dependant::DEPENDANT_OTHER:
//                                    $relation = "OTHER_TEXT";
//                                    break;
//                            }
//                            $item->dependents[] = [
//                                "name" => $dependent->getFirstname() . " " . $dependent->getLastname(),
//                                "relation" => $relation
//                            ];
//                            $i++;
//                        }
//                    }
    }

    /**
     * Relocation
     */
    public function assigneeResultAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $params = Helpers::__getRequestValuesArray();
        $next_token = Helpers::__getRequestValue('nextToken');
        $result = AthenaHelper::getExecutionInfo($executionId);
        if ($result['success'] && $result['status'] == 'SUCCEEDED') {
            $queryResult = AthenaHelper::getQueryResult($executionId, $next_token);
            if (!$queryResult['success']) {
                $result['result'] = $queryResult;
                goto end_of_function;
            }
            $result['nextToken'] = $queryResult['nextToken'];
            $data = [];
            $i = 0;
            foreach ($queryResult['data'] as $item) {
                if ($i > 0 || ($i == 0 && $next_token != null && $next_token != '')) {
                    $data_array = [
                        'id' => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        'identify' => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        'firstname' => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        'lastname' => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : "",
                        'place_of_birth' => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        'country_of_birth' => isset($item['Data'][5]['VarCharValue']) ? $item['Data'][5]['VarCharValue'] : "",
                        'country' => isset($item['Data'][6]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), strtotime($item['Data'][6]['VarCharValue'])) : "",
                        'birth_date' => isset($item['Data'][7]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), strtotime($item['Data'][7]['VarCharValue'])) : "",
                        'citizenship' => isset($item['Data'][8]['VarCharValue']) ? $item['Data'][8]['VarCharValue'] : "",
                        'spoken_languages' => isset($item['Data'][9]['VarCharValue']) ? $item['Data'][9]['VarCharValue'] : "",
                        'school_grade' => isset($item['Data'][10]['VarCharValue']) ? $item['Data'][10]['VarCharValue'] : "",
                        'marital_status' => isset($item['Data'][11]['VarCharValue']) ? AttributeHelper::__translate($item['Data'][11]['VarCharValue'], ModuleModel::$language) : "",
                        'workemail' => isset($item['Data'][12]['VarCharValue']) ? $item['Data'][12]['VarCharValue'] : "",
                        'privateemail' => isset($item['Data'][13]['VarCharValue']) ? $item['Data'][13]['VarCharValue'] : "",
                        'phonework' => isset($item['Data'][14]['VarCharValue']) ? $item['Data'][14]['VarCharValue'] : "",
                        'phonehome' => isset($item['Data'][15]['VarCharValue']) ? $item['Data'][15]['VarCharValue'] : "",
                        'mobilehome' => isset($item['Data'][16]['VarCharValue']) ? $item['Data'][16]['VarCharValue'] : "",
                        'support_contact' => isset($item['Data'][17]['VarCharValue']) ? $item['Data'][17]['VarCharValue'] : "",
                        'buddy_contact' => isset($item['Data'][18]['VarCharValue']) ? $item['Data'][18]['VarCharValue'] : "",
                        'company' => isset($item['Data'][19]['VarCharValue']) ? $item['Data'][19]['VarCharValue'] : "",
                        'office' => isset($item['Data'][20]['VarCharValue']) ? $item['Data'][20]['VarCharValue'] : "",
                        'department' => isset($item['Data'][21]['VarCharValue']) ? $item['Data'][21]['VarCharValue'] : "",
                        'team' => isset($item['Data'][22]['VarCharValue']) ? $item['Data'][22]['VarCharValue'] : "",
                        'jobtitle' => isset($item['Data'][23]['VarCharValue']) ? $item['Data'][23]['VarCharValue'] : "",
                        'dependent_translate' => isset($item['Data'][24]['VarCharValue']) ? $item['Data'][24]['VarCharValue'] : "",
                        'active' => isset($item['Data'][25]['VarCharValue']) ? ($item['Data'][25]['VarCharValue'] == "true" ? ConstantHelper::__translate("YES_TEXT", ModuleModel::$language) : ConstantHelper::__translate("NO_TEXT", ModuleModel::$language)) : "",
                        'assignee_name' => isset($item['Data'][26]['VarCharValue']) ? $item['Data'][26]['VarCharValue'] : "",
                        //passport
                        'passport_number' => isset($item['Data'][27]['VarCharValue']) ? $item['Data'][27]['VarCharValue'] : "",
                        'passport_name' => isset($item['Data'][28]['VarCharValue']) ? $item['Data'][28]['VarCharValue'] : "",
                        'passport_expiry_date' => isset($item['Data'][29]['VarCharValue']) ? $item['Data'][29]['VarCharValue'] : "",
                        'passport_delivery_date' => isset($item['Data'][30]['VarCharValue']) ? $item['Data'][30]['VarCharValue'] : "",
                        'passport_delivery_country_name' => isset($item['Data'][31]['VarCharValue']) ? $item['Data'][31]['VarCharValue'] : "",
                        'visa_number' => isset($item['Data'][32]['VarCharValue']) ? $item['Data'][32]['VarCharValue'] : "",
                        //visa
                        'visa_name' => isset($item['Data'][33]['VarCharValue']) ? $item['Data'][33]['VarCharValue'] : "",
                        'visa_expiry_date' => isset($item['Data'][34]['VarCharValue']) ? $item['Data'][34]['VarCharValue'] : "",
                        'visa_delivery_date' => isset($item['Data'][35]['VarCharValue']) ? $item['Data'][35]['VarCharValue'] : "",
                        'visa_estimated_approval_date' => isset($item['Data'][36]['VarCharValue']) ? $item['Data'][36]['VarCharValue'] : "",
                        'visa_approval_date' => isset($item['Data'][37]['VarCharValue']) ? $item['Data'][37]['VarCharValue'] : "",
                        'visa_delivery_country_name' => isset($item['Data'][38]['VarCharValue']) ? $item['Data'][38]['VarCharValue'] : "",
                        'social_security_number' => isset($item['Data'][39]['VarCharValue']) ? $item['Data'][39]['VarCharValue'] : "",
                        //social_security
                        'social_security_name' => isset($item['Data'][40]['VarCharValue']) ? $item['Data'][40]['VarCharValue'] : "",
                        'social_security_expiry_date' => isset($item['Data'][41]['VarCharValue']) ? $item['Data'][41]['VarCharValue'] : "",
                        'social_security_delivery_date' => isset($item['Data'][42]['VarCharValue']) ? $item['Data'][42]['VarCharValue'] : "",
                        'education_number' => isset($item['Data'][43]['VarCharValue']) ? $item['Data'][43]['VarCharValue'] : "",
                        //education
                        'education_name' => isset($item['Data'][44]['VarCharValue']) ? $item['Data'][44]['VarCharValue'] : "",
                        'education_expiry_date' => isset($item['Data'][45]['VarCharValue']) ? $item['Data'][45]['VarCharValue'] : "",
                        'education_delivery_date' => isset($item['Data'][46]['VarCharValue']) ? $item['Data'][46]['VarCharValue'] : "",
                        'curriculum_number' => isset($item['Data'][47]['VarCharValue']) ? $item['Data'][47]['VarCharValue'] : "",
                        //curriculum
                        'curriculum_name' => isset($item['Data'][48]['VarCharValue']) ? $item['Data'][48]['VarCharValue'] : "",
                        'curriculum_expiry_date' => isset($item['Data'][49]['VarCharValue']) ? $item['Data'][49]['VarCharValue'] : "",
                        'curriculum_delivery_date' => isset($item['Data'][50]['VarCharValue']) ? $item['Data'][50]['VarCharValue'] : "",
                        'driving_license_number' => isset($item['Data'][51]['VarCharValue']) ? $item['Data'][51]['VarCharValue'] : "",
                        //driving_license
                        'driving_license_name' => isset($item['Data'][52]['VarCharValue']) ? $item['Data'][52]['VarCharValue'] : "",
                        'driving_license_expiry_date' => isset($item['Data'][53]['VarCharValue']) ? $item['Data'][53]['VarCharValue'] : "",
                        'driving_license_delivery_date' => isset($item['Data'][54]['VarCharValue']) ? $item['Data'][54]['VarCharValue'] : "",
                        'other_document_number' => isset($item['Data'][55]['VarCharValue']) ? $item['Data'][55]['VarCharValue'] : "",
                        //other_document
                        'other_document_name' => isset($item['Data'][56]['VarCharValue']) ? $item['Data'][56]['VarCharValue'] : "",
                        'other_document_expiry_date' => isset($item['Data'][57]['VarCharValue']) ? $item['Data'][57]['VarCharValue'] : "",
                        'other_document_delivery_date' => isset($item['Data'][58]['VarCharValue']) ? $item['Data'][58]['VarCharValue'] : "",
                        'ee_created_date' => isset($item['Data'][59]['VarCharValue']) ? strtotime($item['Data'][59]['VarCharValue']) : "",
                        'ee_reference' => isset($item['Data'][60]['VarCharValue']) ? $item['Data'][60]['VarCharValue'] : "",
                        ];
                    if (isset($item['Data'][9]['VarCharValue'])) {
                        $spoken_languages = json_decode($item['Data'][9]['VarCharValue']);
                        $data_array['spoken_languages'] = '';
                        if (is_array($spoken_languages) && count($spoken_languages) > 0) {
                            $i = 0;
                            foreach ($spoken_languages as $spoken_language) {
                                if ($i > 0) $data_array['spoken_languages'] = $data_array['spoken_languages'] . "; ";
                                $data_array['spoken_languages'] .= LanguageCode::getLanguageFromCode($spoken_language);
                                $i++;
                            }
                        }
                    }
                    $data[] = $data_array;
                }
                $i++;
            }
            $result['data'] = $data;
            $result['completion_date_time'] = date(ModuleModel::$company->getPhpDateFormat(true), strtotime($result["queryExecution"]["Status"]["CompletionDateTime"]));
            $result['url'] = RelodayS3Helper::__getPresignedUrl("report/" . ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog) {
                if(isset($params['filter_config_id'])){
                    $aOptions = [
                        'filter_config_id' => $params['filter_config_id'],
                    ];
                    $aItem = FilterConfigItem::listJoinByCriteria($aOptions, false);
                    if($aItem['success']){
                        $params['items'] = $aItem['data'];
                    }
                }

                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_DSP_EXTRACT_ASSIGNEE);
                $report_log->setCompanyId(ModuleModel::$company->getId());
                $report_log->setExecutionId($executionId);
                $report_log->setParams(json_encode($params));
                $report_log->setUrl($result['url']);
                $etl_history =  EtlHistory::findFirst([
                    "conditions" => "status = :done_status:",
                    "bind" => [
                        "done_status" => EtlHistory::STATUS_DONE
                    ],
                    'order' => 'id DESC'
                ]);
                if($etl_history){
                    $report_log->setLastUpdate(date('Y-m-d H:i:s', strtotime($etl_history->getTimestamp())));
                }
                $create_report_log = $report_log->__quickCreate();
                $result["create_log"] = $create_report_log;
            } else {
                $result['completion_date_time'] = $report_log->getLastUpdate();
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Action save personal extract data setting
     * @return array
     */
    public function saveExtractDataSettingAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');

        $result = [
            'success' => false,
            'message' => 'SAVE_EXTRACT_DATA_SETTING_FAIL_TEXT'
        ];

        $objectType = Helpers::__getRequestValue("object_type");
        $dataSetting = Helpers::__getRequestValue("data_setting");

        if (!$objectType || !$dataSetting) {
            goto end_of_function;
        }

        $extractDataSetting = ExtractDataSetting::findFirst(
            [
                'conditions' => 'user_profile_id = :user_profile_id: AND object_type = :object_type:',
                'bind' => [
                    'user_profile_id' => ModuleModel::$user_profile->getId(),
                    'object_type' => $objectType,
                ]
            ]
        );

        if (!$extractDataSetting) {
            $extractDataSetting = new ExtractDataSetting();
            $extractDataSetting->setObjectType($objectType);
            $extractDataSetting->setDataSetting($dataSetting);
            $extractDataSetting->setUserProfileId(ModuleModel::$user_profile->getId());
            $extractDataSetting->setCreatedAt(date('d/m/Y H:i'));
        } else {
            $extractDataSetting->setDataSetting($dataSetting);
        }

        if (!$extractDataSetting->save()) {
            foreach ($extractDataSetting->getMessages() as $message) {
                $result['detail'][] = $message->getMessage();
            }
            goto end_of_function;
        }

        $result = [
            'success' => true,
            'message' => 'SAVE_EXTRACT_DATA_SETTING_SUCCESS_TEXT'
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Action retrieve personal extract data setting
     * @return array
     */
    public function getExtractDataSettingAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');

        $result = [
            'success' => false,
            'message' => 'GET_EXTRACT_DATA_SETTING_FAIL_TEXT'
        ];

        $objectType = Helpers::__getRequestValue("object_type");

        if (!$objectType) {
            goto end_of_function;
        }

        $extractDataSetting = ExtractDataSetting::findFirst(
            [
                'conditions' => 'user_profile_id = :user_profile_id: AND object_type = :object_type:',
                'bind' => [
                    'user_profile_id' => ModuleModel::$user_profile->getId(),
                    'object_type' => $objectType,
                ]
            ]
        );

        $data = '';

        if ($extractDataSetting) {
            $data = $extractDataSetting->getDataSetting();
        }

        $result = [
            'success' => true,
            'message' => 'GET_EXTRACT_DATA_SETTING_SUCCESS_TEXT',
            'data' => $data
        ];

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     *
     */
    public function executeAssignmentEndingSoonAction()
    {
        $this->view->disable();
        $this->checkAjaxGet();
        $this->checkAcl('index', 'report');
        $etl_history = EtlHistory::findFirst([
            'order' => 'id DESC'
        ]);
        if($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_IN_PROGRESS){
            $old_report_log = ReportLog::__checkLastResult(ModuleModel::$company->getId(), ReportLog::TYPE_GMS_ASSIGNMENT_ENDING_SOON, []);
            if($old_report_log instanceof ReportLog){
                $result = [
                    "success" => true,
                    'executionId' => $old_report_log->getExecutionId(),
                    'url' => $old_report_log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            } else {
                $result = [
                    "success" => false,
                    'message' => "REFRESHING_REPORT_DATA_TEXT",
                ];
                goto end_of_function;
            }
        } else if ($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_DONE){
            $log = ReportLog::__checkResultAfterTimestamp(ModuleModel::$company->getId(), ReportLog::TYPE_GMS_ASSIGNMENT_ENDING_SOON, [], $etl_history->getTimestamp());
            if ($log) {
                $result = [
                    "success" => true,
                    'executionId' => $log->getExecutionId(),
                    'url' => $log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            }
        }
        $result = Assignment::executeEndingSoonReport();
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function getAssignmentEndingSoonAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');

        $result = AthenaHelper::getExecutionInfo($executionId);

        if ($result['success'] && $result['status'] == 'SUCCEEDED') {
            $queryResult = AthenaHelper::getQueryResult($executionId);

            if (!$queryResult['success']) {
                $result['result'] = $queryResult;
                goto end_of_function;
            }
            $data = [];
            $data[0] = [];
            $data[1] = [];
            $data[2] = [];
            $i = 0;
            foreach ($queryResult['data'] as $item) {
                if ($i > 0 && isset($item['Data'][1]['VarCharValue'])) {
                    $data[intval($item['Data'][1]['VarCharValue'])][] = [
                        isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        isset($item['Data'][0]['VarCharValue']) ? intval($item['Data'][0]['VarCharValue']) : 0
                    ];
                }
                $i++;
            }

            $result['data'] = $data;
            $result['completion_date_time'] = date(ModuleModel::$company->getPhpDateFormat(true), strtotime($result["queryExecution"]["Status"]["CompletionDateTime"]));
            $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog) {
                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_GMS_ASSIGNMENT_ENDING_SOON);
                $report_log->setCompanyId(ModuleModel::$company->getId());
                $report_log->setExecutionId($executionId);
                $etl_history =  EtlHistory::findFirst([
                    "conditions" => "status = :done_status:",
                    "bind" => [
                        "done_status" => EtlHistory::STATUS_DONE
                    ],
                    'order' => 'id DESC'
                ]);
                if($etl_history){
                    $report_log->setLastUpdate(date('Y-m-d H:i:s', strtotime($etl_history->getTimestamp())));
                }
                $report_log->setUrl($result['url']);
                $create_report_log = $report_log->__quickCreate();
                $result["create_log"] = $create_report_log;
            } else {
                $result['completion_date_time'] = $report_log->getLastUpdate();
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Service
     */
    public function serviceAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $result = [
            'success' => false,
            'list' => []
        ];


        $params = Helpers::__getRequestValuesArray();
        /***** new filter ******/
        $isChange = false;
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');

        if (!isset($params['service_company_id']) || $params['service_company_id'] == null || $params['service_company_id'] <= 0){
            goto end_of_function;
        }
        $etl_history = EtlHistory::findFirst([
            'order' => 'id DESC'
        ]);
        if($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_IN_PROGRESS){
            $old_report_log = ReportLog::__checkLastResult(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_SERVICE, $params);
            if($old_report_log instanceof ReportLog){
                $result = [
                    "success" => true,
                    'executionId' => $old_report_log->getExecutionId(),
                    'url' => $old_report_log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            } else {
                $result = [
                    "success" => false,
                    'message' => "REFRESHING_REPORT_DATA_TEXT",
                ];
                goto end_of_function;
            }
        } else if ($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_DONE){
            $log = ReportLog::__checkResultAfterTimestamp(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_SERVICE, $params, $etl_history->getTimestamp());
            if ($log) {
                if($params['is_tmp'] == true){
                    if($log->getParams()){
                        $logParams = json_decode($log->getParams(), true);
                        $logParamItems = [];
                        if(is_array($logParams) &&  isset($logParams['items']) && $logParams['items']){
                            $logParamItems = json_decode(json_encode($logParams['items']), true);
                        }

                        if(is_object($logParams) && isset($logParams->items) && $logParams->items){
                            $logParamItems = json_decode(json_encode($logParams->items), true);
                        }
                        if(isset($params['filter_config_id'])){
                            $isChange = FilterConfig::__checkFilterCached($params['filter_config_id'], $logParamItems);
                            if($isChange){
                                goto go_to_filter;
                            }
                        }else{
                            goto go_to_filter;
                        }

                    }
                }

                $result = [
                    "success" => true,
                    'executionId' => $log->getExecutionId(),
                    'url' => $log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            }
        }

        /***** new filter ******/
        go_to_filter:
        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        } 

        $result = ServiceCompany::__executeReport($params, $ordersConfig);

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @param $executionId
     * @return mixed
     * Service
     */
    public function serviceResultAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $params = Helpers::__getRequestValuesArray();
        $result = AthenaHelper::getExecutionInfo($executionId);
        $next_token = Helpers::__getRequestValue('nextToken');
        if ($result['success'] && $result['status'] == 'SUCCEEDED') {
            $queryResult = AthenaHelper::getQueryResult($executionId, Helpers::__getRequestValue('nextToken'));
            if (!$queryResult['success']) {
                $result['result'] = $queryResult;
                goto end_of_function;
            }
            $result['nextToken'] = $queryResult['nextToken'];
            $data = [];
            $i = 0;
            foreach ($queryResult['data'] as $item) {
                if ($i > 0 || ($i == 0 && $next_token != null && $next_token != '')) {
                    $data_array = [
                        'service_reference' => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        'service_company_name' => isset($item['Data'][5]['VarCharValue']) ? $item['Data'][5]['VarCharValue'] : "",
                        'service_template' => isset($item['Data'][21]['VarCharValue']) ? $item['Data'][21]['VarCharValue'] : "",

                        'assignment_reference' => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        'identify' => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        'relocation_service_name' => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        'relocation_start_date' => isset($item['Data'][3]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), strtotime($item['Data'][3]['VarCharValue'] ))  : "",

                        'hr_company_name' => isset($item['Data'][6]['VarCharValue']) ? $item['Data'][6]['VarCharValue'] : "",
                        'booker_company_name' => isset($item['Data'][7]['VarCharValue']) ? $item['Data'][7]['VarCharValue'] : "",
                        'service_provider_name' => isset($item['Data'][8]['VarCharValue']) ? $item['Data'][8]['VarCharValue'] : "",
                        'assignee_name' => isset($item['Data'][9]['VarCharValue']) ? $item['Data'][9]['VarCharValue'] : "",
                        'data_owner_name' => isset($item['Data'][10]['VarCharValue']) ? $item['Data'][10]['VarCharValue'] : "",
                        'data_reporter_name' => isset($item['Data'][11]['VarCharValue']) ? $item['Data'][11]['VarCharValue'] : "",
                        'data_viewers_name' => isset($item['Data'][12]['VarCharValue']) ? $item['Data'][12]['VarCharValue'] : "",
                        'service_status' => isset($item['Data'][13]['VarCharValue']) ? $item['Data'][13]['VarCharValue'] : "",
                        'progress' => isset($item['Data'][14]['VarCharValue']) ? $item['Data'][14]['VarCharValue'] : "",
//                        'service_creation_date' => isset($item['Data'][15]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), strtotime($item['Data'][15]['VarCharValue'] )) : "",
                        'service_creation_date' => isset($item['Data'][15]['VarCharValue']) ? strtotime($item['Data'][15]['VarCharValue']) : "",
                        'service_start_date' => isset($item['Data'][16]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), doubleval($item['Data'][16]['VarCharValue'] )) : "",
                        'service_end_date' => isset($item['Data'][17]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), doubleval($item['Data'][17]['VarCharValue'] )) : "",
                        'service_authorised_date' => isset($item['Data'][18]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), doubleval($item['Data'][18]['VarCharValue'] )) : "",
                        'service_completion_date' => isset($item['Data'][19]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), doubleval($item['Data'][19]['VarCharValue'] )) : "",
                        'service_expiry_date' => isset($item['Data'][20]['VarCharValue']) ? date(ModuleModel::$company->getPhpDateFormat(), doubleval($item['Data'][20]['VarCharValue'] )) : "",
                        'relo_owner_name' => isset($item['Data'][24]['VarCharValue']) ? $item['Data'][24]['VarCharValue'] : "",
                        'ee_reference' => isset($item['Data'][25]['VarCharValue']) ? $item['Data'][25]['VarCharValue'] : "",
                        'origin_office' => isset($item['Data'][26]['VarCharValue']) ? $item['Data'][26]['VarCharValue'] : "",
                        'destination_office' => isset($item['Data'][27]['VarCharValue']) ? $item['Data'][27]['VarCharValue'] : "",
                    ];

                    if (isset($item['Data'][23]['VarCharValue'])) {
                        $fields = json_decode($item['Data'][23]['VarCharValue']);
                        if (is_object($fields)) {
                            $fields = (array)$fields;
                        }

                        if (is_array($fields) && count($fields) > 0){
                            foreach ($fields as $key => $field) {
//                                var_dump($key);die();
                                $serviceField = ServiceField::findFirst([
                                    'conditions' => 'code = :code:',
                                    'bind' => [
                                        'code' => $key
                                    ]
                                ]);
                                if ($serviceField){
                                    if ($serviceField->getDataTypeName() == 'date'){
                                        if($field){
                                            $date = date(ModuleModel::$company->getPhpDateFormat(), doubleval($field));
                                            $data_array[$key] = $date;
                                        }else{
                                            $data_array[$key] = '';
                                        }

                                    } else if ($serviceField->getServiceFieldTypeId() == ServiceFieldType::TYPE_ATTRIBUTES){
                                        $data_array[$key] = Attributes::__getTranslateValue($field, ModuleModel::$language);
                                    } else{
                                        $data_array[$key] = $field;
                                    }
                                }else{
                                    $data_array[$key] = $field;
                                }

                            }
                        }
                    }else {
                        $fields = ServiceFieldExt::find([
                            'conditions' => 'service_id = :service_id:',
                            'bind' => [
                                'service_id' => (int)$item['Data'][22]['VarCharValue']
                            ],
                            'columns' => ['code']
                        ]);
                        foreach ($fields->toArray() as $field){
                            $data_array[$field['code']] = '';
                        }
                    }

                    $data[] = $data_array;
                }
                $i++;
            }
            $result['data'] = $data;
            $result['i'] = $i;
            $result['completion_date_time'] = date(ModuleModel::$company->getPhpDateFormat(true), strtotime($result["queryExecution"]["Status"]["CompletionDateTime"]));
            $result['url'] = RelodayS3Helper::__getPresignedUrl("report/" . ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog) {
                if(isset($params['filter_config_id'])){
                    $aOptions = [
                        'filter_config_id' => $params['filter_config_id'],
                    ];
                    $aItem = FilterConfigItem::listJoinByCriteria($aOptions, false);
                    if($aItem['success']){
                        $params['items'] = $aItem['data'];
                    }
                }

                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_DSP_EXTRACT_SERVICE);
                $report_log->setCompanyId(ModuleModel::$company->getId());
                $report_log->setExecutionId($executionId);
                $report_log->setParams(json_encode($params));
                $report_log->setUrl($result['url']);
                $etl_history =  EtlHistory::findFirst([
                    "conditions" => "status = :done_status:",
                    "bind" => [
                        "done_status" => EtlHistory::STATUS_DONE
                    ],
                    'order' => 'id DESC'
                ]);
                if($etl_history){
                    $report_log->setLastUpdate(date('Y-m-d H:i:s', strtotime($etl_history->getTimestamp())));
                }
                $create_report_log = $report_log->__quickCreate();
                $result["create_log"] = $create_report_log;
            } else {
                $result['completion_date_time'] = $report_log->getLastUpdate();
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Invoices
     */
    public function invoiceItemAction()
    {
        $this->view->disable();
        $this->checkAjax('POST');
        $this->checkAcl('index', 'report');
        $this->checkAcl('index', 'invoice');

        $params = Helpers::__getRequestValuesArray();
        /***** new filter ******/
        $isChange = false;
        $params['filter_config_id'] = Helpers::__getRequestValue('filter_config_id');
        $params['is_tmp'] = Helpers::__getRequestValue('is_tmp');

        $etl_history = EtlHistory::findFirst([
            'order' => 'id DESC'
        ]);
        if($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_IN_PROGRESS){
            $old_report_log = ReportLog::__checkLastResult(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_INVOICE_ITEM, $params);
            if($old_report_log instanceof ReportLog){
                $result = [
                    "success" => true,
                    'executionId' => $old_report_log->getExecutionId(),
                    'url' => $old_report_log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            } else {
                $result = [
                    "success" => false,
                    'message' => "REFRESHING_REPORT_DATA_TEXT",
                ];
                goto end_of_function;
            }
        } else if ($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_DONE){
            $log = ReportLog::__checkResultAfterTimestamp(ModuleModel::$company->getId(), ReportLog::TYPE_DSP_EXTRACT_INVOICE_ITEM, $params, $etl_history->getTimestamp());
            if ($log) {
                if($params['is_tmp'] == true){
                    if($log->getParams()){
                        $logParams = json_decode($log->getParams(), true);
                        $logParamItems = [];
                        if(is_array($logParams) &&  isset($logParams['items']) && $logParams['items']){
                            $logParamItems = json_decode(json_encode($logParams['items']), true);
                        }

                        if(is_object($logParams) && isset($logParams->items) && $logParams->items){
                            $logParamItems = json_decode(json_encode($logParams->items), true);
                        }
                        if(isset($params['filter_config_id'])){
                            $isChange = FilterConfig::__checkFilterCached($params['filter_config_id'], $logParamItems);
                            if($isChange){
                                goto go_to_filter;
                            }
                        }else{
                            goto go_to_filter;
                        }

                    }
                }

                $result = [
                    "success" => true,
                    'executionId' => $log->getExecutionId(),
                    'url' => $log->getUrl(),
                    'status' => ReportLog::STATUS_SUCCESS,
                ];
                goto end_of_function;
            }
        }

        /***** new filter ******/
        go_to_filter:
        $order = Helpers::__getRequestValueAsArray('sort');
        $ordersConfig = Helpers::__getApiOrderConfig([$order]);
        $orders = Helpers::__getRequestValue('orders');
        if ($orders && is_array($orders)) {
            $orders = reset($orders);
            if (is_object($orders)) {
                $ordersConfig = [];
                $ordersConfig[] = [
                    "field" => strtolower($orders->column),
                    "order" => $orders->descending == true ? 'desc' : 'asc'
                ];
            }
        }

        $result = InvoiceQuoteItem::__executeReport($params, $ordersConfig);

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * Relocation
     */
    public function invoiceItemResultAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $params = Helpers::__getRequestValuesArray();
        $next_token = Helpers::__getRequestValue('nextToken');
        $result = AthenaHelper::getExecutionInfo($executionId);
        if ($result['success'] && $result['status'] == 'SUCCEEDED') {
            $queryResult = AthenaHelper::getQueryResult($executionId, $next_token);
            if (!$queryResult['success']) {
                $result['result'] = $queryResult;
                goto end_of_function;
            }
            $result['nextToken'] = $queryResult['nextToken'];
            $data = [];
            $i = 0;
            foreach ($queryResult['data'] as $item) {
                if ($i > 0 || ($i == 0 && $next_token != null && $next_token != '')) {
                    $currency = isset($item['Data'][31]['VarCharValue']) ? $item['Data'][31]['VarCharValue'] : "";
                    $data[] = [
                        'invoice_number' => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        'invoice_reference' => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        'account_name' => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        'account_reference' => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : "",
                        'item_number' => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        'item_name' => isset($item['Data'][5]['VarCharValue']) ? $item['Data'][5]['VarCharValue'] : "",
                        'item_description' => isset($item['Data'][6]['VarCharValue']) ? $item['Data'][6]['VarCharValue'] : "",
                        'item_type' => isset($item['Data'][7]['VarCharValue']) ? $item['Data'][7]['VarCharValue'] : "",
                        'item_quantity' => isset($item['Data'][8]['VarCharValue']) ? $item['Data'][8]['VarCharValue'] : "",
                        'item_unit_price' => isset($item['Data'][9]['VarCharValue']) ? number_format((float)$item['Data'][9]['VarCharValue'], 2, '.', '') . $currency : "",
                        'item_discount_percent' => isset($item['Data'][10]['VarCharValue']) ? $item['Data'][10]['VarCharValue'] : "",
                        'item_discount_amount' => isset($item['Data'][11]['VarCharValue']) ? $item['Data'][11]['VarCharValue'] : "",
                        'tax_rule' => isset($item['Data'][12]['VarCharValue']) ? $item['Data'][12]['VarCharValue']  : "",
                        'tax_amount' => isset($item['Data'][13]['VarCharValue']) ?number_format((float)$item['Data'][13]['VarCharValue'], 2, '.', '') . $currency  : "",
                        'item_subtotal' => isset($item['Data'][14]['VarCharValue']) ? number_format((float)$item['Data'][14]['VarCharValue'], 2, '.', '') . $currency : "",
                        'item_total' => isset($item['Data'][15]['VarCharValue']) ? number_format((float)$item['Data'][15]['VarCharValue'], 2, '.', '') . $currency  : "",
                        'invoice_subtotal' => isset($item['Data'][16]['VarCharValue']) ? number_format((float)$item['Data'][16]['VarCharValue'], 2, '.', '') . $currency : "",
                        'invoice_total' => isset($item['Data'][17]['VarCharValue']) ? number_format((float)$item['Data'][17]['VarCharValue'], 2, '.', '') . $currency : "",
                        'invoice_total_paid' => isset($item['Data'][18]['VarCharValue']) ? number_format((float)$item['Data'][18]['VarCharValue'], 2, '.', '') . $currency : "",
                        'invoice_status' => isset($item['Data'][19]['VarCharValue']) ? $item['Data'][19]['VarCharValue'] : "",
                        'invoice_date' => isset($item['Data'][20]['VarCharValue']) && $item['Data'][20]['VarCharValue'] > 0 ? date(ModuleModel::$company->getPhpDateFormat(), intval($item['Data'][20]['VarCharValue'])) : "",
                        'invoice_due_date' => isset($item['Data'][21]['VarCharValue']) && $item['Data'][21]['VarCharValue'] > 0 ? date(ModuleModel::$company->getPhpDateFormat(), intval($item['Data'][21]['VarCharValue'])) : "",
                        'biller_for' => isset($item['Data'][22]['VarCharValue']) ? $item['Data'][22]['VarCharValue'] : "",
                        'employee_name' => isset($item['Data'][23]['VarCharValue']) ? $item['Data'][23]['VarCharValue'] : "",
                        'order_number' => isset($item['Data'][24]['VarCharValue']) ? $item['Data'][24]['VarCharValue'] : "",
                        'biller_vat' => isset($item['Data'][25]['VarCharValue']) ? $item['Data'][25]['VarCharValue'] : "",
                        'biller_address' => isset($item['Data'][26]['VarCharValue']) ? $item['Data'][26]['VarCharValue'] : "",
                        'biller_town' => isset($item['Data'][27]['VarCharValue']) ? $item['Data'][27]['VarCharValue'] : "",
                        'country_name' => isset($item['Data'][28]['VarCharValue']) ? $item['Data'][28]['VarCharValue'] : "",
                        'biller_email' => isset($item['Data'][29]['VarCharValue']) ? $item['Data'][29]['VarCharValue'] : "",
                        'template_name' => isset($item['Data'][30]['VarCharValue']) ? $item['Data'][30]['VarCharValue'] : "",
                        'currency' => isset($item['Data'][31]['VarCharValue']) ? $item['Data'][31]['VarCharValue'] : "",
                        'timestamp' => isset($item['Data'][20]['VarCharValue']) ? intval($item['Data'][20]['VarCharValue']): "",
                    ];
                }
                $i++;
            }
            $result['data'] = $data;
            $result['completion_date_time'] = date(ModuleModel::$company->getPhpDateFormat(true), strtotime($result["queryExecution"]["Status"]["CompletionDateTime"]));
            $result['url'] = RelodayS3Helper::__getPresignedUrl("report/" . ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog) {
                if(isset($params['filter_config_id'])){
                    $aOptions = [
                        'filter_config_id' => $params['filter_config_id'],
                    ];
                    $aItem = FilterConfigItem::listJoinByCriteria($aOptions, false);
                    if($aItem['success']){
                        $params['items'] = $aItem['data'];
                    }
                }

                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_DSP_EXTRACT_INVOICE_ITEM);
                $report_log->setCompanyId(ModuleModel::$company->getId());
                $report_log->setExecutionId($executionId);
                $report_log->setParams(json_encode($params));
                $report_log->setUrl($result['url']);
                $etl_history =  EtlHistory::findFirst([
                    "conditions" => "status = :done_status:",
                    "bind" => [
                        "done_status" => EtlHistory::STATUS_DONE
                    ],
                    'order' => 'id DESC'
                ]);
                if($etl_history){
                    $report_log->setLastUpdate(date('Y-m-d H:i:s', strtotime($etl_history->getTimestamp())));
                }
                $create_report_log = $report_log->__quickCreate();
                $result["create_log"] = $create_report_log;
            } else {
                $result['completion_date_time'] = $report_log->getLastUpdate();
            }
        }

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }
}
