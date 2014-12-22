<?php

namespace Kuria\Cache\Provider\Helper;

/**
 * Notes on this test:
 *
 * - not everything can be tested, since we are in a single process
 *   and testing shared vs exclusive locks would require more
 *   processes and exact timing
 */
class FileLockTest extends \PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        touch(self::getTestFilePath());
    }

    public static function tearDownAfterClass()
    {
        unlink(self::getTestFilePath());
    }

    private static function getTestFilePath()
    {
        return __DIR__ . '/../../fixtures/file_lock_test_file';
    }

    public function testAcquireExclusiveBlocking()
    {
        $lock = null;

        try {
            $handle = fopen(self::getTestFilePath(), 'r');
            $lock = FileLock::acquireExclusive($handle, true);

            $this->assertInstanceOf(__NAMESPACE__ . '\\FileLock', $lock);
            $this->assertTrue($lock->isExclusive());

            $lock->release();
        } catch (\Exception $e) {
            if (null !== $lock) {
                $lock->release();
            }

            throw $e;
        }
    }

    public function testAcquireExclusiveNonBlocking()
    {
        $lock = null;

        try {
            $handle = fopen(self::getTestFilePath(), 'r');
            $lock = FileLock::acquireExclusive($handle, false);

            $this->assertInstanceOf(__NAMESPACE__ . '\\FileLock', $lock);
            $this->assertTrue($lock->isExclusive());

            $lock->release();
        } catch (\Exception $e) {
            if (null !== $lock) {
                $lock->release();
            }

            throw $e;
        }
    }

    public function testAcquireSharedBlocking()
    {
        $lock = null;

        try {
            $handle = fopen(self::getTestFilePath(), 'r');
            $lock = FileLock::acquireShared($handle, true);

            $this->assertInstanceOf(__NAMESPACE__ . '\\FileLock', $lock);
            $this->assertFalse($lock->isExclusive());

            $lock->release();
        } catch (\Exception $e) {
            if (null !== $lock) {
                $lock->release();
            }

            throw $e;
        }
    }

    public function testAcquireSharedNonBlocking()
    {
        $lock = null;

        try {
            $handle = fopen(self::getTestFilePath(), 'r');
            $lock = FileLock::acquireShared($handle, false);

            $this->assertInstanceOf(__NAMESPACE__ . '\\FileLock', $lock);
            $this->assertFalse($lock->isExclusive());

            $lock->release();
        } catch (\Exception $e) {
            if (null !== $lock) {
                $lock->release();
            }

            throw $e;
        }
    }

    public function testRelease()
    {
        $lock = null;

        try {
            $handle = fopen(self::getTestFilePath(), 'r');
            $lock = FileLock::acquireExclusive($handle);

            $this->assertInstanceOf(__NAMESPACE__ . '\\FileLock', $lock);
            $this->assertFalse($lock->isReleased());

            $this->assertTrue($lock->release());
            $this->assertTrue($lock->release());
            $this->assertTrue($lock->isReleased());
        } catch (\Exception $e) {
            if (null !== $lock) {
                $lock->release();
            }

            throw $e;
        }
    }

    public function testReleaseOnShutdown()
    {
        $lock = null;

        try {
            $handle = fopen(self::getTestFilePath(), 'r');
            $lock = FileLock::acquireExclusive($handle);

            $this->assertInstanceOf(__NAMESPACE__ . '\\FileLock', $lock);
            $this->assertFalse($lock->isReleased());

            FileLock::releaseOnShutdown();

            $this->assertTrue($lock->isReleased());
        } catch (\Exception $e) {
            if (null !== $lock) {
                $lock->release();
            }

            throw $e;
        }
    }
}
