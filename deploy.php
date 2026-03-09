<?php
/**
 * Libas TMS - Deployment Configuration
 *
 * Run via SSH: php deploy.php
 * Or via cPanel Terminal
 */

$config = [
    'app_url'    => 'https://libas.saifypos.org',
    'db_name'    => 'saifyposorg_libas',
    'db_user'    => 'saifyposorg_libas',
    'db_pass'    => 'CHANGE_THIS',  // Set your cPanel DB password here
    'db_host'    => '127.0.0.1',
    'db_port'    => '3306',
];

echo "=== Libas TMS Deployment ===\n\n";

// Step 1: Generate .env if missing
if (!file_exists(__DIR__ . '/.env')) {
    echo "[1] Creating .env file...\n";
    $env = <<<ENV
APP_NAME="Libas TMS"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL={$config['app_url']}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST={$config['db_host']}
DB_PORT={$config['db_port']}
DB_DATABASE={$config['db_name']}
DB_USERNAME={$config['db_user']}
DB_PASSWORD={$config['db_pass']}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@saifypos.org"
MAIL_FROM_NAME="\${APP_NAME}"

VITE_APP_NAME="\${APP_NAME}"
ENV;

    file_put_contents(__DIR__ . '/.env', $env);
    echo "  .env created\n";
} else {
    echo "[1] .env already exists - skipping\n";
}

// Step 2: Generate APP_KEY
echo "[2] Generating APP_KEY...\n";
passthru('php artisan key:generate --force 2>&1');

// Step 3: Run migrations
echo "\n[3] Running migrations...\n";
passthru('php artisan migrate --force 2>&1');

// Step 4: Seed database
echo "\n[4] Seeding database...\n";
passthru('php artisan db:seed --force 2>&1');

// Step 5: Storage link
echo "\n[5] Creating storage symlink...\n";
passthru('php artisan storage:link --force 2>&1');

// Step 6: Cache config & routes
echo "\n[6] Caching configuration...\n";
passthru('php artisan config:cache 2>&1');
passthru('php artisan route:cache 2>&1');
passthru('php artisan view:cache 2>&1');

// Step 7: Set permissions
echo "\n[7] Setting permissions...\n";
if (PHP_OS_FAMILY !== 'Windows') {
    passthru('chmod -R 755 storage bootstrap/cache 2>&1');
    passthru('chmod -R 775 storage/logs storage/framework 2>&1');
}

echo "\n=== Deployment Complete ===\n";
echo "Site: {$config['app_url']}\n";
echo "Login: admin@libas.test / password\n\n";
echo "IMPORTANT: Change admin password after first login!\n";
echo "IMPORTANT: Set APP_DEBUG=false in .env for production!\n";
