<?php

$loader = new \Phalcon\Loader();
$loader->registerDirs([
    APP_PATH . '/tasks',
    APP_PATH . '/models',
    APP_PATH . '/lib',
    APP_PATH . '/queue'
]);

$loader->registerNamespaces([
    "SMXD\\Application\\Controllers" => __DIR__ . '/../../private/common/lib/application/controllers',
    "SMXD\\Hr" => __DIR__ . '/../../private/modules/hr',
    "SMXD\\Hr\\Models" => __DIR__ . '/../../private/modules/hr/models',
    "SMXD\\Hr\\Controllers" => __DIR__ . '/../../private/modules/hr/controllers',
    "SMXD\\Hr\\Controllers\\API" => __DIR__ . '/../../private/modules/hr/controllers/api',


    "SMXD\\Gms" => __DIR__ . '/../../private/modules/gms',
    "SMXD\\Gms\\Models" => __DIR__ . '/../../private/modules/gms/models',
    "SMXD\\Gms\\Controllers" => __DIR__ . '/../../private/modules/gms/controllers',
    "SMXD\\Gms\\Controllers\\API" => __DIR__ . '/../../private/modules/gms/controllers/api',


    "SMXD\\Employees" => __DIR__ . '/../../private/modules/employees',
    "SMXD\\Employees\\Models" => __DIR__ . '/../../private/modules/employees/models',
    "SMXD\\Employees\\Controllers" => __DIR__ . '/../../private/modules/employees/controllers',
    "SMXD\\Employees\\Controllers\\API" => __DIR__ . '/../../private/modules/employees/controllers/api',
]);


$loader->register();
