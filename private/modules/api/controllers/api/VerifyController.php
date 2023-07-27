<?php
/**
 * Created by PhpStorm.
 * User: macbook
 * Date: 11/12/20
 * Time: 5:10 PM
 */

namespace SMXD\Api\Controllers\API;


use SMXD\Api\Controllers\ModuleApiController;
use SMXD\Api\Models\ModuleModel;
use SMXD\Api\Models\User;
use SMXD\Application\Lib\Helpers;
use SMXD\Application\Models\EmployeeExt;
use SMXD\Hr\Models\Employee;

class VerifyController extends ModuleApiController
{
    public function confirmUserAction(){
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Max-Age: 1000');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Allow-Headers: Token');

        $return = ['success' => false, 'message' => 'VERIFY_ERROR_TEXT'];
        $code = Helpers::__getQuery('code');
        $code_number = Helpers::__getQuery('code_number');

        $detect = new \Mobile_Detect();
        $isMob = $detect->isMobile();

        $login_url = Helpers::__getQuery('login_url');
        if (!$code){
            goto end_of_function;
        }
        try {
            $params = json_decode(Helpers::__cryptoDecrypt($code, getenv('CRYPTO_PASSWORD')));
            if ($params && is_object($params)){
                $params = (array)$params;
            }

            if (!isset($params['email']) || !isset($params['login_url'])){
                goto end_of_function;
            }

            $return = ModuleModel::__confirmUserCognito($code_number, $params['email']);

            if ($return['success']){
                $user = User::findFirstByWorkemail($params['email']);
                if ($user){
                    //Update User Status
                  $user->setHasAccessStatus();
                    $resultUpdate = $user->__quickUpdate();
                }else{
                    $employee = EmployeeExt::findFirstByWorkemail($params['email']);
                    if ($employee){
                        //Update User Status
                        $employee->setHasAccessStatus();
                        $resultUpdate = $employee->__quickUpdate();
                    }

                }

                if($params['login_url'] && !str_contains($params['login_url'], getenv('API_DOMAIN'))){
                    if($isMob){
                        $return['message'] = 'Your account has successfully been verified!';
                        goto end_of_function;
                    }else{
                        return $this->response->redirect($params['login_url'], true);
                    }
                }else{
                    $return['message'] = 'Your account has successfully been verified!';
                    goto end_of_function;
                }

            }
        } catch (\Exception $e) {
            $return['success'] = false;
            $return['message'] = $e->getMessage();
            \Sentry\captureException($e);
        }

        end_of_function:

        $file_logo = "logo.jpg";

        $di = \Phalcon\DI::getDefault();
        $bucketName = $di->get('appConfig')->aws->bucket_thumb_name;
        $presignedUrl_logo = \SMXD\Application\Lib\SMXDS3Helper::__getPresignedUrl('sc-cp-pictures/' . $file_logo, $bucketName, $file_logo, 'image/jpg');

        $view = new \Phalcon\Mvc\View\Simple();
        $view->setDi($di);
        $view->registerEngines(array(
            ".volt" => function ($view, $di) {
                $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);
                $volt->setOptions(
                    array(
                        'compiledPath' => $di->getShared('appConfig')->application->cacheDir,
                        'compiledSeparator' => '_',
                        'compileAlways' => true,
                    )
                );
                $compiler = $volt->getCompiler();
                $volt->getCompiler()->addFunction(
                    'currency_format',
                    function ($keys) {
                        return 'number_format(' . $keys . ', ".", ",")';
                    }
                );
                return $volt;
            }
        ));

        $view->setViewsDir($di->getShared('appConfig')->application->templatesVoltDir);
        $html = $view->render('verify/index', [
            'message' => $return['message'],
            'success' => $return['success'],
            'logo' => $presignedUrl_logo,
            'current_date' => date("d/m/Y"),
        ]);

        die($html);

    }
}
