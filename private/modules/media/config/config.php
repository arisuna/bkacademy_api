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

    'controllers' => [
        'annotationRouted' => [
            '\SMXD\Media\Controllers\API\Item',
            '\SMXD\Media\Controllers\API\Manager',
            '\SMXD\Media\Controllers\API\Direct',
            '\SMXD\Media\Controllers\API\UploadByUrl',
            '\SMXD\Media\Controllers\API\Attachments',
            '\SMXD\Media\Controllers\API\Uploader',
            '\SMXD\Media\Controllers\API\Base',
            '\SMXD\Media\Controllers\API\Avatar',
            '\SMXD\Media\Controllers\API\File',
            '\SMXD\Media\Controllers\API\Index',
        ]
    ],

    'application' => [
        'baseUri' => '/media/',
        'amazon_bucket_name' => getenv('AMAZON_BUCKET_NAME'),
        'amazon_region' => getenv('AWS_REGION'),
    ],

    'aws' => [
        'bucket_name' => getenv('AMAZON_BUCKET_NAME'),
        'bucket_thumb_name' => getenv('AMAZON_BUCKET_THUMB_NAME'),
        'region' => getenv('AWS_REGION'),
        'cognito_region' => getenv('AWS_COGNITO_REGION'),
        'credentials' => __DIR__ . '/../../.configuration/.credentials',
        'cloudSearchTaskName' => getenv('CLOUD_SEARCH_TASK'),
        'cloudSearchRelocationName' => getenv('CLOUD_SEARCH_RELOCATION'),
        'cloudSearchAssignmentName' => getenv('CLOUD_SEARCH_ASSIGNMENT'),
    ]
]);
