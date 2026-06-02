<?php

namespace Deployer;

require 'recipe/symfony.php';
require 'contrib/rsync.php';
require 'contrib/crontab.php';

// ─── Rsync ───────────────────────────────────────────────────────────────────

set('rsync_src', dirname(__DIR__));
set('rsync', [
    'exclude' => [
        '.git',
        '.github',
        '.ddev',
        '.deploy',
        '.vite-hooks',
        'node_modules',
        'vendor',
        'var/cache',
        'var/log',
    ],
    'exclude-file' => false,
    'include-file' => false,
    'filter-file' => false,
    'filter-perdir' => false,
    'include' => [],
    'filter' => [],
    'flags' => 'rlz',
    'options' => ['delete-after', 'force'],
    'timeout' => 300,
]);

// ─── Shared files & directories ──────────────────────────────────────────────

add('shared_files', [
    '.env.local',
    'config/local/database.yaml',
    'var/admin/custom-logo.image',
    'var/config/admin_system_settings/admin_system_settings.yaml',
    'var/config/system_settings/system_settings.yaml',
]);
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

set('application', 'opendxp');
set('web_dir', 'public');
set('keep_releases', 3);
set('writable_mode', 'acl');
set('symfony_env', 'prod');

add('migration_paths', ['OpenDxp\\Bundle\\CoreBundle']);

// ─── Supervisor (Trendhosting) ───────────────────────────────────────────────

set('supervisor:config_path', '/var/www/webroot/supervisor');
set('supervisor:group', static fn () => sprintf('%s-messenger', get('application')));

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

// ─── Host ────────────────────────────────────────────────────────────────────

host(getenv('CI_ENVIRONMENT_NAME'))
    ->setLabels([
        'env' => getenv('CI_ENVIRONMENT_NAME'),
    ])
    ->setSshArguments(['-o StrictHostKeyChecking=no'])
    ->set('backup', getenv('CI_ENVIRONMENT_NAME') === 'prd' && getenv('DATABASE_BACKUP') !== 'false')
    ->set('hostname', getenv('SSH_HOST'))
    ->set('remote_user', getenv('SSH_USER'))
    ->set('http_user', getenv('HTTP_USER') ?: getenv('SSH_USER'))
    ->set('deploy_path', getenv('DEPLOY_PATH'))
    ->set('port', (int) (getenv('SSH_PORT') ?: 22))
    ->set('bin/php', getenv('PHP_BIN') ?: 'php')
    ->set('database', [
        'host' => getenv('DATABASE_HOST'),
        'port' => (int) (getenv('DATABASE_PORT') ?: 3306),
        'name' => getenv('DATABASE_NAME'),
        'user' => getenv('DATABASE_USER'),
        'password' => getenv('DATABASE_PASSWORD'),
    ]);

// ─── Tasks ───────────────────────────────────────────────────────────────────

desc('Render .env.local from ENV_LOCAL_TEMPLATE variable and upload to shared/.env.local');
task('deploy:env', static function () {
    $template = getenv('ENV_LOCAL_TEMPLATE');
    $content = preg_replace_callback(
        '/\$\{([A-Z0-9_]+)\}/',
        static fn ($m) => getenv($m[1]) !== false ? getenv($m[1]) : '',
        $template
    );

    $sharedPath = get('deploy_path') . '/shared';
    run(sprintf('mkdir -p %s', escapeshellarg($sharedPath)));

    $tmpFile = tempnam(sys_get_temp_dir(), 'env_local_');
    file_put_contents($tmpFile, $content);
    upload($tmpFile, $sharedPath . '/.env.local');
    unlink($tmpFile);

    writeln('<info>.env.local written to shared path.</info>');
});

desc('Back up database to shared/backups/ (skipped unless env=prd)');
task('deploy:backup:db', static function () {
    if (!get('backup')) {
        writeln('<comment>Database backup skipped.</comment>');

        return;
    }

    $db = get('database');
    $backupPath = get('deploy_path') . '/shared/backups';
    $backupTime = new \DateTime('now', new \DateTimeZone('Europe/Zurich'));
    $backupFile = $backupPath . '/backup-' . $backupTime->format('YmdHis') . '.sql.gz';

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

desc('Install OpenDXP if not already installed (idempotent via shared/.opendxp.installed marker)');
task('opendxp:install', static function () {
    $markerFile = get('deploy_path') . '/shared/.opendxp.installed';

    if (test(sprintf('[ -f %s ]', escapeshellarg($markerFile)))) {
        writeln('<comment>OpenDXP already installed, skipping.</comment>');

        return;
    }

    $db = get('database');

    // clear_cache is required, so the installer boots App\Kernel before setup_database runs.
    // Without it, Authentication::getPasswordHash() (called during admin-user creation) tries to
    // read opendxp.config from the InstallerKernel's container, which doesn't define that parameter.
    //
    // mark_migrations_as_done is intentionally omitted here: the installer spawns isolated
    // bin/console sub-processes that fail with "Access denied" on Trendhosting because MySQL
    // evaluates grants against the web-server's internal IP instead of the socket/localhost path.
    // We run those two doctrine commands directly via Deployer below, which uses the correct
    // SSH environment (with .env.local loaded) and avoids the sub-process isolation issue.
    $installationSteps = [
        'write_database_config',
        'clear_cache',
        'setup_database',
    ];

    run(sprintf(
        'MYSQL_PWD=%s mysql -h %s -P %d -u %s -e %s',
        escapeshellarg($db['password']),
        escapeshellarg($db['host']),
        $db['port'],
        escapeshellarg($db['user']),
        escapeshellarg(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $db['name'],
        )),
    ));

    run(sprintf('{{bin/php}} {{release_path}}/vendor/bin/opendxp-install --no-interaction --no-debug --only-steps=%s', implode(',', $installationSteps)), env: [
        'OPENDXP_INSTALL_MYSQL_HOST_SOCKET' => $db['host'],
        'OPENDXP_INSTALL_MYSQL_PORT' => $db['port'],
        'OPENDXP_INSTALL_MYSQL_DATABASE' => $db['name'],
        'OPENDXP_INSTALL_MYSQL_USERNAME' => $db['user'],
        'OPENDXP_INSTALL_MYSQL_PASSWORD' => $db['password'],
        'OPENDXP_INSTALL_ADMIN_USERNAME' => getenv('OPENDXP_ADMIN_USERNAME'),
        'OPENDXP_INSTALL_ADMIN_PASSWORD' => getenv('OPENDXP_ADMIN_PASSWORD'),
    ]);

    // Mark all initial migrations as executed so that opendxp:migrate finds nothing to run.
    // setup_database already created the full schema; running the migrations on top would fail
    // with "table already exists" errors.
    run('{{bin/console}} doctrine:migrations:sync-metadata-storage -q');
    run("{{bin/console}} doctrine:migrations:version --all --add --prefix='OpenDxp\\Bundle\\CoreBundle' -n");

    run(sprintf('touch %s', escapeshellarg($markerFile)));

    writeln('<info>OpenDXP installed successfully.</info>');
});

desc('Run Doctrine database migrations');
task('opendxp:migrate', static function () {
    run('{{bin/console}} doctrine:migrations:migrate --no-interaction --allow-no-migration');
});

desc('Rebuild OpenDXP class definitions');
task('opendxp:classes-rebuild', static function () {
    run('{{bin/console}} opendxp:deployment:classes-rebuild --create-classes --no-interaction');
});

desc('Install Symfony bundle web assets');
task('opendxp:assets-install', static function () {
    run('{{bin/console}} assets:install --symlink --no-interaction');
});

desc('Install enabled but not yet installed OpenDXP bundles');
task('opendxp:bundles-install', static function () {
    run('{{bin/console}} cache:clear --no-interaction --ignore-maintenance-mode');

    $output = run('{{bin/console}} opendxp:bundle:list --ignore-maintenance-mode --json --no-debug');
    $bundles = json_decode($output, true) ?? [];

    $toInstall = array_filter(
        $bundles,
        static fn (array $b) => $b['Enabled'] === true && $b['Installed'] === false && $b['Installable'] === true,
    );

    if (empty($toInstall)) {
        writeln('<comment>No bundles to install.</comment>');

        return;
    }

    foreach ($toInstall as $bundle) {
        writeln(sprintf('<info>Installing bundle: %s</info>', $bundle['Bundle']));
        run(sprintf(
            '{{bin/console}} opendxp:bundle:install %s --ignore-maintenance-mode --no-interaction --no-post-change-commands',
            escapeshellarg($bundle['Bundle']),
        ));
    }
});

desc('Stop Symfony Messenger workers before cache clear');
task('opendxp:messenger-stop', static function () {
    run('{{bin/console}} messenger:stop-workers');
});

desc('Clear OpenDXP cache and warm up Symfony cache');
task('opendxp:cache-clear', static function () {
    run('{{bin/console}} opendxp:cache:clear');
    run('{{bin/console}} cache:warmup --no-interaction');
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

    $status = run(sprintf("sudo supervisorctl status '%s-%s:*' || true", $group, $env));
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

// ─── Deploy task sequence ────────────────────────────────────────────────────

task('deploy', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'deploy:writable',
    'rsync',
    'deploy:env',
    'deploy:shared',
    'deploy:vendors',
    'opendxp:install',
    'opendxp:migrate',
    'opendxp:classes-rebuild',
    'opendxp:bundles-install',
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
