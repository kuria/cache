<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\Entry;

interface EntryInterface
{
    function getPath(): string;

    /**
     * Check that the entry exists and that it hasn't expired
     */
    function validate(): bool;

    function readKey(): string;

    function readData();

    /**
     * - $expirationTime is an UNIX timestamp
     * - $expirationTime = 0 means no expiration
     */
    function write(string $key, $data, int $expirationTime, bool $overwrite): void;

    function delete(): void;

    /**
     * Explicitly close the entry and free any internal resources and locks
     *
     * - it is not required to call this method. The entry instance going out of scope has the same effect.
     * - no methods other than getPath() may be called after the entry has been closed (doing so results in undefined behavior)
     */
    function close(): void;
}
