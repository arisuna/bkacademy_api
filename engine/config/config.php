<?php

return new \Phalcon\Config([

    'database' => [
        'adapter' => getenv('DB_ADAPTER'),
        'host' => getenv('DB_HOST'),
        'username' => getenv('DB_USERNAME'),
        'password' => getenv('DB_PASSWORD'),
        'port' => getenv('DB_PORT'),
        'dbname' => getenv('DB_DATABASE'),
        'persistent' => false,
        'charset' => 'utf8',
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

    'databaseSc' => [
        'adapter' => getenv('DB_SC_ADAPTER'),
        'host' => getenv('DB_SC_HOST'),
        'username' => getenv('DB_SC_USERNAME'),
        'password' => getenv('DB_SC_PASSWORD'),
        'dbname' => getenv('DB_SC_DATABASE'),
        'port' => getenv('DB_SC_PORT'),
        'persistent' => false,
        'charset' => 'utf8'
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

    'version' => '1.0',

    'printNewLine' => true,

    'application' => [
        'annotations' => ['adapter' => 'Memory'],
        'models' => [
            'metadata' => ['adapter' => 'Memory']
        ],
        'modelsDir' => __DIR__ . '/../models/',
        'cacheDir' => __DIR__ . '/../../cache/',
        'libDir' => __DIR__ . '/../lib/',
        'langDir' => __DIR__ . '/../lang/',
        'publicLangDir' => __DIR__ . '/../../public/resources/i18n/',
        'constantDir' => __DIR__ . '/../../private/common/lib/application/constant/',
        'engineConstantDir' => __DIR__ . '/../constant/',
        'queueDir' => __DIR__ . '/../queue/',
        'dataDir' => getenv('DATA_DIR') != '' ? getenv('DATA_DIR') : __DIR__ . '/../../data/',
        'resourcesDir' => __DIR__ . '/../../resources/',
        'request_token_expired' => 48, // register token expired time (hours),
        'session_timeout' => 48, // Login expired time (hours),
        'templatesVoltDir' => __DIR__ . '/../../private/common/lib/application/templates/',
        'environment' => getenv('ENVIR'),

        'cachePrefixTransVersal' => '', //cache throw all environment
        'cachePrefix' => '_CLI',
        'cachePrefixHr' => '_HR',
        'cachePrefixGms' => '_GMS',

        'isLocal' => getenv('ENVIR') == 'LOCAL',
        'isProd' => getenv('ENVIR') === 'PROD',
    ],
    'driver' => 'mailgun',
    'mailgun' => [
        'key' => getenv('MAILGUN_KEY'),
        'domain' => getenv('MAILGUN_DOMAIN'),
        'sender' => getenv('MAILGUN_SENDER'),
    ],
    /** Define Directory */
    'base_dir' => [
        'public' => __DIR__ . '/../../public/',
        'app' => __DIR__ . '/../../public/apps/app/',
        'backend' => __DIR__ . '/../../public/apps/backend/',
        'gms' => __DIR__ . '/../../public/apps/gms/',
        'hr' => __DIR__ . '/../../public/apps/hr/',
        'employees' => __DIR__ . '/../../public/apps/employees/',
        'data' => __DIR__ . '/../../data/'
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
        'cloudSearchGeonameCities' => getenv('CLOUD_SEARCH_GEONAME_CITIES'),

        'awsAppClientId' => getenv('AWS_APP_CLIENT_ID'),
        'awsAppClientSecret' => getenv('AWS_APP_SECRET'),
        'awsUserPoolId' => getenv('USER_POOL_ID'),
        'awsCognitoRegion' => getenv('AWS_COGNITO_REGION'),
        'sequenceTable' => getenv('AWS_SEQUENCE_TABLE'),

        //DYNAMODDB
        'dynamoEndpointUrl' => getenv('DYNAMODB_ENDPOINT'),
        'dynamoEndpointLocal' => getenv('DYNAMODB_ENDPOINT_LOCAL') == 'true',


    ],

    'domain' => [
        'prefix' => getenv('APP_PREFIX'),
        'hr_prefix' => getenv('APP_HR_PREFIX'),
        'dsp_prefix' => getenv('APP_DSP_PREFIX'),

        'suffix' => getenv('APP_SUFFIX'),
        'api' => getenv('API_DOMAIN'),
        'backend' => getenv('BACKEND_DOMAIN'),
        
        'assignee' => getenv('ASSIGNEE_DOMAIN'),
        'mobile_assignee' => getenv('MOBILE_ASSIGNEE_DOMAIN'),

        'images' => getenv('API_DOMAIN'),
        'ssl' => getenv('APP_SSL') && getenv('APP_SSL') == "true" ? true : false,
    ],
]);
