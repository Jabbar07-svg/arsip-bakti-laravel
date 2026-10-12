<?php

require_once __DIR__.DIRECTORY_SEPARATOR.'MariaDbTestPreflight.php';

$environment = getenv();
$environment = is_array($environment) ? $environment : [];

$projectRoot = dirname(__DIR__, 2);
Tests\Support\MariaDbTestPreflight::prepare(
    $environment,
    $projectRoot,
    $_SERVER['argv'] ?? [],
);

require_once $projectRoot.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
