<?php declare(strict_types=1);

use phpmock\phpunit\PHPMock;

require __DIR__ . '/../vendor/autoload.php';

// workaround for https://bugs.php.net/bug.php?id=64346
$namespaceToMockedFunctions = [
    'Kuria\\Cache\\Driver\\Filesystem' => ['time'],
    'Kuria\\Cache\\Driver\\Filesystem\\Entry' => ['time', 'unlink'],
    'Kuria\\Cache\\Driver\\Filesystem\\Entry\\File' => ['flock'],
    'Kuria\\Cache\\Driver\\Memcached' => ['time'],
    'Kuria\\Cache\\Driver\\Apcu' => [
        'apcu_exists',
        'apcu_fetch',
        'apcu_store',
        'apcu_add',
        'apcu_delete',
        'apcu_clear_cache',
    ],
];

foreach ($namespaceToMockedFunctions as $namespace => $mockedFunctions) {
    foreach ($mockedFunctions as $mockedFunction) {
        PHPMock::defineFunctionMock($namespace, $mockedFunction);
    }
}

unset($mockedFunctions);
