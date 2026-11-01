<?php

// CLI release check: no Laravel bootstrap, environment read or database access.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
try {
    $loader = require $root.'/vendor/autoload.php';
    if (! $loader->isClassMapAuthoritative()) {
        throw new RuntimeException('Generate an authoritative autoloader before running this release check.');
    }
    foreach ([App\Support\PositionalClassValues::class, App\Jobs\SendOfficeEvaluationReminder::class] as $class) {
        if (! isset($loader->getClassMap()[$class]) || ! class_exists($class)) {
            throw new RuntimeException('Required application class is missing from the authoritative map.');
        }
        $relative = str_replace('\\', '/', $class);
        $expected = $root.'/app/'.substr($relative, strlen('App/')).'.php';
        if (realpath((new ReflectionClass($class))->getFileName()) !== realpath($expected)) {
            throw new RuntimeException('Application class resolved outside its expected source file.');
        }
    }
    if (App\Support\PositionalClassValues::split('GE123,IT456') !== ['GE123', 'IT456']) {
        throw new RuntimeException('Positional parser smoke check failed.');
    }
    echo "PASS: authoritative loading resolves required application classes from the expected source.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: '.$error->getMessage().PHP_EOL);
    exit(1);
}
