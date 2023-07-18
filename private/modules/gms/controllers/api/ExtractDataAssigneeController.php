<?php

namespace Reloday\Gms\Controllers\API;

use Phalcon\Security\Random;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\AttributeHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayS3Helper;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Dependant;
use Reloday\Gms\Models\Employee;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\ReportLog;
use Reloday\Gms\Models\EtlHistory;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ExtractDataAssigneeController extends BaseController
{
    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function executeActiveInactiveAssigneeAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $params['created_at'] = Helpers::__getRequestValue('created_at');
        $params['created_at_period'] = Helpers::__getRequestValue('created_at_period');
        $this->checkAcl('index', 'report');
        $result = Employee::__executeActiveInactiveAssigneeReport($params);
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @param $executionId
     * @throws \Phalcon\Security\Exception
     */
    public function getActiveInactiveAssigneeAction($executionId)
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
            $i = 0;
            foreach ($queryResult['data'] as $item) {
                if ($i > 0) {
                    $data[] = [
                        "data" => isset($item['Data'][0]['VarCharValue']) ? intval($item['Data'][0]['VarCharValue']) : "",
                        "label" => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : ""
                    ];
                }
                $i++;
            }

            $result['data'] = $data;
            $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog) {
                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_GMS_ACTIVE_INACTIVE_ASSIGNEE);
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
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }


    /**
     *
     */
    public function executeVisaExpiringAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAcl('index', 'report');

        $params = [];
        $params['visa_expire_date'] = Helpers::__getRequestValue('visa_expire_date');
        $params['visa_expire_period'] = Helpers::__getRequestValue('visa_expire_period');
        $params['to_date'] = Helpers::__getRequestValue('to_date');
        $params['from_date'] = Helpers::__getRequestValue('from_date');
        $result = Employee::__executeVisaExpiringReport($params);
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @param $executionId
     * @throws \Phalcon\Security\Exception
     */
    public function getVisaExpiringAction($executionId)
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
            $i = 0;

            foreach ($queryResult['data'] as $item) {
                if ($i > 0 && isset($item['Data'][0]['VarCharValue'])) {
                    $data[] = [
                        "uuid" => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        "expiry_date" => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        "full_name" => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        "label" => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : "",
                        "assignment_uuid" => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        "assignment_number" => isset($item['Data'][5]['VarCharValue']) ? $item['Data'][5]['VarCharValue'] : "",
                    ];
                }
                $i++;
            }

            $result['data'] = $data;
            $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
        }
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     *
     */
    public function executeVisaExpiringReportAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $end_date = Helpers::__getRequestValue("end_date");

        $is_download_report = Helpers::__getRequestValue("is_download_report");
        $visa_expire_date = Helpers::__getRequestValue('visa_expire_date');
        $visa_expire_period = Helpers::__getRequestValue('visa_expire_period');
        $to_date = Helpers::__getRequestValue('to_date');
        $from_date = Helpers::__getRequestValue('from_date');

        $params = [];
        if (intval($end_date) > 0) {
            $params["end_date"] = date('Y-m-d', $end_date / 1000);
        }

        if(isset($visa_expire_date)){
            $params['visa_expire_date'] = $visa_expire_date;
        }

        if(isset($visa_expire_period) && is_string($visa_expire_period)){
            $params['visa_expire_period'] = $visa_expire_period;
        }

        if(isset($to_date) && intval($to_date) > 0){
            $params['to_date'] = $to_date;
        }

        if(isset($from_date) && intval($from_date) > 0){
            $params['from_date'] = $from_date;
        }

        if(isset($is_download_report) && $is_download_report === true){
            goto goto_filter;
        }

        $etl_history = EtlHistory::findFirst([
            'order' => 'id DESC'
        ]);
        if($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_IN_PROGRESS){
            $old_report_log = ReportLog::__checkLastResult(ModuleModel::$company->getId(), ReportLog::TYPE_GMS_VISA_EXPIRING_REPORT, $params);
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
            $log = ReportLog::__checkResultAfterTimestamp(ModuleModel::$company->getId(), ReportLog::TYPE_GMS_VISA_EXPIRING_REPORT, $params, $etl_history->getTimestamp());
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
        goto_filter:
        $result = Employee::__executeVisaExpiringToDownloadReport($params);
        $result["end_date"] = date('Y-m-d', $end_date / 1000);
//        $result = Assignment::executeReport();
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @param $executionId
     * @throws \Phalcon\Security\Exception
     */
    public function getVisaExpiringReportAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $this->checkAcl('index', 'report');
        $end_date = Helpers::__getRequestValue("end_date");
        $params = [];
        if (intval($end_date) > 0) {
            $params["end_date"] = date('Y-m-d', $end_date / 1000);
        }
        $result = AthenaHelper::getExecutionInfo($executionId);
        if ($result['success'] && $result['status'] == 'SUCCEEDED') {
            $queryResult = AthenaHelper::getQueryResult($executionId);
            if (!$queryResult['success']) {
                $result['result'] = $queryResult;
                goto end_of_function;
            }

            $data = [];
            $i = 0;

            foreach ($queryResult['data'] as $item) {
                if ($i > 0 && isset($item['Data'][0]['VarCharValue'])) {
                    $data[$i] = [
                        "firstname" => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        "lastname" => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        "type" => isset($item['Data'][2]['VarCharValue']) ? ConstantHelper::__translate($item['Data'][2]['VarCharValue'], ModuleModel::$language)  : "",
                        "dependant_firstname" => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : "",
                        "dependant_lastname" => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        "dependant_relation" => isset($item['Data'][5]['VarCharValue']) ? ( $item['Data'][5]['VarCharValue'] == Dependant::DEPENDANT_SPOUSE ? ConstantHelper::__translate('SPOUSE_TEXT', ModuleModel::$language) : ($item['Data'][5]['VarCharValue'] == Dependant::DEPENDANT_CHILD ? ConstantHelper::__translate('CHILD_TEXT', ModuleModel::$language) : ($item['Data'][5]['VarCharValue'] == Dependant::DEPENDANT_COMMON_LAW_PARTNER ? ConstantHelper::__translate('COMMON_LAW_PARTNER_TEXT', ModuleModel::$language) : ($item['Data'][5]['VarCharValue'] == Dependant::DEPENDANT_OTHER ? ConstantHelper::__translate('OTHER_TEXT', ModuleModel::$language) : "" )))) : "",
                        "company_name" => isset($item['Data'][6]['VarCharValue']) ? $item['Data'][6]['VarCharValue'] : "",
                        "expiry_date" => isset($item['Data'][7]['VarCharValue']) ? date('d/m/Y', $item['Data'][7]['VarCharValue']) : "",
                        "delivery_date" => isset($item['Data'][8]['VarCharValue']) ? date('d/m/Y', $item['Data'][8]['VarCharValue']) : "",
                        "estimated_approval_date" => isset($item['Data'][9]['VarCharValue']) ? date('d/m/Y', $item['Data'][9]['VarCharValue']) : "",
                        "approval_date" => isset($item['Data'][10]['VarCharValue']) ? date('d/m/Y', $item['Data'][10]['VarCharValue'] ) : "",
                        "issue_by" => isset($item['Data'][11]['VarCharValue']) ? $item['Data'][11]['VarCharValue'] : "",
                        "ee_number" => isset($item['Data'][12]['VarCharValue']) ? $item['Data'][12]['VarCharValue'] : "",
                        "ee_reference" => isset($item['Data'][13]['VarCharValue']) ? $item['Data'][13]['VarCharValue'] : "",
                        "workemail" => isset($item['Data'][14]['VarCharValue']) ? $item['Data'][14]['VarCharValue'] : "",
                        "asmt_reference" => isset($item['Data'][15]['VarCharValue']) ? $item['Data'][15]['VarCharValue'] : "",
                        "relo_identify" => isset($item['Data'][16]['VarCharValue']) ? $item['Data'][16]['VarCharValue'] : "",
                        "booker_name" => isset($item['Data'][17]['VarCharValue']) ? $item['Data'][17]['VarCharValue'] : "",
                        "document_name" => isset($item['Data'][18]['VarCharValue']) ? $item['Data'][18]['VarCharValue'] : "",
                        "document_number" => isset($item['Data'][19]['VarCharValue']) ? $item['Data'][19]['VarCharValue'] : "",
                    ];
                }
                $i++;
            }
            $result['data'] = $data;
            $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog) {
                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_GMS_VISA_EXPIRING_REPORT);
                $report_log->setCompanyId(ModuleModel::$company->getId());
                $report_log->setExecutionId($executionId);
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
                $report_log->setParams(json_encode($params));
                $create_report_log = $report_log->__quickCreate();
                $result["create_log"] = $create_report_log;
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     *
     */
    public function executePassportExpiringAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');

        $options = [];
        $passport_expire_period = Helpers::__getRequestValue('passport_expire_period');
        if($passport_expire_period == "") {
            $options = [
                'from_date' => Helpers::__getRequestValue('from_date'),
                'to_date' => Helpers::__getRequestValue('to_date')
            ];
        }


        $result = Employee::executePassportExpiringReportV2($passport_expire_period, $options);
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @param $executionId
     * @throws \Phalcon\Security\Exception
     */
    public function getPassportExpiringAction($executionId)
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
            $i = 0;
            foreach ($queryResult['data'] as $item) {
                if ($i > 0 && isset($item['Data'][0]['VarCharValue'])) {

                    $data[$i] = [
                        "uuid" => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        "name" => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        "type" => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        "passport_expiry_date" => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : "",
                        "assignment_number" => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        "assignment_uuid" => isset($item['Data'][5]['VarCharValue']) ? $item['Data'][5]['VarCharValue'] : "",
                        "relocation_uuid" => isset($item['Data'][6]['VarCharValue']) ? $item['Data'][6]['VarCharValue'] : "",
                        "relocation_name" => isset($item['Data'][7]['VarCharValue']) ? $item['Data'][7]['VarCharValue'] : "",
                    ];

//                    $data[intval($item['Data'][0]['VarCharValue'])][] = [
//                        "uuid" => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
//                        "name" => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
//                        "assignment_number" => isset($item['Data'][5]['VarCharValue']) ? $item['Data'][5]['VarCharValue'] : "",
//                        "assignment_uuid" => isset($item['Data'][6]['VarCharValue']) ? $item['Data'][6]['VarCharValue'] : "",
//                        "type" => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : "",
//                        "passport_expiry_date" => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
//                    ];
                }
                $i++;
            }

            unset($data[0]);
            $result['data'] = array_values($data);

            $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog) {
                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_GMS_PASSPORT_EXPIRING);
                $report_log->setCompanyId(ModuleModel::$company->getId());
                $report_log->setExecutionId($executionId);
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
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function executeHrContactReportAction(){
        $this->view->disable();
        $this->checkAjaxPost();

        $end_date = Helpers::__getRequestValue("end_date");

        $params = [];
        if (intval($end_date) > 0) {
            $params["end_date"] = date('Y-m-d', $end_date / 1000);
        }

        $etl_history = EtlHistory::findFirst([
            'order' => 'id DESC'
        ]);
        if($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_IN_PROGRESS){
            $old_report_log = ReportLog::__checkLastResult(ModuleModel::$company->getId(), ReportLog::TYPE_GMS_VISA_EXPIRING_REPORT, $params);
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
            $log = ReportLog::__checkResultAfterTimestamp(ModuleModel::$company->getId(), ReportLog::TYPE_GMS_VISA_EXPIRING_REPORT, $params, $etl_history->getTimestamp());
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
        $result = Employee::__executeHrContactToDownloadReport($params);

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();

    }

    public function getHrContactReportAction($executionId){
        $this->view->disable();
        $this->checkAjaxPost();

        $result = AthenaHelper::getExecutionInfo($executionId);
        if ($result['success'] && $result['status'] == 'SUCCEEDED') {
            $queryResult = AthenaHelper::getQueryResult($executionId);
            if (!$queryResult['success']) {
                $result['result'] = $queryResult;
                goto end_of_function;
            }

            $data = [];
            $i = 0;
            foreach ($queryResult['data'] as $item) {
                if ($i > 0 && isset($item['Data'][0]['VarCharValue'])) {

                    $data[$i] = [
                        "id" => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        "comapnay_id" => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        "email" => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        "firstname" => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : "",
                        "lastname" => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        "telephone" => isset($item['Data'][5]['VarCharValue']) ? $item['Data'][5]['VarCharValue'] : "",
                        "mobile" => isset($item['Data'][6]['VarCharValue']) ? $item['Data'][6]['VarCharValue'] : "",
                        "job_title" => isset($item['Data'][7]['VarCharValue']) ? $item['Data'][7]['VarCharValue'] : "",
                        "organisations" => isset($item['Data'][8]['VarCharValue']) ? $item['Data'][8]['VarCharValue'] : "",
                    ];
                }
                $i++;
            }

            unset($data[0]);
            $result['data'] = array_values($data);

            $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog) {
                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_DSP_EXTRACT_HR_CONTACT);
                $report_log->setCompanyId(ModuleModel::$company->getId());
                $report_log->setExecutionId($executionId);
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
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     *
     */
    public function executePassportExpiringReportAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $end_date = Helpers::__getRequestValue("end_date");
        $params = [];
        if (intval($end_date) > 0) {
            $params["end_date"] = date('Y-m-d', $end_date / 1000);
        }

        $is_download_report = Helpers::__getRequestValue("is_download_report");
        $passport_expire_date = Helpers::__getRequestValue('passport_expire_date');
        $passport_expire_period = Helpers::__getRequestValue('passport_expire_period');
        $to_date = Helpers::__getRequestValue('to_date');
        $from_date = Helpers::__getRequestValue('from_date');

        if(isset($passport_expire_date)){
            $params['passport_expire_date'] = $passport_expire_date;
        }

        if(isset($passport_expire_period) && is_string($passport_expire_period)){
            $params['passport_expire_period'] = $passport_expire_period;
        }

        if(isset($to_date) && intval($to_date) > 0){
            $params['to_date'] = $to_date;
        }

        if(isset($from_date) && intval($from_date) > 0){
            $params['from_date'] = $from_date;
        }

        if(isset($is_download_report) && $is_download_report === true){
            goto goto_filter;
        }

        $etl_history = EtlHistory::findFirst([
            'order' => 'id DESC'
        ]);
        if($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_IN_PROGRESS){
            $old_report_log = ReportLog::__checkLastResult(ModuleModel::$company->getId(), ReportLog::TYPE_GMS_VISA_EXPIRING_REPORT, $params);
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
            $log = ReportLog::__checkResultAfterTimestamp(ModuleModel::$company->getId(), ReportLog::TYPE_GMS_VISA_EXPIRING_REPORT, $params, $etl_history->getTimestamp());
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
        goto_filter:
        $result = Employee::__executePassportExpiringToDownloadReport($params);
        $result["end_date"] = date('Y-m-d', $end_date / 1000);
//        $result = Assignment::executeReport();
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    /**
     * @param $executionId
     * @throws \Phalcon\Security\Exception
     */
    public function getPassportExpiringReportAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $this->checkAcl('index', 'report');
        $end_date = Helpers::__getRequestValue("end_date");
        $params = [];
        if (intval($end_date) > 0) {
            $params["end_date"] = date('Y-m-d', $end_date / 1000);
        }
        $result = AthenaHelper::getExecutionInfo($executionId);
        if ($result['success'] && $result['status'] == 'SUCCEEDED') {

            $queryResult = AthenaHelper::getQueryResult($executionId);
            if (!$queryResult['success']) {
                $result['result'] = $queryResult;
                goto end_of_function;
            }

            $data = [];
            $i = 0;

            $companyDateFormat = ModuleModel::$company->getDateFormat() ?: 'DD/MM/YYYY';
            $phpDateFormat = Helpers::__convertCompanyDateFormatToPhpDateFormat($companyDateFormat);

            foreach ($queryResult['data'] as $item) {
                if ($i > 0 && isset($item['Data'][0]['VarCharValue'])) {
                    $data[$i] = [
                        "firstname" => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        "lastname" => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        "type" => isset($item['Data'][2]['VarCharValue']) ? ConstantHelper::__translate($item['Data'][2]['VarCharValue'], ModuleModel::$language)  : "",
                        "dependant_firstname" => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : "",
                        "dependant_lastname" => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        "dependant_relation" => isset($item['Data'][5]['VarCharValue']) ? ( $item['Data'][5]['VarCharValue'] == Dependant::DEPENDANT_SPOUSE ? ConstantHelper::__translate('SPOUSE_TEXT', ModuleModel::$language) : ($item['Data'][5]['VarCharValue'] == Dependant::DEPENDANT_CHILD ? ConstantHelper::__translate('CHILD_TEXT', ModuleModel::$language) : ($item['Data'][5]['VarCharValue'] == Dependant::DEPENDANT_COMMON_LAW_PARTNER ? ConstantHelper::__translate('COMMON_LAW_PARTNER_TEXT', ModuleModel::$language) : ($item['Data'][5]['VarCharValue'] == Dependant::DEPENDANT_OTHER ? ConstantHelper::__translate('OTHER_TEXT', ModuleModel::$language) : "" )))) : "",
                        "company_name" => isset($item['Data'][6]['VarCharValue']) ? $item['Data'][6]['VarCharValue'] : "",
                        "document_name" => isset($item['Data'][7]['VarCharValue']) ? $item['Data'][7]['VarCharValue'] : "",
                        "document_number" => isset($item['Data'][8]['VarCharValue']) ? $item['Data'][8]['VarCharValue'] : "",
                        "expiry_date" => isset($item['Data'][9]['VarCharValue']) ? date($phpDateFormat, $item['Data'][9]['VarCharValue']) : "",
                        "delivery_date" => isset($item['Data'][10]['VarCharValue']) ? date($phpDateFormat, $item['Data'][10]['VarCharValue']) : "",
                        "estimated_approval_date" => isset($item['Data'][11]['VarCharValue']) ? date('d/m/Y', $item['Data'][11]['VarCharValue']) : "",
                        "approval_date" => isset($item['Data'][12]['VarCharValue']) ? date($phpDateFormat, $item['Data'][12]['VarCharValue'] ) : "",
                        "issue_by" => isset($item['Data'][13]['VarCharValue']) ? $item['Data'][13]['VarCharValue'] : "",
                        "ee_number" => isset($item['Data'][14]['VarCharValue']) ? $item['Data'][14]['VarCharValue'] : "",
                        "ee_reference" => isset($item['Data'][15]['VarCharValue']) ? $item['Data'][15]['VarCharValue'] : "",
                        "workemail" => isset($item['Data'][16]['VarCharValue']) ? $item['Data'][16]['VarCharValue'] : "",
                        "asmt_reference" => isset($item['Data'][17]['VarCharValue']) ? $item['Data'][17]['VarCharValue'] : "",
                        "relo_identify" => isset($item['Data'][18]['VarCharValue']) ? $item['Data'][18]['VarCharValue'] : "",
                        "booker_name" => isset($item['Data'][19]['VarCharValue']) ? $item['Data'][19]['VarCharValue'] : "",
                    ];
                }
                $i++;
            }
            $result['data'] = $data;
            $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
            $report_log = ReportLog::findFirstByExecutionId($executionId);
            if (!$report_log instanceof ReportLog) {
                $report_log = new ReportLog();
                $random = new Random();
                $report_log->setUuid($random->uuid());
                $report_log->setType(ReportLog::TYPE_GMS_PASSPORT_EXPIRING_REPORT);
                $report_log->setCompanyId(ModuleModel::$company->getId());
                $report_log->setExecutionId($executionId);
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
                $report_log->setParams(json_encode($params));
                $create_report_log = $report_log->__quickCreate();
                $result["create_log"] = $create_report_log;
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function executeAssigneeReportAction(){
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
//        $end_date = Helpers::__getRequestValue("end_date");
//        $created_at = Helpers::__getRequestValue("created_at");
//        $condition = Helpers::__getRequestValue("condition");

        $created_at_period = Helpers::__getRequestValue("created_at_period");
        $is_download_report = Helpers::__getRequestValue("is_download_report");

        $params = [];
        if(isset($created_at_period) && $created_at_period != '' && is_string($created_at_period)){
            $params['created_at_period'] = $created_at_period;
        }

        if(isset($is_download_report) && is_bool($is_download_report) && $is_download_report == true){
            $params['is_download_report'] = $is_download_report;
        }

        $etl_history = EtlHistory::findFirst([
            'order' => 'id DESC'
        ]);
        if($etl_history && $etl_history->getStatus() == EtlHistory::STATUS_IN_PROGRESS){
            $old_report_log = ReportLog::__checkLastResult(ModuleModel::$company->getId(), ReportLog::TYPE_GMS_ASSIGNEE_REPORT, []);
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
            $log = ReportLog::__checkResultAfterTimestamp(ModuleModel::$company->getId(), ReportLog::TYPE_GMS_ASSIGNEE_REPORT, [], $etl_history->getTimestamp());
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
        $result = Employee::__executeReport($params);
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function getAssigneeReportAction($executionId){
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');
        $is_download_report = Helpers::__getRequestValue("is_download_report");
        $created_at_period = Helpers::__getRequestValue("created_at_period");

        $condition = [
            'is_download_report' => $is_download_report,
            'created_at_period' => $created_at_period
        ];
        $result = AthenaHelper::getExecutionInfo($executionId);

        if ($result['success'] && $result['status'] == 'SUCCEEDED') {
            $queryResult = AthenaHelper::getQueryResult($executionId);
            if (!$queryResult['success']) {
                $result['result'] = $queryResult;
                goto end_of_function;
            }

            $data = [];
            $i = 0;
            foreach ($queryResult['data'] as $item) {
                if ($i > 0 && isset($item['Data'][1]['VarCharValue'])) {
                    $data[] = [
                        'id' => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        'identify' => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        'firstname' => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        'lastname' => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : "",
                        'place_of_birth' => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        'country_of_birth' => isset($item['Data'][5]['VarCharValue']) ? $item['Data'][5]['VarCharValue'] : "",
                        'birth_date' => isset($item['Data'][7]['VarCharValue']) ? $item['Data'][7]['VarCharValue'] : "",
                        'citizenship' => isset($item['Data'][8]['VarCharValue']) ? $item['Data'][8]['VarCharValue'] : "",
                        'spoken_language' => isset($item['Data'][9]['VarCharValue']) ? Employee::__convertLanguage($item['Data'][9]['VarCharValue']) : "",
                        'school_grade' => isset($item['Data'][10]['VarCharValue']) ? $item['Data'][10]['VarCharValue'] : "",
                        'marital_status' => isset($item['Data'][11]['VarCharValue']) ? AttributeHelper::__translate($item['Data'][11]['VarCharValue'], ModuleModel::$language) : "",
                        'workemail' => isset($item['Data'][12]['VarCharValue']) ? $item['Data'][12]['VarCharValue'] : "",
                        'privateemail' => isset($item['Data'][13]['VarCharValue']) ? $item['Data'][13]['VarCharValue'] : "",
                        'company' => isset($item['Data'][19]['VarCharValue']) ? $item['Data'][19]['VarCharValue'] : "",
                        'office' => isset($item['Data'][20]['VarCharValue']) ? $item['Data'][20]['VarCharValue'] : "",
                        'department' => isset($item['Data'][21]['VarCharValue']) ? $item['Data'][21]['VarCharValue'] : "",
                        'team' => isset($item['Data'][22]['VarCharValue']) ? $item['Data'][22]['VarCharValue'] : "",
                        'job_title' => isset($item['Data'][23]['VarCharValue']) ? $item['Data'][23]['VarCharValue'] : "",
                        'active' => isset($item['Data'][25]['VarCharValue']) && filter_var($item['Data'][25]['VarCharValue'], FILTER_VALIDATE_BOOLEAN) == true ? ConstantHelper::__translate('YES_TEXT', ModuleModel::$language) : ConstantHelper::__translate('NO_TEXT', ModuleModel::$language),
                        'assignee_reference' => isset($item['Data'][60]['VarCharValue']) ? $item['Data'][60]['VarCharValue'] : "",
                        'gender' => isset($item['Data'][61]['VarCharValue']) ? ($item['Data'][61]['VarCharValue'] == 1 ? ConstantHelper::__translate('MASCULIN_TEXT', ModuleModel::$language) : ($item['Data'][61]['VarCharValue'] == 0 ? ConstantHelper::__translate('FEMININ_TEXT', ModuleModel::$language) : ($item['Data'][61]['VarCharValue'] == -1 ? ConstantHelper::__translate('OTHER_TEXT', ModuleModel::$language) : ConstantHelper::__translate('NOT_SET_TEXT', ModuleModel::$language)))) : ConstantHelper::__translate('NOT_SET_TEXT', ModuleModel::$language),
                        'lastconnect_at' => isset($item['Data'][62]['VarCharValue']) ? $item['Data'][62]['VarCharValue'] : "",
                        'firstconnect_at' => isset($item['Data'][63]['VarCharValue']) ? $item['Data'][63]['VarCharValue'] : "",
                        'ee_create_date' => isset($item['Data'][59]['VarCharValue']) ? $item['Data'][59]['VarCharValue'] : "",
                    ];
                }
                $i++;
            }

            $result['data'] = $data;
            $result['completion_date_time'] = date(ModuleModel::$company->getPhpDateFormat(true), strtotime($result["queryExecution"]["Status"]["CompletionDateTime"]));
            $result['url'] = RelodayS3Helper::__getPresignedUrl("report/" . ModuleModel::$company->getUuid() . "/" . $executionId . '.xlsx', "", $executionId . '.xlsx', "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            if (!$result['url']) {
                $result['success'] = false;
            } else {
                $report_log = ReportLog::findFirstByExecutionId($executionId);
                if (!$report_log instanceof ReportLog) {
                    $report_log = new ReportLog();
                    $random = new Random();
                    $report_log->setUuid($random->uuid());
                    $report_log->setType(ReportLog::TYPE_GMS_ASSIGNEE_REPORT);
                    $report_log->setCompanyId(ModuleModel::$company->getId());
                    $report_log->setExecutionId($executionId);
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
                    $report_log->setParams(json_encode(["condition" => $condition]));
                    $create_report_log = $report_log->__quickCreate();
                    $result["create_log"] = $create_report_log;
                } else {
                    $result['completion_date_time'] = $report_log->getLastUpdate();
                }
            }
        }
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }
}
