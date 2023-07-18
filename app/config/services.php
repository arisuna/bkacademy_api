<?php

use Phalcon\Db\Adapter\Pdo;

$di->setShared('config', function () {
    return include APP_PATH . '/config/config.php';
});

$di->set('db', function () {
    $config = $this->getConfig();
    return new \Phalcon\Db\Adapter\Pdo\Mysql(array(
        'host' => $config->database->host,
        'username' => $config->database->username,
        'password' => $config->database->password,
        'dbname' => $config->database->dbname,
        'charset' => $config->database->charset
    ));
});
