<?php

declare(strict_types=1);

namespace Drupal\Tests\site_template_helper\Build;

use Composer\InstalledVersions;
use Composer\Json\JsonFile;
use Drupal\BuildTests\Framework\BuildTestBase;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Symfony\Component\Filesystem\Filesystem;

final class GenerateThemeTest extends BuildTestBase {

  public function testGenerateThemeForSiteTemplate(): void {
    $workspace = $this->getWorkspaceDirectory();

    // Copy the fixture to the workspace.
    (new Filesystem())->mirror(
      dirname(__DIR__, 2) . '/fixture',
      $this->getWorkspaceDirectory(),
    );
    // Create a vendor repository with all installed dependencies so we can
    // build a Drupal code base.
    // @see fixture/composer.json
    $file = new JsonFile($workspace . '/vendor.json');
    $vendor = [];
    foreach (InstalledVersions::getInstalledPackages() as $name) {
      $path = InstalledVersions::getInstallPath($name) . '/composer.json';
      // Certain packages (i.e., metapackages) are not physically installed.
      if (file_exists($path)) {
        $data = Json::decode(file_get_contents($path));
        $this->assertIsArray($data, "$path is not valid JSON.");

        $version = InstalledVersions::getVersion($name);
        $vendor['packages'][$name][$version] = [
          'name' => $name,
          'version' => $version,
          'dist' => [
            'type' => 'path',
            'url' => dirname($path),
          ],
        ] + $data;
      }
    }
    $file->write($vendor);

    $info_file = $workspace . '/themes/blank/blank.info.yml';

    // Always mirror path repositories to prevent symlinking shenanigans.
    $process = $this->executeCommand('COMPOSER_MIRROR_PATH_REPOS=1 composer install --no-ansi --no-interaction -vvv');
    $this->assertCommandSuccessful();
    $output = $process->getOutput();
    $this->assertStringContainsString('Generated blank theme for drupal/fake_site_template', $output);
    $this->assertStringContainsString("Updated $info_file.", $output);

    $this->assertFileExists($info_file);
    $info = Yaml::decode(file_get_contents($info_file));
    $this->assertIsArray($info);
    // The regions defined in the fake site template should be the ones in the
    // generated info file.
    $this->assertSame(['header', 'content', 'footer'], array_keys($info['regions']));
  }

}
