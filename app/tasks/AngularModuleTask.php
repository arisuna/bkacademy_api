<?php
/**
 * Created by PhpStorm.
 * User: anmeo
 * Date: 10/11/16
 * Time: 4:54 PM
 */

use \Phalcon\Cli\Task;

class AngularModuleTask extends Task {
    public function mainAction() {
        echo PHP_EOL . "This is the create Angular Module task from template" . PHP_EOL;
        echo "Action Create module: angular-module create [project_name] [module_name]" . PHP_EOL;
        echo "*** [project_name] is available with: base, app, hr, gms, employees" . PHP_EOL . PHP_EOL;
    }

    public function createAction(array $params) {
        $project_available = [
            'base',
            'app',
            'hr',
            'gms',
            'employees'
        ];

        $is_project = false;

        $project = $params[0];
        $module_name = $params[1];

        $module_template = __DIR__ . '/../../resources/templates/module';
        $module_base = __DIR__ . '/../../resources/templates/base-controller';
        $zone_path = __DIR__ . '/../../resources/scripts/core/';

        foreach ($project_available as $pr_ava) {
            if ($pr_ava === $project) {
                $is_project = true;
            }
        }

        if (!$is_project) {
            die(PHP_EOL . "Project is not exist" . PHP_EOL);
        }
        else {
            $zone_path = $zone_path . $project;
        }

        $module_path = $zone_path . '/' . $module_name;

        if (is_dir($module_path)) {
            die(PHP_EOL . 'Module ' . $module_name . ' is exist' . PHP_EOL . PHP_EOL);
        }
        else {

            if ($module_name === 'base') {
                $this->copy_dir($module_base, $module_path);
            }
            else {
                $this->copy_dir($module_template, $module_path);
            }

            echo PHP_EOL . "Module " . $module_name . " has been created" . PHP_EOL .
            "Module Directory: /resources/scripts/core/" . $project . '/' . $module_name . PHP_EOL . PHP_EOL;
        }
    }

    private function copy_dir($src, $des) {
        $dir = opendir($src);
        @mkdir($des);

        while(false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->copy_dir($src . '/' . $file, $des . '/' . $file);
                }
                else {
                    copy($src . '/' . $file, $des . '/' . $file);
                }
            }
        }

        closedir($dir);
    }
}