<?php

declare(strict_types=1);

// Built-in dev server (composer start): let it serve real files such as
// /assets/site.css and /app/assets/*.js directly.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if (is_string($path) && $path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}

// Don't advertise the PHP version (spec 11.2, "Information leakage").
header_remove('X-Powered-By');

/** @var callable(): \Slim\App<\Psr\Container\ContainerInterface|null> $createApp */
$createApp = require dirname(__DIR__) . '/config/bootstrap.php';
$createApp()->run();
