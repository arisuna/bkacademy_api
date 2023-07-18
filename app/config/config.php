<?php

/**
 * Simple application configuration
 */
require __DIR__ . '/../../vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(__DIR__ . '/../../');
$dotenv->load();

return new \Phalcon\Config([

    'database' => [
        'adapter' => getenv('DB_ADAPTER'),
        'host' => getenv('DB_HOST'),
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
        'dbname' => getenv('DB_DATABASE'),
        'port' => getenv('DB_PORT'),
        'charset' => 'utf8',
        'persistent' => false,
    ],

    'databaseCommunicationRead' => [
        'adapter' => getenv('DB_COM_ADAPTER_READ'),
        'host' => getenv('DB_COM_HOST_READ'),
        'username' => getenv('DB_COM_USERNAME_READ'),
        'password' => getenv('DB_COM_PASSWORD_READ'),
        'dbname' => getenv('DB_COM_DATABASE_READ'),
        'port' => getenv('DB_COM_PORT_READ'),
        'persistent' => false,
        'charset' => 'utf8'
    ],

    'databaseCommunicationWrite' => [
        'adapter' => getenv('DB_COM_ADAPTER_WRITE'),
        'host' => getenv('DB_COM_HOST_WRITE'),
        'username' => getenv('DB_COM_USERNAME_WRITE'),
        'password' => getenv('DB_COM_PASSWORD_WRITE'),
        'dbname' => getenv('DB_COM_DATABASE_WRITE'),
        'port' => getenv('DB_COM_PORT_WRITE'),
        'persistent' => false,
        'charset' => 'utf8'
    ],

    'application' => [
        'baseUri' => '/',
        'annotations' => ['adapter' => 'Memory'],
        'models' => [
            'metadata' => ['adapter' => 'Memory']
        ],
        'cacheDir' => __DIR__ . '/../../cache/',
        'modelsDir' => __DIR__ . '/../../private/common/lib/application/models/',
        'libDir' => __DIR__ . '/../../private/common/lib/',
        'request_token_expired' => 48, // register token expired time (hours),
        'session_timeout' => 48, // Login expired time (hours),
        'modulesDir' => __DIR__ . '/../../private/modules/',
        'internalLibDir' => __DIR__ . '/../library/',
        'originDir' => __DIR__ . '/../../',
    ],

]);
