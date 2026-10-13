<?php
namespace fremeo\core\tests;

use PHPUnit\Framework\TestCase;

/**
 * setup/Composer.php: Ist proc_open gesperrt (disable_functions), läuft Composer im selben PHP-Prozess.
 * Der Test simuliert das, indem er canSpawnProcess() überschreibt.
 */
class SetupComposerTest extends TestCase {

	private string $setupDir;

	protected function setUp(): void {
		$this->setupDir = realpath(__DIR__ . '/../../../../../setup');
		if (!$this->setupDir || !is_file("{$this->setupDir}/composer.phar")) {
			$this->markTestSkipped('setup/composer.phar nicht vorhanden');
		}
		require_once "{$this->setupDir}/Composer.php";
		$_SERVER['SCRIPT_FILENAME'] = "{$this->setupDir}/console.php"; // Projektwurzel = Ordner über setup/
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
		$composer = $this->inProcessComposer();
		$cwd = getcwd();

		$yielded = iterator_to_array($composer->stream('--version'), false);
		chdir($cwd);

		$this->assertSame([], $yielded, 'keine Fehlermeldung erwartet: ' . implode(' | ', $yielded));
		$this->assertStringContainsString('Composer version', $composer->out);
	}

	public function testUnknownCommandIsReportedByComposer(): void {
		$composer = $this->inProcessComposer();
		$cwd = getcwd();

		iterator_to_array($composer->stream('gibtesnicht'), false);
		chdir($cwd);

		$this->assertStringContainsString('gibtesnicht', $composer->out);
	}

	/** Projekt in einem Temp-Ordner mit Skripten, die eine Marker-Datei schreiben; setup/ liegt wie im echten Projekt darin. */
	private function tempProject(array $scripts): string {
		$tmp = sys_get_temp_dir() . '/setup_composer_' . uniqid();
		mkdir("$tmp/setup", 0777, true);
		file_put_contents("$tmp/composer.json", json_encode(['name' => 'test/test', 'require' => new \stdClass(), 'scripts' => $scripts]));
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

		iterator_to_array($composer->stream('install'), false);
		chdir($cwd);

		$this->assertStringContainsString('kann kein Prozess gestartet werden', $composer->out);
		$this->assertStringContainsString('@php index.php migration', $composer->out); // der Befehl zum manuellen Ausführen

		(new \Symfony\Component\Filesystem\Filesystem())->remove($tmp);
	}
}
