<?php

namespace Deployer;
/********** production ************/

$allowedHosts = ['production.relotalent.com', 'production-eu.relotalent.com', 'production-fk1.relotalent.com', 'production-fk2.relotalent.com' , 'production-public-api-1.relotalent.com', 'production-public-api-2.relotalent.com', 'production-public-api-3.relotalent.com'];

$prefixDirFile = '../';

task('deploy:production', [
    'deploy:info',
    'deploy:prepare',
    //'deploy:lock',
    'upload:production',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
])->onHosts($allowedHosts);

task('upload:production', function () use ($prefixDirFile) {
    if (Task\Context::get()->getHost()->getHostname() == 'production-eu.relotalent.com') {
        writeln('<info>Upload...</info>');
        $deployPath = get('deploy_path');
        $appFiles = [
            '.configuration/env/production-eu.env' => '.env',
        ];
        foreach ($appFiles as $fileOrigin => $fileDestination) {
            upload($prefixDirFile . $fileOrigin, "{$deployPath}/shared/{$fileDestination}");
        }
        writeln('<info>Upload is done.</info>');
    } else {
        writeln('<info>Upload...</info>');
        $deployPath = get('deploy_path');
        $appFiles = [
            //'.configuration/env/production.env' => '.env',
        ];
        foreach ($appFiles as $fileOrigin => $fileDestination) {
            upload($prefixDirFile . $fileOrigin, "{$deployPath}/shared/{$fileDestination}");
        }
        writeln('<info>Upload is done.</info>');
    }
})->onHosts($allowedHosts);

task('build:production', implode(';', [
    'mkdir app/cache',
    'mkdir app/cache/emails',
    'mkdir cache',
    'sudo chmod -R 0777 app/cache',
    'sudo chmod -R 0777 cache',
    'sudo service php-fpm restart',
    'sudo service nginx restart',
    '/usr/bin/php app/cli.php migration run --module=basic',
]))->onHosts($allowedHosts);

task('test:production', function () use ($prefixDirFile) {
    writeln('<info>Upload Code</info>');
    $deployPath = get('deploy_path');
    $appFiles = [
//        'private/modules/business-api-gms/models/Expense.php',
//        'private/modules/business-api-gms/controllers/api/ExpenseController.php',
//        'private/modules/gms/controllers/ModuleApiController.php',
//        'private/modules/api/controllers/api/VerifyController.php',
//        'private/modules/employees/controllers/api/AuthController.php',
        'private/modules/employees/models/Task.php',
        'private/modules/gms/controllers/api/SvpCompanyController.php',
        // 'private/common/lib/application/lib/Helpers.php',
//            'private/modules/gms/models/FilterConfig.php',
//            'private/modules/gms/controllers/api/ExtractDataController.php',
        // 'private/common/lib/application/lib/SequenceHelper.php'
        // //'private/common/lib/application/lib/Helpers.php'
    ];
    foreach ($appFiles as $fileDestination) {
        upload($prefixDirFile . $fileDestination, "{$deployPath}/current/{$fileDestination}");
    }
    writeln('<info>Upload is done.</info>');
})->onHosts($allowedHosts);

task('update:production', [
    'upload:production',
    'push:production',
    'cli:production',
    'success',
])->onHosts($allowedHosts);

task('push:production',
    function () {
        run('cd {{release_path}} && sudo chown -R centos:centos .');
        run('cd {{release_path}} && git reset --hard origin/{{branch}}');
        run('cd {{release_path}} && git pull');
        run('sudo service php-fpm restart');
        run('sudo service nginx restart');
    }
)->onHosts($allowedHosts);


task('cli:production', function () {
    if (Task\Context::get()->getHost()->getHostname() == 'production-fk1.relotalent.com' || Task\Context::get()->getHost()->getHostname() == 'production-fk2.relotalent.com'  || Task\Context::get()->getHost()->getHostname() == 'production-public-api-1.relotalent.com'  || Task\Context::get()->getHost()->getHostname() == 'production-public-api-2.relotalent.com'  || Task\Context::get()->getHost()->getHostname() == 'production-public-api-3.relotalent.com') {
        $deployPath = get('deploy_path');
        run("cd  {$deployPath}/current && /usr/bin/php engine/cli_with_aws.php main flushcache");
        run("cd  {$deployPath}/current && /usr/bin/php engine/cli_with_aws.php secret-manager importEnv --name=relotalent/api/production/env --region=eu-central-1");
    } else {
        $deployPath = get('deploy_path');
        run("cd  {$deployPath}/current && /usr/bin/php engine/cli_with_aws.php main flushcache");
        run("cd  {$deployPath}/current && /usr/bin/php engine/cli_with_aws.php secret-manager importEnv --name=relotalent/api/production/env --region=ap-southeast-1");
    }
})->onHosts($allowedHosts);

task('env:production', function () {
    if (Task\Context::get()->getHost()->getHostname() == 'production-fk1.relotalent.com' || Task\Context::get()->getHost()->getHostname() == 'production-fk2.relotalent.com'  || Task\Context::get()->getHost()->getHostname() == 'production-public-api-1.relotalent.com'  || Task\Context::get()->getHost()->getHostname() == 'production-public-api-2.relotalent.com'  || Task\Context::get()->getHost()->getHostname() == 'production-public-api-3.relotalent.com') {
        $deployPath = get('deploy_path');
        run("cd  {$deployPath}/current && /usr/bin/php engine/cli_with_aws.php secret-manager importEnv --name=relotalent/api/production/env --region=eu-central-1");
    } else {
        $deployPath = get('deploy_path');
        run("cd  {$deployPath}/current && /usr/bin/php engine/cli_with_aws.php secret-manager importEnv --name=relotalent/api/production/env --region=ap-southeast-1");
    }
})->onHosts($allowedHosts);

task('composer:production', implode(';', [
    'composer update',
]))->onHosts($allowedHosts);

task('cache:production', implode(';', [
    'php engine/cli_with_aws.php main flushcache',
]))->onHosts($allowedHosts);

task('migration:production', implode(';', [
    'php engine/cli_with_aws.php main flushcache',
    'php app/cli.php migration run --module=basic',
]))->onHosts($allowedHosts);


after('deploy:production', 'build:production');
after('build:production', 'cli:production');
before('update:production', 'upload:production');
after('update:production', 'cli:production');
after('cli:production', 'env:production');
after('success', 'slack:notify:success');
/********** end production ************/

?>