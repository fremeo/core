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

	protected function setUp(): void {
		$this->tmp = str_replace('\\', '/', sys_get_temp_dir()) . '/fremeo_template_' . bin2hex(random_bytes(8));
		$this->modules = [];
		$this->template = new Template();
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
		$C->set('Template', fn () => new Template());
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
