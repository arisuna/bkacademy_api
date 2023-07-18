<?php

namespace Reloday\Gms\Controllers\API;

use Reloday\Application\Lib\AthenaHelper;
use Reloday\Application\Lib\AttributeHelper;
use Reloday\Application\Lib\ConstantHelper;
use Reloday\Application\Lib\Helpers;
use Reloday\Application\Lib\RelodayS3Helper;
use \Reloday\Gms\Controllers\ModuleApiController;
use Reloday\Gms\Models\ModuleModel;
use Reloday\Gms\Models\Relocation;

/**
 * Concrete implementation of Gms module controller
 *
 * @RoutePrefix("/gms/api")
 */
class ExtractDataRelocationController extends BaseController
{
    /**
     *
     */
    public function executeRelocationPerMonthReportAction()
    {
        $this->view->disable();
        $this->checkAjaxPutPost();
        $this->checkAcl('index', 'report');

        $params = [];
        $params['year'] = Helpers::__getRequestValue('year');
        $result = Relocation::__executeRelocationPerMonthReport($params);
        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }


    /**
     *
     */
    public function getRelocationPerMonthReportAction($executionId)
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

            for ($cpt = 1; $cpt <= 12; $cpt++) {
                $data[$cpt] = [Helpers::__getMonthName($cpt), 0];
            }
            $i = 0;
            foreach ($queryResult['data'] as $item) {
                if ($i > 0 && isset($item['Data'][0]['VarCharValue'])) {
                    //$currentTime = isset($item['Data'][0]['VarCharValue']) && isset($item['Data'][1]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] . "/" . $item['Data'][1]['VarCharValue'] : "";
                    $data[$item['Data'][0]['VarCharValue']] = [
                        Helpers::__getMonthName($item['Data'][0]['VarCharValue']), isset($item['Data'][2]['VarCharValue']) ? intval($item['Data'][2]['VarCharValue']) : 0,
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

    public function executeRelocationReportDownloadAction(){
        $this->view->disable();
        $this->checkAjaxPost();
        $this->checkAcl('index', 'report');

        $is_download_report = Helpers::__getRequestValue('is_download_report');
        $year = Helpers::__getRequestValue('year');

        $params = [
            'is_download_report' => $is_download_report,
            'year' => $year
        ];
        $result = Relocation::__executeReport($params);

        end_of_function:
        $this->response->setJsonContent($result);
        $this->response->send();
    }

    public function getRelocationReportDownloadAction($executionId){
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
                if ($i > 0 && isset($item['Data'][1]['VarCharValue'])) {
                    $data[] = [
                        'relocation_identify' => isset($item['Data'][0]['VarCharValue']) ? $item['Data'][0]['VarCharValue'] : "",
                        'assignee_name' => isset($item['Data'][1]['VarCharValue']) ? $item['Data'][1]['VarCharValue'] : "",
                        'assignee_email' => isset($item['Data'][2]['VarCharValue']) ? $item['Data'][2]['VarCharValue'] : "",
                        'assignment_reference' => isset($item['Data'][4]['VarCharValue']) ? $item['Data'][4]['VarCharValue'] : "",
                        'start_date' => isset($item['Data'][6]['VarCharValue']) ? str_replace(' 00:00:00.000', '', $item['Data'][6]['VarCharValue']) : "",
                        'end_date' => isset($item['Data'][7]['VarCharValue']) ? str_replace(' 00:00:00.000', '', $item['Data'][7]['VarCharValue']) : "",
                        'hr_company_name' => isset($item['Data'][8]['VarCharValue']) ? $item['Data'][8]['VarCharValue'] : "",
                        'booker_company_name' => isset($item['Data'][9]['VarCharValue']) ? $item['Data'][9]['VarCharValue'] : "",
                        'dsp_reporter_name' => isset($item['Data'][10]['VarCharValue']) ? $item['Data'][10]['VarCharValue'] : "",
                        'dsp_owner_name' => isset($item['Data'][11]['VarCharValue']) ? $item['Data'][11]['VarCharValue'] : "",
                        'dsp_viewer_name' => isset($item['Data'][12]['VarCharValue']) ? str_replace('"', '', $item['Data'][12]['VarCharValue'])  : "",
                        'status' => isset($item['Data'][13]['VarCharValue']) ? $item['Data'][13]['VarCharValue'] : "",
                        'services_name' => isset($item['Data'][26]['VarCharValue']) ? $item['Data'][26]['VarCharValue'] : "",
                        'relocation_created_date' => isset($item['Data'][16]['VarCharValue']) ? $item['Data'][16]['VarCharValue'] : "",
                        'archived' => isset($item['Data'][20]['VarCharValue']) ? $item['Data'][20]['VarCharValue'] : "",
                        'ee_reference' => isset($item['Data'][21]['VarCharValue']) ? $item['Data'][21]['VarCharValue'] : "",
                        'ee_firstname' => isset($item['Data'][22]['VarCharValue']) ? $item['Data'][22]['VarCharValue'] : "",
                        'ee_lastname' => isset($item['Data'][23]['VarCharValue']) ? $item['Data'][23]['VarCharValue'] : "",
                        'initiated_on' => isset($item['Data'][24]['VarCharValue']) ? $item['Data'][24]['VarCharValue'] : "",
                        'ee_number' => isset($item['Data'][25]['VarCharValue']) ? $item['Data'][25]['VarCharValue'] : "",
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
}
