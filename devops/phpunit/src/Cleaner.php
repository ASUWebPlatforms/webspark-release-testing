<?php
namespace DrupalComposerManaged;

// @todo: convert to symfony/filesystem
use Composer\Util\Filesystem;

class Cleaner {
  /** @var array List of files and directories to remove. */
  private array $fileOrDirList = [];
  /** @var array Map of files to the content to revert them to. */
  private array $revertList = [];
  /** @var bool Flag indicating whether or not we have registered the shutdown function. */
  private $registered = false;

  /**
   * Ensure that our cleanup function is called when php shuts down.
   */
  private function register() {
    if ($this->registered) {
      return;
    }
    register_shutdown_function([$this, 'clean']);
    $this->preventRegistration();
  }

  /**
   * Prevents shutdown function registration, e.g. for testing.
   */
  public function preventRegistration() {
    $this->registered = true;
  }

  /**
   * Clean up everything that needs to be cleaned.
   *
   * Typically, this method will never be called directly by
   * client code, but it may be directly called by unit tests.
   */
  public function clean() {
    $fs = new Filesystem();
    foreach ($this->fileOrDirList as $fileOrDir) {
      $fs->remove($fileOrDir);
    }
    foreach ($this->revertList as $file => $contents) {
      if (file_exists($file) && is_writable($file)) {
        file_put_contents($file, $contents);
      }
    }
    $this->fileOrDirList = [];
    $this->revertList = [];
  }

  /**
   * Remove the specified file or directory when we clean up.
   */
  public function removeWhenDone($fileOrDir) {
    $this->fileOrDirList[] = $fileOrDir;
    $this->register();
  }

  /**
   * Revert the specified file to the provided contents when we clean up.
   */
  public function revertWhenDone($file, $contents = null) {
    if ($contents === null) {
      $contents = file_get_contents($file);
    }
    $this->revertList[$file] = $contents;
    $this->register();
  }

  /**
   * Create a temporary directory; everything inside it will be deleted
   * when we clean up.
   */
  public function tmpdir($dir, $namePrefix) {
    $tempfile = tempnam($dir, $namePrefix);
    // tempnam creates file on disk
    if (file_exists($tempfile)) {
      unlink($tempfile);
    }
    mkdir($tempfile);
    if (is_dir($tempfile)) {
      $this->removeWhenDone($tempfile);
      return $tempfile;
    }
  }
}
