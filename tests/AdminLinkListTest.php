<?php
namespace fremeo\core\tests;

use fremeo\core\Link;
use PHPUnit\Framework\TestCase;
use phploader\CData;
use Symfony\Component\Filesystem\Filesystem;

/** Integrationstest: admin__link.list.php löscht markierte Links je Modul (erst beim Speichern). */
class AdminLinkListTest extends TestCase {

	private string $tmp;
	private array $C;

	protected function setUp(): void {
		$this->tmp = sys_get_temp_dir() . '/fremeo_linklist_' . uniqid();
		mkdir($this->tmp);

		foreach (['a' => 'fremeo/a', 'b' => 'fremeo/b'] as $n => $id) {
			$db = new CData(['DB' => ['FILENAME' => "{$this->tmp}/{$n}.db", 'FILENAME_C' => "{$this->tmp}/{$n}_c.db"]]);
			$db->registerPattern(['LINK' => [
				'Active' => ['Type' => 'checkbox'], 'FromURL' => ['Type' => 'text'], 'ToURL' => ['Type' => 'text'],
			]]);
			$this->C[$id]['CData'] = $db;
		}
	}

	protected function tearDown(): void {
		$this->C = [];
		gc_collect_cycles();
		(new Filesystem())->remove($this->tmp);
	}

	private function seoList(string $module): array {
		$f['LINK'] = [];
		$this->C[$module]['CData']->get_object($d, $f);
		return array_column($d['LINK']['D'] ?? [], 'FromURL');
	}

	/** Führt die Seite wie ein Klick auf "Speichern" aus. */
	private function save(array $delete): void {
		$C = &$this->C;
		$D = ['ACTION' => 'save', 'MODULE' => ['D' => ['fremeo/a' => [], 'fremeo/b' => []]]];
		$R = ['delete' => $delete];
		(function () use (&$C, &$D, &$R) {
			include __DIR__ . '/../system/admin__link.list.php';
		})();
	}

	public function testMarkedLinksAreDeletedOnlyInTheirModule(): void {
		$a = new Link($this->C['fremeo/a']['CData']);
		$b = new Link($this->C['fremeo/b']['CData']);
		$idA1 = $a->createOne('eins', 'p', 'fremeo/a');
		$idA2 = $a->createOne('zwei', 'p', 'fremeo/a');
		$b->createOne('drei', 'p', 'fremeo/b');

		$this->save(['fremeo/a' => [$idA1]]);

		$this->assertSame(['zwei'], $this->seoList('fremeo/a'));
		$this->assertSame(['drei'], $this->seoList('fremeo/b'));
		$this->assertNotSame($idA1, $idA2);
	}

	public function testNothingIsDeletedWithoutSave(): void {
		$a = new Link($this->C['fremeo/a']['CData']);
		$a->createOne('bleibt', 'p', 'fremeo/a');

		$C = &$this->C;
		$D = ['MODULE' => ['D' => ['fremeo/a' => []]]];
		$R = ['delete' => ['fremeo/a' => [$a->id('bleibt')]]];
		(function () use (&$C, &$D, &$R) {
			include __DIR__ . '/../system/admin__link.list.php';
		})();

		$this->assertSame(['bleibt'], $this->seoList('fremeo/a'));
	}

	public function testUnknownModuleAndInvalidIdsAreIgnored(): void {
		$a = new Link($this->C['fremeo/a']['CData']);
		$a->createOne('sicher', 'p', 'fremeo/a');

		$this->save(['fremeo/unbekannt' => ['x'], 'fremeo/a' => ["x' OR '1'='1", ['array']]]);

		$this->assertSame(['sicher'], $this->seoList('fremeo/a'));
	}

	#[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
	public function testTemplateUsesThemeColorsAndSeparateFooter(): void {
		if (!defined('SCRIPT_NAME')) define('SCRIPT_NAME', '/test/');
		$C = new \fremeo\core\Container();
		$security = new \fremeo\core\Security();
		$security->SecurityActive = false;
		$C->set('Security', $security);
		$smarty = new \Smarty();
		$smarty->setCompileDir($this->tmp);
		$smarty->error_reporting = E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED;
		$smarty->assign('D', [
			'C' => $C, 'R' => ['LinkModules' => ['fremeo/a', 'fremeo/b']],
			'MODULE' => ['D' => [
				'fremeo/a' => ['LINK' => ['COUNT' => 1, 'D' => ['test' => ['Active' => 1, 'FromURL' => '<test>', 'ToURL' => 'R[Page]=test']]]],
				'fremeo/b' => ['LINK' => ['COUNT' => 0, 'D' => []]],
			]],
		]);
		$tpl = __DIR__ . '/../system/template/';
		$html = $smarty->fetch("extends:{$tpl}index__index.tpl|{$tpl}index__admin.tpl|{$tpl}admin__link.list.tpl|{$tpl}include/input.tpl");
		$smarty->clearAllAssign();
		$this->assertStringNotContainsString('bg-white', $html);
		$this->assertStringNotContainsString('bg-light', $html);
		$this->assertStringNotContainsString('background:#eee', $html);
		$this->assertStringContainsString('form="link-list-form"', $html);
		$this->assertStringContainsString('>Abbrechen</a>', $html);
		$this->assertStringContainsString('Anzahl: 1', $html);
		$this->assertStringContainsString('tab-pane fade show active', $html);
		$this->assertStringContainsString('Keine Links vorhanden.', $html);
		$this->assertStringContainsString('&lt;test&gt;', $html);
		$this->assertLessThan(strpos($html, 'form="link-list-form"'), strpos($html, '</form>'));
	}
}
