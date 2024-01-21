<?php
/**
 * Change error reporting level for production use
 */
ini_set('always_populate_raw_post_data', -1);
@ini_set('upload_max_filesize', '10M');
@ini_set('post_max_size', '11M');

$GLOBALS["HTTP_RAW_POST_DATA"] = file_get_contents("php://input");
date_default_timezone_set("UTC");

ini_set('display_errors', "on");
error_reporting(E_ALL & ~E_DEPRECATED);
define('BASE_PATH', dirname(__DIR__));
define('DS', DIRECTORY_SEPARATOR);
define('APP_PATH', BASE_PATH . '/app');
define("_MPDF_TEMP_PATH", BASE_PATH . '/public/uploads' . DIRECTORY_SEPARATOR);
define("_MPDF_TTFONTDATAPATH", BASE_PATH . '/cache' . DIRECTORY_SEPARATOR);

use Phalcon\DI\FactoryDefault;

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::create(__DIR__ . '/../');
$dotenv->load();

$disable_sentry = false;

if (getenv('SENTRY_ACTIVE') == "true" && $disable_sentry == false) {
    if (getenv('ENVIR') == 'PROD') {
        \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'production']);
    } elseif (getenv('ENVIR') == 'LOCAL') {
        \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'local']);
    } elseif (getenv('ENVIR') == 'PREPROD') {
        \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'staging']);
    } elseif (getenv('ENVIR') == 'THINHDEV') {
        \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'thinhdev']);
    } elseif (getenv('ENVIR') == 'THUYDEV') {
        \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'thuydev']);
    } elseif (getenv('ENVIR') == 'MINHDEV') {
        \Sentry\init(['dsn' => getenv('SENTRY_KEY_URL'), 'environment' => 'minhdev']);
    }
    require __DIR__ . '/../private/common/lib/application/Application.php';
    try {
        $application = new SMXD\Application\Application(new FactoryDefault());
        $application->main();
    } catch (\Throwable $exception) {
        \Sentry\captureException($exception);
    } catch (\Phalcon\Exception $exception) {
        \Sentry\captureException($exception);
    } catch (\PDOException $exception) {
        echo 'A PDOException occurred: ', $e->getMessage(), $e->getTraceAsString();
        \Sentry\captureException($exception);
    } catch (\Exception $e) {
        echo 'An Exception occurred: ', $e->getMessage(), $e->getTraceAsString();
        \Sentry\captureException($exception);
    }
} else {
    require __DIR__ . '/../private/common/lib/application/Application.php';
    $application = new SMXD\Application\Application(new FactoryDefault());
    $application->main();
}
