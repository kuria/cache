<?php

namespace Kuria\Cache\Extension;

use Kuria\Cache\Provider\MemoryCache;

class BoundFileExtensionTest extends \PHPUnit_Framework_TestCase
{
    public static function tearDownAfterClass()
    {
        if (is_file($boundFilePath = self::getBoundFilePath())) {
            unlink($boundFilePath);
        }
    }

    /**
     * Get path to the test bound file
     *
     * @return string
     */
    protected static function getBoundFilePath()
    {
        return __DIR__ . '/../fixtures/test_bound_file.tmp';
    }

    /**
     * Create and/or set modification time of the bound file
     *
     * @param int|null $timeOffset time offset
     */
    protected function touchBoundFile($timeOffset = 0)
    {
        $boundFilePath = $this->getBoundFilePath();

        touch($boundFilePath, time() + $timeOffset);
        clearstatcache(true, $boundFilePath);
    }

    /**
     * Create a test cache with the extension
     *
     * @return array MemoryCache, BoundFileExtension
     */
    protected function createTestCache()
    {
        $extension = new BoundFileExtension();

        $cache = new MemoryCache();
        $cache->addSubscriber($extension);

        return array($cache, $extension);
    }

    public function testSetGet()
    {
        list($cache) = $this->createTestCache();

        // init bound file
        $this->touchBoundFile(-60);

        // set()
        $cache->set('foo', 'bar', 'test-data', 0, array('bound_files' => array(
            $this->getBoundFilePath(),
        )));

        // get() with untouched bound file should return original data
        $this->assertSame('test-data', $cache->get('foo', 'bar', array(
            'has_bound_files' => true,
        )));

        $this->touchBoundFile();

        // get() with touched bound file should return FALSE
        $this->assertFalse($cache->get('foo', 'bar', array(
            'has_bound_files' => true,
        )));
    }

    public function testDisabledVerification()
    {
        list($cache, $extension) = $this->createTestCache();

        $extension->setVerifyBoundFiles(false);

        // init bound file
        $this->touchBoundFile(-60);

        // set()
        $cache->set('foo', 'bar', 'test-data', 0, array('bound_files' => array(
            $this->getBoundFilePath(),
        )));

        // get() with untouched bound file should return original data
        $this->assertSame('test-data', $cache->get('foo', 'bar', array(
            'has_bound_files' => true,
        )));

        $this->touchBoundFile();

        // get() with touched bound file should still return original data
        $this->assertSame('test-data', $cache->get('foo', 'bar', array(
            'has_bound_files' => true,
        )));

        $extension->setVerifyBoundFiles(true);

        // re-enabling verification should not invalidate existing entries
        // unless setAlwaysMapBoundFiles() has been enabled
        $this->assertSame('test-data', $cache->get('foo', 'bar', array(
            'has_bound_files' => true,
        )));
    }

    public function testAlwaysMapBoundFiles()
    {
        list($cache, $extension) = $this->createTestCache();

        $extension->setVerifyBoundFiles(false);
        $extension->setAlwaysMapBoundFiles(true);

        // init bound file
        $this->touchBoundFile(-60);

        // set()
        $cache->set('foo', 'bar', 'test-data', 0, array('bound_files' => array(
            $this->getBoundFilePath(),
        )));

        // get() with untouched bound file should return original data
        $this->assertSame('test-data', $cache->get('foo', 'bar', array(
            'has_bound_files' => true,
        )));

        $this->touchBoundFile();

        // get() with touched bound file should still return original data
        $this->assertSame('test-data', $cache->get('foo', 'bar', array(
            'has_bound_files' => true,
        )));

        $extension->setVerifyBoundFiles(true);

        // re-enabling verification should invalidate existing entries
        // when setAlwaysMapBoundFiles() been enabled
        $this->assertFalse($cache->get('foo', 'bar', array(
            'has_bound_files' => true,
        )));
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Invalid bound file
     */
    public function testExceptionOnInvalidBoundFile()
    {
        list($cache) = $this->createTestCache();

        $cache->set('foo', 'bar', 'test-data', 0, array('bound_files' => array(
            __DIR__ . '/nonexistent',
        )));
    }
}
