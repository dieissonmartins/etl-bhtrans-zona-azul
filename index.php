<?php

# $application = parse_ini_file(__DIR__ . "/.env");

try {

    require_once 'vendor/autoload.php';

    $class = $argv[1];
    $className = "Scripts\\$class";

    $instance = new $className();

    $instance->runScript();

} catch (\Throwable $e) {

    echo "Error loading autoloader: " . $e->getMessage() . PHP_EOL;

    exit(1);
}
