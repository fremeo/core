<?php
namespace fremeo\core\tests;

use fremeo\core\Container;
use fremeo\core\Security;
use phploader\CData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class AdminLanguageVariableListTest extends TestCase {
	private string $tmp;
	private Container $C;
	private array $modules;
	private ?string $requestMethod;

	protected function setUp(): void {
		$this->tmp = sys_get_temp_dir() . '/fremeo_language_' . uniqid();
		(new Filesystem())->mkdir($this->tmp . '/system/template/language');
		file_put_contents($this->tmp . '/system/template/language/de.php', '<?php return ["page" => ["title" => "Standard"]];');
		file_put_contents($this->tmp . '/system/template/language/en.php', '<?php return ["page" => ["title" => "Default"]];');
		$this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
		$this->C = new Container();
		$this->C->set('Setting', new class {
			public function get(string $module, string $key): string { return 'DE|EN'; }
		});
		$db = new CData(['DB' => ['FILENAME' => $this->tmp . '/data.db', 'FILENAME_C' => $this->tmp . '/cache.db']]);
		$db->registerPattern(require __DIR__ . '/../system/default/default.global.pattern.php');
		$this->C->set('CData', $db, 'fremeo/core');
		$this->modules = [
			'fremeo/core' => ['INFO' => ['Type' => 'core', 'ModulDir' => $this->tmp], 'Active' => 1],
			'fremeo/shop' => ['INFO' => ['Type' => 'module', 'ModulDir' => $this->tmp], 'Active' => 0],
			'fremeo/library' => ['INFO' => ['Type' => 'library', 'ModulDir' => $this->tmp]],
		];
	}

	protected function tearDown(): void {
		if ($this->requestMethod === null) unset($_SERVER['REQUEST_METHOD']);
		else $_SERVER['REQUEST_METHOD'] = $this->requestMethod;
		unset($this->C['fremeo/core']['CData']);
		unset($this->C);
		gc_collect_cycles();
		(new Filesystem())->remove($this->tmp);
	}

	private function page(array $posted = [], string $method = 'GET'): array {
		$_SERVER['REQUEST_METHOD'] = $method;
		$C = $this->C;
		$D = ['ACTION' => 'save', 'MODULE' => ['D' => array_replace_recursive($this->modules, $posted)]];
		$R = [];
		$F = ['PAGE' => []];
		include __DIR__ . '/../system/admin__languagevariable.list.php';
		$this->assertSame(['PAGE' => []], $F);
		return $R;
	}

	private function values(array $rows): array {
		return ['LANGUAGE' => ['D' => $rows]];
	}

	public function testDisabledModulesNeedNoServicesOnDisplayOrSave(): void {
		foreach (['GET', 'POST'] as $method) {
			$R = $this->page(['fremeo/shop' => $this->values(['DE' => ['I18N' => ['D' => ['title' => ['Value' => 'Ignored']]]]])], $method);
			$this->assertSame(['fremeo/core'], array_keys($R['LanguageRows']));
			$this->assertSame(['DE' => 'Standard', 'EN' => 'Default'], $R['LanguageRows']['fremeo/core']['title']);
		}
	}

	public function testPartialSaveAndDefaultRestoration(): void {
		$db = $this->C['fremeo/core']['CData'];
		$db->set_object($this->values([
			'DE' => ['I18N' => ['D' => ['title' => ['Value' => 'Alt']]]],
			'EN' => ['I18N' => ['D' => ['title' => ['Value' => 'Keep']]]],
		]));
		$R = $this->page(['fremeo/core' => $this->values(['DE' => ['I18N' => ['D' => ['title' => ['Value' => 'Neu']]]]])], 'POST');
		$this->assertSame(['DE' => 'Neu', 'EN' => 'Keep'], $R['LanguageRows']['fremeo/core']['title']);
		$R = $this->page(['fremeo/core' => $this->values(['DE' => ['I18N' => ['D' => ['title' => ['Value' => 'Standard']]]]])], 'POST');
		$this->assertSame(['DE' => 'Standard', 'EN' => 'Keep'], $R['LanguageRows']['fremeo/core']['title']);
	}

	public function testOverridesRemainAvailableAfterReactivation(): void {
		$this->C['fremeo/core']['CData']->set_object($this->values([
			'DE' => ['I18N' => ['D' => ['title' => ['Value' => 'Behalten']]]],
		]));
		$this->modules['fremeo/core']['Active'] = 0;
		$this->assertSame([], $this->page(['fremeo/core' => $this->values([
			'DE' => ['I18N' => ['D' => ['title' => '__DELETE__']]],
		])], 'POST')['LanguageRows']);
		$this->modules['fremeo/core']['Active'] = 1;
		$this->assertSame('Behalten', $this->page()['LanguageRows']['fremeo/core']['title']['DE']);
	}

	public function testDeletionRemovesOverridesInAllLanguagesOnlyOnPost(): void {
		$this->C['fremeo/core']['CData']->set_object($this->values([
			'DE' => ['I18N' => ['D' => ['custom' => ['Value' => 'Deutsch']]]],
			'FR' => ['I18N' => ['D' => ['custom' => ['Value' => 'French']]]],
		]));
		$posted = ['fremeo/core' => $this->values(['DE' => ['I18N' => ['D' => ['custom' => '__DELETE__']]]])];
		$this->assertArrayHasKey('custom', $this->page($posted)['LanguageRows']['fremeo/core']);
		$this->assertArrayNotHasKey('custom', $this->page($posted, 'POST')['LanguageRows']['fremeo/core']);
		$f = ['LANGUAGE' => ['I18N' => []]];
		$this->C['fremeo/core']['CData']->get_object($stored, $f);
		$this->assertArrayNotHasKey('custom', $stored['LANGUAGE']['D']['FR']['I18N']['D'] ?? []);
	}

	#[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
	public function testThemeAwareTemplateAndFooter(): void {
		if (!defined('SCRIPT_NAME')) define('SCRIPT_NAME', '/test/');
		$security = new Security();
		$security->SecurityActive = false;
		$this->C->set('Security', $security);
		$smarty = new \Smarty();
		$smarty->setCompileDir($this->tmp);
		$smarty->error_reporting = E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED;
		$smarty->assign('D', ['C' => $this->C, 'R' => [
			'LanguageIsos' => ['DE', 'EN'],
			'LanguageRows' => ['fremeo/core' => ['<title>' => ['DE' => 'Standard', 'EN' => 'Default']], 'fremeo/empty' => []],
		]]);
		$tpl = __DIR__ . '/../system/template/';
		$html = $smarty->fetch("extends:{$tpl}index__index.tpl|{$tpl}index__admin.tpl|{$tpl}admin__languagevariable.list.tpl|{$tpl}include/input.tpl");
		$smarty->clearAllAssign();
		$this->assertStringNotContainsString('bg-white', $html);
		$this->assertStringNotContainsString('bg-light', $html);
		$this->assertStringNotContainsString('background:#eee', $html);
		$this->assertStringContainsString('bg-body-tertiary border-top', $html);
		$this->assertStringContainsString('form="language-list-form"', $html);
		$this->assertStringContainsString('>Abbrechen</a>', $html);
		$this->assertStringContainsString('Anzahl: 1', $html);
		$this->assertStringContainsString('&lt;title&gt;', $html);
		$this->assertStringContainsString('Keine Sprachvariablen vorhanden.', $html);
		$this->assertStringContainsString('tab-pane fade show active', $html);
		$this->assertLessThan(strpos($html, 'form="language-list-form"'), strpos($html, '</form>'));
	}
}
