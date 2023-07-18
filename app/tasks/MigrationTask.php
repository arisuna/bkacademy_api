<?php

use \Phalcon\Cli\Task;

/**
 * Class MigrationTask
 */
class MigrationTask extends Task
{

    protected $modules = [];

    /**
     *
     */
    public function initialize()
    {
        $this->modules = [
            'basic' => [
                'name' => 'basic',
                'database' => $this->config->database,
            ],
            /*
            'communication' => [
                'name' => 'communication',
                'database' => $this->config->databaseCommunicationWrite,
                'tables' => [
                    'communication_topic',
                    'communication_topic_follower',
                    'communication_topic_read',
                    'communication_topic_flag'
                ]
            ],
            */
        ];
    }

    /**
     *
     */
    public function runAction($params)
    {
        $parseParams = $this->parseParams($params);
        $moduleName = isset($parseParams['module']) ? $parseParams['module'] : false;

        if ($moduleName != '') {
            $module = isset($this->modules[$moduleName]) ? $this->modules[$moduleName] : false;
            if ($module) {
                if (isset($module['tables'])) {
                    $tableNames = implode(',', $module['tables']);
                    $options = [
                        'migrationsDir' => __DIR__ . '/../migrations/',
                        'tableName' => $tableNames,
                        'migrationsInDb' => true,
                        'config' => new \Phalcon\Config(['database' => $module['database']])
                    ];
                } else {
                    $options = [
                        'migrationsDir' => __DIR__ . '/../migrations/',
                        'migrationsInDb' => true,
                        'config' => new \Phalcon\Config(['database' => $module['database']])
                    ];
                }
                try {
                    \Phalcon\Migrations::run($options);
                } catch (\Phalcon\Db\Exception $e) {
                    echo $e->getMessage();
                } catch (\Phalcon\Mvc\Model\Exception $e) {
                    echo $e->getMessage();
                } catch (PDOException $e) {
                    //echo $e->getTraceAsString();
                    echo $e->getMessage();
                } catch (Exception $e) {
                    echo $e->getMessage();
                }
            }
        } else {
            foreach ($this->modules as $module) {
                if (isset($module['tables'])) {
                    $tableNames = implode(',', $module['tables']);
                    $options = [
                        'migrationsDir' => __DIR__ . '/../migrations/',
                        'tableName' => $tableNames,
                        'migrationsInDb' => true,
                        'config' => new \Phalcon\Config(['database' => $module['database']])
                    ];
                } else {
                    $options = [
                        'migrationsDir' => __DIR__ . '/../migrations/',
                        'migrationsInDb' => true,
                        'config' => new \Phalcon\Config(['database' => $module['database']])
                    ];
                }

                \Phalcon\Migrations::run($options);
            }
        }
    }

    /**
     * @param $params
     */
    public function parseParams($params)
    {
        $return = [];
        foreach ($params as $item) {
            if (preg_match('#^--([a-z]+)=([a-z0-9_]+)?$#', $item, $matches)) {
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