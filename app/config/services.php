<?php

use Phalcon\Db\Adapter\Pdo;

$di->setShared('config', function () {
    return include APP_PATH . '/config/config.php';
});

$di->setShared('filter', function () {
    $filter = new \Phalcon\Filter();
    $filter->add('email', function ($value) {
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    });
    return $filter;
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
