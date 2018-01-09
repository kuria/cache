<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Feature;

interface CleanupInterface
{
    /**
     * Perform cleanup procedures
     */
    function cleanup(): void;
}
