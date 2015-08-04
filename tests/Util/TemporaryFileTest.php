<?php

namespace Kuria\Cache\Util;

class TemporaryFileTest extends \PHPUnit_Framework_TestCase
{
    public function testDiscard()
    {
        $tmpFile = new TemporaryFile();
        $tmpFileClone = clone $tmpFile;
        $tmpFilePath = $tmpFile->getPathname();
        $tmpFile->discard();

        $this->assertFalse(file_exists($tmpFilePath), 'the file should no longer exist');
        $this->assertFalse($tmpFile->discard(), 'discard should fail if already discarded');
        $this->assertTrue($tmpFileClone->discard(), 'discard should succeed if already discarded by other means');
    }

    public function testUnregister()
    {
        $tmpFile = new TemporaryFile();
        $tmpFilePath = $tmpFile->getPathname();
        $tmpFile->unregister();

        TemporaryFile::discardAll();

        $tmpFileExists = file_exists($tmpFilePath);
        if ($tmpFileExists) {
            // to not to leave junk behind
            unlink($tmpFilePath);
        }

        $this->assertTrue($tmpFileExists, 'the file should still exist');
    }

    public function testMove()
    {
        $tmpFile = new TemporaryFile();
        $newPath = __DIR__ . '/../test_directory/moved_tmp_file/foo.tmp';
        $newPath2 = __DIR__ . '/../test_directory/moved_tmp_file/bar.tmp';
        
        $this->assertTrue($tmpFile->move($newPath));
        $this->assertTrue(is_file($newPath));
        $this->assertFalse($tmpFile->move($newPath2));
        $this->assertFalse($tmpFile->discard());

    }

    public function testCleanupOnShutdown()
    {
        $tmpFile = new TemporaryFile();
        $tmpFilePath = $tmpFile->getPathname();

        TemporaryFile::discardAll();

        $tmpFileExists = file_exists($tmpFilePath);
        if ($tmpFileExists) {
            // to not to leave junk behind
            unlink($tmpFilePath);
        }

        $this->assertFalse($tmpFileExists, 'the file should no longer exist');
    }
}
