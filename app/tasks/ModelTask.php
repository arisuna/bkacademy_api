<?php

use \Phalcon\Cli\Task;

class ModelTask extends Task
{

    public $apps = [];

    /*
    public $app_namespaces = [
        'gms' => [
            'namespace' => 'Reloda\\Gms\\Models\\',
            'dir'		=> '',
        ],
        'app' => [
            'namespace' => 'Reloda\\Gms\\Models\\',
            'dir'		=> $this->config->application->modulesDir."app/",
        ]
    ]
    */

    public function initialize()
    {
        $this->apps = [
            'gms' => [
                'namespace' => 'SMXD\\Gms\\Models',
                'dir' => $this->config->application->modulesDir . "gms/",
            ],
            'employees' => [
                'namespace' => 'SMXD\\Employees\\Models',
                'dir' => $this->config->application->modulesDir . "employees/",
            ],
            'hr' => [
                'namespace' => 'SMXD\\Hr\\Models',
                'dir' => $this->config->application->modulesDir . "hr/",
            ],
            'api' => [
                'namespace' => 'SMXD\\Api\\Models',
                'dir' => $this->config->application->modulesDir . "api/",
            ],
            'app' => [
                'namespace' => 'SMXD\\App\\Models',
                'dir' => $this->config->application->modulesDir . "app/",
            ],
            'backend' => [
                'namespace' => 'SMXD\\Backend\\Models',
                'dir' => $this->config->application->modulesDir . "backend/",
            ],
            'media' => [
                'namespace' => 'SMXD\\Media\\Models',
                'dir' => $this->config->application->modulesDir . "media/",
            ],
            'needform' => [
                'namespace' => 'SMXD\\Needform\\Models',
                'dir' => $this->config->application->modulesDir . "needform/",
            ],
            'business-api-hr' => [
                'namespace' => 'SMXD\\BusinessApiHr\\Models',
                'dir' => $this->config->application->modulesDir . "business-api-hr/",
            ],
            'business-api-master' => [
                'namespace' => 'SMXD\\BusinessApiMaster\\Models',
                'dir' => $this->config->application->modulesDir . "business-api-master/",
            ],
            'business-api-gms' => [
                'namespace' => 'SMXD\\BusinessApiGms\\Models',
                'dir' => $this->config->application->modulesDir . "business-api-gms/",
            ],
        ];
    }

    /**
     * [generateAction description]
     * @param array $params [description]
     * @return [type]         [description]
     */
    function generateAction(array $params)
    {

        $parseParams = $this->parseParams($params);
        $tablename = isset($parseParams['table']) ? $parseParams['table'] : false;
        $app = isset($parseParams['app']) ? $parseParams['app'] : false;
        $force = isset($parseParams['force']) ? $parseParams['force'] : false;
        $ext = isset($parseParams['ext']) ? $parseParams['ext'] : false;


        if ($force == true) {
            $file_open_mode = "w+";
        } else {
            $file_open_mode = "w+";
        }
        $appNamespace = 'SMXD\\Application\\Models';
        if ($tablename == false) {
            echo "[FAIL] TableName not exist \r\n";
            die();
        }

        if (isset($this->apps[$app]) == false) {
            echo "[FAIL] App not exist \r\n";
            die();
        }

        $modelName = str_replace(' ', '', ucwords(str_replace('_', ' ', $tablename)));

        $modelsDir = $this->config->application->modelsDir;
        $modelNameExt = $modelName . "Ext";

        if ($ext !== false) {
            if (!file_exists($modelsDir . $modelNameExt . ".php") || filesize($modelsDir . $modelNameExt . ".php") == 0 || $force == true) {
                $file = fopen($modelsDir . $modelNameExt . ".php", $file_open_mode);
                $content = file_get_contents($this->config->application->internalLibDir . "generators/ModelExt.txt");
                $content = str_replace("{appNamespace}", $appNamespace, $content);
                $content = str_replace("{class}", $modelName, $content);
                $content = str_replace("{tableName}", $tablename, $content);
                fputs($file, $content);
                fclose($file);

                echo "[SUCCESS] File Generated : " . $modelsDir . $modelNameExt . ".php" . "\r\n";
            } else {
                echo "[FAIL] File exist : " . $modelsDir . $modelNameExt . ".php" . "\r\n";
            }
        }

        if ($app !== false) {
            $namespace = $this->apps[$app]['namespace'];
            $dir = $this->apps[$app]['dir'];

            if (!file_exists($dir . "models/" . $modelName . ".php") || filesize($dir . "models/" . $modelName . ".php") == 0 || $force == true) {
                $file = fopen($dir . "models/" . $modelName . ".php", $file_open_mode);
                $content = file_get_contents($this->config->application->internalLibDir . "generators/Model.txt");
                $content = str_replace("{appNamespace}", $appNamespace, $content);
                $content = str_replace("{class}", $modelName, $content);
                $content = str_replace("{namespace}", $namespace, $content);
                $content = str_replace("{tableName}", $tablename, $content);
                fputs($file, $content);
                fclose($file);

                echo "[SUCCESS] Generate : " . $dir . "models/" . $modelName . ".php" . "\r\n";

            } else {
                echo "[FAIL] File exist : " . $dir . "models/" . $modelName . ".php" . "\r\n";
            }

        }


        die('done');
    }

    /**
     * @param $params
     */
    public function parseParams($params)
    {
        $return = [];
        foreach ($params as $item) {
            if (preg_match('#^--([a-z]+)=([a-z0-9\_\-]+)?$#', $item, $matches)) {
                if (isset($matches[1]) && isset($matches[2])) {
                    $return[$matches[1]] = $matches[2];
                } elseif (isset($matches[1])) {
                    $return[$matches[1]] = true;
                }
            } elseif (preg_match('#^--([a-z]+)$#', $item, $matches)) {
                if (isset($matches[1])) {
                    $return[$matches[1]] = true;
                }
            }
        }
        return $return;
    }
}