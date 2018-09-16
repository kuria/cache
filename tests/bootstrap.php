<?php declare(strict_types=1);

use phpmock\phpunit\PHPMock;

require __DIR__ . '/../vendor/autoload.php';

(function () {
    // workaround for https://bugs.php.net/bug.php?id=64346
    $namespaceToMockedFunctions = [
        'Kuria\\Cache\\Driver\\Filesystem\\Entry' => ['unlink'],
        'Kuria\\Cache\\Driver\\Filesystem\\Entry\\File' => ['flock'],
        'Kuria\\Cache\\Driver\\Filesystem\\PathResolver' => ['extension_loaded'],
        'Kuria\\Cache\\Driver\\Apcu' => [
            'extension_loaded',
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
})();
