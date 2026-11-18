<?php

function ggcClientCanonical(string $path): string|false
{
    $resolved = realpath($path);
    return $resolved === false ? false : str_replace('\\', '/', $resolved);
}

// Required only by the explicit local-testing entry points, never by production routes.
function ggcClientPaths(): array
{
    $root = ggcClientCanonical(dirname(__DIR__, 2));
    if (!$root) throw new RuntimeException('Application folder is unavailable.');
    return [$root, $root.'/storage/client-local'];
}

function ggcClientMetadata(): array
{
    [$root, $runtime] = ggcClientPaths();
    if (is_link($runtime) || ggcClientCanonical($runtime) !== $runtime) throw new RuntimeException('Run setup first; linked or unexpected runtime folders are refused.');
    $data = json_decode((string) @file_get_contents($runtime.'/local-only.json'), true);
    if (!is_array($data) || ($data['purpose'] ?? '') !== 'ggc-client-local-disposable-v1' || ($data['source_root'] ?? '') !== $root
        || !preg_match('/\A[a-f0-9]{12}\z/', $data['installation'] ?? '') || empty($data['app_key']) || empty($data['password'])) {
        throw new RuntimeException('Disposable database ownership could not be verified. Refusing to use or reset it.');
    }
    if (is_link($runtime.'/database.sqlite') || ggcClientCanonical($runtime.'/database.sqlite') !== $runtime.'/database.sqlite') {
        throw new RuntimeException('Expected a regular disposable SQLite file inside the verified local runtime.');
    }
    return $data;
}

function ggcClientBoot(): \Illuminate\Foundation\Application
{
    [$root, $runtime] = ggcClientPaths();
    $metadata = ggcClientMetadata();
    if (!is_file($root.'/vendor/autoload.php')) throw new RuntimeException('Run composer install first.');
    require_once $root.'/vendor/autoload.php';
    // Override process/server variables before bootstrap and ignore the project's .env/caches.
    $env = ['APP_NAME'=>'GGC Client Local Testing', 'APP_ENV'=>'local', 'APP_DEBUG'=>'false', 'APP_KEY'=>$metadata['app_key'],
        'APP_URL'=>'http://127.0.0.1:8000', 'APP_CONFIG_CACHE'=>$runtime.'/bootstrap/config.php',
        'APP_SERVICES_CACHE'=>$runtime.'/bootstrap/services.php', 'APP_PACKAGES_CACHE'=>$runtime.'/bootstrap/packages.php',
        'APP_ROUTES_CACHE'=>$runtime.'/bootstrap/routes.php', 'APP_EVENTS_CACHE'=>$runtime.'/bootstrap/events.php',
        'DB_CONNECTION'=>'sqlite', 'DB_DATABASE'=>$runtime.'/database.sqlite', 'DB_URL'=>'',
        'DB_QUEUE_CONNECTION'=>'sqlite', 'DB_QUEUE'=>'default', 'DB_QUEUE_RETRY_AFTER'=>'180',
        'QUEUE_CONNECTION'=>'database', 'CACHE_STORE'=>'database', 'DB_CACHE_CONNECTION'=>'sqlite', 'DB_CACHE_LOCK_CONNECTION'=>'sqlite',
        'SESSION_DRIVER'=>'file', 'SESSION_COOKIE'=>'ggc_local_'.$metadata['installation'], 'SESSION_DOMAIN'=>'',
        'SESSION_SECURE_COOKIE'=>'false', 'MAIL_MAILER'=>'array', 'LOG_CHANNEL'=>'single', 'FILESYSTEM_DISK'=>'local'];
    foreach ($env as $key=>$value) { putenv($key.'='.$value); $_ENV[$key]=$value; $_SERVER[$key]=$value; }
    $app = require $root.'/bootstrap/app.php';
    $app->useEnvironmentPath($runtime);
    $app->useStoragePath($runtime.'/storage');
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    // Explicit runtime configuration is a second boundary against inherited host settings.
    config(['database.default'=>'sqlite', 'database.connections.sqlite.url'=>null,
        'database.connections.sqlite.database'=>$runtime.'/database.sqlite', 'database.connections.sqlite.foreign_key_constraints'=>true,
        'database.connections.sqlite.busy_timeout'=>10000, 'queue.default'=>'database', 'queue.connections.database.connection'=>'sqlite',
        'queue.connections.database.queue'=>'default', 'queue.connections.database.retry_after'=>180,
        'queue.failed.database'=>'sqlite', 'cache.default'=>'database', 'cache.stores.database.connection'=>'sqlite',
        'cache.stores.database.lock_connection'=>'sqlite', 'session.driver'=>'file', 'session.files'=>$runtime.'/storage/framework/sessions',
        'session.domain'=>null, 'session.secure'=>false, 'mail.default'=>'array', 'filesystems.default'=>'local',
        'filesystems.disks.local.root'=>$runtime.'/storage/app/private', 'filesystems.disks.public.root'=>$runtime.'/storage/app/public',
        'filesystems.disks.public.url'=>'http://127.0.0.1:8000/storage']);
    \Illuminate\Support\Facades\Vite::useHotFile($runtime.'/disabled-vite-hot');
    \Illuminate\Support\Facades\DB::purge('sqlite');
    if (config('database.default') !== 'sqlite' || ggcClientCanonical(config('database.connections.sqlite.database')) !== $runtime.'/database.sqlite') {
        throw new RuntimeException('Unsafe database configuration refused.');
    }
    return $app;
}
