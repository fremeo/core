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
			protected function canSpawnProcess(): bool { return false; }
			protected function emit(string $text): void { $this->out .= $text; }
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
}
