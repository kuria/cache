<?php

namespace Kuria\Cache\Extension\BoundFile;

use Kuria\Event\EventEmitterInterface;

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
        return __DIR__ . '/../../test_directory/test_bound_file.tmp';
    }

    public function testSubscription()
    {
        $ext = new BoundFileExtension();

        $ext
            ->setPriority('wrap', -123)
            ->setPriority('unwrap', 123)
        ;

        /** @var EventEmitterInterface|\PHPUnit_Framework_MockObject_MockObject $eventEmitterMock */
        $eventEmitterMock = $this->getMock('Kuria\Event\EventEmitterInterface');

        $eventEmitterMock
            ->expects($this->exactly(2))
            ->method('on')
            ->withConsecutive(
                array('store', array($ext, 'onStore'), -123),
                array('fetch', array($ext, 'onFetch'), 123)
            )
        ;

        $ext->subscribeTo($eventEmitterMock);
    }

    public function testEvents()
    {
        $ext = new BoundFileExtension();

        // init bound file
        $this->touchBoundFile(-60);

        // prepare the value
        $value = 'test-data';

        // storing a value with bound files should transform
        // it into an instance of FileBoundValue
        $ext->onStore(
            $this->createStoreEventData(
                'foo.bar',
                $value,
                array('bound_files' => array($this->getBoundFilePath()))
            )
        );

        $this->assertInstanceOf(__NAMESPACE__ . '\FileBoundValue', $value);
        /** @var FileBoundValue $value */
        $this->assertArrayHasKey(realpath($this->getBoundFilePath()), $value->getBoundFileMap());

        // store a clone of the not-yet-validated file-bound object for later
        $freshFileBoundValue = clone $value;

        // fetching a file-bound value with untouched bound files
        // should yield the original data
        $ext->onFetch($this->createFetchEventData('foo.bar', $value));

        $this->assertSame('test-data', $value);

        // restore the file-bound object
        $value = $freshFileBoundValue;

        // fetching a value with modified bound files should yield FALSE
        $this->touchBoundFile();
        $ext->onFetch($this->createFetchEventData('foo.bar', $value));

        $this->assertFalse($value);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage Invalid bound file
     */
    public function testExceptionOnInvalidBoundFile()
    {
        $ext = new BoundFileExtension();

        $value = 'test-value';

        $ext->onStore(
            $this->createStoreEventData(
                'foo.bar',
                $value,
                array('bound_files' => array(__DIR__ . '/nonexistent'))
            )
        );
    }

    public function testFileBoundValueValidationCache()
    {
        // init bound file
        $this->touchBoundFile(-60);

        // create a value
        $value = new FileBoundValue(array($this->getBoundFilePath()), 'test-value');

        // first validation check
        $this->assertTrue($value->validate());

        // touch the bound file
        $this->touchBoundFile();

        // second validation should yield the cached result
        $this->assertTrue($value->validate());

        // validation with explicit cache bypass should yield the new result
        $this->assertFalse($value->validate(true));
    }

    public function testFileBoundValueValidationCacheClearedOnSerialize()
    {
        // init bound file
        $this->touchBoundFile(-60);

        // create a value
        $value = new FileBoundValue(array($this->getBoundFilePath()), 'test-value');

        // first validation check
        $this->assertTrue($value->validate());

        // touch the bound file
        $this->touchBoundFile();

        // serialize and unserialize
        $value = unserialize(serialize($value));

        // the cached result should have been discared
        $this->assertFalse($value->validate());
    }

    /**
     * Create and/or set modification time of the bound file
     *
     * @param int|null $timeOffset time offset
     */
    private function touchBoundFile($timeOffset = 0)
    {
        $boundFilePath = $this->getBoundFilePath();

        touch($boundFilePath, time() + $timeOffset);
        clearstatcache(true, $boundFilePath);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param array  $options
     * @return array
     */
    private function createStoreEventData($key, &$value, array $options = array())
    {
        return array(
            'key' => $key,
            'value' => &$value,
            'ttl' => 0,
            'options' => &$options,
        );
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param array  $options
     * @return array
     */
    private function createFetchEventData($key, &$value, array $options = array())
    {
        return array(
            'key' => $key,
            'options' => &$options,
            'value' => &$value,
        );
    }
}
