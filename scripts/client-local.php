<?php

// Explicit, loopback-only client test launcher. Never a production entry point.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
require __DIR__.'/client-local/environment.php';

function ggcClientAccounts(): void
{
    $data=ggcClientMetadata();
    echo "LOCAL SYNTHETIC ACCOUNTS ONLY — http://127.0.0.1:8000/login\n";
    foreach (['ADMIN'=>'Super Admin','DEPARTMENT'=>'Department Admin','OFFICE'=>'Registrar Office Admin','LIBRARY'=>'Library Office Admin','STUDENT6'=>'Student (new submissions)'] as $suffix=>$role) {
        echo $role.': DEMO-'.$data['installation'].'-'.$suffix."\n";
    }
    echo 'Local-only password for these accounts: '.$data['password']."\nKeep this local file/output private. No account is created on any other system.\n";
}

function ggcClientLock()
{
    [, $runtime]=ggcClientPaths(); ggcClientMetadata();
    if (is_link($runtime.'/running.lock')) throw new RuntimeException('Linked runtime lock refused.');
    $lock=fopen($runtime.'/running.lock','c+');
    if (!$lock || !flock($lock, LOCK_EX|LOCK_NB)) throw new RuntimeException('The local system is running. Stop it before setup/reset/start.');
    return $lock;
}

function ggcClientClearDirectory(string $path): void
{
    if (is_link($path)) { unlink($path); return; }
    if (is_dir($path)) {
        foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) ggcClientClearDirectory($entry->getPathname());
        rmdir($path);
    } elseif (file_exists($path)) unlink($path);
}

function ggcClientSetup(): void
{
    [$root,$runtime]=ggcClientPaths();
    if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('Enable the PHP pdo_sqlite extension first.');
    if (!is_file($root.'/vendor/autoload.php')) throw new RuntimeException('Run composer install --classmap-authoritative first.');
    if (file_exists($runtime) || is_link($runtime)) throw new RuntimeException('Local runtime already exists. Use accounts/start, or reset --confirm-disposable after stopping. Setup never reuses an existing database.');
    mkdir($runtime,0700,true);
    foreach (['bootstrap','storage/app/private','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs'] as $dir) mkdir($runtime.'/'.$dir,0700,true);
    $metadata=['purpose'=>'ggc-client-local-disposable-v1','source_root'=>$root,'installation'=>bin2hex(random_bytes(6)),
        'app_key'=>'base64:'.base64_encode(random_bytes(32)), 'password'=>'Local!'.bin2hex(random_bytes(16))];
    file_put_contents($runtime.'/local-only.json',json_encode($metadata,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    chmod($runtime.'/local-only.json',0600); touch($runtime.'/.env'); touch($runtime.'/database.sqlite'); chmod($runtime.'/database.sqlite',0600);
    $lock=ggcClientLock();
    $app=ggcClientBoot();
    if (\Illuminate\Support\Facades\Schema::hasTable('users')) throw new RuntimeException('Refusing an existing database.');
    \Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
    require __DIR__.'/client-local/fixtures.php'; ggcClientSeed();
    flock($lock,LOCK_UN); fclose($lock);
    echo "Disposable local setup complete. No existing database was used.\n"; ggcClientAccounts();
}

try {
    $command=$argv[1]??'help';
    [$root,$runtime]=ggcClientPaths();
    if ($command==='help') {
        echo "GGC client localhost testing\nSetup: php scripts/client-local.php setup\nStart: php scripts/client-local.php start\nShow accounts: php scripts/client-local.php accounts\nReset ONLY this demo: php scripts/client-local.php reset --confirm-disposable\nSee docs/CLIENT_LOCAL_TESTING.md.\n"; exit;
    }
    if ($command==='setup') { ggcClientSetup(); exit; }
    if ($command==='accounts') { ggcClientAccounts(); exit; }
    if ($command==='reset') {
        if (($argv[2]??'')!=='--confirm-disposable') throw new RuntimeException('Reset requires --confirm-disposable and affects only this marked local demonstration database.');
        ggcClientMetadata(); $lock=ggcClientLock();
        // The canonical, owned runtime has been verified. Never follow linked children.
        foreach (new FilesystemIterator($runtime, FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->getFilename()!=='running.lock') ggcClientClearDirectory($entry->getPathname());
        }
        flock($lock,LOCK_UN); fclose($lock); unlink($runtime.'/running.lock'); rmdir($runtime);
        ggcClientSetup(); exit;
    }
    if ($command==='worker' || $command==='scheduler' || $command==='tick') {
        $app=ggcClientBoot();
        if ($command==='worker') { \Illuminate\Support\Facades\Artisan::call('queue:work',['connection'=>'database','--queue'=>'default,imports','--sleep'=>1,'--tries'=>3,'--timeout'=>120]); exit; }
        do {
            \Illuminate\Support\Facades\Artisan::call('schedule:run');
            if ($command==='tick') break;
            sleep(60);
        } while (true);
        exit;
    }
    if ($command==='start') {
        $lock=ggcClientLock();
        if (!is_file($root.'/public/build/manifest.json')) throw new RuntimeException('Run npm ci and npm run build before starting.');
        // Fail before launching any children if another service owns the loopback port.
        $socket=@stream_socket_server('tcp://127.0.0.1:8000',$errorCode,$errorMessage);
        if (!$socket) throw new RuntimeException('Port 8000 is in use. Stop the other local server, then retry.');
        fclose($socket);
        $children=[];
        register_shutdown_function(function () use (&$children,$lock) {
            foreach ($children as $child) { $status=proc_get_status($child); if ($status['running']) proc_terminate($child); }
            foreach ($children as $child) proc_close($child);
            flock($lock,LOCK_UN); fclose($lock);
        });
        $running=true;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT,function () use (&$running) { $running=false; });
            pcntl_signal(SIGTERM,function () use (&$running) { $running=false; });
        }
        $commands=[[PHP_BINARY,'-S','127.0.0.1:8000','-t',$root.'/public',__DIR__.'/client-local/router.php'],
            [PHP_BINARY,__FILE__,'worker'],[PHP_BINARY,__FILE__,'scheduler']];
        foreach ($commands as $args) {
            $child=proc_open($args,[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,$root,null,['bypass_shell'=>true]);
            if (!is_resource($child)) throw new RuntimeException('Could not start a local process. Enable PHP proc_open.');
            $children[]=$child;
        }
        echo "\nGGC LOCAL TESTING: http://127.0.0.1:8000/login\nWebsite + local queue + local scheduler are running. Ctrl-C stops them.\n";
        while ($running) {
            foreach ($children as $child) if (!proc_get_status($child)['running']) throw new RuntimeException('A local process stopped. Read the terminal error above, stop, and restart.');
            sleep(1);
        }
        exit;
    }
    throw new RuntimeException('Unknown command. Run php scripts/client-local.php help.');
} catch (Throwable $error) {
    fwrite(STDERR,'Local testing: '.$error->getMessage()."\n"); exit(1);
}
