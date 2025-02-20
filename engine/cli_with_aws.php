<?php
date_default_timezone_set("UTC");

use Phalcon\Di\FactoryDefault\Cli as CliDi;
use Phalcon\Cli\Console as ConsoleApp;
use \Aws\Credentials\CredentialProvider;

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
$appConfig = $di->getShared('appConfig');

$di->setShared('aws', function () use ($appConfig) {
    $options = [
        'version' => 'latest',
        'region' => $appConfig->aws->region
    ];
    if ($appConfig->application->environment == 'LOCAL' && isset($appConfig->aws->credentials) && $appConfig->aws->credentials != '' && is_file($appConfig->aws->credentials)) {
        $options['credentials'] = \Aws\Credentials\CredentialProvider::ini('default', $appConfig->aws->credentials);
    }
    return new \Aws\Sdk($options);
});


$di->setShared('aws-eu', function () use ($appConfig) {
    $options = [
        'version' => 'latest',
        'region' => 'eu-central-1'
    ];
    if ($appConfig->application->environment == 'LOCAL' && isset($appConfig->aws->credentials) && $appConfig->aws->credentials != '' && is_file($appConfig->aws->credentials)) {
        $options['credentials'] = \Aws\Credentials\CredentialProvider::ini('default', $appConfig->aws->credentials);
    }
    return new \Aws\Sdk($options);
});

$di->setShared('awsCognitoService', function () use ($appConfig) {
    if ($appConfig->application->environment == 'LOCAL') {
        $options = [
            'version' => 'latest',
            'region' => $appConfig->aws->awsCognitoRegion
        ];
        if ($appConfig->application->environment == 'LOCAL' && isset($appConfig->aws->credentials) && $appConfig->aws->credentials != '' && is_file($appConfig->aws->credentials)) {
            $options['credentials'] = \Aws\Credentials\CredentialProvider::ini('default', $appConfig->aws->credentials);
        }
        return new \Aws\Sdk($options);
    } else {
        return new \Aws\Sdk([
            'version' => 'latest',
            'region' => $appConfig->aws->awsCognitoRegion
        ]);
    }
});

$di->setShared('filter', function () {
    $filter = new \Phalcon\Filter();
    $filter->add('email', function ($value) {
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    });
    return $filter;
});


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

}  catch (\Throwable $exception) {
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
} catch (\Exception $e) {
    \Sentry\captureException($exception);
    echo $exception->getMessage() . PHP_EOL;
    echo $exception->getTraceAsString() . PHP_EOL;
    exit(255);
}
