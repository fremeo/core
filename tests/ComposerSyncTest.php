<?php
namespace fremeo\core\tests;

use PHPUnit\Framework\TestCase;

/**
 * system/core/Composer.php und setup/Composer.php müssen identisch bleiben:
 * Das Setup kann den Core bei der Installation noch nicht laden und liefert deshalb eine eigene Kopie mit.
 * Das Setup lädt nur seine Kopie (setup/console.php), das Projekt nur die des Cores (init.php).
 */
class ComposerSyncTest extends TestCase {

	public function testSetupAndCoreComposerAreIdentical(): void {
		$setup = realpath(__DIR__ . '/../../../../../setup/Composer.php');
		if (!$setup) {
			$this->markTestSkipped('setup/Composer.php nicht vorhanden (Core läuft außerhalb des Projekts)');
		}

		$this->assertSame(
			hash_file('sha256', $setup),
			hash_file('sha256', __DIR__ . '/../system/core/Composer.php'),
			'setup/Composer.php und system/core/Composer.php weichen voneinander ab: die geänderte Datei in die andere kopieren.'
		);
	}
}
