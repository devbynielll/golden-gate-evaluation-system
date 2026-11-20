<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

class ClientLocalCachePathsTest extends TestCase
{
    private const CACHES = [
        'APP_CONFIG_CACHE' => ['getCachedConfigPath', 'config.php'],
        'APP_SERVICES_CACHE' => ['getCachedServicesPath', 'services.php'],
        'APP_PACKAGES_CACHE' => ['getCachedPackagesPath', 'packages.php'],
        'APP_ROUTES_CACHE' => ['getCachedRoutesPath', 'routes.php'],
        'APP_EVENTS_CACHE' => ['getCachedEventsPath', 'events.php'],
    ];

    private function verifyPaths(string $runtime, bool $demonstrateDriveBug = false): void
    {
        require_once dirname(__DIR__, 2).'/scripts/client-local/environment.php';
        $previousContainer = Container::getInstance();
        $previous = [];
        foreach (self::CACHES as $key => [$method, $file]) {
            $previous[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            $path = $runtime.'/bootstrap/'.$file;
            putenv($key.'='.$path); $_ENV[$key] = $path; $_SERVER[$key] = $path;
        }
        try {
            // Real framework path resolution only; no kernel, filesystem writes or DB bootstrap.
            $app = new Application('/synthetic-project-root');
            if ($demonstrateDriveBug) {
                $this->assertNotSame($runtime.'/bootstrap/config.php', $app->getCachedConfigPath());
            }
            \ggcClientRegisterCachePrefix($app, $runtime);
            foreach (self::CACHES as [$method, $file]) {
                $this->assertSame($runtime.'/bootstrap/'.$file, $app->$method());
            }
        } finally {
            foreach ($previous as $key => [$process, $env, $server]) {
                putenv($process === false ? $key : $key.'='.$process);
                if ($env === null) unset($_ENV[$key]); else $_ENV[$key] = $env;
                if ($server === null) unset($_SERVER[$key]); else $_SERVER[$key] = $server;
            }
            Container::setInstance($previousContainer);
        }
    }

    public function test_windows_drive_cache_paths_remain_absolute_for_every_runtime_cache(): void
    {
        foreach (['C:/GGC Client/storage/client-local', 'D:/College/Test/storage/client-local', 'c:/Demo/storage/client-local'] as $runtime) {
            $this->verifyPaths($runtime, true);
        }
    }

    public function test_unix_and_unc_cache_paths_keep_their_existing_absolute_resolution(): void
    {
        foreach (['/tmp/ggc-test/storage/client-local', '//server/share/GGC/storage/client-local'] as $runtime) {
            $this->verifyPaths($runtime);
        }
    }
}
