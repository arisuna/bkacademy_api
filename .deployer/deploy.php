<?php
namespace Deployer;

require 'recipe/common.php';

set('use_ssh2', true);

set('real_hostname', function () {
    return Task\Context::get()->getHost()->getHostname();
});

set('default_timeout', 3600);

// Project name
set('application', 'my_project');

// Project repository
set('repository', 'git@bitbucket.org:renaissancio/smxd_api.git');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);

set('keep_releases', 2);

// Shared files/dirs between deploys 
set('shared_files', [
    ".env"
]);

set('shared_dirs', [
    "cache",
    "tmp"
]);

// Writable dirs by web server 
set('writable_dirs', ["cache"]);
set('allow_anonymous_stats', false);

set('control_path', "~/.ssh/");

// Hosts
inventory('hosts.yml');
// Tasks

set('default_stage', 'dev');

desc('Deploy your project');

task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
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
]);

// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

require __DIR__ . '/../vendor/deployer/recipes/recipe/slack.php';

// require __DIR__ . "/config.php";

foreach (glob(__DIR__ . "/tasks/*.php") as $filename) {
    require $filename;
}
?>
