<?php
namespace fremeo\core\tests;

use PHPUnit\Framework\TestCase;

/**
 * setup/Composer.php: Ist proc_open gesperrt (disable_functions), läuft Composer im selben PHP-Prozess.
 * Der Test simuliert das, indem er canSpawnProcess() überschreibt.
 * Composer-PHARs verschiedener Testprojekte registrieren dieselben globalen Klassen: je Test ein eigener PHP-Prozess.
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
class SetupComposerTest extends TestCase {

	private string $setupDir;
	private string $cwd;
	private array $server;
	private array $projects = [];

	protected function setUp(): void {
		$this->cwd = getcwd();
		$this->server = $_SERVER;
		$this->setupDir = realpath(__DIR__ . '/../../../../../setup');
		if (!$this->setupDir || !is_file("{$this->setupDir}/composer.phar")) {
			$this->markTestSkipped('setup/composer.phar nicht vorhanden');
		}
		require_once "{$this->setupDir}/Composer.php";
		$_SERVER['SCRIPT_FILENAME'] = "{$this->setupDir}/console.php"; // Projektwurzel = Ordner über setup/
	}

	protected function tearDown(): void {
		chdir($this->cwd);
		$_SERVER = $this->server;
		(new \Symfony\Component\Filesystem\Filesystem())->remove($this->projects);
	}

	/** Composer ohne Prozessstart; Ausgabe wird gesammelt statt an den Browser gesendet. */
	private function inProcessComposer(): object {
		return new class("{$this->setupDir}/composer.phar") extends \Composer {
			public string $out = '';
			public bool $noShell = false;
			protected function canSpawnProcess(): bool { return false; }
			protected function emit(string $text): void { $this->out .= $text; }
			protected function shell(string $command): ?string { return $this->noShell ? null : parent::shell($command); }
		};
	}

	public function testRunsComposerWithoutProcOpen(): void {
		$this->tempProject([]);
		$composer = $this->inProcessComposer();
		$cwd = getcwd();

		$yielded = iterator_to_array($composer->stream('--version'), false);
		chdir($cwd);

		$this->assertSame([], $yielded, 'keine Fehlermeldung erwartet: ' . implode(' | ', $yielded));
		$this->assertStringContainsString('Composer version', $composer->out);
	}

	public function testUnknownCommandIsReportedByComposer(): void {
		$this->tempProject([]);
		$composer = $this->inProcessComposer();
		$cwd = getcwd();

		iterator_to_array($composer->stream('gibtesnicht'), false);
		chdir($cwd);

		$this->assertStringContainsString('gibtesnicht', $composer->out);
	}

	/** Projekt in einem Temp-Ordner mit Skripten, die eine Marker-Datei schreiben; setup/ liegt wie im echten Projekt darin. */
	private function tempProject(array $scripts): string {
		$tmp = sys_get_temp_dir() . '/setup_composer_' . uniqid();
		$this->projects[] = $tmp;
		mkdir("$tmp/setup", 0777, true);
		file_put_contents("$tmp/composer.json", json_encode(['name' => 'test/test', 'require' => new \stdClass(), 'scripts' => (object)$scripts]));
		$_SERVER['SCRIPT_FILENAME'] = "$tmp/setup/console.php";
		return $tmp;
	}

	private function marker(string $name): string {
		return '@php -r "file_put_contents(\'' . $name . '\', 1);"';
	}

	public function testInstallRunsComposerScriptsThroughSetup(): void {
		$tmp = $this->tempProject([
			'post-install-cmd' => [$this->marker('install_ran.txt')],
			'post-update-cmd'  => [$this->marker('update_ran.txt')],
		]);
		$composer = $this->inProcessComposer();
		$cwd = getcwd();

		$yielded = iterator_to_array($composer->stream('install'), false);
		chdir($cwd);

		$this->assertSame([], $yielded, implode(' | ', $yielded));
		$this->assertFileExists("$tmp/install_ran.txt");      // post-install-cmd lief über das Setup
		$this->assertFileDoesNotExist("$tmp/update_ran.txt"); // nur das Skript des passenden Events
		$this->assertStringContainsString('Composer-Skript post-install-cmd', $composer->out);

		(new \Symfony\Component\Filesystem\Filesystem())->remove($tmp);
	}

	public function testRunScriptCommandRunsTheScriptThroughSetup(): void {
		$tmp = $this->tempProject(['post-update-cmd' => [$this->marker('update_ran.txt')]]);
		$composer = $this->inProcessComposer();
		$cwd = getcwd();

		iterator_to_array($composer->stream('run-script post-update-cmd'), false);
		chdir($cwd);

		$this->assertFileExists("$tmp/update_ran.txt");

		(new \Symfony\Component\Filesystem\Filesystem())->remove($tmp);
	}

	public function testReportsManualCommandWhenNoProcessCanBeStarted(): void {
		$tmp = $this->tempProject(['post-install-cmd' => ['@php index.php migration']]);
		$composer = $this->inProcessComposer();
		$composer->noShell = true;
		$cwd = getcwd();

		$errors = iterator_to_array($composer->stream('install'), false);
		chdir($cwd);

		$this->assertStringContainsString('kann kein Prozess gestartet werden', implode("\n", $errors));
		$this->assertStringContainsString('@php index.php migration', implode("\n", $errors));

		(new \Symfony\Component\Filesystem\Filesystem())->remove($tmp);
	}

	public function testNonZeroExitIsNotReportedAsSuccessInEitherMode(): void {
		$tmp = $this->tempProject([]);
		$before = getcwd();
		foreach ([true, false] as $spawn) {
			$composer = $this->projectComposer($tmp, $spawn);
			$result = $composer->run('gibtesnicht');
			$this->assertFalse($result['success'], $result['output']);
			$this->assertStringContainsString('gibtesnicht', $result['output']);
			$this->assertStringContainsString('Exitcode', $result['output']);
			$this->assertSame($before, getcwd());
		}
	}

	public function testFailedPostUpdateScriptIsReportedInEitherMode(): void {
		$tmp = $this->tempProject(['post-update-cmd' => ['@php -r "exit(7);"']]);
		foreach ([true, false] as $spawn) {
			$result = $this->projectComposer($tmp, $spawn)->run('update');
			$this->assertFalse($result['success'], $result['output']);
			$this->assertStringContainsString('7', $result['output']);
		}
	}

	public function testNoProcessDryRunDoesNotRunMigrationScripts(): void {
		$tmp = $this->tempProject(['post-update-cmd' => [$this->marker('migration.txt')]]);
		$result = $this->projectComposer($tmp, false)->run('update --dry-run');
		$this->assertTrue($result['success'], $result['output']);
		$this->assertFileDoesNotExist("$tmp/migration.txt");
	}

	public function testConcurrentUpdateIsRejectedBeforeComposerRuns(): void {
		$tmp = $this->tempProject([]);
		$lock = fopen(sys_get_temp_dir() . '/fremeo-composer-' . hash('sha256', realpath($tmp)) . '.lock', 'c');
		$this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
		try {
			$result = $this->projectComposer($tmp, true)->run('update');
			$this->assertFalse($result['success']);
			$this->assertStringContainsString('bereits aktiv', $result['output']);
			$this->assertFileDoesNotExist("$tmp/composer.lock");
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		$result = $this->projectComposer($tmp, true)->run('update');
		$this->assertTrue($result['success'], $result['output']);
	}

	private function projectComposer(string $root, bool $spawn): \Composer {
		return new class("{$this->setupDir}/composer.phar", $root, $spawn) extends \Composer {
			function __construct(string $phar, string $root, private bool $spawn) {
				parent::__construct($phar, $root);
			}
			protected function canSpawnProcess(): bool { return $this->spawn && parent::canSpawnProcess(); }
		};
	}

	private function packageProject(string $root, array $versions, string $constraint): void {
		$packages = [];
		foreach ($versions as $version => $archive) {
			$packages[] = [
				'name' => 'test/module', 'version' => $version, 'type' => 'library',
				'dist' => ['type' => 'zip', 'url' => 'file://' . (DIRECTORY_SEPARATOR === '\\' ? '/' : '') . str_replace('\\', '/', $archive)],
			];
		}
		file_put_contents("$root/composer.json", json_encode([
			'name' => 'test/project',
			'require' => ['test/module' => $constraint],
			'repositories' => [
				['type' => 'package', 'package' => $packages],
				['packagist.org' => false],
			],
			'config' => ['vendor-dir' => 'system/vendor', 'cache-dir' => "$root/cache"],
			'scripts' => ['post-update-cmd' => [$this->marker('migration.txt')]],
		], JSON_THROW_ON_ERROR));
	}

	public function testRealPackageUpdateReplacesFilesAndPreservesApplicationData(): void {
		$this->checkPackageUpdate(true);
	}

	public function testRealPackageUpdateWithoutProcessStart(): void {
		$this->checkPackageUpdate(false);
	}

	private function checkPackageUpdate(bool $spawn): void {
		if (!class_exists(\ZipArchive::class)) $this->markTestSkipped('ZipArchive erforderlich');
		$tmp = $this->tempProject([]);
		$archives = [];
		foreach (['1.0.0', '1.1.0'] as $version) {
			$archives[$version] = "$tmp/module-$version.zip";
			$zip = new \ZipArchive();
			$this->assertTrue($zip->open($archives[$version], \ZipArchive::CREATE));
			$zip->addFromString('module/init.php', "<?php // $version");
			$zip->addFromString($version === '1.0.0' ? 'module/old.txt' : 'module/new.txt', $version);
			$zip->close();
		}
		$this->packageProject($tmp, $archives, '1.0.0');
		$composer = $this->projectComposer($tmp, $spawn);
		$result = $composer->run('update');
		$this->assertTrue($result['success'], $result['output']);
		$this->assertFileExists("$tmp/system/vendor/test/module/old.txt");
		file_put_contents("$tmp/data.db", 'unveraendert');
		unlink("$tmp/migration.txt");

		$this->packageProject($tmp, $archives, '1.1.0');
		$result = $composer->run('update');
		$this->assertTrue($result['success'], $result['output']);
		$this->assertStringContainsString('1.1.0', file_get_contents("$tmp/system/vendor/test/module/init.php"));
		$this->assertFileExists("$tmp/system/vendor/test/module/new.txt");
		$this->assertFileDoesNotExist("$tmp/system/vendor/test/module/old.txt");
		$this->assertFileExists("$tmp/migration.txt");
		$this->assertSame('unveraendert', file_get_contents("$tmp/data.db"));

		unlink("$tmp/migration.txt");
		(new \Symfony\Component\Filesystem\Filesystem())->remove([
			"$tmp/system/vendor/test/module/init.php",
			"$tmp/system/vendor/test/module/new.txt",
		]);
		$this->assertDirectoryExists("$tmp/system/vendor/test/module");
		$result = $composer->run('reinstall test/module --prefer-dist --no-scripts');
		$this->assertTrue($result['success'], $result['output']);
		$this->assertStringContainsString('1.1.0', file_get_contents("$tmp/system/vendor/test/module/init.php"));
		$this->assertFileExists("$tmp/system/vendor/test/module/new.txt");
		$this->assertFileDoesNotExist("$tmp/migration.txt");
		$result = $composer->run('install --no-scripts');
		$this->assertTrue($result['success'], $result['output']);

		$this->packageProject($tmp, $archives, '9.0.0');
		$result = $composer->run('update');
		$this->assertFalse($result['success'], $result['output']);
		$this->assertStringContainsString('9.0.0', $result['output']);
		$this->assertFileExists("$tmp/system/vendor/test/module/init.php");
		$this->assertFileDoesNotExist("$tmp/migration.txt");

		$this->packageProject($tmp, ['1.2.0' => "$tmp/missing.zip"], '1.2.0');
		$result = $composer->run('update');
		$this->assertFalse($result['success'], $result['output']);
		$this->assertStringContainsString('missing.zip', $result['output']);
		$this->assertFileExists("$tmp/system/vendor/test/module/init.php");
		$this->assertFileDoesNotExist("$tmp/migration.txt");
	}

	public function testSetupComposerCanRemoveLegacyBundledArchiveWithoutProcessStart(): void {
		$this->checkSelfUpdate(false);
	}

	public function testSetupComposerCanRemoveLegacyBundledArchiveInChildProcess(): void {
		$this->checkSelfUpdate(true);
	}

	private function checkSelfUpdate(bool $spawn): void {
		if (!class_exists(\ZipArchive::class)) $this->markTestSkipped('ZipArchive erforderlich');
		$tmp = $this->tempProject([]);
		$archives = [];
		foreach (['1.0.0', '1.1.0'] as $version) {
			$archives[$version] = "$tmp/self-$version.zip";
			$zip = new \ZipArchive();
			$this->assertTrue($zip->open($archives[$version], \ZipArchive::CREATE));
			$zip->addFromString('module/init.php', "<?php // $version");
			if ($version === '1.0.0') {
				$zip->addFile("{$this->setupDir}/composer.phar", 'module/composer.phar');
			}
			$zip->close();
		}
		$this->packageProject($tmp, $archives, '1.0.0');
		$result = $this->projectComposer($tmp, true)->run('update');
		$this->assertTrue($result['success'], $result['output']);
		unlink("$tmp/migration.txt");
		$this->packageProject($tmp, $archives, '1.1.0');
		$composer = $this->projectComposer($tmp, $spawn);
		$result = $composer->run('update');
		$this->assertTrue($result['success'], $result['output']);
		$this->assertStringContainsString('1.1.0', file_get_contents("$tmp/system/vendor/test/module/init.php"));
		$this->assertFileDoesNotExist("$tmp/system/vendor/test/module/composer.phar");
		$this->assertFileExists("$tmp/migration.txt");
		$this->assertDirectoryDoesNotExist("$tmp/data_c/composer-runtime");
		if ($spawn) {
			$this->assertStringContainsString($this->setupDir, file_get_contents("$tmp/composer_debug.txt"));
		}
		$result = $composer->run('show test/module');
		$this->assertTrue($result['success'], $result['output']);
	}
}
