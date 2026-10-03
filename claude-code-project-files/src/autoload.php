<?php
// Every entry point (web, CLI scripts, tests) loads this file first, so the app's
// clock is set here. Database::getConnection() makes MariaDB use the same offset.
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Manila');

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});
