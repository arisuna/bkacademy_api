<?php

use \Phalcon\Cli\Task;
use Reloday\Hr\Controllers\API as HrControllers;
use Reloday\Gms\Controllers\API as GmsControllers;
use Reloday\Employees\Controllers\API as EmployeesControllers;

class PublicApiTask extends Task
{

    const API_TEXT = '/**
 * @api {{apiMethod}} {apiUrl} {apiName}
 * @apiName {apiName}
 * @apiGroup {apiGroup}
 * @apiVersion 1.0.0
 * @apiDescription {apiDescription}
 *
 *
 *
 * @apiHeader {String} TokenKey access token-key of User, lifetime max = 1 hour
 * @apiHeader {String} RefreshToken refresh token-key of User lifetime max = 7 days
 * @apiHeaderExample {json} Header-Example:
 *     {
 *        "Accept":"application/json",
 *        "Accept-Language":"en-US,en;q=0.5",
 *        "X-Requested-With":"XMLHttpRequest",
 *        "Token-Key":"eyJraWQiOiJjZ3JObGcreXFPdnhlakMzV09Wd....",
 *        "Refresh-Token":"eyJjdHkiOiJKV1QiLCJlbmMiOiJBMjU2R0NNIiwiYWxnIjoiUlNBLU9BRVAif....",
 *        "Language-Key": "en",
 *        "Timezone-Offset": "420",
 *        "Timezone": "UTC"
 *     }
 *
 * @apiSuccess {String} success True/False = Status of Process Data
 * @apiSuccess {String} data  Json String of Data returned by the api
 * @apiSuccess {String} data  Json String of Data returned by the api

 * @apiSuccessExample {json} Success-Response:
 *     HTTP/1.1 200 OK
 *     {
 *       "success": true,
 *       "data": {
 *          "id": 1,
 *          "uuid": "da2aa0e2-b4d0-4e3c-99f5-f5ef62c57fe2"
 *       }
 *     }
 */';

    /**
     * @throws ReflectionException
     */
    public function generateAction()
    {
        $this->generateModule('hr');
        $this->generateModule('gms');
        $this->generateModule('employees');
    }

    /**
     * @param $module
     */
    public function generateModule(String $module)
    {
        if ($module == 'hr') {
            $folderOutput = 'hr';
            $apiPrefix = 'hr';
            $folderController = APP_PATH . '/../private/modules/hr/controllers/api/*Controller.php';
            $prefixClassController = 'Reloday\Hr\Controllers\API\\';
        }

        if ($module == 'gms') {
            $folderOutput = 'gms';
            $apiPrefix = 'gms';
            $folderController = APP_PATH . '/../private/modules/gms/controllers/api/*Controller.php';
            $prefixClassController = 'Reloday\Gms\Controllers\API\\';
        }


        if ($module == 'employees') {
            $folderOutput = 'employee';
            $apiPrefix = 'employees';
            $folderController = APP_PATH . '/../private/modules/employees/controllers/api/*Controller.php';
            $prefixClassController = 'Reloday\Employees\Controllers\API\\';
        }


        $files = glob(APP_PATH . '/docjs/' . $folderOutput . '/*.js'); // get all file names
        foreach ($files as $file) { // iterate files
            if (is_file($file)) {
                unlink($file); // delete file
            }
        }

        $controllers = [];
        foreach (glob($folderController) as $controller) {
            $className = $prefixClassController . str_replace('.php', '', basename($controller, ' . php'));
            $controllers[$className] = [];
            try {
                $methods = (new \ReflectionClass($className))->getMethods(\ReflectionMethod::IS_PUBLIC);
                foreach ($methods as $method) {
                    if (\Phalcon\Text::endsWith($method->name, 'Action')) {
                        $controllers[$className][] = $method->name;
                    }
                }
            } catch (ReflectionException $e) {
                echo $className . "\r\n";
                echo $e->getMessage() . "\r\n";
                echo $e->getTraceAsString() . "\r\n";
                die();
            }
        }


        foreach ($controllers as $controllerName => $actionList) {
            $controllerParsed = explode("\\", $controllerName);
            $fileControllerName = end($controllerParsed);
            $file = fopen(APP_PATH . '/docjs/' . $folderOutput . '/' . \Phalcon\Text::uncamelize($fileControllerName) . '.js', "w+");
            $actionInputString = [];
            foreach ($actionList as $actionName) {
                $actionName = (str_replace('Action', '', $actionName));
                $actionUrl = '/' . $apiPrefix . '/' . \Phalcon\Text::uncamelize(str_replace('Controller', '', $fileControllerName)) . '/' . $actionName;
                $text = self::API_TEXT;
                $text = str_replace('{apiMethod}', 'GET', $text);
                $text = str_replace('{apiNameCombo}', $actionName, $text);
                $text = str_replace('{apiUrl}', $actionUrl, $text);
                $text = str_replace('{apiName}', $actionName, $text);
                $text = str_replace('{apiGroup}', str_replace('Controller', '', $fileControllerName), $text);
                $text = str_replace('{apiDescription}', "Description:", $text);
                $actionInputString[] = $text;
            }
            fputs($file, implode("\r\n \r\n  \r\n", $actionInputString));
            fclose($file);
        }
    }

    public function copyLocalAction()
    {
        $this->copyLocalModule('hr');
        $this->copyLocalModule('gms');
        $this->copyLocalModule('employees');
    }

    public function copyLocalModule($module)
    {

        $folderTaget = BASE_PATH . "/../relodayapp_doc/app";

        if ($module == 'hr') {
            $folderOutput = 'hr';
        }

        if ($module == 'gms') {
            $folderOutput = 'gms';
        }


        if ($module == 'employees') {
            $folderOutput = 'employee';
        }

        $files = glob(APP_PATH . '/docjs/' . $folderOutput . '/*.js');
        foreach ($files as $file) {
            if (is_file($file)) {
                //echo basename($file)."\r\n";
                copy($file, $folderTaget . "/" . $folderOutput . "/" . basename($file));
            }
        }
    }
}