<?php

use Phalcon\Db\Adapter\Pdo;
use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Events\Manager as EventsManager;


/**
 * Shared configuration service
 */
$di->setShared('config', function () {
    return include APP_PATH . '/config/config.php';
});


$di->setShared('appConfig', function () {
    return include APP_PATH . '/config/config.php';
});

$di->setShared('eventsManager', function () {
    $eventsManager = new EventsManager();
    return $eventsManager;
});

/**
 * Module specific dispatcher
 */
$di->setShared('dispatcher', function () use ($di) {
    $eventsManager = $di->getShared('eventsManager');
    $eventsManager->attach(
        "dispatch:beforeException",
        function ($event, $dispatcher, $exception) {
            if (is_null($exception) == false) {
                \Sentry\captureException($exception);
            }
            return false;
        }
    );

    $dispatcher = new Phalcon\Cli\Dispatcher();
    $dispatcher->setEventsManager($eventsManager);
    return $dispatcher;
});


/**
 * Database connection is created based in the parameters defined in the configuration file
 */
$di->setShared('db', function () {
    $config = $this->getConfig();
    $class = 'Phalcon\Db\Adapter\Pdo\\' . $config->database->adapter;
    $connection = new $class([
        'host' => $config->database->host,
        'username' => $config->database->username,
        'password' => $config->database->password,
        'dbname' => $config->database->dbname,
        'charset' => $config->database->charset,
        'port' => $config->database->port,
        'persistent' => false,
    ]);
    return $connection;
});


$di->setShared('dbRead', function () {
    $config = $this->getConfig();
    $class = 'Phalcon\Db\Adapter\Pdo\\' . $config->databaseRead->adapter;
    $connection = new $class([
        'host' => $config->databaseRead->host,
        'username' => $config->databaseRead->username,
        'password' => $config->databaseRead->password,
        'dbname' => $config->databaseRead->dbname,
        'charset' => $config->databaseRead->charset,
        'port' => $config->databaseRead->port,
        'persistent' => false,
    ]);
    return $connection;
});

$di->setShared('dbCommunicationRead', function () {
    $config = $this->getConfig();
    $class = 'Phalcon\Db\Adapter\Pdo\\' . $config->databaseCommunicationRead->adapter;
    $connection = new $class([
        'host' => $config->databaseCommunicationRead->host,
        'username' => $config->databaseCommunicationRead->username,
        'password' => $config->databaseCommunicationRead->password,
        'dbname' => $config->databaseCommunicationRead->dbname,
        'charset' => $config->databaseCommunicationRead->charset,
        'port' => $config->databaseCommunicationRead->port
    ]);
    return $connection;
});


$di->setShared('dbCommunicationWrite', function () {
    $config = $this->getConfig();
    $class = 'Phalcon\Db\Adapter\Pdo\\' . $config->databaseCommunicationWrite->adapter;
    $connection = new $class([
        'host' => $config->databaseCommunicationWrite->host,
        'username' => $config->databaseCommunicationWrite->username,
        'password' => $config->databaseCommunicationWrite->password,
        'dbname' => $config->databaseCommunicationWrite->dbname,
        'charset' => $config->databaseCommunicationWrite->charset,
        'port' => $config->databaseCommunicationWrite->port
    ]);
    return $connection;
});


$di->setShared('dbSC', function () {
    $config = $this->getConfig();
    $class = 'Phalcon\Db\Adapter\Pdo\\' . $config->databaseSc->adapter;
    $connection = new $class([
        'host' => $config->databaseSc->host,
        'username' => $config->databaseSc->username,
        'password' => $config->databaseSc->password,
        'dbname' => $config->databaseSc->dbname,
        'charset' => $config->databaseSc->charset,
        'port' => $config->databaseSc->port
    ]);
    return $connection;
});


$di->setShared('dbWebhook', function () {
    $config = $this->getConfig();
    $class = 'Phalcon\Db\Adapter\Pdo\\' . $config->databaseWebhook->adapter;
    $connection = new $class([
        'host' => $config->databaseWebhook->host,
        'username' => $config->databaseWebhook->username,
        'password' => $config->databaseWebhook->password,
        'dbname' => $config->databaseWebhook->dbname,
        'charset' => $config->databaseWebhook->charset,
        'port' => $config->databaseWebhook->port
    ]);
    return $connection;
});




//Set the models cache service
$di->set('modelsCache', function ()  {
    $config = $this->getConfig();
    $frontCache = new \Phalcon\Storage\SerializerFactory();
    //Memcached connection settings
    $cache = new \Phalcon\Cache\Backend\Redis($frontCache, array(
        "prefix" => $config->application->cachePrefix . "_MODELS_",
        "host" => getenv('REDIS_HOST'),
        "port" => getenv('REDIS_PORT'),
        "statsKey" => "_PHCR",
        "persistent" => false,
        "index" => 1 // select db 1
    ));
    return $cache;
});


$di->set('cache', function ()  {
    $config = $this->getConfig();
    $frontCache = new \Phalcon\Storage\SerializerFactory();
    //Memcached connection settings
    $cache = new \Phalcon\Cache\Backend\Redis($frontCache, array(
        "prefix" => $config->application->cachePrefix . "_MODELS_",
        "host" => getenv('REDIS_HOST'),
        "port" => getenv('REDIS_PORT'),
        "persistent" => false,
        "index" => 1 // select db 1
    ));
    return $cache;
});

$di->setShared('filter', function () {
    $filter = new \Phalcon\Filter();
    $filter->add('email', function ($value) {
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    });
    return $filter;
});

