<?php

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    $classLoader = require __DIR__ . '/../vendor/autoload.php';
    $classLoader->addPsr4('Kuria\\Cache\\', __DIR__);
}
