<?php
namespace fremeo\core\tests;

use fremeo\core\Container;
use fremeo\core\LanguageVariable;
use fremeo\core\Template;
use phploader\CData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class LanguageVariableTest extends TestCase {
	private string $tmp;
	private Container $C;
	private array $D;
	private \Smarty $smarty;
	private LanguageVariable $language;

	protected function setUp(): void {
		$this->tmp = sys_get_temp_dir() . '/fremeo_lang_compile_' . bin2hex(random_bytes(6));
		$this->C = new Container();
		$this->C->set('Filesystem', new Filesystem());
		$this->D = ['MODULE' => ['D' => []]];
		foreach (['test/core' => null, 'test/child' => 'test/core', 'test/other' => null] as $id => $parent) {
			$dir = $this->tmp . '/' . str_replace('/', '~', $id);
			(new Filesystem())->mkdir($dir . '/system/template/language');
			file_put_contents($dir . '/composer.json', json_encode($parent ? ['require' => [$parent => '*'], 'extra' => ['module-parent' => $parent]] : []));
			$this->D['MODULE']['D'][$id] = ['Active' => 1, 'INFO' => [
				'Type' => $id === 'test/core' ? 'core' : 'template', 'ModulDir' => $dir, 'DataDir' => $dir . '/data',
			]];
			$db = new class(['DB' => ['FILENAME' => $dir . '/data/data.db', 'FILENAME_C' => $dir . '/data/cache.db']]) extends CData {
				public int $reads = 0;
				public function get_object(&$D = null, &$F = null, $Parent_Hash = [], $Parent_Type = '', $Parent_Id = '') {
					$this->reads++;
					return parent::get_object($D, $F, $Parent_Hash, $Parent_Type, $Parent_Id);
				}
			};
			$db->registerPattern(require __DIR__ . '/../system/default/default.global.pattern.php');
			$this->C->set('CData', $db, $id);
		}
		$this->C->set('Template', new Template($this->C, $this->D));
		$this->smarty = new \Smarty();
		$this->smarty->setCompileDir($this->tmp . '/compiled');
		$this->smarty->setCacheDir($this->tmp . '/html-cache');
		$this->C->set('Smarty', $this->smarty);
		$this->language = new LanguageVariable($this->C, $this->D);
		$this->language->configure($this->smarty);
	}

	protected function tearDown(): void {
		$this->smarty->clearAllAssign();
		$this->smarty->unregisterPlugin(\Smarty::PLUGIN_COMPILER, 'i18n');
		foreach (array_keys($this->D['MODULE']['D']) as $id) unset($this->C[$id]['CData']);
		unset($this->language, $this->smarty, $this->C);
		gc_collect_cycles();
		(new Filesystem())->remove($this->tmp);
	}

	private function defaults(string $module, array $texts, string $language = 'de'): void {
		file_put_contents($this->D['MODULE']['D'][$module]['INFO']['ModulDir'] . "/system/template/language/$language.php", '<?php return ' . var_export(['texts' => $texts], true) . ';');
	}

	private function render(string $text, array $values = [], string $module = 'test/core'): string {
		$file = $this->D['MODULE']['D'][$module]['INFO']['ModulDir'] . '/system/template/test_' . sha1($text) . '.tpl';
		if (!is_file($file)) file_put_contents($file, $text);
		$this->smarty->clearAllAssign();
		$this->smarty->assign($values);
		return $this->smarty->fetch($file);
	}

	public function testCompiledTextSupportsHtmlIcuAndLocalDynamicValues(): void {
		$this->defaults('test/core', ['result' => '<u>{count, plural, one {Ein Bild} other {# Bilder}}</u> {name}']);
		$template = '{i18n key="result" values=["count" => $number, "name" => $user]}';
		$this->assertSame('<u>Ein Bild</u> &lt;Anna&gt;', $this->render($template, ['number' => 1, 'user' => '<Anna>']));
		$this->assertSame('<u>5 Bilder</u> Bob', $this->render($template, ['number' => 5, 'user' => 'Bob']));
		$compiled = implode('', array_map('file_get_contents', glob($this->tmp . '/compiled/*.php')));
		$this->assertStringContainsString('Ein Bild', $compiled);
		$this->assertStringNotContainsString('->resolve(', $compiled);
		$this->assertStringNotContainsString('->getDefaults(', $compiled);
		$this->defaults('test/core', ['local' => 'isolated']);
		$this->language->clearCache();
		$this->assertSame('isolated', $this->render('{i18n key="local"}', ['secret' => 'must-not-leak']));
	}

	public function testMissingRegistrationDoesNotBlockLaterDefaultsAndBlankOverridePersists(): void {
		$this->assertSame('missing', $this->render('{i18n key="missing"}'));
		$this->assertSame('missing', $this->language->resolve('test/core', 'missing', 'test.tpl'));
		$filter = ['LANGUAGE' => ['I18N' => []]];
		$this->C['test/core']['CData']->get_object($data, $filter);
		$this->assertCount(1, $data['LANGUAGE']['D']['DE']['I18N']['D']);
		$this->assertSame(1, (int)$data['LANGUAGE']['D']['DE']['I18N']['D']['missing']['Missing']);
		$this->defaults('test/core', ['missing' => 'Now supplied']);
		$this->language->clearCache();
		$this->assertSame('Now supplied', $this->render('{i18n key="missing"}'));
		$this->language->save('test/core', ['DE' => ['I18N' => ['D' => ['missing' => ['Value' => '']]]]], ['DE']);
		$this->assertSame('', $this->render('{i18n key="missing"}'));
		$this->language->save('test/core', ['DE' => ['I18N' => ['D' => ['missing' => ['Reset' => 1]]]]], ['DE']);
		$this->assertSame('Now supplied', $this->render('{i18n key="missing"}'));
	}

	public function testChildPriorityLanguageFallbackAndModuleIsolation(): void {
		$this->defaults('test/core', ['title' => 'Base']);
		$this->defaults('test/core', ['title' => 'English'], 'en');
		$this->defaults('test/child', ['test/core:title' => 'Child']);
		$this->defaults('test/other', ['title' => 'Other']);
		$this->language->configure($this->smarty, ['Template' => 'test/child', 'Branch' => 'admin']);
		$this->assertSame('Child', $this->render('{i18n key="title"}'));
		$this->assertSame('Other', $this->render('{i18n module="test/other" key="title"}'));
		$this->language->save('test/child', ['DE' => ['I18N' => ['D' => ['title' => ['Value' => 'Child-local title']]]]], ['DE']);
		$this->assertSame('Child', $this->render('{i18n key="title"}'));
		$this->language->save('test/core', ['DE' => ['I18N' => ['D' => ['title' => ['Value' => 'Custom base']]]]], ['DE']);
		$this->assertSame('Custom base', $this->render('{i18n key="title"}'));
		$this->language->save('test/child', ['DE' => ['I18N' => ['D' => ['test/core:title' => ['Value' => 'Custom child']]]]], ['DE']);
		$this->assertSame('Custom child', $this->render('{i18n key="title"}'));
		$id = $this->smarty->compile_id;
		$this->language->configure($this->smarty, ['Template' => 'test/child', 'Branch' => 'admin'], 'EN');
		$this->assertNotSame($id, $this->smarty->compile_id);
		$this->assertSame('English', $this->render('{i18n key="title"}'));
		$this->language->configure($this->smarty, ['Template' => 'test/child', 'Branch' => 'admin'], 'FR');
		$this->assertSame('Custom child', $this->render('{i18n key="title"}'));
	}

	public function testInheritedTemplateUsesItsOwnModuleAndDynamicKeysFailExplicitly(): void {
		$this->defaults('test/core', ['title' => 'Core']);
		$this->defaults('test/other', ['title' => 'Other']);
		$core = $this->D['MODULE']['D']['test/core']['INFO']['ModulDir'] . '/system/template/base.tpl';
		file_put_contents($core, '{i18n key="title"}{block name="body"}{/block}');
		$child = $this->D['MODULE']['D']['test/other']['INFO']['ModulDir'] . '/system/template/child.tpl';
		file_put_contents($child, '{block name="body"}{i18n key="title"}{/block}');
		$this->assertSame('CoreOther', $this->smarty->fetch("extends:$core|$child"));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('feste Zeichenkette');
		$this->render('{i18n key=$key}', ['key' => 'title']);
	}

	public function testWarmCompilationNeedsNeitherLanguageQueriesNorFileContents(): void {
		$file = $this->D['MODULE']['D']['test/core']['INFO']['ModulDir'] . '/system/template/language/de.php';
		$this->defaults('test/core', ['title' => 'Compiled']);
		$this->language->configure($this->smarty);
		$this->assertSame('Compiled', $this->render('{i18n key="title"}'));
		$reads = $this->C['test/core']['CData']->reads;
		$timestamp = filemtime($file);
		file_put_contents($file, '<?php throw new \RuntimeException("Language file read on warm render");');
		touch($file, $timestamp);
		clearstatcache();
		$this->assertFreshTranslation('{i18n key="title"}', 'Compiled', false);
		$this->assertSame($reads, $this->C['test/core']['CData']->reads);
	}

	public function testFileChangesAndNewLanguageFilesInvalidateCompiledTranslations(): void {
		$template = '{i18n key="new_title"}';
		$this->assertSame('new_title', $this->render($template));
		$this->defaults('test/core', ['new_title' => 'New file']);
		$this->assertFreshTranslation($template, 'New file');
		$file = $this->D['MODULE']['D']['test/core']['INFO']['ModulDir'] . '/system/template/language/de.php';
		$timestamp = filemtime($file);
		$this->defaults('test/core', ['new_title' => 'Changed file']);
		touch($file, $timestamp + 2);
		clearstatcache();
		$this->assertFreshTranslation($template, 'Changed file');
		unlink($file);
		clearstatcache();
		$this->assertFreshTranslation($template, 'new_title');
	}

	public function testWarmDynamicCompilationNeedsNoLanguageQueriesOrFileContents(): void {
		$this->defaults('test/core', ['result' => '{count, plural, one {Ein Bild} other {# Bilder}}: {name}']);
		$this->language->configure($this->smarty);
		$template = '{i18n key="result" values=["count" => $number, "name" => $name]}';
		$this->assertSame('Ein Bild: Anna', $this->render($template, ['number' => 1, 'name' => 'Anna']));
		$file = $this->D['MODULE']['D']['test/core']['INFO']['ModulDir'] . '/system/template/language/de.php';
		$timestamp = filemtime($file);
		file_put_contents($file, '<?php throw new \RuntimeException("Language file read on warm render");');
		touch($file, $timestamp);
		clearstatcache();
		$this->assertFreshTranslation($template, '20 Bilder: &lt;Bob&gt;', false, ['number' => 20, 'name' => '<Bob>']);
	}

	private function assertFreshTranslation(string $text, string $expected, bool $allowReads = true, array $values = []): void {
		$file = $this->D['MODULE']['D']['test/core']['INFO']['ModulDir'] . '/system/template/test_' . sha1($text) . '.tpl';
		$code = 'require ' . var_export(__DIR__ . '/../../../autoload.php', true) . ';'
			. '$D = ' . var_export($this->D, true) . ';'
			. '$C = new \fremeo\core\Container(); $C->set("Filesystem", new \Symfony\Component\Filesystem\Filesystem());'
			. 'foreach ($D["MODULE"]["D"] as $id => $module) {'
			. '$dir = $module["INFO"]["DataDir"];'
			. '$db = new class(["DB" => ["FILENAME" => "$dir/data.db", "FILENAME_C" => "$dir/cache.db"]]) extends \phploader\CData {'
			. 'public function get_object(&$D = null, &$F = null, $Parent_Hash = [], $Parent_Type = "", $Parent_Id = "") {'
			. ($allowReads ? 'return parent::get_object($D, $F, $Parent_Hash, $Parent_Type, $Parent_Id);' : 'throw new \RuntimeException("Language database read on warm render");')
			. '}};'
			. '$db->registerPattern(require ' . var_export(__DIR__ . '/../system/default/default.global.pattern.php', true) . ');'
			. '$C->set("CData", $db, $id); }'
			. '$C->set("Template", new \fremeo\core\Template($C, $D));'
			. '$smarty = new \Smarty(); $smarty->setCompileDir(' . var_export($this->tmp . '/compiled', true) . ');'
			. '$C->set("Smarty", $smarty); $lang = new \fremeo\core\LanguageVariable($C, $D); $lang->configure($smarty);'
			. '$smarty->assign(' . var_export($values, true) . ');'
			. 'echo $smarty->fetch(' . var_export($file, true) . ');';
		$process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		$this->assertIsResource($process);
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$this->assertSame(0, proc_close($process), $error);
		$this->assertSame($expected, $output, $error);
	}

	#[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
	public function testRealLoginUsesGermanDefaultsAndEscapesAttributeTranslations(): void {
		$directory = str_replace('\\', '/', dirname(__DIR__));
		$this->D['MODULE']['D']['fremeo/core'] = ['Active' => 1, 'INFO' => [
			'Type' => 'core', 'ModulDir' => $directory, 'DataDir' => $this->tmp . '/real-data',
		]];
		$this->C->set('CData', $this->C['test/core']['CData'], 'fremeo/core');
		$this->language->configure($this->smarty);
		$this->smarty->error_reporting = E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED;
		$this->smarty->registerPlugin(\Smarty::PLUGIN_MODIFIER, 'strstr', 'strstr');
		$this->smarty->assign('D', ['R' => ['Return' => ['Page' => '', 'ModuleId' => ''], 'UserName' => ''], 'SESSION' => ['AccountId' => '']]);
		$file = 'extends:' . $directory . '/system/template/index__login.tpl|' . $directory . '/system/template/include/input.tpl';
		$html = $this->smarty->fetch($file);
		$this->assertStringContainsString('class="login-title">Anmelden</h2>', $html);
		$this->assertStringContainsString('placeholder="Benutzername"', $html);
		$this->assertStringContainsString('placeholder="Passwort"', $html);
		$this->assertStringContainsString('Passwort vergessen', $html);
		$this->language->save('fremeo/core', ['DE' => ['I18N' => ['D' => [
			'login_username' => ['Value' => '"<u>Name</u>'],
		]]]], ['DE']);
		$html = $this->smarty->fetch($file);
		$this->assertStringContainsString('placeholder="&quot;&lt;u&gt;Name&lt;/u&gt;"', $html);
	}

	public function testExplicitBlankNewEntryAndLegacyBlankRemainOverrides(): void {
		$this->language->save('test/core', ['DE' => ['I18N' => ['D' => ['blank' => ['Empty' => 1]]]]], ['DE']);
		$this->assertSame('', $this->render('{i18n key="blank"}'));
		$legacy = ['LANGUAGE' => ['D' => ['DE' => ['I18N' => ['D' => ['old' => ['Value' => 'Legacy']]]]]]];
		$this->C['test/core']['CData']->set_object($legacy);
		$this->language->clearCache();
		$this->assertSame('Legacy', $this->render('{i18n key="old"}'));
	}

	public function testIcuSyntaxIsNotValidatedOnSaveButCompilationNamesTheVariable(): void {
		$this->language->save('test/core', ['DE' => ['I18N' => ['D' => ['broken' => ['Value' => '{count, plural, one {Ein Bild}']]]]], ['DE']);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('i18n broken (de)');
		$this->render('{i18n key="broken" values=["count" => 1]}');
	}

	public function testOnlySelectedDescendantsContributeAndDisabledModulesAreRejected(): void {
		$this->defaults('test/core', ['title' => 'Base']);
		$this->defaults('test/child', ['test/core:title' => 'Child']);
		$this->language->configure($this->smarty, ['Template' => 'test/other', 'Branch' => 'frontend']);
		$this->assertSame('Base', $this->render('{i18n key="title"}'));
		$this->D['MODULE']['D']['test/child']['Active'] = 0;
		$this->language->configure($this->smarty, ['Template' => 'test/core', 'Branch' => 'admin']);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Ungueltige Sprachvariable');
		$this->render('{i18n module="test/child" key="title"}');
	}
}
