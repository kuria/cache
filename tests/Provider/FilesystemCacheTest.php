<?php

namespace Kuria\Cache\Provider;

use Kuria\Cache\CacheTest;

class FilesystemCacheTest extends CacheTest
{
    public function provideTestInstanceCreators()
    {
        $that = $this;

        // arguments:
        // 0: provider callback
        // 1: cache number
        // 2: storage mode
        // 3: tmp file + unlink usage 1/0
        // 4: tmp dir path / null

        return array(
            // normal storage, tmp files and unlink disabled
            array(function () use ($that) {
                $cache = new FilesystemCache($that->getCacheDir(1));
                $cache->setStorageMode(FilesystemCache::STORAGE_NORMAL);
                $cache->setUseTemporaryFiles(false);
                $cache->setUseUnlink(false);
                $cache->clear();

                return $cache;
            }, 1, FilesystemCache::STORAGE_NORMAL, false, null),

            // PHP storage, tmp files and unlink disabled
            array(function () use ($that) {
                $cache = new FilesystemCache($that->getCacheDir(2));
                $cache->setStorageMode(FilesystemCache::STORAGE_PHP);
                $cache->setUseTemporaryFiles(false);
                $cache->setUseUnlink(false);
                $cache->clear();

                return $cache;
            }, 2, FilesystemCache::STORAGE_PHP, false, null),

            // normal storage, tmp files and unlink enabled
            array(function () use ($that) {
                $cache = new FilesystemCache($that->getCacheDir(3));
                $cache->setStorageMode(FilesystemCache::STORAGE_NORMAL);
                $cache->setUseTemporaryFiles(true);
                $cache->setUseUnlink(true);
                $cache->clear();

                return $cache;
            }, 3, FilesystemCache::STORAGE_NORMAL, true, null),

            // PHP storage, tmp files and unlink enabled
            array(function () use ($that) {
                $cache = new FilesystemCache($that->getCacheDir(4));
                $cache->setStorageMode(FilesystemCache::STORAGE_PHP);
                $cache->setUseTemporaryFiles(true);
                $cache->setUseUnlink(true);
                $cache->clear();

                return $cache;
            }, 4, FilesystemCache::STORAGE_PHP, true, null),

            // custom tmp directory
            array(function () use ($that) {
                $cache = new FilesystemCache($that->getCacheDir(5));
                $cache->setStorageMode(FilesystemCache::STORAGE_NORMAL);
                $cache->setUseTemporaryFiles(true);
                $cache->setUseUnlink(true);
                $cache->setTemporaryDir($that->getCacheDir(6));
                $cache->clear();

                return $cache;
            }, 5, FilesystemCache::STORAGE_NORMAL, true, $this->getCacheDir(6)),
        );
    }

    protected function setUp()
    {
        parent::setUp();

        for ($i = 1; $i <= 6; ++$i) {
            if (!$this->clearCacheDir($i)) {
                $this->markTestIncomplete(sprintf('Could not clear test cache directory #%d', $i));
            }
        }
    }

    protected function tearDown()
    {
        parent::tearDown();

        for ($i = 1; $i <= 6; ++$i) {
            $this->clearCacheDir($i);
        }
    }

    /**
     * Get test cache directory path
     *
     * @param int $number
     * @return string
     */
    public function getCacheDir($number)
    {
        return __DIR__ . "/../fixtures/filesystem_cache_test_{$number}";
    }

    /**
     * Clear test cache directory
     *
     * @param int $number
     * @return bool
     */
    private function clearCacheDir($number)
    {
        $directoryPath = $this->getCacheDir($number);

        if (is_dir($directoryPath)) {
            $directoryIterator = new \RecursiveDirectoryIterator(
                $directoryPath,
                \RecursiveDirectoryIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS
            );

            $iterator = new \RecursiveIteratorIterator(
                $directoryIterator,
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if (is_dir($item)) {
                    if (!@rmdir($item)) {
                        return false;
                    }
                } elseif (!@unlink($item)) {
                    return false;
                }
            }

            if (!@rmdir($directoryPath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @dataProvider provideTestInstanceCreators
     */
    public function testConfiguration(
        $cacheProvider,
        $cacheDirNumber,
        $expectedStorageMode,
        $expectedTmpFileAndUnlinkUsage,
        $expectedTmpDir
    ) {
        $cache = $cacheProvider();

        $this->assertSame($this->getCacheDir($cacheDirNumber), $cache->getCacheDir());
        $this->assertSame($expectedStorageMode, $cache->getStorageMode());
        $this->assertSame($expectedTmpFileAndUnlinkUsage, $cache->getUseTemporaryFiles());
        $this->assertSame($expectedTmpFileAndUnlinkUsage, $cache->getUseUnlink());
        $this->assertSame($expectedTmpFileAndUnlinkUsage, $cache->getUseUnlink());
        $this->assertSame($expectedTmpDir, $cache->getTemporaryDir());
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Invalid storage mode
     */
    public function testExceptionOnSettingInvalidStorageMode()
    {
        $cache = new FilesystemCache($this->getCacheDir(1));

        $cache->setStorageMode(1234);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Invalid cache directory
     */
    public function testExceptionOnSettingFilesystemRootAsCacheDir1()
    {
        new FilesystemCache('/');
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Invalid cache directory
     */
    public function testExceptionOnSettingFilesystemRootAsCacheDir2()
    {
        new FilesystemCache('C:/');
    }
}
