<?php

$loader = new \Phalcon\Loader();
$loader->registerDirs([
    APP_PATH . '/tasks',
    APP_PATH . '/models',
    APP_PATH . '/lib',
    APP_PATH . '/queue'
]);

$loader->registerNamespaces([
    "Reloday\\Application\\Controllers" => __DIR__ . '/../../private/common/lib/application/controllers',
    "Reloday\\Hr" => __DIR__ . '/../../private/modules/hr',
    "Reloday\\Hr\\Models" => __DIR__ . '/../../private/modules/hr/models',
    "Reloday\\Hr\\Controllers" => __DIR__ . '/../../private/modules/hr/controllers',
    "Reloday\\Hr\\Controllers\\API" => __DIR__ . '/../../private/modules/hr/controllers/api',


    "Reloday\\Gms" => __DIR__ . '/../../private/modules/gms',
    "Reloday\\Gms\\Models" => __DIR__ . '/../../private/modules/gms/models',
    "Reloday\\Gms\\Controllers" => __DIR__ . '/../../private/modules/gms/controllers',
    "Reloday\\Gms\\Controllers\\API" => __DIR__ . '/../../private/modules/gms/controllers/api',


    "Reloday\\Employees" => __DIR__ . '/../../private/modules/employees',
    "Reloday\\Employees\\Models" => __DIR__ . '/../../private/modules/employees/models',
    "Reloday\\Employees\\Controllers" => __DIR__ . '/../../private/modules/employees/controllers',
    "Reloday\\Employees\\Controllers\\API" => __DIR__ . '/../../private/modules/employees/controllers/api',
]);


$loader->register();
