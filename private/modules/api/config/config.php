<?php

return new \Phalcon\Config([

    'database' => [
        'adapter' => getenv('DB_ADAPTER'),
        'host' => getenv('DB_HOST'),
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
        'dbname' => getenv('DB_DATABASE'),
        'port' => getenv('DB_PORT'),
        'persistent' => false,
        'charset' => 'utf8'
    ],

    'databaseRead' => [
        'adapter' => getenv('DB_ADAPTER_READ'),
        'host' => getenv('DB_HOST_READ'),
        'username' => getenv('DB_USERNAME_READ'),
        'password' => getenv('DB_PASSWORD_READ'),
        'dbname' => getenv('DB_DATABASE_READ'),
        'port' => getenv('DB_PORT_READ'),
        'persistent' => false,
        'charset' => 'utf8'
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


    'controllers' => [
        'annotationRouted' => [
			'\Reloday\Api\Controllers\API\Communication',
            '\Reloday\Api\Controllers\API\Mail',
            '\Reloday\Api\Controllers\API\Index',
        ]
    ],

    'application' => [
        'mailsDir' => __DIR__ . '/../../../../data/',
        'cachePrefix' => '__API__'
    ]
]);
