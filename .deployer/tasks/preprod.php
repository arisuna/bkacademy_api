<?php

namespace Deployer;

$allowedHosts = ['api-preprod.sanmayxaydung.com'];

$prefixDirFile = '../';

/********** thinhdev ************/
task('deploy:preprod', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'upload:preprod',
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
])->onHosts();

task('update:preprod', [
    'begin:preprod',
    'upload:preprod',
    'push:preprod',
    'success',
    'end:preprod'
])->onHosts($allowedHosts);


task('upload:preprod', function () use ($prefixDirFile) {
    writeln('<info>Upload...</info>');
    $deployPath = get('deploy_path');
    // $appFiles = [
    //     '.configuration/supervisord/preprod.conf' => 'sanmayxaydung.conf',
    // ];
    if (Task\Context::get()->getHost()->getHostname() == 'thinhdev-job-2.reloday.com') {
        $appFiles = [
            '.configuration/supervisord/preprod.conf' => 'sanmayxaydung.conf',
        ];
        foreach ($appFiles as $fileOrigin => $fileDestination) {
            upload($prefixDirFile . $fileOrigin, "{$deployPath}/shared/{$fileDestination}");
        }
    }
    writeln('<info>Upload is done.</info>');
})->onHosts($allowedHosts);


task('build:preprod', implode(';', [
    '/usr/bin/php engine/cli_with_aws.php main flushcache',
]))->onHosts($allowedHosts);

task('migration:preprod', implode(';', [
    '/usr/bin/php app/cli.php migration run --module=basic',
]))->onHosts($allowedHosts);

task('cli:preprod', implode(';', [
    '/usr/bin/php engine/cli_with_aws.php main flushcache',
]))->onHosts($allowedHosts);

task('cache:preprod', implode(';', [
    '/usr/bin/php engine/cli_with_aws.php main flushcache',
    '/usr/bin/php engine/cli.php report-log clear',
]))->onHosts($allowedHosts);

$supervisordRun = function () {
    writeln('<info>Upload super thinhdev.conf...</info>');
    run('sudo service supervisord stop');
    run('sudo ln -sfn ' . get('deploy_path') . "/shared/reloday.conf /etc/supervisord/conf.d/thinhdev.conf");
    run('sudo service supervisord start');
};

task('job:preprod', $supervisordRun)->onHosts(['thinhdev-job.reloday.com', 'thinhdev-job-1.reloday.com','thinhdev-job-2.reloday.com']);

task('job:preprod:stop', function () {
    run('sudo service supervisord stop');
})->onHosts(['thinhdev-job.reloday.com', 'thinhdev-job-1.reloday.com', 'thinhdev-job-2.reloday.com']);

task('job:preprod:start', function () {
    run('sudo service supervisord start');
})->onHosts(['thinhdev-job.reloday.com', 'thinhdev-job-1.reloday.com', 'thinhdev-job-2.reloday.com']);

task('push:preprod', implode(';', [
    'cd {{release_path}} && sudo chown -R centos:centos .',
    'git reset --hard origin/preprod',
    'git pull'
]))->onHosts($allowedHosts);

task('restart:preprod', function () {
    if (Task\Context::get()->getHost()->getHostname() == 'thinhdev-job.reloday.com' || Task\Context::get()->getHost()->getHostname() == 'thinhdev-job-1.reloday.com' || Task\Context::get()->getHost()->getHostname() == 'thinhdev-job-2.reloday.com') {
        run('sudo service supervisord stop');
        run('sudo ln -sfn ' . get('deploy_path') . "/shared/reloday.conf /etc/supervisord/conf.d/thinhdev.conf");
        run('sudo service supervisord start');
    }
})->onHosts($allowedHosts);

task('begin:preprod', function () {
    if (Task\Context::get()->getHost()->getHostname() == 'thinhdev-job.reloday.com' || Task\Context::get()->getHost()->getHostname() == 'thinhdev-job-1.reloday.com' || Task\Context::get()->getHost()->getHostname() == 'thinhdev-job-2.reloday.com') {
        run('sudo service supervisord stop');
    }
})->onHosts($allowedHosts);

task('end:preprod', function () {
    if (Task\Context::get()->getHost()->getHostname() == 'thinhdev-job.reloday.com' || Task\Context::get()->getHost()->getHostname() == 'thinhdev-job-1.reloday.com' || Task\Context::get()->getHost()->getHostname() == 'thinhdev-job-2.reloday.com') {
        run('sudo service supervisord start');
    }
})->onHosts($allowedHosts);

task('env:preprod', function () {
    if (Task\Context::get()->getHost()->getHostname() == 'production-fk1.relotalent.com' || Task\Context::get()->getHost()->getHostname() == 'production-fk2.relotalent.com') {
        $deployPath = get('deploy_path');
        run("cd  {$deployPath}/current && /usr/bin/php engine/cli_with_aws.php secret-manager importEnv --name=relotalent/api/preprod/thinhdev/env/v1 --region=eu-central-1");
    } else {
        $deployPath = get('deploy_path');
        run("cd  {$deployPath}/current && /usr/bin/php engine/cli_with_aws.php secret-manager importEnv --name=smxd/api/preprod --region=ap-southeast-1");
    }
})->onHosts($allowedHosts);

task('test:preprod', function () use ($prefixDirFile) {
    writeln('<info>Upload Code</info>');
    $deployPath = get('deploy_path');
    $appFiles = [
//    'private/common/lib/application/models/InvoiceQuoteExt.php',
//   'private/modules/app/controllers/api/AuthController.php',
//   'private/common/lib/application/models/ApplicationModel.php',
//   'private/modules/api/controllers/api/ProfileController.php',
//   'private/modules/api/controllers/api/AuthController.php',
//   'private/modules/app/controllers/api/AuthController.php',
//   'private/modules/api/controllers/api/BaseController.php',
//   'private/modules/api/controllers/api/AttachmentsV2Controller.php',
//   'private/modules/api/controllers/api/AttachmentsController.php',
//   'private/modules/api/controllers/api/IndexController.php',
//   'private/modules/api/controllers/ModuleApiController.php',
//   'private/modules/api/models/ModuleModel.php',
//   'private/modules/api/controllers/api/ProfileController.php',
//   'private/common/lib/application/models/MediaAttachmentExt.php',
//   'private/modules/api/controllers/api/ProfileController.php',
   'private/modules/media/controllers/api/AvatarController.php',
   'private/modules/api/controllers/api/ObjectImageController.php',
//    'private/modules/api/models/User.php',
//    'private/modules/api/controllers/api/SettingController.php',
//    'private/common/lib/application/models/UserExt.php'
//    'private/modules/api/models/BusinessOrder.php',
//    'private/modules/api/models/Product.php',
//    'private/modules/api/controllers/api/BusinessOrderController.php',
//    'private/common/lib/application/models/BusinessOrderExt.php',
//    'engine/tasks/MainTask.php'
//    'private/modules/business-api-gms/models/Bill.php'
//    'private/modules/business-api-gms/help/TransactionHelper.php'
//    'private/modules/business-api-gms/help/RequestHelper.php',
//    'private/common/lib/application/models/InvoiceQuoteExt.php'
    ];
    foreach ($appFiles as $fileDestination) {
        upload($prefixDirFile . $fileDestination, "{$deployPath}/current/{$fileDestination}");
    }
    writeln('<info>Upload is done.</info>');
})->onHosts($allowedHosts);


task('composer:dev', implode(';', [
    'composer update',
]))->onHosts('thinhdev.reloday.com');

task('composer:preprod', implode(';', [
    'composer update'
]))->onHosts($allowedHosts);

after('deploy:preprod', 'build:preprod');

before('update:preprod', 'upload:preprod');

after('update:preprod', 'env:preprod');

before('job:preprod', 'upload:preprod');

after('env:preprod', 'build:preprod');

before('update:preprod', 'begin:preprod');

before('deploy:preprod', 'slack:notify');

after('success', 'slack:notify:success');
?>