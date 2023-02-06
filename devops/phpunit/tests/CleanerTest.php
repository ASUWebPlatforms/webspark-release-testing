<?php
namespace DrupalComposerManaged;

use PHPUnit\Framework\TestCase;

/**
 * Test our 'help' command.
 */
class CleanerTest extends TestCase
{
    protected $cleaner = null;

    public function setUp(): void {
        $this->cleaner = new Cleaner();
        $this->cleaner->preventRegistration();
    }

    function testTmpDir() {
        $tmpDir = $this->cleaner->tmpdir(sys_get_temp_dir(), 'example');
        $tmpFile = $tmpDir . DIRECTORY_SEPARATOR . 'example.txt';
        file_put_contents($tmpFile, 'Example file');
        $this->assertStringContainsString('example', $tmpDir);
        $this->assertDirectoryExists($tmpDir);
        $this->assertFileExists($tmpFile);
        $this->cleaner->clean();
        $this->assertFileDoesNotExist($tmpFile);
        $this->assertFileDoesNotExist($tmpDir);
    }

    function testRemoveWhenDone() {
        $tmpFile = 'test-remove-when-done';
        file_put_contents($tmpFile, 'Example file');
        $this->cleaner->removeWhenDone($tmpFile);
        $this->assertFileExists($tmpFile);
        $this->cleaner->clean();
        $this->assertFileDoesNotExist($tmpFile);
   }

    function testRevertWhenDone() {
        $tmpFile = 'test-revert-when-done';
        $this->assertFileDoesNotExist($tmpFile);
        file_put_contents($tmpFile, 'Original content');
        $this->cleaner->revertWhenDone($tmpFile);
        file_put_contents($tmpFile, 'Temporary content');
        $this->cleaner->clean();
        $this->assertFileExists($tmpFile);
        $actualContents = file_get_contents($tmpFile);
        $this->assertEquals('Original content', $actualContents);
        @unlink($tmpFile);
        $this->assertFileDoesNotExist($tmpFile);
   }
}
