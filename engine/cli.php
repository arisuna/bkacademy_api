<?php
date_default_timezone_set("UTC");

use Phalcon\Di\FactoryDefault\Cli as CliDi;
use Phalcon\Cli\Console as ConsoleApp;

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/engine');
define('DS', DIRECTORY_SEPARATOR);
define("_MPDF_TTFONTDATAPATH", BASE_PATH . '/cache' . DIRECTORY_SEPARATOR);
define("_MPDF_TEMP_PATH", BASE_PATH . '/cache' . DIRECTORY_SEPARATOR);

include BASE_PATH . '/vendor/autoload.php';

/**
 * The FactoryDefault Dependency Injector automatically registers the services that
 * provide a full stack framework. These default services can be overidden with custom ones.
 */

$dotenv = new \Dotenv\Dotenv(__DIR__ . '/../');
$dotenv->load();


if (getenv('ENVIR') == 'PROD') {
    \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'production']);
} elseif (getenv('ENVIR') == 'LOCAL') {
    \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'development']);
} elseif (getenv('ENVIR') == 'PREPROD') {
    \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'staging']);
} elseif (getenv('ENVIR') == 'THINHDEV') {
    \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'thinhdev']);
} elseif (getenv('ENVIR') == 'THUYDEV') {
    \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'thuydev']);
} elseif (getenv('ENVIR') == 'MINHDEV') {
    \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'minhdev']);
}

$di = new CliDi();

/**
 * Include Autoloader
 */
include APP_PATH . '/config/loader.php';

/**
 * Include Services
 */
include APP_PATH . '/config/services.php';

/**
 * Get config service for use in inline setup below
 */
$config = $di->getConfig();

/**
 * Create a console application
 */
$console = new ConsoleApp($di);

/**
 * Process the console arguments
 */
$arguments = [];

foreach ($argv as $k => $arg) {
    if ($k == 1) {
        $arguments['task'] = $arg;
    } elseif ($k == 2) {
        $arguments['action'] = $arg;
    } elseif ($k >= 3) {
        $arguments['params'][] = $arg;
    }
}

try {

    $console->handle($arguments);
    if (isset($config["printNewLine"]) && $config["printNewLine"]) {
        echo PHP_EOL;
    }

} catch (\Throwable $exception) {
    \Sentry\captureException($exception);
    echo $exception->getMessage() . PHP_EOL;
    echo $exception->getTraceAsString() . PHP_EOL;
    exit(255);
} catch (\Phalcon\Exception $exception) {
    \Sentry\captureException($exception);
    echo $exception->getMessage() . PHP_EOL;
    echo $exception->getTraceAsString() . PHP_EOL;
    exit(255);
} catch (\PDOException $exception) {
    \Sentry\captureException($exception);
    echo $exception->getMessage() . PHP_EOL;
    echo $exception->getTraceAsString() . PHP_EOL;
    exit(255);
} catch (\Exception $exception) {
    \Sentry\captureException($exception);
    echo $exception->getMessage() . PHP_EOL;
    echo $exception->getTraceAsString() . PHP_EOL;
    exit(255);
}
