<?php
namespace Deployer;
/********** dev host ************/
task('deploy:dev',[
    'deploy:setup_dev',
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'upload:nhuandev',
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
])->onHosts('dev.reloday.com');


task('upload:dev', function () {
    $host = host('dev.reloday.com');
    writeln('<info>Upload...</info>');
    $deployPath = get('deploy_path');

    $appFiles = [
        '.configuration/env/dev.env' => '.env',
    ];
    foreach ($appFiles as  $fileOrigin => $fileDestination) {
        upload($fileOrigin, "{$deployPath}/shared/{$fileDestination}");
    }
    writeln('<info>Upload is done.</info>');
});


task('deploy:setup_dev', function () {
    $host = host('dev.reloday.com');
});

task('build:dev', implode(';',[
    'mkdir app/cache',
    'mkdir app/cache/emails',
    '/usr/bin/php app/cli.php migration run --module=basic',
    'mkdir cache',
    'sudo chmod -R 0777 app/cache',
    'sudo chmod -R 0777 cache',
    'sudo service php-fpm restart',
    'sudo service nginx restart',
    'bower install',
    'bower update',
    'npm install',
    'gulp setup-libraries',
    'gulp build:dev',
    'php engine/cli.php lang main'
]));


after('deploy:dev', 'build:dev');

?>