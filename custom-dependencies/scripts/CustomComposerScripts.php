<?php

namespace WebsparkCustomScripts;

use Composer\IO\IOInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;

class CustomComposerScripts
{
  // Copied from Pantheon example, but doesnt actually seem to install the module.
  public static function customRequire(Event $event) {
    $io = $event->getIO();
    $composer = $event->getComposer();
    $arguments = $event->getArguments();

    $hasNoUpdate = array_search('--no-update', $arguments) !== false;

    // Remove --working-dir, --no-update and --no-install, if provided
    $arguments = array_filter($arguments, function ($item) {
      return
        (substr($item, 0, 13) != '--working-dir') &&
        ($item != '--no-update') &&
        ($item != '--no-install');
    });

    // Escape the arguments passed in.
    $args = array_map(function ($item) {
      return escapeshellarg($item);
    }, $arguments);

    // Run `require` with '--no-update' if there is no composer.lock file,
    // and without it if there is.
    $addNoUpdate = $hasNoUpdate || !file_exists('custom-dependencies/composer.lock');

    if ($addNoUpdate) {
      $args[] = '--no-update';
    } else {
      $args[] = '--no-install';
    }

    // Insert the new projects into the custom-dependencies composer.json
    // without writing vendor & etc to the custom-dependencies directory.
    $cmd = "composer --working-dir=custom-dependencies require " . implode(' ', $args);
    $io->writeError($cmd . PHP_EOL);
    passthru($cmd, $statusCode);

    if ($statusCode) {
      throw new \RuntimeException("Could not add dependency to custom dependencies.");
    }

    $io->writeError('custom-dependencies/composer.json updated.');

    return $statusCode;
  }

  // Not quite right, installs into the wrong place.
  // public static function customRequire(Event $event)
  // {
  //   $args = $event->getArguments();
  //   $package_and_version = $args[0] ?? null;

  //   if (!$package_and_version) {
  //     echo "Please provide a package name and version constraint to require.\n";
  //     exit(1);
  //   }

  //   $custom_composer_file = __DIR__ . '/../composer.json';
  //   $custom_composer = json_decode(file_get_contents($custom_composer_file), true);

  //   list($package, $version_constraint) = explode(':', $package_and_version);
  //   $custom_composer['require'][$package] = $version_constraint;

  //   // Make sure to install the module into the web/modules/composer folder
  //   // $custom_composer['extra']['installer-paths']['web/modules/composer/{$name}'] = ['type:drupal-module'];

  //   file_put_contents($custom_composer_file, json_encode($custom_composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

  //   chdir(__DIR__ . '/..');

  //   // Run composer require for the specified package in the custom-dependencies folder
  //   exec("composer require --no-update {$package_and_version}");

  //   // Install the new package without updating other dependencies
  //   exec("composer install --no-dev --optimize-autoloader");

  //   echo "Successfully required package: {$package_and_version}\n";
  // }
}
