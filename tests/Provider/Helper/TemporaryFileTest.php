<?php

namespace Kuria\Cache\Provider\Helper;

class TemporaryFileTest extends \PHPUnit_Framework_TestCase
{
    public function testDiscard()
    {
        $tmpFile = new TemporaryFile();
        $tmpFileClone = clone $tmpFile;
        $tmpFilePath = $tmpFile->getPathname();
        $tmpFile->discard();

        $this->assertFalse(file_exists($tmpFilePath), 'the file should no longer exist');
        $this->assertTrue($tmpFile->discard(), 'discard should succeed if already discarded');
        $this->assertTrue($tmpFileClone->discard(), 'discard should succeed if already discarded by other means');
    }

    public function testKeep()
    {
        $tmpFile = new TemporaryFile();
        $tmpFilePath = $tmpFile->getPathname();
        $tmpFile->keep();

        TemporaryFile::cleanupOnShutdown();

        $tmpFileExists = file_exists($tmpFilePath);
        if ($tmpFileExists) {
            // to not to leave junk behind
            unlink($tmpFilePath);
        }

        $this->assertTrue($tmpFileExists, 'the file should still exist');
    }

    public function testCleanupOnShutdown()
    {
        $tmpFile = new TemporaryFile();
        $tmpFilePath = $tmpFile->getPathname();

        TemporaryFile::cleanupOnShutdown();

        $tmpFileExists = file_exists($tmpFilePath);
        if ($tmpFileExists) {
            // to not to leave junk behind
            unlink($tmpFilePath);
        }

        $this->assertFalse($tmpFileExists, 'the file should no longer exist');
    }
}
