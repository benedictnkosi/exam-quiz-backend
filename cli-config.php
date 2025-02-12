<?php
use Doctrine\ORM\Tools\Console\ConsoleRunner;

$entityManager = require_once __DIR__.'/config/bootstrap.php';

return ConsoleRunner::createHelperSet($entityManager); 