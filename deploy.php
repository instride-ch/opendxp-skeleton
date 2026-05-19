<?php

namespace Deployer;

require 'recipe/symfony.php';
require 'contrib/rsync.php';
require 'contrib/crontab.php';

// ─── Rsync ───────────────────────────────────────────────────────────────────

set('rsync_src', __DIR__);
set('rsync', [
    'exclude' => [
        '.git',
        '.github',
        '.ddev',
        'node_modules',
        'vendor',
        'var/cache',
        'var/log',
    ],
    'exclude-file' => false,
    'include-file'  => false,
    'filter-file'   => false,
    'filter-perdir' => false,
    'include' => [],
    'filter'  => [],
    'flags'   => 'rlz',
    'options' => ['delete-after', 'force'],
    'timeout' => 300,
]);

// ─── Shared files & directories ──────────────────────────────────────────────

add('shared_files', ['.env.local']);
add('shared_dirs', [
    'public/var',
    'var/config/portal',
    'var/classes/customlayouts',
    'var/application-logger',
    'var/admin/user-image',
    'var/bundles',
    'var/recyclebin',
    'var/versions',
    'var/sessions',
    'var/email',
]);

add('writable_dirs', ['var/classes/DataObject']);

// ─── OpenDXP settings ────────────────────────────────────────────────────────

set('web_dir', 'public');
set('keep_releases', 3);
set('writable_mode', 'acl');
set('symfony_env', 'prod');

add('migration_paths', ['OpenDxp\\Bundle\\CoreBundle']);

// ─── Supervisor (Trendhosting) ────────────────────────────────────────────────

set('supervisor:config_path', '/var/www/webroot/supervisor');
set('supervisor:group', static function () {
    return sprintf('%s-messenger', get('application'));
});

// Reads job list from .deploy/cron/{env}.crontab; silently skips when absent
set('crontab:jobs', static function () {
    $env = get('labels')['env'];
    $file = sprintf('.deploy/cron/%s.crontab', $env);

    if (!is_file($file)) {
        return [];
    }

    return array_values(array_filter(
        array_map('trim', file($file)),
        static fn ($line) => $line !== '' && !str_starts_with($line, '#')
    ));
});

// ─── Host ─────────────────────────────────────────────────────────────────────

host(getenv('CI_ENVIRONMENT_NAME'))
    ->setLabels([
        'env' => getenv('CI_ENVIRONMENT_NAME'),
    ])
    ->setSshArguments(['-o StrictHostKeyChecking=no'])
    ->set('backup', getenv('CI_ENVIRONMENT_NAME') === 'prd' && getenv('DB_BACKUP') !== 'false')
    ->set('hostname', getenv('SSH_HOST'))
    ->set('remote_user', getenv('SSH_USER'))
    ->set('http_user', getenv('HTTP_USER') ?: getenv('SSH_USER'))
    ->set('deploy_path', getenv('DEPLOY_PATH'))
    ->set('port', (int) (getenv('SSH_PORT') ?: 22))
    ->set('bin/php', getenv('PHP_BIN') ?: 'php')
    ->set('database', [
        'host' => getenv('DB_HOST'),
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME'),
        'user' => getenv('DB_USER'),
        'password' => getenv('DB_PASS'),
    ]);

// ─── Tasks ────────────────────────────────────────────────────────────────────

desc('Back up database to shared/backups/ (skipped unless env=prd)');
task('deploy:backup:db', static function () {
    if (!get('backup')) {
        writeln('<comment>Database backup skipped.</comment>');

        return;
    }

    $db = get('database');
    $backupPath = get('previous_release') . '/backups';
    $backupFile = $backupPath . '/backup-' . date('YmdHis') . '.sql.gz';

    run(sprintf('mkdir -p %s', $backupPath));
    run(sprintf(
        'MYSQL_PWD=%s mysqldump -h %s -P %d -u %s %s | gzip > %s',
        escapeshellarg($db['password']),
        escapeshellarg($db['host']),
        $db['port'],
        escapeshellarg($db['user']),
        escapeshellarg($db['name']),
        $backupFile
    ));

    writeln(sprintf('<info>Database backed up to %s</info>', $backupFile));
});

desc('Run Doctrine database migrations');
task('opendxp:migrate', static function () {
    run('cd {{release_path}} && {{console_env}} {{bin/php}} {{bin/console}} doctrine:migrations:migrate --no-interaction --allow-no-migration');
});

desc('Rebuild OpenDXP class definitions');
task('opendxp:classes-rebuild', static function () {
    run('cd {{release_path}} && {{console_env}} {{bin/php}} {{bin/console}} opendxp:deployment:classes-rebuild --create-classes --no-interaction');
});

desc('Install Symfony bundle web assets');
task('opendxp:assets-install', static function () {
    run('cd {{release_path}} && {{console_env}} {{bin/php}} {{bin/console}} assets:install --symlink --no-interaction');
});

desc('Stop Symfony Messenger workers before cache clear');
task('opendxp:messenger-stop', static function () {
    run('cd {{release_path}} && {{console_env}} {{bin/php}} {{bin/console}} messenger:stop-workers');
});

desc('Clear OpenDXP cache and warm up Symfony cache');
task('opendxp:cache-clear', static function () {
    run('cd {{release_path}} && {{console_env}} {{bin/php}} {{bin/console}} opendxp:cache:clear');
    run('cd {{release_path}} && {{console_env}} {{bin/php}} {{bin/console}} cache:warmup --no-interaction');
});

desc('Sync supervisor config and verify worker processes');
task('supervisor:sync', static function () {
    $env = get('labels')['env'];
    $localFile = sprintf('.deploy/supervisor/%s.ini', $env);

    if (!is_file($localFile)) {
        writeln(sprintf('<comment>No supervisor config for "%s", skipping.</comment>', $env));

        return;
    }

    $group = get('supervisor:group');

    upload($localFile, sprintf('%s/%s-%s.ini', get('supervisor:config_path'), $group, $env));

    run('sudo supervisorctl reread');
    run('sudo supervisorctl update');

    $status = run(sprintf("sudo supervisorctl status '%s-%s:*'", $group, $env));
    writeln($status);

    if (preg_match('/\b(FATAL|STOPPED|BACKOFF|EXITED)\b/', $status)) {
        throw error(sprintf('Supervisor processes in failed state: %s', $status));
    }
});

desc('Upload env-specific nginx config files from .deploy/nginx/');
task('deploy:nginx:config', static function () {
    $env = get('labels')['env'];
    $files = glob('.deploy/nginx/*.conf');

    if (empty($files)) {
        return;
    }

    foreach ($files as $file) {
        $basename = basename($file);
        // Skip files suffixed for a different environment (e.g. my-rule-stg.conf)
        if (preg_match('/-([^-]+)\.conf$/', $basename, $matches) && $matches[1] !== $env) {
            continue;
        }

        $target = sprintf('/etc/nginx/conf.d/custom-user.d/%s', $basename);
        writeln(sprintf('Uploading %s → %s', $file, $target));
        upload($file, $target);
    }
});

desc('Test and reload nginx');
task('deploy:nginx:reload', static function () {
    run('nginx -t && sudo service nginx reload');
});

// ─── Deploy task sequence ─────────────────────────────────────────────────────

task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:writable',
    'rsync',
    'deploy:shared',
    'deploy:vendors',
    'opendxp:migrate',
    'opendxp:classes-rebuild',
    'opendxp:assets-install',
    'opendxp:messenger-stop',
    'opendxp:cache-clear',
    'deploy:symlink',
    'crontab:sync',
    'supervisor:sync',
    'deploy:nginx:config',
    'deploy:nginx:reload',
    'deploy:unlock',
    'deploy:cleanup',
    'deploy:success',
]);

before('deploy', 'deploy:backup:db');
after('deploy:failed', 'deploy:unlock');
