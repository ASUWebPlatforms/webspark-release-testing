<?php
namespace DrupalComposerManaged;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Test requiring and updating upstream dependencies.
 */
class UpstreamManagementCommandTest extends TestCase
{
    protected $cleaner;
    protected $sut;

    public function setUp(): void {
        $this->cleaner = new Cleaner();
        $this->cleaner->preventRegistration();
        $tmpDir = $this->cleaner->tmpdir(sys_get_temp_dir(), 'sut');
        $this->sut = $tmpDir . DIRECTORY_SEPARATOR . 'sut';
    }

    public function tearDown(): void {
        //$this->cleaner->clean();
    }

    protected function createSut() {
        $source = dirname(__DIR__, 3);
        $filesystem = new Filesystem();
        foreach (['upstream-configuration', 'web'] as $dir) {
            $filesystem->mirror($source . DIRECTORY_SEPARATOR . $dir, $this->sut . DIRECTORY_SEPARATOR . $dir);
        }
        foreach (scandir($source) as $item) {
            $fileToCopy = $source . DIRECTORY_SEPARATOR . $item;
            if (is_file($fileToCopy)) {
                $filesystem->copy($fileToCopy, $this->sut . DIRECTORY_SEPARATOR . $item);
            }
        }

        // Make sure we start out without a lock file in the upstream configuration directory
        @unlink($this->sut . '/upstream-configuration/composer.lock');
        @unlink($this->sut . '/composer.lock');

        // Run 'composer update'. This has two important impacts:
        // 1. The composer.lock file is created, which is necessary for the upstream dependency locking feature to work.
        // 2. Our preUpdate modifications are applied to the SUT.
        $this->composer('update');
    }

    public function testUpstreamRequire() {
        $this->createSut();
        // 'composer upstream require' will return an error if used on the Pantheon platform upstream.
        $process = $this->composer('upstream-require', ['drupal/ctools']);
        $this->assertFalse($process->isSuccessful());
        $output = $process->getErrorOutput();
        $this->assertStringContainsString('The upstream-require command can only be used with a custom upstream', $output);

        $this->pregReplaceSutFile('#pantheon-upstreams/drupal-composer-managed#', 'customer-org/custom-upstream', 'composer.json');
        $this->assertSutFileContains('"customer-org/custom-upstream"', 'composer.json');

        // Once we change the name of the upstream, 'composer upstream require' should work.
        $process = $this->composer('upstream-require', ['drupal/ctools']);
        $this->assertTrue($process->isSuccessful());
        $this->assertSutFileDoesNotExist('upstream-configuration/composer.lock');
        $this->assertSutFileContains('"drupal/ctools"', 'upstream-configuration/composer.json');
        $this->assertSutFileNotContains('"drupal/ctools"', 'composer.json');
        $this->assertSutFileNotContains('"drupal/ctools"', 'composer.lock');
        $process = $this->composer('update');
        $output = $process->getOutput() . PHP_EOL . $process->getErrorOutput();
        $this->assertStringContainsString('drupal/ctools', $output);
        $this->assertSutFileNotContains('"drupal/ctools"', 'composer.json');
        $this->assertSutFileContains('drupal/ctools', 'composer.lock');
        $this->assertSutFileDoesNotExist('upstream-configuration/composer.lock');
    }

    public function testUpdateUpstreamDependencies() {
        //$this->markTestSkipped("Test fails due to problem running shutdown handler in subprocess from phpunit");
        $this->createSut();

        $this->pregReplaceSutFile('#pantheon-upstreams/drupal-composer-managed#', 'customer-org/custom-upstream', 'composer.json');

        // Add drupal/ctools to the project; only allow version 4.0.2.
        $process = $this->composer('upstream-require', ['drupal/ctools:4.0.2']);
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . PHP_EOL . $process->getErrorOutput());
        $this->assertSutFileDoesNotExist('upstream-configuration/composer.lock');

        // Running 'update-upstream-dependencies' creates our locked composer.json
        // file. drupal/ctools will not update past version 4.0.2 until updated.
        $process = $this->composer('update-upstream-dependencies');
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . PHP_EOL . $process->getErrorOutput());
        $this->assertSutFileExists('upstream-configuration/composer.lock');
        $this->assertSutFileExists('upstream-configuration/locked/composer.json');
        $this->assertMatchesRegularExpression('#drupal/ctools"[^"]*"4\.0\.2#', $this->sutFileContents('upstream-configuration/composer.json'));
        $process = $this->composer('info');
        $output = $process->getOutput();
        $this->assertStringNotContainsString('drupal/ctools', $output);
        $this->assertSutFileContains('"drupal/ctools"', 'upstream-configuration/composer.json');

        // Run `composer update`. This should bring in the locked (4.0.2) version of drupal/ctools.
        $this->assertSutFileExists('upstream-configuration/composer.lock');
        $process = $this->composer('update');
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . PHP_EOL . $process->getErrorOutput());
        $this->assertSutFileExists('upstream-configuration/composer.lock');
        $output = $process->getErrorOutput();
        $process = $this->composer('info');
        $output = $process->getOutput();
        $this->assertMatchesRegularExpression('#drupal/ctools *4\.0\.2#', $output);

        // Set drupal/ctools constraint back to ^4. At this point, though, the
        // upstream dependency lock file is still at version 4.0.2.
        $process = $this->composer('upstream-require', ['drupal/ctools:^4', '--', '--no-update']);
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . PHP_EOL . $process->getErrorOutput());
        $output = $process->getOutput() . PHP_EOL . $process->getErrorOutput();
        $this->assertMatchesRegularExpression('#drupal/ctools"[^"]*"\^4#', $this->sutFileContents('upstream-configuration/composer.json'));

        // Run `composer update` again. This should not affect drupal/ctools; it should stay at version 4.0.2.
        $process = $this->composer('update');
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . PHP_EOL . $process->getErrorOutput());
        $this->assertMatchesRegularExpression('#drupal/ctools"[^"]*"\^4#', $this->sutFileContents('upstream-configuration/composer.json'));
        $output = $process->getOutput() . PHP_EOL . $process->getErrorOutput();
        $this->assertStringNotContainsString('drupal/ctools (4.0.2 => 4.)', $output);
        $this->assertStringNotContainsString('No locked dependencies in the upstream', $output);
        $process = $this->composer('info');
        $output = $process->getOutput();
        $this->assertTrue($process->isSuccessful());
        $this->assertMatchesRegularExpression('#drupal/ctools *4\.0\.2#', $output);

        // Update the upstream dependencies. This should not affect the installed dependencies;
        // however, it will update the locked version of drupal/ctools to the latest
        // avaiable version. The project will acquire this version the next time it is updated.
        $this->composer('update-upstream-dependencies');
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . PHP_EOL . $process->getErrorOutput());
        $output = $process->getOutput() . PHP_EOL . $process->getErrorOutput();
        $this->assertMatchesRegularExpression('#drupal/ctools *4\.0\.2#', $output);
        $process = $this->composer('info');
        $output = $process->getOutput();
        $this->assertTrue($process->isSuccessful());
        $this->assertMatchesRegularExpression('#drupal/ctools *4\.0\.2#', $output);

        // Now run `composer update` again. This should update drupal/ctools.
        $process = $this->composer('update');
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . PHP_EOL . $process->getErrorOutput());
        $output = $process->getOutput() . PHP_EOL . $process->getErrorOutput();
        $this->assertStringNotContainsString('No locked dependencies in the upstream', $output);
        $process = $this->composer('info');
        $output = $process->getOutput();
        $this->assertTrue($process->isSuccessful());
        $this->assertMatchesRegularExpression('#drupal/ctools *4\.#', $output);
        $this->assertDoesNotMatchRegularExpression('#drupal/ctools *4\.0\.2#', $output);
    }

    public function sutFileContents($file) {
        return file_get_contents($this->sut . DIRECTORY_SEPARATOR . $file);
    }

    public function assertSutFileContains($needle, $haystackFile) {
        $this->assertStringContainsString($needle, $this->sutFileContents($haystackFile));
    }

    public function assertSutFileNotContains($needle, $haystackFile) {
        $this->assertStringNotContainsString($needle, $this->sutFileContents($haystackFile));
    }

    public function assertSutFileDoesNotExist($file) {
        $this->assertFileDoesNotExist($this->sut . DIRECTORY_SEPARATOR . $file);
    }

    public function assertSutFileExists($file) {
        $this->assertFileExists($this->sut . DIRECTORY_SEPARATOR . $file);
    }

    public function pregReplaceSutFile($regExp, $replace, $file) {
        $path = $this->sut . DIRECTORY_SEPARATOR . $file;
        $contents = file_get_contents($path);
        $contents = preg_replace($regExp, $replace, $contents);
        file_put_contents($path, $contents);
    }

    protected function composer(string $command, array $args = []): Process {
        $cmd = array_merge(['composer', '--working-dir=' . $this->sut, $command], $args);
        $process = new Process($cmd);
        $process->run();

        return $process;
    }
}
