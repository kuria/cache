<?php declare(strict_types=1);

namespace Kuria\Cache\Driver\Filesystem\PathResolver;

use Kuria\Cache\Driver\Exception\DriverExceptionInterface;
use Kuria\Cache\Driver\Filesystem\Entry\File\FileFormatInterface;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class HashedPathResolverTest extends TestCase
{
    use PHPMock;

    /** @var HashedPathResolver */
    private $resolver;
    /** @var FileFormatInterface|MockObject */
    private $fileFormatMock;

    protected function setUp()
    {
        $this->resolver = new HashedPathResolver();
        $this->fileFormatMock = $this->createConfiguredMock(FileFormatInterface::class, [
            'getFilenameSuffix' => '.suffix',
        ]);
    }

    function testShouldThrowExceptionIfHashExtensionIsNotLoaded()
    {
        $this->expectException(DriverExceptionInterface::class);
        $this->expectExceptionMessage('Hashed path resolver requires the hash extension');

        $this->getFunctionMock(__NAMESPACE__, 'extension_loaded')
            ->expects($this->once())
            ->with('hash')
            ->willReturn(false);

        new HashedPathResolver();
    }

    function testShouldHaveDefaultSettings()
    {
        $this->assertSame('/a9/a93287ddf7050214.suffix', $this->resolver->resolve($this->fileFormatMock, 'foo.bar'));
        $this->assertSame('/00/00392c1913393882.suffix', $this->resolver->resolve($this->fileFormatMock, 'baz'));
    }

    function testShouldSupportCustomSettings()
    {
        $this->resolver->setHashAlgo('md5');
        $this->resolver->setPathSegmentCount(3);
        $this->resolver->setPathSegmentLength(3);

        $this->assertSame('/04f/981/009/04f98100995b2f5633210e10f21ee022.suffix', $this->resolver->resolve($this->fileFormatMock, 'foo.bar'));
        $this->assertSame('/73f/eff/a4b/73feffa4b7f6bb68e44cf984c85f6e88.suffix', $this->resolver->resolve($this->fileFormatMock, 'baz'));
    }

    function testShouldResolveWithNoSegments()
    {
        $this->resolver->setPathSegmentCount(0);

        $this->assertSame('/a93287ddf7050214.suffix', $this->resolver->resolve($this->fileFormatMock, 'foo.bar'));
        $this->assertSame('/00392c1913393882.suffix', $this->resolver->resolve($this->fileFormatMock, 'baz'));
    }

    function testShouldThrowExceptionOnInvalidSettings()
    {
        $this->resolver->setHashAlgo('md5');
        $this->resolver->setPathSegmentLength(5);
        $this->resolver->setPathSegmentCount(10);

        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage(
            'Cannot produce path (segment count = 10 and segment length = 5) from hash "04f98100995b2f5633210e10f21ee022" (algo = md5)'
                . ' because the hash is not long enough (need at least 50 bytes, got 32)'
        );

        $this->resolver->resolve($this->fileFormatMock, 'foo.bar');
    }
}
