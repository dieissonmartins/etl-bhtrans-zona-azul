<?php

# $application = parse_ini_file(__DIR__ . "/.env");

try {

    $scriptPath = dirname(__FILE__) . '/../vendor/autoload.php';
    require_once $scriptPath;

    $class = $argv[1];
    $className = "Scripts\\$class";

    $instance = new $className();

    $instance->runScript();

} catch (\Throwable $e) {

    echo "Error loading autoloader: " . $e->getMessage() . PHP_EOL;

    exit(1);
}
