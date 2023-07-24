<?php

namespace SMXD\App\Controllers\API;

use \SMXD\App\Controllers\ModuleApiController;
use SMXD\App\Models\SupportedLanguage;
use SMXD\App\Models\Constant;
use SMXD\App\Models\ConstantTranslation;
use SMXD\Application\Lib\Helpers;

/**
 * Concrete implementation of App module controller
 *
 * @RoutePrefix("/app/api")
 */
class SalaryCalculatorController extends ModuleApiController
{
    /**
     * @Route("/lang", paths={module="app"}, methods={"GET"}, name="app-lang-index")
     */
    public function indexAction()
    {
        $this->view->disable();
        $this->checkAjaxPost();
        $data = Helpers::__getRequestValues();
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_VERBOSE, true);
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
//            echo( getenv("SALARY_CALCULATOR_ENPOINT"));
//            echo(getenv("SALARY_CALCULATOR_GATEWAY"));
            curl_setopt($ch, CURLOPT_URL, getenv("SALARY_CALCULATOR_ENPOINT"));
//        curl_setopt($ch, CURLOPT_URL, "https://vpce-0c3a462e70fcf04f6-z8lshbml.execute-api.ap-southeast-1.vpce.amazonaws.com/Prod/");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Host: ".getenv("SALARY_CALCULATOR_GATEWAY")
//            "Host: cza04py0r4.execute-api.ap-southeast-1.amazonaws.com"
            ]);

            curl_setopt($ch, CURLOPT_POST, count($data));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $response = curl_exec($ch);
            curl_close($ch);
//            echo "Status code ".$httpcode. " .... \r\n";
//            echo json_encode($input). " .... \r\n";
//            echo " .... \r\n";
//            echo "Reponse ". $response." .... \r\n";
//            $output = json_decode($response, true);
        } catch (\Exception $e) {
            var_dump($e);
            die(__METHOD__);
        }
        end_of_function:
        $this->response->setJsonContent($response);
        $this->response->send();

    }
}
