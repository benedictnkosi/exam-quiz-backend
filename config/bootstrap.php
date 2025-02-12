<?php

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

require_once "vendor/autoload.php";

// Create a simple "default" Doctrine ORM configuration for Annotations
$isDevMode = true;
$proxyDir = null;
$cache = null;
$useSimpleAnnotationReader = false;

// Get your database connection parameters from .env
$dbParams = [
    'driver'   => 'pdo_mysql',
    'host'     => '127.0.0.1',
    'user'     => 'root',
    'password' => 'Nhlaka@02',
    'dbname'   => 'app',
];

$config = Setup::createAnnotationMetadataConfiguration(
    [__DIR__."/../src"],
    $isDevMode,
    $proxyDir,
    $cache,
    $useSimpleAnnotationReader
);

$entityManager = EntityManager::create($dbParams, $config);

return $entityManager; 