<?php

namespace Reloday\Gms\Controllers\API;

use mysql_xdevapi\Exception;
use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\ColorHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayS3Helper;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\Constant;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Relocation;
use Reloday\Gms\Models\RelocationServiceCompany;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ExtractDataServiceController extends BaseController
{
    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function executeServiceTopReportAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAcl('index', 'report');

        $params = [];
        $params['created_at_period'] = Helpers::__getRequestValue('created_at_period');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['hr_company_id'] = Helpers::__getRequestValue('hr_company_id');

        $result = RelocationServiceCompany::__executeServiceTopReport($params);
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @param $executionId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getServiceTopReportAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
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
            if(is_array($queryResult['data']) && count($queryResult['data']) > 0) {
                foreach ($queryResult['data'] as $item) {
                    if ($i > 0) {
                        $data[] = [
                            "data" => isset($item['Data'][2]['VarCharValue']) ? intval($item['Data'][2]['VarCharValue']) : "",
                            "label" => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : ""
                        ];
                    }
                    $i++;
                }
            }
            $result['data'] = array_values($data);
            $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function executeServiceTopDownloadReportAction(){
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAcl('index', 'report');

        $params = [];
        $params['created_at_period'] = Helpers::__getRequestValue('created_at_period');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['hr_company_id'] = Helpers::__getRequestValue('hr_company_id');

        $result = RelocationServiceCompany::__executeTopServiceDownloadReport($params);
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function getServiceTopDownloadReportAction($executionId){
        $this->view->disable();
        $this->checkAjaxPutPost();
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
                        "index" => $i,
                        "name" => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        "template_service" => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        "count" => isset($item['Data'][3]['VarCharValue']) ? $item['Data'][3]['VarCharValue'] : ""
                    ];
                }
                $i++;
            }

            $result['data'] = $data;
            $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function executeServicePerStatusReportAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAcl('index', 'report');

        $params = [];
        $params['created_at_period'] = Helpers::__getRequestValue('created_at_period');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['service_company_id'] = Helpers::__getRequestValue('service_company_id');

        $result = RelocationServiceCompany::__executeServicePerStatusReport($params);
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function executeServicePerStatusDownloadReportAction(){
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAcl('index', 'report');

        $params = [];
        $params['created_at_period'] = Helpers::__getRequestValue('created_at_period');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['service_company_id'] = Helpers::__getRequestValue('service_company_id');

        $result = RelocationServiceCompany::__executeServicePerStatusDownloadReport($params);
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    /**
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getDataDirectServicePerStatusReportAction(){
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAcl('index', 'report');

        $params = [];
        $params['created_at_period'] = Helpers::__getRequestValue('created_at_period');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['service_company_id'] = Helpers::__getRequestValue('service_company_id');

        $result = RelocationServiceCompany::__executeServicePerStatusReport($params, true);
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }


    /**
     * @param $executionId
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function getServicePerStatusReportAction($executionId)
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
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
                    $statusInt = isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : 0;
                    $statusInt = intval($statusInt);


                    $data[] = [
                        "data" => isset($item['Data'][1]['VarCharValue']) ? intval($item['Data'][1]['VarCharValue']) : "",
                        "label" => ConstantHelper::__translate(RelocationServiceCompany::$status_label_list[$statusInt], ModuleModel::$language),
                        "color" => $statusInt == 0 ? ColorHelper::YELLOW : ( $statusInt == 1 ? ColorHelper::BRIGHT_BLUE : ($statusInt == 3 ? ColorHelper::GREEN : ColorHelper::GRAY))
                    ];
                }
                $i++;
            }
            $result['data'] = array_values($data);
            $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function executeServicePerAccountDownloadReportAction(){
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAcl('index', 'report');

        $params = [];
        $params['created_at_period'] = Helpers::__getRequestValue('created_at_period');
        $params['limit'] = Helpers::__getRequestValue('limit');
        $params['hr_company_id'] = Helpers::__getRequestValue('hr_company_id');

        $result = RelocationServiceCompany::__executeServicePerAccountDownloadReport($params);
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }

    public function getServicePerAccountDownloadReportAction($executionId){
        $this->view->disable();
        $this->checkAjaxPutPost();
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

                    if(isset($item['Data'][15]['VarCharValue'])){
                        $dateArray = explode(',',$item['Data'][15]['VarCharValue']);
                        $start_date = isset($dateArray[0]) ? $dateArray[0] : "";
                        $end_date = isset($dateArray[1]) ? $dateArray[1] : "";
                        $authorise_date = isset($dateArray[2]) ? $dateArray[2] : "";
                        $completion_date = isset($dateArray[4]) ? $dateArray[4] : "";
                        $expiry_date = isset($dateArray[3]) ? $dateArray[3] : "";
                    }else{
                        $start_date = "";
                        $end_date =  "";
                        $authorise_date =  "";
                        $completion_date =  "";
                        $expiry_date =  "";
                    }

                    $data[] = [
                        "service_name" => isset($item['Data'][0]['VarCharValue']) ? ($item['Data'][0]['VarCharValue']) : "",
                        "service_template" => isset($item['Data'][1]['VarCharValue']) ? ($item['Data'][1]['VarCharValue']) : "",
                        "service_id" => isset($item['Data'][2]['VarCharValue']) ? ($item['Data'][2]['VarCharValue']) : "",
                        "firstname" => isset($item['Data'][3]['VarCharValue']) ? ($item['Data'][3]['VarCharValue']) : "",
                        "lastname" => isset($item['Data'][4]['VarCharValue']) ? ($item['Data'][4]['VarCharValue']) : "",
                        "status" => isset($item['Data'][5]['VarCharValue']) ? ($item['Data'][5]['VarCharValue']) : "",
                        "progress" => isset($item['Data'][6]['VarCharValue']) ? ($item['Data'][6]['VarCharValue']). "%" : "",
                        "archived" => isset($item['Data'][7]['VarCharValue']) ? ($item['Data'][7]['VarCharValue']) : "",
                        "asmt_id" => isset($item['Data'][8]['VarCharValue']) ? ($item['Data'][8]['VarCharValue']) : "",
                        "relo_id" => isset($item['Data'][9]['VarCharValue']) ? ($item['Data'][9]['VarCharValue']) : "",
                        "account" => isset($item['Data'][10]['VarCharValue']) ? ($item['Data'][10]['VarCharValue']) : "",
                        "booker" => isset($item['Data'][11]['VarCharValue']) ? ($item['Data'][11]['VarCharValue']) : "",
                        "ee_interal_id" => isset($item['Data'][12]['VarCharValue']) ? ($item['Data'][12]['VarCharValue']) : "",
                        "workemail" => isset($item['Data'][13]['VarCharValue']) ? ($item['Data'][13]['VarCharValue']) : "",
                        "launched_on" => isset($item['Data'][14]['VarCharValue']) ? ($item['Data'][14]['VarCharValue']) : "",
                        "start_date" => isset($start_date) && $start_date != '' ? date('Y-m-d', $start_date)  : "",
                        "end_date" => isset($end_date) && $end_date != '' ? date('Y-m-d', $end_date) : "",
                        "authorise_date" => isset($authorise_date) && $authorise_date != '' ? date('Y-m-d', $authorise_date) : "",
                        "completion_date" => isset($completion_date) && $completion_date != '' ? date('Y-m-d', $completion_date) : "",
                        "expiry_date" => isset($expiry_date) && $expiry_date != '' ? date('Y-m-d', $expiry_date) : "",
                        "dsp_reporter_name" => isset($item['Data'][16]['VarCharValue']) ? ($item['Data'][16]['VarCharValue']) : "",
                        "dsp_owner_name" => isset($item['Data'][17]['VarCharValue']) ? ($item['Data'][17]['VarCharValue']) : "",
                        "dsp_viewer_name" => isset($item['Data'][18]['VarCharValue']) ? ($item['Data'][18]['VarCharValue']) : "",
                    ];
                }
                $i++;
            }
            $result['data'] = array_values($data);
            $result['url'] = RelodayS3Helper::__getPresignedUrl(ModuleModel::$company->getUuid() . "/" . $executionId . '.csv', "", $executionId . '.csv', "text/csv");
        }
        end_of_function:
        $this->response->setJsonContent($result);
        return $this->response->send();
    }
}
