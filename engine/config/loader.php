<?php

$loader = new \Phalcon\Loader();

$loader->registerDirs([
    APP_PATH . '/tasks',
    APP_PATH . '/models',
    APP_PATH . '/lib',
    APP_PATH . '/queue'
]);

$loader->registerNamespaces([
    "SMXD\\Application\\Models" => __DIR__ . '/../../private/common/lib/application/models',
    "SMXD\\Application\\Lib" => __DIR__ . '/../../private/common/lib/application/lib',
    "SMXD\\Application\\Aws" => __DIR__ . '/../../private/common/lib/application/aws',
    "SMXD\\Application\\Aws\\AwsCognito" => __DIR__ . '/../../private/common/lib/application/aws/cognito',
    "SMXD\\Application\\Aws\\AwsCognito\\Exception" => __DIR__ . '/../../private/common/lib/application/aws/cognito/exception',
    "SMXD\\Application\\Provider" => __DIR__ . '/../../private/common/lib/application/provider',
    "SMXD\\Application\\Behavior" => __DIR__ . '/../../private/common/lib/application/behavior',
    "SMXD\\Application\\Validation" => __DIR__ . '/../../private/common/lib/application/validation',
    "SMXD\\Application\\Validator" => __DIR__ . '/../../private/common/lib/application/validator',
    "SMXD\\Application\\Resulset" => __DIR__ . '/../../private/common/lib/application/resulset',
    "SMXD\\Application\\Traits" => __DIR__ . '/../../private/common/lib/application/traits',

    "SMXD\\Application\\SalaryCalculator" => __DIR__ . '/../../private/common/lib/application/salary-calculator',
    "SMXD\\Application\\SalaryCalculator\\Models" => __DIR__ . '/../../private/common/lib/application/salary-calculator/models',
    "SMXD\\Application\\SalaryCalculator\\Validators" => __DIR__ . '/../../private/common/lib/application/salary-calculator/validators',
    "SMXD\\Application\\SalaryCalculator\\Helpers" => __DIR__ . '/../../private/common/lib/application/salary-calculator/helpers',

    "SMXD\\Application\\CostProjection" => __DIR__ . '/../../private/common/lib/application/cost-projection',
    "SMXD\\Application\\CostProjection\\Helpers" => __DIR__ . '/../../private/common/lib/application/cost-projection/helpers',

    "SMXD\\Application\\CloudModels" => __DIR__ . '/../../private/common/lib/application/cloud-models',
    "SMXD\\Gms\\Models" => __DIR__ . '/../../private/modules/gms/models',
    "SMXD\\Application\\DynamoDb\\ORM" => __DIR__ . '/../../private/common/lib/application/dynamodb/orm',
    "SMXD\\Application\\ElasticSearch\\Models" => __DIR__ . '/../../private/common/lib/application/elasticsearch/models',
]);

$loader->register();



