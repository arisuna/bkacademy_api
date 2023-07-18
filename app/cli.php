<?php

date_default_timezone_set('Asia/Singapore');

use \Phalcon\DI\FactoryDefault\Cli as CliDI;
use \Phalcon\Cli\Console as ConsoleApp;
use \Phalcon\Loader;

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

require __DIR__ . '/../vendor/autoload.php';

$di = new CliDI();

include APP_PATH . '/config/loader.php';

/**
 * Include Services
 */
include APP_PATH . '/config/services.php';

/**
 * Get config service for use in inline setup below
 */
$config = $di->getConfig();

// Create a console application
$console = new ConsoleApp();

$console->setDI($di);

/**
 * Process the console arguments
 */

$arguments = [];

foreach ($argv as $k => $arg) {
    if ($k === 1) {
        $arguments['task'] = $arg;
    } elseif ($k === 2) {
        $arguments['action'] = $arg;
    } elseif ($k >= 3) {
        $arguments['params'][] = $arg;
    }
}

try {
    //Handle incoming arguments
    $console->handle($arguments);
} catch (\Phalcon\Exception $e) {
    echo $e->getMessage();

    exit(255);
}