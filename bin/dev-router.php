<?php

/*
 * Router for PHP's built-in web server (development only):
 *   php -S 127.0.0.1:8000 -t public bin/dev-router.php
 * Serves existing files from public/ directly and sends everything else
 * (including AssetMapper's /assets/ URLs in dev) to Symfony.
 */
$path = parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
if (is_string($path) && '/' !== $path && is_file(dirname(__DIR__).'/public'.$path)) {
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__).'/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__).'/public/index.php';
