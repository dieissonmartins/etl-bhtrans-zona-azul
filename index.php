<?php

$application = parse_ini_file(__DIR__ . "/.env");

require_once 'vendor/autoload.php';


$class = $argv[1];
$className = "Scripts\\$class";

$instance = new $className();

$instance->runScript();