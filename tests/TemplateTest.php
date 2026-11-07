<?php
namespace fremeo\core\tests;

use fremeo\core\Container;
use fremeo\core\Template;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class TemplateTest extends TestCase {

	private string $tmp;
	private array $modules;
	private Template $template;
	private Container $C;
	private array $D;

	protected function setUp(): void {
		$this->tmp = str_replace('\\', '/', sys_get_temp_dir()) . '/fremeo_template_' . bin2hex(random_bytes(8));
		$this->modules = [];
		$this->C = new Container();
		$this->D = [];
		$this->template = new Template($this->C, $this->D);
		foreach (['core' => 'core', 'frontend' => 'template', 'extension' => 'template', 'page' => 'module', 'library' => 'library'] as $name => $type) {
			$dir = "{$this->tmp}/$name";
			mkdir("$dir/system/template", 0777, true);
			$this->modules["test/$name"] = ['Type' => $type, 'INFO' => ['Type' => $type, 'ModulDir' => $dir]];
		}

		foreach ([
			'core/index' => '<root>{block name="body"}root{/block}</root>',
			'core/index__index' => '{block name="body"}common{/block}',
			'core/index__admin' => '{block name="body"}admin:{block name="content"}empty{/block}{/block}',
			'frontend/index__frontend' => '{block name="body"}frontend:{block name="content"}empty{/block}{/block}',
			'page/frontend__article' => '{block name="content"}article{/block}',
			'page/admin__article' => '{block name="content"}admin-article{/block}',
		] as $entry => $tpl) {
			[$module, $name] = explode('/', $entry);
			$this->write($module, "$name.tpl", $tpl);
			$this->write($module, "$name.php", '<?php $R["Trace"][] = ' . var_export($entry, true) . ';');
		}
	}

	protected function tearDown(): void {
		(new Filesystem())->remove($this->tmp);
	}

	private function write(string $module, string $file, string $content): string {
		$folder = str_ends_with($file, '.tpl') ? 'system/template' : 'system';
		$path = "{$this->tmp}/$module/$folder/$file";
		file_put_contents($path, $content);
		return $path;
	}

	public function testAdminSidebarSupportsCompactIconsAndScrollableFlyouts(): void {
		$smarty = new \Smarty();
		$smarty->setCompileDir($this->tmp);
		$html = $smarty->fetch(__DIR__ . '/../system/template/index__admin.tpl');
		$this->assertStringContainsString('aria-controls="admin-sidebar"', $html);
		$this->assertStringContainsString('id="admin-sidebar"', $html);
		$this->assertStringContainsString('aria-label="Administration"', $html);
		$this->assertStringContainsString('id="toggleSidebar"', $html);
		$this->assertStringNotContainsString('toggleButton', $html);
		$this->assertSame(1, substr_count($html, 'href="?R[Page]=admin__setting.list&R[ModuleId]=fremeo/core#submenu5"'));
		$this->assertStringNotContainsString('> Menu</a>', $html);
		$css = file_get_contents(__DIR__ . '/../system/template/theme/basic/css/admin.css');
		$this->assertStringNotContainsString('.sidebar.collapsed', $css);
		$this->assertStringContainsString('overflow-y: auto;', $css);
		$this->assertStringContainsString('max-height: calc(100vh - 16px)', $css);
		$this->assertStringContainsString('.sidebar-flyout', $css);
		$js = file_get_contents(__DIR__ . '/../system/template/theme/basic/js/admin.js');
		$this->assertStringNotContainsString('removeAttribute', $js);
		$this->assertStringContainsString('heading.textContent = label', $js);
		$this->assertStringContainsString('link.title = menu ? "" : label', $js);
		$this->assertStringContainsString('menu.style.left = link.getBoundingClientRect().right', $js);
		$this->assertStringContainsString('setTimeout(closeFlyout, 200)', $js);
	}

	#[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
	public function testSettingsFooterIsOutsideScrollableForm(): void {
		if (!defined('SCRIPT_NAME')) define('SCRIPT_NAME', '/test/');
		$security = new \fremeo\core\Security();
		$security->SecurityActive = false;
		$this->C->set('Security', $security);
		$smarty = new \Smarty();
		$smarty->setCompileDir($this->tmp);
		$smarty->error_reporting = E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED;
		$smarty->assign('D', ['C' => $this->C, 'R' => ['SETTING_VIEW' => [
			'default' => ['Items' => [[
				'Module' => 'fremeo/core', 'Key' => 'test', 'Value' => 'Value',
				'Meta' => ['Label' => 'Test', 'Description' => '', 'InputType' => 'text', 'Options' => []],
			]]],
		]]]);
		$tpl = __DIR__ . '/../system/template/';
		$html = $smarty->fetch("extends:{$tpl}index__index.tpl|{$tpl}index__admin.tpl|{$tpl}admin__setting.list.tpl|{$tpl}include/input.tpl");
		$smarty->clearAllAssign();
		$this->assertStringContainsString('id="setting-list-form"', $html);
		$this->assertStringContainsString('form="setting-list-form"', $html);
		$this->assertStringContainsString('>Speichern</button>', $html);
		$this->assertStringContainsString('>Abbrechen</a>', $html);
		$this->assertStringContainsString('bg-body-tertiary border-top', $html);
		$this->assertLessThan(strpos($html, 'form="setting-list-form"'), strpos($html, '</form>'));
	}

	public function testInactiveModulesAndTheirTemplateExtensionsAreExcluded(): void {
		$this->modules['test/page']['Active'] = 0;
		$this->assertSame([], $this->template->getExtends($this->modules, 'test/page', 'frontend__article')['tpl']);
		$this->modules['test/page']['Active'] = 1;
		$this->modules['test/frontend']['Active'] = 0;
		$chain = $this->template->getExtends($this->modules, 'test/page', 'frontend__article');
		$this->assertNotContains("{$this->tmp}/frontend/system/template/index__frontend.tpl", $chain['tpl']);
		$this->modules['test/frontend__child'] = ['Active' => 1, 'INFO' => ['Type' => 'template', 'ModulDir' => "{$this->tmp}/child"]];
		(new Filesystem())->dumpFile("{$this->tmp}/child/composer.json", json_encode([
			'extra' => ['module-parent' => 'test/frontend'], 'require' => ['test/frontend' => '*'],
		]));
		$this->assertArrayNotHasKey('test/frontend__child', $this->template->getThemeModules($this->modules));
		$this->modules['test/core']['Active'] = 0;
		$this->assertArrayHasKey('test/core', $this->template->getThemeModules($this->modules));
		$this->assertNotEmpty($this->template->getExtends($this->modules, 'test/core', 'admin__article')['tpl']);
	}
	public function testRootPageKeepsItsOwnOptionalPhp(): void {
		$tpl = "{$this->tmp}/core/system/template/index.tpl";
		$this->assertSame([
			'extends' => "extends:$tpl",
			'tpl' => [$tpl],
			'php' => ["{$this->tmp}/core/system/index.php"],
		], $this->template->getExtends($this->modules, 'test/core', 'index'));

		$tpl = $this->write('page', 'standalone.tpl', 'standalone');
		$this->assertSame([
			'extends' => "extends:$tpl",
			'tpl' => [$tpl],
			'php' => [],
		], $this->template->getExtends($this->modules, 'test/page', 'standalone'));
	}

	public function testFrontendChainDoesNotContainAdminOrUnrelatedTemplates(): void {
		$this->write('extension', 'index__account.tpl', 'unrelated');
		$this->write('library', 'index__frontend.tpl', 'library');
		$tpl = [
			"{$this->tmp}/core/system/template/index.tpl",
			"{$this->tmp}/core/system/template/index__index.tpl",
			"{$this->tmp}/frontend/system/template/index__frontend.tpl",
			"{$this->tmp}/page/system/template/frontend__article.tpl",
		];
		$this->assertSame([
			'extends' => 'extends:' . implode('|', $tpl),
			'tpl' => $tpl,
			'php' => [
				"{$this->tmp}/core/system/index.php",
				"{$this->tmp}/core/system/index__index.php",
				"{$this->tmp}/frontend/system/index__frontend.php",
				"{$this->tmp}/page/system/frontend__article.php",
			],
		], $this->template->getExtends($this->modules, 'test/page', 'frontend__article'));
	}

	public function testAdminChainStaysInCoreUntilTheContentPage(): void {
		$chain = $this->template->getExtends($this->modules, 'test/page', 'admin__article');
		$this->assertSame([
			"{$this->tmp}/core/system/template/index.tpl",
			"{$this->tmp}/core/system/template/index__index.tpl",
			"{$this->tmp}/core/system/template/index__admin.tpl",
			"{$this->tmp}/page/system/template/admin__article.tpl",
		], $chain['tpl']);
	}

	public function testTemplateExtensionsAndSubRootsKeepTheirPositions(): void {
		$extension = $this->write('extension', 'frontend__frontend.tpl', '{block name="content" append}extension{/block}');
		$this->write('extension', 'frontend__frontend.php', '<?php');
		$subRoot = $this->write('frontend', 'article__article.tpl', '{block name="content" append}subroot{/block}');
		$chain = $this->template->getExtends($this->modules, 'test/page', 'frontend__article');

		$this->assertSame([
			"{$this->tmp}/core/system/template/index.tpl",
			"{$this->tmp}/core/system/template/index__index.tpl",
			"{$this->tmp}/frontend/system/template/index__frontend.tpl",
			$extension,
			"{$this->tmp}/page/system/template/frontend__article.tpl",
			$subRoot,
		], $chain['tpl']);
		$this->assertSame(count($chain['tpl']), count(array_unique($chain['tpl'])));
		$this->assertContains("{$this->tmp}/extension/system/frontend__frontend.php", $chain['php']);
	}

	public function testPhpOnlyExtensionsRemainInThePhpChain(): void {
		$file = $this->write('extension', 'frontend__frontend.php', '<?php');
		$chain = $this->template->getExtends($this->modules, 'test/page', 'frontend__article');
		$this->assertSame([
			"{$this->tmp}/core/system/index.php",
			"{$this->tmp}/core/system/index__index.php",
			"{$this->tmp}/frontend/system/index__frontend.php",
			$file,
			"{$this->tmp}/page/system/frontend__article.php",
		], $chain['php']);
		$this->assertCount(4, $chain['tpl']);
	}

	public function testRootSelectionStillAllowsATemplateProvidedRoot(): void {
		$root = $this->write('frontend', 'index.tpl', 'alternative root');
		$this->write('frontend', 'index.php', '<?php');
		$chain = $this->template->getExtends($this->modules, 'test/page', 'frontend__article');
		$this->assertSame($root, $chain['tpl'][0]);
		$this->assertSame("{$this->tmp}/frontend/system/index.php", $chain['php'][0]);
	}

	public function testUnknownModuleKeepsTheExistingEmptyResult(): void {
		$this->assertSame(['extends' => '', 'tpl' => [], 'php' => []],
			$this->template->getExtends($this->modules, 'test/missing', 'frontend__article'));
	}

	public function testContainerAndSmartyUseTheResolvedChains(): void {
		$C = new Container();
		$C->set('Template', fn () => new Template($C, $this->D));
		$C->set('Smarty', fn () => new \Smarty());
		$this->assertSame($C['Template'], $C['Template']);
		$C['Smarty']->setCompileDir("{$this->tmp}/compile");
		$C['Smarty']->error_reporting = E_ALL & ~E_DEPRECATED;
		$policy = new \Smarty_Security($C['Smarty']);
		$policy->secure_dir = [$this->tmp];
		$C['Smarty']->enableSecurity($policy);

		foreach (['frontend__article' => '<root>frontend:article</root>', 'admin__article' => '<root>admin:admin-article</root>'] as $page => $expected) {
			$chain = $C['Template']->getExtends($this->modules, 'test/page', $page);
			$R = [];
			foreach ($chain['php'] as $file) {
				include $file;
			}
			$this->assertSame(['core/index', 'core/index__index', $page === 'frontend__article' ? 'frontend/index__frontend' : 'core/index__admin', "page/$page"], $R['Trace']);
			$this->assertSame($expected, $C['Smarty']->fetch($chain['extends']));
		}
	}

	public function testRealCoreAndFrontendModulePathsResolveWithoutChangingTheirMetadata(): void {
		$this->C->set('Setting', new class {
			public function get(string $module, string $key): string { return 'basic'; }
		});
		$vendor = str_replace('\\', '/', dirname(__DIR__, 3));
		$modules = [];
		foreach (glob("$vendor/*/*", GLOB_ONLYDIR) as $dir) {
			$composer = "$dir/composer.json";
			if (!is_file($composer)) {
				continue;
			}
			$meta = json_decode(file_get_contents($composer), true, 512, JSON_THROW_ON_ERROR);
			$modules[$meta['name']] = ['INFO' => ['Type' => $meta['type'] ?? 'library', 'ModulDir' => $dir]];
		}
		$core = $modules['fremeo/core']['INFO']['ModulDir'];
		$frontend = $modules['fremeo/frontend']['INFO']['ModulDir'];
		$before = $modules;
		$admin = $this->template->getExtends($modules, 'fremeo/core', 'admin__setting.list');
		$public = $this->template->getExtends($modules, 'fremeo/frontend', 'index__frontend');

		$this->assertContains("$core/system/template/index__admin.tpl", $admin['tpl']);
		$this->assertNotContains("$frontend/system/template/index__frontend.tpl", $admin['tpl']);
		$this->assertContains("$frontend/system/template/index__frontend.tpl", $public['tpl']);
		$this->assertNotContains("$core/system/template/index__admin.tpl", $public['tpl']);
		$this->assertSame($before, $modules);
		foreach ([$admin, $public] as $chain) {
			$this->assertSame('extends:' . implode('|', $chain['tpl']), $chain['extends']);
			foreach (array_merge($chain['tpl'], $chain['php']) as $path) {
				$this->assertFileExists($path);
			}
		}
	}
}
