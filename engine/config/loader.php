<?php

$loader = new \Phalcon\Loader();

$loader->registerDirs([
    APP_PATH . '/tasks',
    APP_PATH . '/models',
    APP_PATH . '/lib',
    APP_PATH . '/queue'
]);

$loader->registerNamespaces([
    "Reloday\\Application\\Models" => __DIR__ . '/../../private/common/lib/application/models',
    "Reloday\\Application\\Lib" => __DIR__ . '/../../private/common/lib/application/lib',
    "Reloday\\Application\\Aws" => __DIR__ . '/../../private/common/lib/application/aws',
    "Reloday\\Application\\Aws\\AwsCognito" => __DIR__ . '/../../private/common/lib/application/aws/cognito',
    "Reloday\\Application\\Aws\\AwsCognito\\Exception" => __DIR__ . '/../../private/common/lib/application/aws/cognito/exception',
    "Reloday\\Application\\Provider" => __DIR__ . '/../../private/common/lib/application/provider',
    "Reloday\\Application\\Behavior" => __DIR__ . '/../../private/common/lib/application/behavior',
    "Reloday\\Application\\Validation" => __DIR__ . '/../../private/common/lib/application/validation',
    "Reloday\\Application\\Validator" => __DIR__ . '/../../private/common/lib/application/validator',
    "Reloday\\Application\\Resulset" => __DIR__ . '/../../private/common/lib/application/resulset',
    "Reloday\\Application\\Traits" => __DIR__ . '/../../private/common/lib/application/traits',

    "Reloday\\Application\\SalaryCalculator" => __DIR__ . '/../../private/common/lib/application/salary-calculator',
    "Reloday\\Application\\SalaryCalculator\\Models" => __DIR__ . '/../../private/common/lib/application/salary-calculator/models',
    "Reloday\\Application\\SalaryCalculator\\Validators" => __DIR__ . '/../../private/common/lib/application/salary-calculator/validators',
    "Reloday\\Application\\SalaryCalculator\\Helpers" => __DIR__ . '/../../private/common/lib/application/salary-calculator/helpers',

    "Reloday\\Application\\CostProjection" => __DIR__ . '/../../private/common/lib/application/cost-projection',
    "Reloday\\Application\\CostProjection\\Helpers" => __DIR__ . '/../../private/common/lib/application/cost-projection/helpers',

    "Reloday\\Application\\CloudModels" => __DIR__ . '/../../private/common/lib/application/cloud-models',
    "Reloday\\Gms\\Models" => __DIR__ . '/../../private/modules/gms/models',
    "Reloday\\Application\\DynamoDb\\ORM" => __DIR__ . '/../../private/common/lib/application/dynamodb/orm',
    "Reloday\\Application\\ElasticSearch\\Models" => __DIR__ . '/../../private/common/lib/application/elasticsearch/models',
]);

$loader->register();



