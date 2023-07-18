<?php

/**
 * Simple application configuration
 */
return new \Phalcon\Config([

    'database' => [
        'adapter' => getenv('DB_ADAPTER'),
        'host' => getenv('DB_HOST'),
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
        'dbname' => getenv('DB_DATABASE'),
        'port' => getenv('DB_PORT'),
        'persistent' => false,
        'charset' => 'utf8',
        'options' => null,
    ],

    'databaseRead' => [
        'adapter' => getenv('DB_ADAPTER_READ'),
        'host' => getenv('DB_HOST_READ'),
        'username' => getenv('DB_USERNAME_READ'),
        'password' => getenv('DB_PASSWORD_READ'),
        'dbname' => getenv('DB_DATABASE_READ'),
        'port' => getenv('DB_PORT_READ'),
        'persistent' => false,
        'charset' => 'utf8',
        'options' => null,
    ],

    'databaseCommunicationRead' => [
        'adapter' => getenv('DB_COM_ADAPTER_READ'),
        'host' => getenv('DB_COM_HOST_READ'),
        'username' => getenv('DB_COM_USERNAME_READ'),
        'password' => getenv('DB_COM_PASSWORD_READ'),
        'dbname' => getenv('DB_COM_DATABASE_READ'),
        'port' => getenv('DB_COM_PORT_READ'),
        'persistent' => false,
        'charset' => 'utf8',
        'options' => null,
    ],

    'databaseCommunicationWrite' => [
        'adapter' => getenv('DB_COM_ADAPTER_WRITE'),
        'host' => getenv('DB_COM_HOST_WRITE'),
        'username' => getenv('DB_COM_USERNAME_WRITE'),
        'password' => getenv('DB_COM_PASSWORD_WRITE'),
        'dbname' => getenv('DB_COM_DATABASE_WRITE'),
        'port' => getenv('DB_COM_PORT_WRITE'),
        'persistent' => false,
        'charset' => 'utf8',
        'options' => null,
    ],

    'databaseSc' => [
        'adapter' => getenv('DB_SC_ADAPTER'),
        'host' => getenv('DB_SC_HOST'),
        'username' => getenv('DB_SC_USERNAME'),
        'password' => getenv('DB_SC_PASSWORD'),
        'dbname' => getenv('DB_SC_DATABASE'),
        'port' => getenv('DB_SC_PORT'),
        'persistent' => false,
        'charset' => 'utf8',
        'options' => null,
    ],

    'databaseWebhook' => [
        'adapter' => getenv('DB_WEBHOOK_ADAPTER'),
        'host' => getenv('DB_WEBHOOK_HOST'),
        'username' => getenv('DB_WEBHOOK_USERNAME'),
        'password' => getenv('DB_WEBHOOK_PASSWORD'),
        'dbname' => getenv('DB_WEBHOOK_DATABASE'),
        'port' => getenv('DB_WEBHOOK_PORT'),
        'persistent' => false,
        'charset' => 'utf8'
    ],

    'application' => [
        'baseUri' => '/',
        'annotations' => ['adapter' => 'Memory'],
        'models' => [
            'metadata' => ['adapter' => 'Memory']
        ],
        'baseDir' => __DIR__ . '/../../',
        'tempDir' => __DIR__ . '/../../tmp',
        'cacheDir' => __DIR__ . '/../../cache/',
        'modelsDir' => __DIR__ . '/../common/lib/application/models/',
        'libDir' => __DIR__ . '/../lib/',
        'queueDir' => __DIR__ . '/../queue/',
        'request_token_expired' => 48, // register token expired time (hours),
        'session_timeout' => 48, // Login expired time (hours),

        'environment' => getenv('ENVIR'),

        'isLocal' => getenv('ENVIR') == 'LOCAL',
        'isProd' => getenv('ENVIR') === 'PROD',

        'publicLangDir' => __DIR__ . '/../../public/resources/i18n/',
        'uploadDir' => __DIR__ . '/../../public/upload/',
        'templatesVoltDir' => __DIR__ . '/../common/lib/application/templates/',
        'keyDir' => __DIR__ . '/../common/lib/application/key/',

        'needRedirectAfterLogin' => getenv('REDIRECT_AFTER_LOGIN') === true || getenv('REDIRECT_AFTER_LOGIN') === 'true',
        'session_timeout' => 24
    ],

    'cache' => [
        'prefix' => getenv('CACHE_PREFIX'),
        'modelPrefix' => getenv('CACHE_PREFIX') . "_MODELS_",
        'lifetime' => getenv('CACHE_TIME')
    ],

    'redis' => [
        'host' => getenv('REDIS_HOST'),
        'port' => getenv('REDIS_PORT'),
    ],


    'aws' => [
        'bucket_name' => getenv('AMAZON_BUCKET_NAME'),
        'bucket_thumb_name' => getenv('AMAZON_BUCKET_THUMB_NAME'),

        'bucket_public_name' => getenv('AMAZON_BUCKET_PUBLIC_NAME'),
        'bucket_public_url' => getenv('AMAZON_BUCKET_PUBLIC_URL'),

        'bucket_content_name' => getenv('AMAZON_BUCKET_CONTENT_NAME'),
        'bucket_content_url' => getenv('AMAZON_BUCKET_CONTENT_URL'),

        'region' => getenv('AWS_REGION'),

        'credentials' => __DIR__ . '/../../.configuration/.credentials',
        'cloudSearchTaskName' => getenv('CLOUD_SEARCH_TASK'),
        'cloudSearchContact' => getenv('CLOUD_SEARCH_CONTACT'),
        'cloudSearchRelocationName' => getenv('CLOUD_SEARCH_RELOCATION'),
        'cloudSearchAssignmentName' => getenv('CLOUD_SEARCH_ASSIGNMENT'),
        //COGNITO CONFIGURATION
        'awsAppClientId' => getenv('AWS_APP_CLIENT_ID'),
        'awsAppClientSecret' => getenv('AWS_APP_SECRET'),
        'awsUserPoolId' => getenv('USER_POOL_ID'),
        'awsCognitoRegion' => getenv('AWS_COGNITO_REGION'),
        'sequenceTable' => getenv('AWS_SEQUENCE_TABLE'),
        //DYNAMODDB
        'dynamoEndpointUrl' => getenv('DYNAMODB_ENDPOINT'),
        'dynamoEndpointLocal' => getenv('DYNAMODB_ENDPOINT_LOCAL') == 'true',
    ],

    /** Define Directory */
    'base_dir' => [
        'public' => __DIR__ . '/../../public/',
        'app' => __DIR__ . '/../../public/apps/app/',
        'gms' => __DIR__ . '/../../public/apps/gms/',
        'hr' => __DIR__ . '/../../public/apps/hr/',
        'employees' => __DIR__ . '/../../public/apps/employees/',
        'needform' => __DIR__ . '/../../public/apps/needform/',
        'tasks' => __DIR__ . '/../../public/apps/tasks/',
        'data' => __DIR__ . '/../../data/'
    ],

    /** Config Thumbnail */
    'thumbnail' => [
        "large" => 300,
        "normal" => 200,
        "small" => 100
    ],
    /** config domain url */
    'domain' => [
        'prefix' => getenv('APP_PREFIX'),
        'hr_prefix' => getenv('APP_HR_PREFIX'),
        'dsp_prefix' => getenv('APP_DSP_PREFIX'),

        'suffix' => getenv('APP_SUFFIX'),

        'api' => getenv('API_DOMAIN'),
        'images' => getenv('API_DOMAIN'),
        'backend' => getenv('BACKEND_DOMAIN'),
        'assignee' => getenv('ASSIGNEE_DOMAIN'),
        'mobile_assignee' => getenv('MOBILE_ASSIGNEE_DOMAIN'),
        'ssl' => getenv('APP_SSL') && getenv('APP_SSL') == "true" ? true : false,
    ],

    'captcha' => [
        'secret_key' => getenv('GOOGLE_CAPTCHA_SECRET_KEY'),
        'site_key' => getenv('GOOGLE_CAPTCHA_SITE_KEY')
    ],

    'jwt' => [
        'key' => getenv('JWT_KEY'),
        'loginKey' => getenv('JWT_LOGIN_KEY'),
        'alg' => getenv('JWT_ALG'),
        'exp' => getenv('JWT_ALG'),
        'leeway' => intval(getenv('JWT_LEEWAY')),
    ],

    'driver' => 'mailgun',
    'mailgun' => [
        'key' => getenv('MAILGUN_KEY'),
        'domain' => getenv('MAILGUN_DOMAIN'),
        'sender' => getenv('MAILGUN_SENDER'),
    ],


]);
