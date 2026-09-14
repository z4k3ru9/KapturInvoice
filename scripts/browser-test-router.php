<?php

// Router script for the PHP built-in server, used only by
// browser-test-server.sh. Identical in behavior to the router Laravel's
// own `php artisan serve` uses (vendor/laravel/framework/src/Illuminate/
// Foundation/resources/server.php) — duplicated here rather than
// depended on by vendor path so this script doesn't invoke `artisan
// serve` at all (see browser-test-server.sh for why: it needs to pass a
// `-d memory_limit` flag that `artisan serve` has no way to forward to
// the underlying `php -S` process).

$publicPath = getcwd().'/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicPath.'/index.php';

require $publicPath.'/index.php';
