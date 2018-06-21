<?php declare(strict_types=1);

namespace Kuria\Cache;

use Kuria\Cache\Test\IterableAssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class CachePrefixTraitTest extends TestCase
{
    use IterableAssertionTrait;

    /** @var CachePrefixTrait|mixed */
    private $cachePrefix;

    protected function setUp()
    {
        $this->cachePrefix = new class {
            use CachePrefixTrait {
                applyPrefix as public;
                applyPrefixToValues as public;
                applyPrefixToKeys as public;
                stripPrefix as public;
            }
        };
    }

    function testShouldConfigurePrefix()
    {
        $this->assertSame('', $this->cachePrefix->getPrefix());

        $this->cachePrefix->setPrefix('prefix_');

        $this->assertSame('prefix_', $this->cachePrefix->getPrefix());
    }

    function testShouldApplyPrefix()
    {
        $this->cachePrefix->setPrefix('prefix_');

        $this->assertSame('prefix_bar', $this->cachePrefix->applyPrefix('bar'));
    }

    function testShouldApplyPrefixWithNoPrefix()
    {
        $this->assertSame('bar', $this->cachePrefix->applyPrefix('bar'));
    }

    function testShouldApplyPrefixToValues()
    {
        $this->cachePrefix->setPrefix('prefix_');

        $this->assertSameIterable(
            ['foo' => 'prefix_bar', 'baz' => 'prefix_qux'],
            $this->cachePrefix->applyPrefixToValues(['foo' => 'bar', 'baz' => 'qux'])
        );
    }

    function testShouldApplyPrefixToValuesWithNoPrefix()
    {
        $this->assertSameIterable(
            ['foo' => 'bar', 'baz' => 'qux'],
            $this->cachePrefix->applyPrefixToValues(['foo' => 'bar', 'baz' => 'qux'])
        );
    }

    function testShouldApplyPrefixToKeys()
    {
        $this->cachePrefix->setPrefix('prefix_');

        $this->assertSameIterable(
            ['prefix_foo' => 'bar', 'prefix_baz' => 'qux'],
            $this->cachePrefix->applyPrefixToKeys(['foo' => 'bar', 'baz' => 'qux'])
        );
    }

    function testShouldApplyPrefixToKeysWithNoPrefix()
    {
        $this->assertSameIterable(
            ['foo' => 'bar', 'baz' => 'qux'],
            $this->cachePrefix->applyPrefixToKeys(['foo' => 'bar', 'baz' => 'qux'])
        );
    }

    function testShouldStripPrefix()
    {
        $this->cachePrefix->setPrefix('prefix_');

        $this->assertSame('foo', $this->cachePrefix->stripPrefix('prefix_foo'));
    }

    function testShouldStripPrefixWithNoPrefix()
    {
        $this->assertSame('foo', $this->cachePrefix->stripPrefix('foo'));
    }
}
