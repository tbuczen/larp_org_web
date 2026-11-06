<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Build a fresh test database (migrations + fixtures) once per test run.
// Set SKIP_DB_RESET=1 to reuse the existing one.
if ('test' === ($_SERVER['APP_ENV'] ?? null) && !($_SERVER['SKIP_DB_RESET'] ?? false)) {
    $console = escapeshellarg(dirname(__DIR__).'/bin/console');
    // SQLite creates the file on first connection, and its platform can't list
    // databases, so this step may fail there; it only matters for MySQL.
    exec(sprintf('php %s doctrine:database:create --if-not-exists --env=test --quiet 2>&1', $console));
    foreach ([
        'doctrine:schema:drop --full-database --force',
        'doctrine:migrations:migrate --no-interaction',
        'doctrine:fixtures:load --no-interaction',
    ] as $command) {
        passthru(sprintf('php %s %s --env=test --quiet', $console, $command), $exitCode);
        if (0 !== $exitCode) {
            throw new RuntimeException(sprintf('Test database setup failed at "%s".', $command));
        }
    }
}
