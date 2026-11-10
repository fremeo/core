<?php
namespace fremeo\core\tests;

use fremeo\core\Container;
use fremeo\core\Setting;
use fremeo\core\Template;
use PHPUnit\Framework\TestCase;
use phploader\CData;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

class ThemeTest extends TestCase {

	private string $tmp;
	private Container $C;
	private ?array $D;
	private Template $template;
	private Filesystem $fs;

	protected function setUp(): void {
		$this->tmp = str_replace('\\', '/', sys_get_temp_dir()) . '/fremeo_theme_' . bin2hex(random_bytes(8));
		$this->fs = new Filesystem();
		$this->C = new Container();
		$this->C->set('Filesystem', $this->fs);
		$this->D = ['MODULE' => ['D' => []]];
		$this->template = new Template($this->C, $this->D);
		$this->C->set('Template', $this->template);
		$this->C->set('Setting', new Setting($this->C, $this->D));
		foreach (['core' => 'core', 'frontend' => 'template', 'frontend__addon' => 'template', 'page' => 'module', 'other' => 'template'] as $name => $type) {
			$this->addModule($name, $type);
		}
		foreach ([
			'core/index' => '{block name="page"}{/block}',
			'core/index__index' => '{block name="page"}<html data-bs-theme="{$D.R.Theme.ColorScheme}"><head>{foreach $D.R.Theme.Assets.css as $asset}<link href="{$asset.Url|escape}">{/foreach}</head><body>{block name="body"}{/block}</body></html>{/block}',
			'core/index__admin' => '{block name="body"}admin{/block}',
			'frontend/index__frontend' => '{block name="body"}frontend{/block}',
			'frontend__addon/frontend__frontend' => '{block name="body" append}addon{/block}',
			'frontend__addon/admin__admin' => '{block name="body" append}menu{/block}',
			'other/index__account' => '{block name="body"}account{/block}',
			'page/frontend__article' => '{block name="body" append}article{/block}',
			'page/admin__article' => '{block name="body" append}admin-article{/block}',
		] as $entry => $content) {
			[$module, $name] = explode('/', $entry);
			$this->fs->dumpFile("{$this->tmp}/$module/system/template/$name.tpl", $content);
		}
		$this->theme('core', 'basic', ['color_scheme' => 'light', 'css' => ['css/base.css']], ['css/base.css' => 'body { color: black; }']);
		$this->theme('other', 'basic', [], []);
		$this->theme('core', 'basic__dark_mode', ['color_scheme' => 'dark', 'css' => ['css/dark.css']], ['css/dark.css' => 'body { color: white; }']);
		$this->theme('frontend', 'basic', ['color_scheme' => 'light', 'css' => ['css/base.css'], 'head_js' => ['js/base.js']], [
			'css/base.css' => 'body { background: url(../images/background.png); }',
			'js/base.js' => 'window.base = true;',
			'images/background.png' => 'image',
			'vendor/fonts/1.2.3/font.woff2' => 'font',
		]);
		$this->theme('frontend', 'basic__dark_mode', ['color_scheme' => 'dark', 'css' => ['css/dark.css']], ['css/dark.css' => 'body { color: white; }']);
		$this->theme('frontend', 'basic__white', ['css' => ['css/white.css']], ['css/white.css' => 'body { color: black; }']);
		$this->theme('frontend', 'dark_mode__contrast', ['css' => ['css/contrast.css']], ['css/contrast.css' => 'body { border: 1px solid; }']);
		$this->theme('frontend', 'classic', ['css' => ['css/classic.css']], ['css/classic.css' => 'body { color: blue; }']);
		$this->theme('frontend__addon', 'basic', ['css' => ['css/addon.css']], ['css/addon.css' => 'body { margin: 0; }']);
		$this->theme('frontend__addon', 'basic__dark_mode', ['css' => ['css/addon-dark.css']], ['css/addon-dark.css' => 'body { padding: 1px; }']);
	}

	protected function tearDown(): void {
		unset($this->template);
		$this->D = [];
		$this->C = new Container();
		gc_collect_cycles();
		$this->fs->remove($this->tmp);
	}

	private function addModule(string $name, string $type): void {
		$dir = "{$this->tmp}/$name";
		$this->fs->mkdir("$dir/system/template");
		$this->D['MODULE']['D']["test/$name"] = ['INFO' => ['Type' => $type, 'ModulDir' => $dir]];
		$parent = [
			'frontend__addon' => 'test/frontend', 'addon__customer' => 'test/frontend__addon',
			'frontend__sibling' => 'test/frontend', 'frontend__second' => 'test/frontend',
			'missing__child' => 'test/missing',
		][$name] ?? null;
		$meta = ['name' => "test/$name", 'type' => $type];
		if ($parent !== null) {
			$meta['extra']['module-parent'] = $parent;
			$meta['require'][$parent] = '*';
		}
		$this->fs->dumpFile("$dir/composer.json", json_encode($meta, JSON_THROW_ON_ERROR));
		if (!in_array($type, ['core', 'template'], true)) {
			return;
		}
		$db = new CData(['DB' => ['FILENAME' => "$dir/data.db", 'FILENAME_C' => "$dir/cache.db"]]);
		$db->registerPattern(include __DIR__ . '/../system/default/default.global.pattern.php');
		$db->registerPattern(include __DIR__ . '/../system/default/default.pattern.php');
		$this->C->set('CData', $db, "test/$name");
		$config = ['SETTING' => [
			'theme' => ['Value' => 'basic', 'InputType' => 'select'],
			'theme_css_basic' => ['Value' => '', 'InputType' => 'textarea'],
			'theme_css_classic' => ['Value' => '', 'InputType' => 'textarea'],
			'disabled' => ['Value' => true],
		]];
		$this->fs->dumpFile("$dir/system/default/default.config.php", '<?php return ' . var_export($config, true) . ';');
	}

	private function theme(string $module, string $name, array $manifest, array $files): string {
		$dir = "{$this->tmp}/$module/system/template/theme/$name";
		$this->fs->dumpFile("$dir/theme.json", json_encode($manifest, JSON_THROW_ON_ERROR));
		foreach ($files as $file => $content) {
			$this->fs->dumpFile("$dir/$file", $content);
		}
		return $dir;
	}

	private function resolve(string $page = 'frontend__article', string $module = 'test/page'): array {
		$chain = $this->template->getExtends($this->D['MODULE']['D'], $module, $page);
		return $this->template->resolveTheme($chain['tpl']);
	}

	private function publish(array $theme): array {
		return $this->template->publishTheme($theme, "{$this->tmp}/public/theme", '/application/');
	}

	private function diskPath(string $url): string {
		return "{$this->tmp}/public/theme/" . explode('/file/theme/', parse_url($url, PHP_URL_PATH), 2)[1];
	}

	public function testProviderComesFromTheLayoutNotThePageOrAdminMenuModule(): void {
		$frontend = $this->resolve();
		$this->assertSame('test/frontend', $frontend['Provider']);
		$this->assertSame('frontend', $frontend['Branch']);
		$this->assertCount(1, $frontend['Layers']);
		$admin = $this->resolve('admin__article');
		$this->assertSame('test/core', $admin['Provider']);
		$this->assertSame('admin', $admin['Branch']);
		$this->assertCount(1, $admin['Layers']);
		$this->assertSame([], $this->template->resolveTheme(["{$this->tmp}/core/system/template/index.tpl"]));
		$this->assertSame([], $this->template->publishTheme([], "{$this->tmp}/public/theme", '/'));
	}

	public function testOnlySelectedAncestorsAreLoadedInLevelThenProviderOrder(): void {
		$this->theme('frontend__addon', 'dark_mode__contrast', [], []);
		$this->C['Setting']->set('test/frontend', 'theme', 'test/frontend__addon:dark_mode__contrast');
		$theme = $this->resolve();
		$this->assertSame(['basic', 'basic__dark_mode', 'dark_mode__contrast'], $theme['Chain']);
		$this->assertSame([
			"{$this->tmp}/frontend/system/template/theme/basic",
			"{$this->tmp}/frontend__addon/system/template/theme/basic",
			"{$this->tmp}/frontend/system/template/theme/basic__dark_mode",
			"{$this->tmp}/frontend__addon/system/template/theme/basic__dark_mode",
			"{$this->tmp}/frontend/system/template/theme/dark_mode__contrast",
			"{$this->tmp}/frontend__addon/system/template/theme/dark_mode__contrast",
		], $theme['Layers']);
		$result = $this->publish($theme);
		$this->assertSame('dark', $result['ColorScheme']);
		$this->assertSame(['base.css', 'addon.css', 'dark.css', 'addon-dark.css', 'contrast.css'],
			array_map(fn ($entry) => basename($this->diskPath($entry['Url'])), $result['Assets']['css']));
		$this->assertStringNotContainsString('test/frontend', $result['Assets']['css'][0]['Url']);
	}

	public function testCustomCssIsLastSharedByVariantsAndSeparateForMainFamilies(): void {
		$this->C['Setting']->set('test/frontend', 'theme_css_basic', 'body { color: orange; }');
		$this->C['Setting']->set('test/frontend', 'theme_css_classic', 'body { color: purple; }');
		$light = $this->publish($this->resolve());
		$this->C['Setting']->set('test/frontend', 'theme', 'basic__dark_mode');
		$dark = $this->publish($this->resolve());
		foreach ([$light, $dark] as $result) {
			$last = end($result['Assets']['css']);
			$this->assertSame('_custom.css', basename($this->diskPath($last['Url'])));
			$this->assertSame('body { color: orange; }', file_get_contents($this->diskPath($last['Url'])));
		}
		$this->C['Setting']->set('test/frontend', 'theme', 'classic');
		$classic = $this->publish($this->resolve());
		$last = end($classic['Assets']['css']);
		$this->assertSame('body { color: purple; }', file_get_contents($this->diskPath($last['Url'])));
	}

	public function testPublicationPreservesRelativePathsOverridesFilesAndReusesItsHash(): void {
		$this->C['Setting']->set('test/frontend', 'theme', 'test/frontend__addon:basic');
		$this->fs->dumpFile("{$this->tmp}/frontend__addon/system/template/theme/basic/css/base.css", 'body { color: green; }');
		$result = $this->publish($this->resolve());
		$path = $this->diskPath($result['Assets']['css'][0]['Url']);
		$this->assertSame('body { color: green; }', file_get_contents($path));
		$root = dirname($path, 2);
		$this->assertFileExists("$root/images/background.png");
		$this->assertFileExists("$root/vendor/fonts/1.2.3/font.woff2");
		$this->assertSame($result, $this->publish($this->resolve()));
		$this->assertFileDoesNotExist("{$this->tmp}/public/theme/.htaccess");
		$this->assertSame([], glob("{$this->tmp}/public/theme/.build-*"));
		$this->fs->dumpFile("{$this->tmp}/frontend/system/template/theme/basic/images/background.png", 'changed image');
		$this->assertNotSame($result['Hash'], $this->publish($this->resolve())['Hash']);
	}

	public function testPublicationReplacesOneFixedDirectoryAndKeepsPreviousBuildOnFailure(): void {
		$source = "{$this->tmp}/frontend/system/template/theme/basic";
		$this->fs->dumpFile("$source/images/obsolete.png", 'old');
		$first = $this->publish($this->resolve());
		$url = $first['Assets']['css'][0]['Url'];
		$root = dirname($this->diskPath($url), 2);
		$this->assertMatchesRegularExpression('~/file/theme/[a-f0-9]{12}/basic/css/base\.css\?v=[a-f0-9]{24}$~', $url);
		$this->assertSame($first, $this->publish($this->resolve()));
		$this->fs->dumpFile("$root/0123456789abcdef01234567/css/old.css", 'legacy version');
		$this->fs->remove("$source/images/obsolete.png");
		$this->fs->dumpFile("$source/images/background.png", 'new background');
		$second = $this->publish($this->resolve());
		$this->assertSame($this->diskPath($url), $this->diskPath($second['Assets']['css'][0]['Url']));
		$this->assertNotSame($url, $second['Assets']['css'][0]['Url']);
		$this->assertFileDoesNotExist("$root/images/obsolete.png");
		$this->assertSame(['css', 'images', 'js', 'vendor'], array_map('basename', glob("$root/*", GLOB_ONLYDIR)));
		$css = file_get_contents($this->diskPath($url));
		$this->assertStringContainsString('background.png?v=' . $second['Hash'], $css);
		$this->fs->dumpFile("$source/css/base.css", 'body { background: url("unterminated');
		try {
			$this->publish($this->resolve());
			$this->fail('Invalid new build must fail.');
		} catch (RuntimeException $exception) {
			$this->assertStringContainsString('Ungueltiges CSS', $exception->getMessage());
		}
		$this->assertSame($css, file_get_contents($this->diskPath($url)));
		$this->assertSame([], glob("{$this->tmp}/public/theme/.build-*"));
	}

	public function testSourceScriptsAndManifestsAreNotPublished(): void {
		$dir = "{$this->tmp}/frontend/system/template/theme/basic";
		foreach (['bad.php', 'bad.php.css', 'bad.pht.css', 'bad.phps.css', 'bad.phtml.js', '.htaccess', 'php/nested.css'] as $name) {
			$this->fs->dumpFile("$dir/$name", 'not public');
		}
		$result = $this->publish($this->resolve());
		$root = dirname($this->diskPath($result['Assets']['css'][0]['Url']), 2);
		foreach (['bad.php', 'bad.php.css', 'bad.pht.css', 'bad.phps.css', 'bad.phtml.js', '.htaccess', 'php/nested.css', 'theme.json'] as $name) {
			$this->assertFileDoesNotExist("$root/$name");
		}
	}

	public function testMissingOrForbiddenManifestAssetsAreExplicitErrors(): void {
		$this->theme('frontend', 'basic', ['css' => ['../outside.css']], []);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Theme-Asset fehlt');
		$this->publish($this->resolve());
	}

	public function testUnavailableThemeSelectionFallsBackToParentMainTheme(): void {
		$this->C['Setting']->set('test/frontend', 'theme', 'missing');
		$this->assertSame('test/frontend:basic', $this->resolve()['Selection']);
	}

	public function testThemeOriginSelectsTplPhpAndAssetsTogether(): void {
		$php = "{$this->tmp}/frontend__addon/system/frontend__frontend.php";
		$this->fs->dumpFile($php, '<?php $R["addon"] = true;');
		$this->assertContains('test/frontend__addon:basic|test/frontend__addon : basic', $this->template->getThemeOptions('test/frontend'));
		$this->assertContains('test/frontend:basic|test/frontend : basic', $this->template->getThemeOptions('test/frontend'));
		$parent = $this->template->getExtends($this->D['MODULE']['D'], 'test/page', 'frontend__article');
		$this->assertNotContains($php, $parent['php']);
		$this->assertStringNotContainsString('frontend__addon', $parent['extends']);
		$this->C['Setting']->set('test/frontend', 'theme', 'test/frontend__addon:basic');
		$child = $this->template->getExtends($this->D['MODULE']['D'], 'test/page', 'frontend__article');
		$this->assertContains($php, $child['php']);
		$this->assertStringContainsString('frontend__addon', $child['extends']);
		$theme = $this->template->resolveTheme($child['tpl']);
		$this->assertSame('test/frontend__addon', $theme['Template']);
		$this->assertCount(2, $theme['Layers']);
	}

	public function testThemeOnlyChildAndIndependentSiblingThemes(): void {
		$this->fs->remove("{$this->tmp}/frontend__addon/system/template/frontend__frontend.tpl");
		$this->theme('frontend__addon', 'basic__photo', [], []);
		$this->addModule('frontend__sibling', 'template');
		$this->theme('frontend__sibling', 'basic__photo', ['css' => ['css/sibling.css']], ['css/sibling.css' => 'body {}']);
		$this->fs->dumpFile("{$this->tmp}/frontend__sibling/system/template/frontend__frontend.tpl", '{block name="body"}sibling{/block}');
		$this->C['Setting']->set('test/frontend', 'theme', 'test/frontend__addon:basic__photo');
		$chain = $this->template->getExtends($this->D['MODULE']['D'], 'test/page', 'frontend__article');
		$this->assertStringNotContainsString('frontend__sibling', $chain['extends']);
		$theme = $this->template->resolveTheme($chain['tpl']);
		$this->assertSame('test/frontend__addon', $theme['Template']);
		$this->assertSame(['basic', 'basic__photo'], $theme['Chain']);
		$this->assertStringNotContainsString('frontend__sibling', implode('|', $theme['Layers']));
		$this->C['Setting']->set('test/frontend', 'theme', 'test/frontend__sibling:basic__photo');
		$this->assertSame('test/frontend__sibling', $this->resolve()['Template']);
	}

	public function testDisabledOrRemovedChildFallsBackWithoutChangingStoredSelection(): void {
		$this->theme('frontend__addon', 'basic__photo', [], []);
		$key = 'test/frontend__addon:basic__photo';
		$this->C['Setting']->set('test/frontend', 'theme', $key);
		$this->D['MODULE']['D']['test/frontend__addon']['Active'] = 0;
		$this->assertSame('test/frontend:basic', $this->resolve()['Selection']);
		$this->assertArrayNotHasKey($key, $this->template->getThemeChoices('test/frontend'));
		$this->assertSame($key, $this->C['Setting']->get('test/frontend', 'theme'));
		unset($this->D['MODULE']['D']['test/frontend__addon']);
		$this->assertSame('test/frontend:basic', $this->resolve()['Selection']);
	}

	public function testNestedChildFallsBackToAvailableImmediateParent(): void {
		$this->addModule('addon__customer', 'template');
		$this->theme('addon__customer', 'basic__photo', [], []);
		$this->C['Setting']->set('test/frontend', 'theme', 'test/addon__customer:basic__photo');
		$this->D['MODULE']['D']['test/addon__customer']['Active'] = 0;
		$this->assertSame('test/frontend__addon:basic', $this->resolve()['Selection']);
		unset($this->D['MODULE']['D']['test/addon__customer']);
		$this->assertSame('test/frontend:basic', $this->resolve()['Selection']);
	}

	public function testThemeCyclesAreRejected(): void {
		$this->theme('frontend', 'cycle__other', [], []);
		$this->theme('frontend', 'other__cycle', [], []);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Zyklische Theme-Vererbung');
		$this->template->getThemes('test/frontend');
	}

	public function testMissingThemeParentsAreRejected(): void {
		$this->theme('frontend', 'missing__child', [], []);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Theme-Elternteil fehlt');
		$this->template->getThemes('test/frontend');
	}

	public function testTemplateExtensionsAreOrderedByParentNotInstallationOrder(): void {
		$this->addModule('addon__customer', 'template');
		$this->fs->dumpFile("{$this->tmp}/addon__customer/system/template/frontend__frontend.tpl", '{block name="body" append}customer{/block}');
		$this->theme('addon__customer', 'basic', ['css' => ['css/customer.css']], ['css/customer.css' => 'body { padding: 0; }']);
		$this->C['Setting']->set('test/frontend', 'theme', 'test/addon__customer:basic');
		$this->D['MODULE']['D'] = array_reverse($this->D['MODULE']['D'], true);
		$chain = $this->template->getExtends($this->D['MODULE']['D'], 'test/page', 'frontend__article');
		$addon = array_search("{$this->tmp}/frontend__addon/system/template/frontend__frontend.tpl", $chain['tpl'], true);
		$customer = array_search("{$this->tmp}/addon__customer/system/template/frontend__frontend.tpl", $chain['tpl'], true);
		$this->assertIsInt($addon);
		$this->assertIsInt($customer);
		$this->assertLessThan($customer, $addon);
		$theme = $this->template->resolveTheme($chain['tpl']);
		$this->assertCount(3, $theme['Layers']);
		$this->assertSame('test/frontend', $theme['Provider']);
	}

	public function testMissingTemplateParentsAreRejected(): void {
		$this->addModule('missing__child', 'template');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Module-Elternmodul fehlt');
		$this->template->getThemeModules();
	}

	public function testCrossVendorTemplateParentComesFromExplicitComposerMetadata(): void {
		$dir = "{$this->tmp}/external";
		$this->fs->dumpFile("$dir/composer.json", json_encode([
			'name' => 'nirgendheim/frontend__focusbox', 'type' => 'template',
			'require' => ['test/frontend' => '>=0.1 <1.0'],
			'extra' => ['module-parent' => 'test/frontend'],
		], JSON_THROW_ON_ERROR));
		$this->D['MODULE']['D']['nirgendheim/frontend__focusbox'] = ['INFO' => ['Type' => 'template', 'ModulDir' => $dir]];
		$this->fs->dumpFile("$dir/system/template/theme/basic__photo/theme.json", '{}');
		$this->fs->dumpFile("$dir/system/template/frontend__frontend.tpl", '{block name="body" append}external{/block}');
		$providers = $this->template->getThemeModules();
		$this->assertSame('test/frontend', $providers['nirgendheim/frontend__focusbox']['Parent']);
		$this->C['Setting']->set('test/frontend', 'theme', 'nirgendheim/frontend__focusbox:basic__photo');
		$this->assertSame('nirgendheim/frontend__focusbox', $this->resolve()['Template']);
		$chain = $this->template->getExtends($this->D['MODULE']['D'], 'test/page', 'frontend__article');
		$this->assertContains("$dir/system/template/frontend__frontend.tpl", $chain['tpl']);
		$this->D['MODULE']['D']['test/frontend']['Active'] = 0;
		$this->assertArrayNotHasKey('nirgendheim/frontend__focusbox', $this->template->getThemeModules());
	}

	public function testPackageNameNoLongerDefinesParentAndRequireAloneDoesNotEither(): void {
		$this->fs->dumpFile("{$this->tmp}/frontend__addon/composer.json", json_encode([
			'require' => ['test/frontend' => '*'],
		]));
		$this->assertNull($this->template->getThemeModules()['test/frontend__addon']['Parent']);
	}

	public function testModuleParentMustBeRequiredAndCyclesAreRejected(): void {
		$file = "{$this->tmp}/frontend__addon/composer.json";
		$this->fs->dumpFile($file, json_encode(['extra' => ['module-parent' => 'test/frontend']]));
		try {
			$this->template->getModuleInheritance();
			$this->fail('Missing require must be rejected.');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString('require', $e->getMessage());
		}
		$this->fs->dumpFile($file, json_encode([
			'extra' => ['module-parent' => 'test/frontend__addon'],
			'require' => ['test/frontend__addon' => '*'],
		]));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Zyklische Module-Vererbung');
		$this->template->getModuleInheritance();
	}

	public function testNormalModuleParentControlsOrderWithoutInheritingPatterns(): void {
		$this->addModule('extension', 'module');
		$this->fs->dumpFile("{$this->tmp}/extension/composer.json", json_encode([
			'extra' => ['module-parent' => 'test/page'], 'require' => ['test/page' => '*'],
		]));
		$this->fs->dumpFile("{$this->tmp}/page/system/template/frontend__frontend.tpl", '');
		$this->fs->dumpFile("{$this->tmp}/extension/system/template/frontend__frontend.tpl", '');
		$this->fs->dumpFile("{$this->tmp}/extension/system/frontend__frontend.php", '<?php');
		$chain = $this->template->getExtends($this->D['MODULE']['D'], 'test/page', 'frontend__article');
		$parent = array_search("{$this->tmp}/page/system/template/frontend__frontend.tpl", $chain['tpl'], true);
		$child = array_search("{$this->tmp}/extension/system/template/frontend__frontend.tpl", $chain['tpl'], true);
		$this->assertIsInt($parent);
		$this->assertIsInt($child);
		$this->assertLessThan($child, $parent);
		$this->assertContains("{$this->tmp}/extension/system/frontend__frontend.php", $chain['php']);
		$this->assertArrayNotHasKey('test/extension', $this->template->getThemeModules());
		$this->D['MODULE']['D']['test/page']['Active'] = 0;
		$this->assertArrayNotHasKey('test/extension', $this->template->getModuleInheritance());
	}

	public function testIndependentLayoutProvidersAreRejected(): void {
		$this->fs->dumpFile("{$this->tmp}/other/system/template/index__frontend.tpl", '');
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Mehrdeutiger Theme-Anbieter');
		$this->resolve();
	}

	public function testUnselectedSiblingExtensionsDoNotEnterLayoutChain(): void {
		$this->addModule('frontend__second', 'template');
		$this->fs->dumpFile("{$this->tmp}/frontend__second/system/template/frontend__frontend.tpl", '');
		$this->assertSame('test/frontend', $this->resolve()['Template']);
		$chain = $this->template->getExtends($this->D['MODULE']['D'], 'test/page', 'frontend__article');
		$this->assertStringNotContainsString('frontend__second', $chain['extends']);
	}

	public function testOtherBranchesAndBackendExtractionNeedNoSpecialCase(): void {
		$this->theme('other', 'basic', ['css' => ['css/account.css']], ['css/account.css' => 'body {}']);
		$account = $this->resolve('index__account', 'test/other');
		$this->assertSame('account', $account['Branch']);
		$this->assertSame('test/other', $account['Provider']);
		$this->fs->remove("{$this->tmp}/core/system/template/index__admin.tpl");
		$this->fs->dumpFile("{$this->tmp}/other/system/template/index__admin.tpl", '');
		$admin = $this->resolve('admin__article');
		$this->assertSame('test/other', $admin['Provider']);
	}

	public function testBranchSpecificAssetsStayInTheirBranch(): void {
		$this->theme('core', 'basic', [
			'css' => [['path' => 'css/base.css', 'branches' => ['admin']]],
			'body_js' => ['js/base.js'],
		], ['css/base.css' => 'body {}', 'js/base.js' => 'window.base = true;']);
		$this->fs->dumpFile("{$this->tmp}/core/system/template/index__login.tpl", '');
		$admin = $this->publish($this->resolve('admin__article'));
		$login = $this->publish($this->resolve('index__login', 'test/core'));
		$this->assertCount(1, $admin['Assets']['css']);
		$this->assertSame([], $login['Assets']['css']);
		$this->assertCount(1, $login['Assets']['body_js']);
		$this->assertNotSame($admin['Hash'], $login['Hash']);
	}

	public function testSettingKeepsFalseAndEmptyValuesAndSharedDataSurvivesCDataReads(): void {
		$this->C['Setting']->set('test/frontend', 'disabled', '0');
		$this->C['Setting']->set('test/frontend', 'theme_css_basic', '');
		$this->assertSame('0', (string)$this->C['Setting']->get('test/frontend', 'disabled'));
		$this->assertSame('', $this->C['Setting']->get('test/frontend', 'theme_css_basic'));
		$filter = ['SETTING' => []];
		$this->C['test/core']['CData']->get_object($this->D, $filter);
		$this->assertSame('test/frontend', $this->resolve()['Provider']);
	}

	public function testSettingsPageSavesThemeAndCssInTheirOwningDatabase(): void {
		$C = $this->C;
		$D = $this->D;
		$D['MODULE']['D']['test/frontend']['SETTING']['D'] = [
			'theme' => ['Value' => 'basic__dark_mode'],
			'theme_css_basic' => ['Value' => 'body { color: red; }'],
			'not_declared' => ['Value' => 'ignored'],
		];
		$R = ['ACTION' => 'save'];
		$method = $_SERVER['REQUEST_METHOD'] ?? null;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		try {
			include __DIR__ . '/../system/admin__setting.list.php';
		} finally {
			if ($method === null) {
				unset($_SERVER['REQUEST_METHOD']);
			} else {
				$_SERVER['REQUEST_METHOD'] = $method;
			}
		}
		$this->assertSame([], $R['SettingError']);
		$this->assertSame('test/frontend:basic__dark_mode', $C['Setting']->get('test/frontend', 'theme'));
		$this->assertSame('body { color: red; }', $C['Setting']->get('test/frontend', 'theme_css_basic'));
		$this->assertSame('basic', $C['Setting']->get('test/core', 'theme'));
		$this->assertNull($C['Setting']->get('test/frontend', 'not_declared'));
	}

	public function testModuleLoadingAndSettingsOrderIgnoreSubmittedModuleOrder(): void {
		$root = "{$this->tmp}/loader";
		$expected = ['fremeo/core', 'fremeo/b_template', 'fremeo/z_template', 'fremeo/a_module', 'fremeo/z_module', 'fremeo/library'];
		foreach (['core' => 'core', 'b_template' => 'template', 'z_template' => 'template', 'a_module' => 'module', 'z_module' => 'module', 'library' => 'library'] as $name => $type) {
			$dir = "$root/system/vendor/fremeo/$name";
			$this->fs->mkdir(["$root/data/fremeo~$name", "$root/data_c/fremeo~$name"]);
			$this->fs->dumpFile("$dir/composer.json", json_encode(['type' => $type], JSON_THROW_ON_ERROR));
			$this->fs->dumpFile("$dir/init.php", '<?php $R["InitOrder"][] = ' . var_export("fremeo/$name", true) . ';');
			$this->fs->dumpFile("$dir/system/default/default.config.php", '<?php return ' . var_export([
				'SETTING' => ['sample' => ['Value' => 'default']],
				'SECTION' => ['Template' => ['D' => [$name => ['D' => ['sample' => 1]]]]],
			], true) . ';');
		}
		$script = "$root/run.php";
		$this->fs->dumpFile($script, '<?php define("PROJECT_ROOT", ' . var_export("$root/", true) . ');'
			. 'ini_set("session.save_path", PROJECT_ROOT);'
			. '$_REQUEST = ["D" => ["MODULE" => ["D" => json_decode($argv[1], true)]], "R" => ["ACTION" => "save"]];'
			. '$_SERVER["REQUEST_METHOD"] = "POST";'
			. 'require ' . var_export(dirname(__DIR__) . '/init.php', true) . ';'
			. '$result = ["Modules" => array_keys($D["MODULE"]["D"]), "Init" => $R["InitOrder"]];'
			. 'include ' . var_export(dirname(__DIR__) . '/system/admin__setting.list.php', true) . ';'
			. '$result["Sections"] = array_keys($R["SETTING_VIEW"]["Template"]["Children"]);'
			. '$result["Values"] = array_column(array_column($R["SETTING_VIEW"]["Template"]["Children"], "Items"), 0);'
			. 'echo json_encode($result, JSON_THROW_ON_ERROR);');
		foreach ([[], ['fremeo/z_template', 'fremeo/b_template'], ['fremeo/core'], ['fremeo/z_module', 'fremeo/a_module']] as $submitted) {
			$post = [];
			foreach ($submitted as $id) {
				$post[$id]['SETTING']['D']['sample']['Value'] = "saved $id";
			}
			$process = proc_open([PHP_BINARY, $script, json_encode($post, JSON_THROW_ON_ERROR)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 5));
			$this->assertIsResource($process);
			fclose($pipes[0]);
			$output = stream_get_contents($pipes[1]);
			$error = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$this->assertSame(0, proc_close($process), $error . $output);
			$this->assertSame('', $error);
			$result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
			$this->assertSame($expected, $result['Modules']);
			$this->assertSame(array_slice($expected, 1, 4), $result['Init']);
			$this->assertSame(['core', 'b_template', 'z_template', 'a_module', 'z_module'], $result['Sections']);
			foreach ($result['Values'] as $item) {
				if (in_array($item['Module'], $submitted, true)) {
					$this->assertSame("saved {$item['Module']}", $item['Value']);
				}
			}
		}
	}

	public function testSettingsRejectInvalidVariantsIgnoreGetAndDeleteDefaultOverrides(): void {
		$save = function (array $values, string $method = 'POST'): array {
			$C = $this->C;
			$D = $this->D;
			$D['MODULE']['D']['test/frontend']['SETTING']['D'] = $values;
			$R = ['ACTION' => 'save'];
			$previous = $_SERVER['REQUEST_METHOD'] ?? null;
			$_SERVER['REQUEST_METHOD'] = $method;
			try {
				include __DIR__ . '/../system/admin__setting.list.php';
				return $R;
			} finally {
				if ($previous === null) {
					unset($_SERVER['REQUEST_METHOD']);
				} else {
					$_SERVER['REQUEST_METHOD'] = $previous;
				}
			}
		};
		$result = $save(['theme' => ['Value' => 'missing'], 'theme_css_basic' => ['Value' => 'not saved']]);
		$this->assertCount(1, $result['SettingError']);
		$this->assertSame('', $this->C['Setting']->get('test/frontend', 'theme_css_basic'));
		$save(['theme' => ['Value' => 'basic__dark_mode']], 'GET');
		$this->assertSame('basic', $this->C['Setting']->get('test/frontend', 'theme'));
		$save(['theme' => ['Value' => 'basic__dark_mode']]);
		$this->assertSame('test/frontend:basic__dark_mode', $this->C['Setting']->get('test/frontend', 'theme'));
		$save(['theme' => ['Value' => '']]);
		$this->assertSame('test/frontend:basic__dark_mode', $this->C['Setting']->get('test/frontend', 'theme'));
		$this->C['Setting']->set('test/frontend', 'theme_variant', 'basic__white');
		$this->assertSame('basic__white', $this->C['Setting']->get('test/frontend', 'theme'));
		$this->assertSame('basic', $this->resolve()['Main']);
		$save(['theme' => ['Value' => 'basic']]);
		$filter = ['SETTING' => []];
		$this->C['test/frontend']['CData']->get_object($data, $filter);
		$this->assertArrayNotHasKey('theme_variant', $data['SETTING']['D'] ?? []);
		$this->assertArrayNotHasKey('theme', $data['SETTING']['D'] ?? []);
		$this->assertSame('basic', $this->C['Setting']->get('test/frontend', 'theme'));
		$result = $save(['theme' => ['Value' => 'test/frontend__addon:basic__dark_mode']]);
		$this->assertSame([], $result['SettingError']);
		$this->assertSame('test/frontend__addon:basic__dark_mode', $this->C['Setting']->get('test/frontend', 'theme'));
		$this->assertSame('test/frontend__addon', $this->resolve()['Template']);
	}

	public function testConcurrentRequestsPublishOneCompleteVersion(): void {
		$theme = $this->resolve();
		$autoload = str_replace('\\', '/', dirname(__DIR__, 3)) . '/autoload.php';
		$target = "{$this->tmp}/public/theme";
		$script = "{$this->tmp}/publish.php";
		$this->fs->dumpFile($script, '<?php require ' . var_export($autoload, true)
			. '; $D = []; $C = new \fremeo\core\Container(); $C->set("Filesystem", new \Symfony\Component\Filesystem\Filesystem());'
			. '$C->set("Setting", new class($C, $D) extends \fremeo\core\Setting { public function get(string $moduleId, string $settingId): mixed { return 1; } });'
			. '$template = new \fremeo\core\Template($C, $D);'
			. 'echo json_encode($template->publishTheme(' . var_export($theme, true)
			. ', ' . var_export($target, true) . ', "/"), JSON_THROW_ON_ERROR);');
		$workers = [];
		for ($i = 0; $i < 2; $i++) {
			$process = proc_open([PHP_BINARY, $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
			$this->assertIsResource($process);
			fclose($pipes[0]);
			$workers[] = [$process, $pipes];
		}
		$results = [];
		foreach ($workers as [$process, $pipes]) {
			$output = stream_get_contents($pipes[1]);
			$error = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$this->assertSame(0, proc_close($process), $error);
			$this->assertSame('', $error);
			$results[] = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
		}
		$this->assertSame($results[0], $results[1]);
		foreach ($results[0]['Assets'] as $assets) {
			foreach ($assets as $asset) {
				$this->assertFileExists($this->diskPath($asset['Url']));
			}
		}
		$this->assertSame([], glob("$target/.build-*"));
	}

	public function testPerformanceSwitchesAreIndependentAndInvalidateThePublishedVersion(): void {
		$this->theme('frontend', 'basic', ['css' => ['css/base.css'], 'head_js' => ['js/base.js'], 'body_js' => ['js/ready.min.js']], [
			'css/base.css' => "/* removable comment */\nbody {\n    background: url('../images/background.png?v=2#icon');\n    color: orange;\n}\n",
			'js/base.js' => "/* removable comment */\nfunction themeTest(value) {\n    return value + 1;\n}\n",
			'js/ready.min.js' => 'window.ready=themeTest(1);',
		]);
		$this->C['Setting']->set('test/frontend', 'theme', 'test/frontend__addon:basic__dark_mode');
		$this->C['Setting']->set('test/frontend', 'theme_css_basic', 'body { color: purple; }');
		$hashes = [];
		for ($mask = 0; $mask < 8; $mask++) {
			foreach (['minify_css', 'minify_js', 'combine_css'] as $bit => $key) {
				$this->C['Setting']->set('test/frontend', "theme_$key", (string)(int)(bool)($mask & (1 << $bit)));
			}
			$result = $this->publish($this->resolve());
			$hashes[] = $result['Hash'];
			$this->assertSame($result, $this->publish($this->resolve()));
			$this->assertCount(($mask & 4) ? 1 : 5, $result['Assets']['css']);
			$text = implode("\n", array_map(fn ($entry) => file_get_contents($this->diskPath($entry['Url'])), $result['Assets']['css']));
			$this->assertLessThan(strrpos($text, 'purple'), strpos($text, 'orange'));
			$this->assertLessThan(strrpos($text, 'purple'), strpos($text, 'padding'));
			$first = $result['Assets']['css'][0]['Url'];
			$this->assertStringContainsString('../images/background.png?v=' . $result['Hash'] . '#icon', file_get_contents($this->diskPath($first)));
			if ($mask & 1) {
				$this->assertStringEndsWith('.min.css', $this->diskPath($first));
				$this->assertStringNotContainsString('removable comment', $text);
			} elseif ($mask & 4) {
				$this->assertStringEndsWith('.css', $this->diskPath($first));
				$this->assertStringNotContainsString('.min.css', $first);
			} else {
				$this->assertStringContainsString('color: orange', file_get_contents($this->diskPath($first)));
			}
			$js = $result['Assets']['head_js'][0]['Url'];
			$this->assertCount(1, $result['Assets']['head_js']);
			$this->assertCount(1, $result['Assets']['body_js']);
			$this->assertStringEndsWith('js/ready.min.js', $this->diskPath($result['Assets']['body_js'][0]['Url']));
			$this->assertSame('window.ready=themeTest(1);', file_get_contents($this->diskPath($result['Assets']['body_js'][0]['Url'])));
			if ($mask & 2) {
				$this->assertStringEndsWith('.min.js', $this->diskPath($js));
				$this->assertLessThan(0.7 * filesize("{$this->tmp}/frontend/system/template/theme/basic/js/base.js"), filesize($this->diskPath($js)));
			} else {
				$this->assertSame(file_get_contents("{$this->tmp}/frontend/system/template/theme/basic/js/base.js"), file_get_contents($this->diskPath($js)));
			}
		}
		$this->assertCount(8, array_unique($hashes));
	}

	public function testPerformanceSettingsCanBeSavedAndResetToDefaults(): void {
		$config = (static fn ($file) => include $file)(__DIR__ . '/../system/default/default.config.php');
		$keys = ['theme_minify_css', 'theme_minify_js', 'theme_combine_css'];
		$config['SETTING'] = array_intersect_key($config['SETTING'], array_flip($keys));
		$config['SECTION'] = ['Template' => ['D' => ['Core' => ['D' => ['Performance' => $config['SECTION']['Template']['D']['Core']['D']['Performance']]]]]];
		$this->fs->dumpFile("{$this->tmp}/core/system/default/default.config.php", '<?php return ' . var_export($config, true) . ';');
		$C = $this->C;
		$D = $this->D;
		$method = $_SERVER['REQUEST_METHOD'] ?? null;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		try {
			foreach (['1', '0'] as $value) {
				foreach ($keys as $key) {
					$D['MODULE']['D']['test/core']['SETTING']['D'][$key]['Value'] = $value;
				}
				$R = ['ACTION' => 'save'];
				include __DIR__ . '/../system/admin__setting.list.php';
				$this->assertSame([], $R['SettingError']);
				$this->assertCount(3, $R['SETTING_VIEW']['Template']['Children']['Core']['Children']['Performance']['Items']);
				foreach ($keys as $key) {
					$this->assertSame((bool)(int)$value, (bool)$C['Setting']->get('test/core', $key));
					$this->assertSame($value === '1', isset($D['MODULE']['D']['test/core']['SETTING']['D'][$key]));
				}
			}
		} finally {
			if ($method === null) {
				unset($_SERVER['REQUEST_METHOD']);
			} else {
				$_SERVER['REQUEST_METHOD'] = $method;
			}
		}
	}

	public function testPerformanceOptionsAreIsolatedByLayoutProvider(): void {
		$this->theme('other', 'basic', ['css' => ['css/account.css']], ['css/account.css' => '.account { color: green; }']);
		$themes = [
			'test/core' => $this->resolve('admin__article'),
			'test/frontend' => $this->resolve(),
			'test/other' => $this->resolve('index__account', 'test/other'),
		];
		$baseline = [];
		foreach ($themes as $provider => $theme) {
			$baseline[$provider] = $this->publish($theme);
		}
		foreach (['minify_css', 'minify_js', 'combine_css'] as $option) {
			foreach ($themes as $active => $theme) {
				$this->C['Setting']->set($active, "theme_$option", '1');
				foreach ($themes as $provider => $candidate) {
					$result = $this->publish($candidate);
					if ($provider === $active) {
						$this->assertNotSame($baseline[$provider]['Hash'], $result['Hash']);
					} else {
						$this->assertSame($baseline[$provider], $result);
					}
				}
				$this->C['Setting']->set($active, "theme_$option", '0');
			}
		}
		$this->C['Setting']->set('test/frontend__addon', 'theme_minify_css', '1');
		$this->assertSame($baseline['test/frontend'], $this->publish($themes['test/frontend']));
		$this->C['Setting']->set('test/frontend', 'theme_minify_css', '1');
		$this->fs->dumpFile("{$this->tmp}/frontend/system/template/theme/basic/css/base.css", 'body { color: "unterminated');
		try {
			$this->publish($themes['test/frontend']);
			$this->fail('Invalid frontend CSS must fail.');
		} catch (RuntimeException $exception) {
			$this->assertStringContainsString('Ungueltiges CSS', $exception->getMessage());
		}
		$this->assertSame($baseline['test/core'], $this->publish($themes['test/core']));
	}

	public function testCssBundlesPreserveMediaImportsCharsetsNamespacesAndRelativeUrls(): void {
		$this->C['Setting']->set('test/frontend', 'theme_combine_css', '1');
		$this->theme('frontend', 'basic', ['css' => [
			'css/base.css', 'css/second.css', ['path' => 'css/print.css', 'media' => 'print'],
			'css/import.css', 'css/charset.css', 'css/namespace.css', 'css/legacy.css', 'css/final.css',
		]], [
			'css/base.css' => '/*! License test */ .base { background: url("../images/background.png?q=1#x"); src: url("../vendor/fonts/1.2.3/font.woff2"); }',
			'css/second.css' => '@charset "UTF-8"; .second { background-image: image-set("../images/background.png" 1x, url(../images/background.png) 2x); mask: url(#mask); cursor: url(/absolute.png), auto; border-image: url(https://example.test/x.png); background: url("data:image/svg+xml,%3Csvg%3E"); }',
			'css/print.css' => '.print { display: none; }',
			'css/import.css' => '@import "second.css" screen; .import { color: red; }',
			'css/charset.css' => '@charset "windows-1252"; .charset { content: "a"; }',
			'css/namespace.css' => '@namespace svg url(http://www.w3.org/2000/svg); svg|a { color: red; }',
			'css/legacy.css' => ".legacy { content: \"\xe4\"; }",
			'css/final.css' => '.final { color: green; }',
		]);
		$result = $this->publish($this->resolve());
		$entries = $result['Assets']['css'];
		$this->assertCount(7, $entries);
		$this->assertSame('all', $entries[0]['Media']);
		$this->assertSame('print', $entries[1]['Media']);
		foreach (['import', 'charset', 'namespace', 'legacy'] as $i => $name) {
			$this->assertStringEndsWith("css/$name.css", $this->diskPath($entries[$i + 2]['Url']));
			if ($name === 'import') {
				$this->assertStringContainsString('second.css?v=' . $result['Hash'], file_get_contents($this->diskPath($entries[$i + 2]['Url'])));
			} else {
				$this->assertSame(file_get_contents("{$this->tmp}/frontend/system/template/theme/basic/css/$name.css"), file_get_contents($this->diskPath($entries[$i + 2]['Url'])));
			}
		}
		$css = file_get_contents($this->diskPath($entries[0]['Url']));
		$this->assertStringContainsString('/*! License test */', $css);
		$this->assertSame(1, substr_count($css, '@charset'));
		foreach (['../images/background.png?q=1&v=' . $result['Hash'] . '#x', '../vendor/fonts/1.2.3/font.woff2?v=' . $result['Hash'], 'image-set("../images/background.png?v=', 'url("#mask")', 'url("/absolute.png")', 'https://example.test/x.png', 'data:image/svg+xml,%3Csvg%3E'] as $expected) {
			$this->assertStringContainsString($expected, $css);
		}
		$this->assertLessThan(strpos($css, '.second'), strpos($css, '.base'));
	}

	public function testInvalidCssAbortsTheBuildAndRemovesItsTemporaryDirectory(): void {
		$this->C['Setting']->set('test/frontend', 'theme_minify_css', '1');
		$this->fs->dumpFile("{$this->tmp}/frontend/system/template/theme/basic/css/base.css", 'body { color: "unterminated');
		try {
			$this->publish($this->resolve());
			$this->fail('Invalid CSS must fail explicitly.');
		} catch (RuntimeException $exception) {
			$this->assertStringContainsString('Ungueltiges CSS in css/base.css', $exception->getMessage());
			$this->assertSame([], glob("{$this->tmp}/public/theme/.build-*"));
		}
	}

	public function testSmartyRendersThemeUrlsAndSelectedScheme(): void {
		$this->C['Setting']->set('test/frontend', 'theme', 'basic__dark_mode');
		$theme = $this->publish($this->resolve());
		$chain = $this->template->getExtends($this->D['MODULE']['D'], 'test/page', 'frontend__article');
		$smarty = new \Smarty();
		$smarty->addPluginsDir(__DIR__ . '/../system/core/smarty_plugin');
		$smarty->setCompileDir("{$this->tmp}/compile");
		$policy = new \Smarty_Security($smarty);
		$policy->secure_dir = [$this->tmp];
		$smarty->enableSecurity($policy);
		$smarty->assign('D', ['R' => ['Theme' => $theme]]);
		$html = $smarty->fetch($chain['extends']);
		$this->assertStringContainsString('data-bs-theme="dark"', $html);
		$this->assertStringContainsString('/application/file/theme/', $html);
		$this->assertStringContainsString('frontendarticle', $html);
	}

	#[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
	public function testRealCoreAndFrontendThemesRenderDarkModeAndTheirSettings(): void {
		$C = new Container();
		$C->set('Filesystem', $this->fs);
		$D = ['MODULE' => ['D' => []], 'SESSION' => []];
		$vendor = str_replace('\\', '/', dirname(__DIR__, 3));
		foreach (['core' => 'core', 'frontend' => 'template'] as $name => $type) {
			$id = "fremeo/$name";
			$D['MODULE']['D'][$id] = ['INFO' => ['Type' => $type, 'ModulDir' => "$vendor/fremeo/$name"]];
			$dir = "{$this->tmp}/real/$name";
			$this->fs->mkdir($dir);
			$db = new CData(['DB' => ['FILENAME' => "$dir/data.db", 'FILENAME_C' => "$dir/cache.db"]]);
			$db->registerPattern(include __DIR__ . '/../system/default/default.global.pattern.php');
			$C->set('CData', $db, $id);
		}
		$template = new Template($C, $D);
		$C->set('Template', $template);
		$C->set('Setting', new Setting($C, $D));
		$C->set('Builder', fn () => new \fremeo\core\Builder($C['fremeo/core']['CData']));
		$C->set('Security', new class extends \fremeo\core\Security {
			public function __construct() { $this->SecurityActive = false; }
		});
		try {
			$D['C'] = $C;
			$R = [];
			$D['R'] = &$R;
			$F = [];
			include __DIR__ . '/../system/admin__setting.list.php';
			$this->assertArrayHasKey('Template', $R['SETTING_VIEW']);
			foreach (['Core' => 'fremeo/core', 'Frontend' => 'fremeo/frontend'] as $label => $provider) {
				$items = $R['SETTING_VIEW']['Template']['Children'][$label]['Children']['Performance']['Items'];
				$this->assertCount(3, $items);
				$this->assertSame([$provider], array_values(array_unique(array_column($items, 'Module'))));
			}
			$C['Setting']->set('fremeo/frontend', 'theme_variant', 'basic__dark_mode');
			include __DIR__ . '/../system/admin__setting.list.php';
			$this->assertSame('fremeo/frontend:basic__dark_mode', $R['SETTING_VIEW']['Template']['Children']['Frontend']['Items'][0]['Value']);
			$smarty = new \Smarty();
			$smarty->setCompileDir("{$this->tmp}/real/compile");
			$smarty->addTemplateDir(__DIR__ . '/../system/template');
			$smarty->error_reporting = E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_USER_DEPRECATED;
			$policy = new \Smarty_Security($smarty);
			$policy->secure_dir = [$vendor];
			$policy->php_functions = ['max', 'round', 'hrtime', 'rtrim', 'dirname', 'mail', 'base64_encode', 'mb_convert_encoding', 'hash', 'header', 'json_decode', 'json_encode', 'serialize', 'COUNT', 'file_get_contents', 'substr', 'array_key_exists', 'array_merge_recursive', 'array_diff_key', 'str_pad', 'strtotime', 'date', 'ceil', 'array_keys', 'current', 'count', 'strlen', 'strtolower', 'number_format', 'md5', 'in_array', 'is_array', 'time', 'nl2br', 'print_r', 'array_key_first', 'strpos', 'strrpos', 'str_replace', 'isset', 'empty', 'sizeof', 'trim', 'explode', 'implode'];
			$policy->php_modifiers = ['in_array', 'strlen', 'strstr', 'array_keys', 'count', 'COUNT', 'number_format'];
			$smarty->enableSecurity($policy);
			if (!defined('SCRIPT_NAME')) {
				define('SCRIPT_NAME', '/application/');
			}
			foreach (['fremeo/core' => 'admin__setting.list', 'fremeo/frontend' => 'index__frontend'] as $id => $page) {
				$R['ModuleId'] = $id;
				$R['Page'] = $page;
				$this->assertSame($id === 'fremeo/frontend' ? 'basic__dark_mode' : 'basic', $C['Setting']->get($id, 'theme'));
				$themes = $template->getThemes($id);
				$this->assertSame(['basic', 'basic__dark_mode'], $themes['basic__dark_mode']['Chain']);
				$C['Setting']->set($id, 'theme', 'basic__dark_mode');
				$this->assertNull($C['Setting']->get($id, 'theme_variant'));
				$C['Setting']->set($id, 'theme_css_basic', 'body { --custom-theme-test: 1; }');
				$chain = $template->getExtends($D['MODULE']['D'], $id, $page);
				$R['Theme'] = $template->publishTheme($template->resolveTheme($chain['tpl']), "{$this->tmp}/public/theme", '/application/');
				$smarty->assign('D', $D);
				if ($id === 'fremeo/core') {
					foreach ([null, true, false] as $allowEmpty) {
						$p = ['name' => 'example', 'type' => 'select', 'option' => ['basic'], 'value' => 'basic'];
						if ($allowEmpty !== null) {
							$p['allow_empty'] = $allowEmpty;
						}
						$smarty->assign('p', $p);
						$select = $smarty->fetch('string:' . file_get_contents(__DIR__ . '/../system/template/include/input.tpl') . '{input p=$p}');
						$this->assertSame($allowEmpty !== false, str_contains($select, '<option value="">---</option>'));
					}
				}
				$reporting = error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
				try {
					$html = $smarty->fetch($chain['extends'] . '|include/input.tpl');
				} finally {
					error_reporting($reporting);
				}
				$this->assertStringContainsString('data-bs-theme="dark"', $html);
				$this->assertStringContainsString('file/theme/', $html);
				$this->assertStringContainsString('dark_mode.css', $html);
				$this->assertStringContainsString('_custom.css', $html);
				$this->assertDoesNotMatchRegularExpression('~<script[^>]+src="https?://~', $html);
				$this->assertStringNotContainsString('public/shop/', $html);
				$this->assertStringNotContainsString('/vendor/popper.js/', $html);
				$this->assertSame(1, substr_count($html, '/js/bootstrap.bundle.min.js'));
				$this->assertGreaterThan(strpos($html, '</head>'), strpos($html, '/js/bootstrap.bundle.min.js'));
				$this->assertLessThan(strpos($html, '</body>'), strpos($html, '/js/bootstrap.bundle.min.js'));
				if ($id === 'fremeo/core') {
					preg_match_all('~<select\b[^>]*name="D\[MODULE\]\[D\]\[fremeo/(?:core|frontend)\]\[SETTING\]\[D\]\[(theme|theme_variant)\]\[Value\]"[^>]*>(.*?)</select>~s', $html, $selects, PREG_SET_ORDER);
					$this->assertCount(2, $selects);
					$this->assertStringNotContainsString('[theme_variant][Value]', $html);
					foreach ($selects as $select) {
						$this->assertStringNotContainsString('>---</option>', $select[2]);
						$this->assertSame(0, substr_count($select[2], 'value=""'));
						$this->assertMatchesRegularExpression('/value="fremeo\/(?:core|frontend):basic"/', $select[2]);
						$this->assertMatchesRegularExpression('/value="fremeo\/(?:core|frontend):basic__dark_mode"/', $select[2]);
					}
					$this->assertStringContainsString('basic__dark_mode', $html);
					$this->assertStringContainsString('css/admin.css', $html);
					$this->assertStringContainsString('js/admin.js', $html);
					$this->assertLessThan(strpos($html, 'js/admin.js'), strpos($html, '/js/bootstrap.bundle.min.js'));
				} else {
					$this->assertStringNotContainsString('css/admin.css', $html);
					$this->assertStringNotContainsString('js/admin.js', $html);
				}
				foreach ($R['Theme']['Assets'] as $assets) {
					foreach ($assets as $asset) {
						$this->assertFileExists($this->diskPath($asset['Url']));
					}
				}
				foreach (['minify_css', 'minify_js', 'combine_css'] as $key) {
					$C['Setting']->set($id, "theme_$key", '1');
				}
				$originalSize = array_sum(array_map(fn ($entry) => filesize($this->diskPath($entry['Url'])), $R['Theme']['Assets']['css']));
				$optimized = $template->publishTheme($template->resolveTheme($chain['tpl']), "{$this->tmp}/public/theme", '/application/');
				$this->assertNotSame($R['Theme']['Hash'], $optimized['Hash']);
				$this->assertCount(3, $optimized['Assets']['css']);
				$this->assertSame('bogus', $optimized['Assets']['css'][1]['Media']);
				$css = file_get_contents($this->diskPath($optimized['Assets']['css'][0]['Url']));
				$this->assertStringContainsString('../vendor/font-awesome/7.0.1/webfonts/fa-solid-900.woff2', $css);
				$this->assertStringContainsString('../vendor/jquery-ui/1.14.2/images/ui-icons_444444_256x240.png', $css);
				$this->assertStringContainsString('Bootstrap  v5.3.8', $css);
				$optimizedSize = array_sum(array_map(fn ($entry) => filesize($this->diskPath($entry['Url'])), $optimized['Assets']['css']));
				$this->assertLessThan($originalSize, $optimizedSize);
				foreach (['head_js', 'body_js'] as $group) {
					$this->assertCount(count($R['Theme']['Assets'][$group]), $optimized['Assets'][$group]);
					foreach ($optimized['Assets'][$group] as $entry) {
						$this->assertStringEndsWith('.min.js', $this->diskPath($entry['Url']));
						$this->assertFileExists($this->diskPath($entry['Url']));
					}
				}
				foreach (['minify_css', 'minify_js', 'combine_css'] as $key) {
					$C['Setting']->set($id, "theme_$key", '0');
				}
			}
		} finally {
			unset($C['Builder']);
			foreach (array_keys($C->getAll('CData')) as $id) {
				unset($C[$id]['CData']);
			}
			unset($db);
			if (isset($smarty)) {
				$smarty->clearAllAssign();
			}
		}
	}
}
