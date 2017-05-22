<?php

namespace Kuria\Cache\Driver;

class FilesystemDriverTest extends DriverTest
{
    public function provideDriverFactories()
    {
        $that = $this;

        return array(
            // normal storage, tmp files and unlink disabled
            array(function () use ($that) {
                $driver = new FilesystemDriver($that->getCacheDir());
                $driver
                    ->setStorageMode(FilesystemDriver::STORAGE_NORMAL)
                    ->setUseTemporaryFiles(false)
                    ->setUseUnlink(false);

                return $driver;
            }),

            // PHP storage, tmp files and unlink disabled
            array(function () use ($that) {
                $driver = new FilesystemDriver($that->getCacheDir());
                $driver
                    ->setStorageMode(FilesystemDriver::STORAGE_PHP)
                    ->setUseTemporaryFiles(false)
                    ->setUseUnlink(false);

                return $driver;
            }),

            // normal storage, tmp files and unlink enabled
            array(function () use ($that) {
                $driver = new FilesystemDriver($that->getCacheDir());
                $driver
                    ->setStorageMode(FilesystemDriver::STORAGE_NORMAL)
                    ->setUseTemporaryFiles(true)
                    ->setUseUnlink(true);

                return $driver;
            }),

            // PHP storage, tmp files and unlink enabled
            array(function () use ($that) {
                $driver = new FilesystemDriver($that->getCacheDir());
                $driver
                    ->setStorageMode(FilesystemDriver::STORAGE_PHP)
                    ->setUseTemporaryFiles(true)
                    ->setUseUnlink(true);

                return $driver;
            }),

            // custom tmp directory
            array(function () use ($that) {
                $driver = new FilesystemDriver($that->getCacheDir());
                $driver
                    ->setStorageMode(FilesystemDriver::STORAGE_NORMAL)
                    ->setUseTemporaryFiles(true)
                    ->setUseUnlink(true)
                    ->setTemporaryDir($that->getCacheDir());

                return $driver;
            }),
        );
    }

    protected function setUp()
    {
        $this->removeCacheDirs();
    }

    protected function tearDown()
    {
        $this->removeCacheDirs();
    }

    /**
     * Get test cache directory path
     *
     * @param string $suffix
     * @return string
     */
    public function getCacheDir($suffix = 'main')
    {
        return __DIR__ . "/../test_directory/{$suffix}";
    }

    /**
     * Clear cache directories
     */
    private function removeCacheDirs()
    {
        foreach (new \DirectoryIterator(__DIR__ . '/../test_directory') as $item) {
            if (!$item->isDot() && $item->isDir()) {
                if (!$this->removeDir($item->getPathname())) {
                    $this->markTestIncomplete(sprintf('Could not remove cache directory "%s"', $item));
                }
            }
        }
    }

    /**
     * @param string $path
     * @return bool
     */
    private function removeDir($path)
    {
        $directoryIterator = new \RecursiveDirectoryIterator(
            $path,
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

        if (!@rmdir($path)) {
            return false;
        }

        return true;
    }

    /**
     * @expectedException        RuntimeException
     * @expectedExceptionMessage Could not create cache directory
     */
    public function testExceptionOnInvalidCacheDir()
    {
        new FilesystemDriver($this->getCacheDir('<?:>'));
    }

    /**
     * @expectedException        RuntimeException
     * @expectedExceptionMessage Could not create temporary directory
     */
    public function testExceptionOnInvalidTemporaryDir()
    {
        new FilesystemDriver(
            $this->getCacheDir(),
            $this->getCacheDir('<?:>')
        );
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Invalid storage mode
     */
    public function testExceptionOnSettingInvalidStorageMode()
    {
        $driver = new FilesystemDriver($this->getCacheDir());

        $driver->setStorageMode(1234);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Invalid cache directory
     */
    public function testExceptionOnSettingFilesystemRootAsCacheDir1()
    {
        new FilesystemDriver('/');
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Invalid cache directory
     */
    public function testExceptionOnSettingFilesystemRootAsCacheDir2()
    {
        new FilesystemDriver('C:/');
    }
}
