<?php
namespace fremeo\core\tests;

use fremeo\core\PackageManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class PackageManagerTest extends TestCase {
	private string $tmp;

	protected function setUp(): void {
		$this->tmp = sys_get_temp_dir() . '/fremeo_package_manager_' . bin2hex(random_bytes(6));
		mkdir($this->tmp . '/system/vendor/composer', 0777, true);
		file_put_contents($this->tmp . '/composer.json', '{"config":{"vendor-dir":"system/vendor"}}');
		file_put_contents($this->tmp . '/system/vendor/composer/installed.json', json_encode(['packages' => [
			['name' => 'fremeo/core', 'version' => 'v0.0.10', 'type' => 'core'],
		]]));
	}

	protected function tearDown(): void {
		(new Filesystem())->remove($this->tmp);
	}

	public function testMissingSetupPreservesInstalledPackageListAndRejectsActions(): void {
		$manager = new PackageManager($this->tmp);
		$this->assertFalse($manager->isAvailable());
		$this->assertSame('v0.0.10', $manager->getInstalledPackages()['fremeo/core']['version']);
		foreach (['update', 'require test/module:*', 'reinstall fremeo/core', 'remove test/module'] as $command) {
			$this->assertSame(['success' => false, 'output' => PackageManager::UNAVAILABLE], $manager->run($command));
		}
	}

	public function testBothSetupFilesAreRequired(): void {
		mkdir($this->tmp . '/setup');
		$manager = new PackageManager($this->tmp);
		file_put_contents($this->tmp . '/setup/Composer.php', '<?php');
		$this->assertFalse($manager->isAvailable());
		file_put_contents($this->tmp . '/setup/composer.phar', '');
		$this->assertTrue($manager->isAvailable());
		unlink($this->tmp . '/setup/Composer.php');
		$this->assertFalse($manager->isAvailable());
	}

	public function testAvailableSetupRunsRealComposerWithoutCoreDistribution(): void {
		$setup = realpath(__DIR__ . '/../../../../../setup');
		if (!$setup || !is_file("$setup/composer.phar")) $this->markTestSkipped('Loader-Setup fehlt');
		mkdir($this->tmp . '/setup');
		copy("$setup/Composer.php", $this->tmp . '/setup/Composer.php');
		copy("$setup/composer.phar", $this->tmp . '/setup/composer.phar');
		$clears = 0;
		$manager = new PackageManager($this->tmp, function () use (&$clears): void { $clears++; });
		$result = $manager->run('--version');
		$this->assertTrue($result['success'], $result['output']);
		$this->assertSame(0, $clears);
		$this->assertStringContainsString('Composer version', $result['output']);
		$result = $manager->run('update --no-install --no-audit --no-scripts');
		$this->assertTrue($result['success'], $result['output']);
		$this->assertSame(1, $clears);
		$result = $manager->run('update --invalid-language-cache-test-option');
		$this->assertFalse($result['success']);
		$this->assertSame(2, $clears);
		$this->assertFileDoesNotExist(__DIR__ . '/../system/core/Composer.php');
		$this->assertFileDoesNotExist(__DIR__ . '/../system/core/composer.phar');
	}
}
