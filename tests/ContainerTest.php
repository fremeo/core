<?php
namespace fremeo\core\tests;

use fremeo\core\Container;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase {

	protected function setUp(): void {
		require_once __DIR__ . '/../system/core/Container.php';
	}

	public function testGetAllReturnsTheServiceOfEveryModuleThatHasIt(): void {
		$C = new Container();
		$C->set('Smarty', fn() => new \stdClass());      // globaler Dienst, kein Modul
		$C->set('CData', 'core-db', 'fremeo/core');
		$C->set('CData', 'page-db', 'fremeo/page');
		$C->set('File', fn() => 'file', 'fremeo/page');
		$C->set('Other', fn() => 1, 'phploader/cdata');   // Modul ohne CData (Library)

		$this->assertSame(['fremeo/core' => 'core-db', 'fremeo/page' => 'page-db'], $C->getAll('CData'));
		$this->assertSame(['fremeo/page' => 'file'], $C->getAll('File'));
		$this->assertSame([], $C->getAll('Gibtsnicht'));
	}

	public function testGetAllCreatesLazyServicesOnlyOnce(): void {
		$C = new Container();
		$calls = 0;
		$C->set('File', function () use (&$calls) { return new \stdClass(); }, 'fremeo/page');

		$first = $C->getAll('File')['fremeo/page'];
		$second = $C->getAll('File')['fremeo/page'];

		$this->assertSame($first, $second);
		$this->assertSame($first, $C['fremeo/page']['File']);
	}
}
