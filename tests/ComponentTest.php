<?php
namespace fremeo\core\tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ComponentTest extends TestCase {
	private string $tmp;
	private \Smarty $smarty;
	private array $D;

	protected function setUp(): void {
		$this->tmp = sys_get_temp_dir() . '/fremeo_components_' . bin2hex(random_bytes(8));
		mkdir($this->tmp);
		$this->smarty = new \Smarty();
		$this->smarty->setCompileDir($this->tmp);
		$this->smarty->addPluginsDir(__DIR__ . '/../system/core/smarty_plugin');
		$policy = new \Smarty_Security($this->smarty);
		$policy->secure_dir = [$this->tmp, dirname(__DIR__, 2)];
		$this->smarty->enableSecurity($policy);
		$this->D = ['MODULE' => ['D' => [
			'fremeo/frontend' => ['Active' => 1, 'INFO' => ['Type' => 'template', 'ModulDir' => dirname(__DIR__, 2) . '/frontend']],
		]]];
	}

	protected function tearDown(): void {
		unset($this->smarty);
		(new Filesystem())->remove($this->tmp);
	}

	private function render(string $source, array $data = []): string {
		$this->smarty->assign(['D' => $this->D, 'navigation' => $data, 'data' => 'unchanged']);
		return $this->smarty->fetch('string:' . $source);
	}

	private function navigation(): array {
		return ['Active' => 1, 'Name' => 'Menu', 'Json' => '[]', 'Tree' => [
			['Title' => ['DE' => '<Start>', 'EN' => 'Home'], 'Url' => 'start?x="', 'Target' => '_blank', 'Children' => [
				['Title' => 'Kind', 'Url' => 'kind', 'Target' => '_self', 'Children' => []],
			]],
		]];
	}

	public function testNavigationUsesPatternDataWithVariantsAndUniqueContainers(): void {
		$html = $this->render(
			'{component module="fremeo/frontend" type="navigation" data=$navigation}'
			. '{component module="fremeo/frontend" type="navigation" data=$navigation id="sidebar-links" options=["variant"=>"vertical","language"=>"EN"]}',
			$this->navigation()
		);
		$this->assertStringContainsString('id="component-navigation-1" class="component component-navigation"', $html);
		$this->assertStringContainsString('id="sidebar-links"', $html);
		$this->assertStringContainsString('data-component-module="fremeo/frontend"', $html);
		$this->assertStringContainsString('navbar-nav flex-row flex-wrap column-gap-3 me-auto', $html);
		$this->assertStringContainsString('dropdown-menu position-absolute', $html);
		$this->assertStringContainsString('list-unstyled mb-0', $html);
		$this->assertStringContainsString('&lt;Start&gt;', $html);
		$this->assertStringContainsString('Home', $html);
		$this->assertStringContainsString('Kind', $html);
		$this->assertStringContainsString('start?x=&quot;', $html);
		$this->assertStringContainsString('rel="noopener noreferrer"', $html);
		$this->assertSame('unchanged', $this->smarty->getTemplateVars('data'));
	}

	public function testAutomaticIdsAreUniqueAndInactiveNavigationDoesNotRenderLinks(): void {
		$html = $this->render(
			str_repeat('{component module="fremeo/frontend" type="navigation" data=$navigation}', 2),
			['Active' => 0]
		);
		$this->assertStringContainsString('id="component-navigation-1"', $html);
		$this->assertStringContainsString('id="component-navigation-2"', $html);
		$this->assertStringNotContainsString('<ul', $html);
	}

	public function testExplicitProviderSupportsModulesAndCoreWithoutOverriding(): void {
		foreach (['vendor/a' => 'module', 'vendor/b' => 'core'] as $id => $type) {
			$directory = $this->tmp . '/' . basename($id);
			mkdir($directory . '/system/template/components/navigation', 0777, true);
			file_put_contents($directory . '/system/template/components/navigation/component.json', '{"type":"navigation"}');
			file_put_contents($directory . '/system/template/components/navigation/navigation.tpl', '<p>' . $id . ':{$data.Name|escape}</p>');
			$this->D['MODULE']['D'][$id] = ['Active' => 1, 'INFO' => ['Type' => $type, 'ModulDir' => $directory]];
		}
		$html = $this->render(
			'{component module="vendor/a" type="navigation" data=$navigation}'
			. '{component module="vendor/b" type="navigation" data=$navigation}',
			['Name' => '<Menu>']
		);
		$this->assertStringContainsString('<p>vendor/a:&lt;Menu&gt;</p>', $html);
		$this->assertStringContainsString('<p>vendor/b:&lt;Menu&gt;</p>', $html);
	}

	public function testMissingComponentDoesNotFallBackToAnotherProvider(): void {
		$this->D['MODULE']['D']['vendor/empty'] = ['Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $this->tmp]];
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Komponente nicht gefunden');
		$this->render('{component module="vendor/empty" type="navigation" data=[]}');
	}

	public function testDisabledProviderIsRejected(): void {
		$this->D['MODULE']['D']['fremeo/frontend']['Active'] = 0;
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Komponenten-Modul nicht verfuegbar');
		$this->render('{component module="fremeo/frontend" type="navigation" data=[]}');
	}

	public function testDuplicateExplicitIdsAreRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('bereits vergeben');
		$this->render(str_repeat('{component module="fremeo/frontend" type="navigation" data=[] id="same"}', 2));
	}

	public function testInvalidTypeIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->render('{component module="fremeo/frontend" type="../navigation" data=[]}');
	}

	public function testInvalidVariantIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Komponenten-Variante');
		$this->render('{component module="fremeo/frontend" type="navigation" data=[] options=["variant"=>"missing"]}');
	}

	public function testTypedSettingsAreValidatedWithoutCoercionAndKeepUneditedOptions(): void {
		$manifest = [
			'defaults' => ['text' => 'Default', 'enabled' => false, 'count' => 1, 'ratio' => 0.5, 'choice' => 'a', 'internal' => 'keep'],
			'settings' => [
				'text' => ['type' => 'string', 'label' => 'Text'],
				'enabled' => ['type' => 'boolean', 'label' => 'Enabled'],
				'count' => ['type' => 'integer', 'label' => 'Count', 'min' => 1, 'max' => 3],
				'ratio' => ['type' => 'number', 'label' => 'Ratio', 'min' => 0, 'max' => 1],
				'choice' => ['type' => 'string', 'label' => 'Choice', 'enum' => ['a', 'b']],
			],
		];
		$values = \fremeo\core\Component::options($manifest, ['enabled' => true, 'count' => 3, 'choice' => 'b']);
		$this->assertTrue($values['enabled']);
		$this->assertSame('keep', $values['internal']);
		$this->assertSame(3, $values['count']);
		foreach ([
			['text' => []], ['enabled' => 'true'], ['count' => '2'], ['count' => 0], ['count' => 4],
			['count' => 1.5], ['ratio' => 1.1], ['ratio' => INF], ['choice' => 'c'],
		] as $invalid) {
			try {
				\fremeo\core\Component::options($manifest, $invalid);
				$this->fail('Invalid setting accepted: ' . array_key_first($invalid));
			} catch (\RuntimeException $error) {
				$this->assertStringContainsString('Einstellung', $error->getMessage());
			}
		}
	}

	public function testInvalidSettingDefinitionIsRejected(): void {
		$directory = $this->tmp . '/system/template/components/example';
		mkdir($directory, 0777, true);
		file_put_contents($directory . '/example.tpl', '');
		file_put_contents($directory . '/component.json', '{"type":"example","settings":{"count":{"type":"integer","label":"Count"}}}');
		$this->expectException(\RuntimeException::class);
		\fremeo\core\Component::load('test/provider', 'example', [
			'Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $this->tmp],
		]);
	}

	public function testTranslationsRequireAnActiveTargetAndPreserveExplicitEmptyContent(): void {
		$manifest = [
			'defaults' => ['text' => 'Default', 'variant' => 'plain'],
			'settings' => ['text' => ['type' => 'string', 'label' => 'Text', 'translatable' => true]],
		];
		$translations = [
			'EN' => ['active' => true, 'options' => ['text' => 'English']],
			'DE' => ['active' => true],
			'FR' => ['active' => false, 'options' => ['text' => 'Francais']],
			'ES' => ['active' => true, 'options' => ['text' => '']],
		];
		$resolve = static fn ($code, $values) => \fremeo\core\Component::localizedOptions($manifest, ['variant' => 'keep'], $values, $code);
		$this->assertSame('English', $resolve('DE', $translations)['options']['text']);
		$this->assertTrue($resolve('DE', $translations)['visible']);
		$this->assertFalse($resolve('FR', $translations)['visible']);
		$this->assertFalse($resolve('IT', $translations)['visible']);
		$this->assertSame('', $resolve('ES', $translations)['options']['text']);
		$this->assertTrue($resolve('ES', $translations)['visible']);
		$this->assertSame('keep', $resolve('DE', $translations)['options']['variant']);
		$translations['DE']['options'] = ['text' => 'Deutsch'];
		$this->assertSame('Deutsch', $resolve('DE', $translations)['options']['text']);
		$translations['EN']['active'] = false;
		unset($translations['DE']['options']);
		$this->assertFalse($resolve('DE', $translations)['visible']);
		foreach ([
			['DE' => ['active' => 1]], ['de' => ['active' => true]], ['DE' => ['active' => true, 'options' => []]],
			['DE' => ['active' => true, 'options' => ['text' => 42]]],
			['DE' => ['active' => true, 'options' => ['text' => 'x', 'variant' => 'not translatable']]],
			['DE' => ['active' => true, 'unknown' => 'x']],
		] as $invalid) {
			try {
				$resolve('DE', $invalid);
				$this->fail('Invalid translation accepted.');
			} catch (\RuntimeException $error) {
				$this->assertNotSame('', $error->getMessage());
			}
		}
		$this->assertTrue(\fremeo\core\Component::localizedOptions(['defaults' => []], [], [], 'FR')['visible']);
	}

	public function testCoreTextUsesGermanTranslationsAndRendersValidatedHtml(): void {
		$provider = ['Active' => 1, 'INFO' => ['Type' => 'core', 'ModulDir' => dirname(__DIR__)]];
		$definition = \fremeo\core\Component::load('fremeo/core', 'text', $provider);
		$manifest = $definition['manifest'];
		$this->assertSame('Text', $manifest['name']);
		$this->assertSame('DE', $manifest['editorLanguage']);
		$this->assertSame('wysiwyg', $manifest['settings']['text']['input']);
		$this->assertStringEndsWith('text.settings.tpl', $definition['settings']);
		$this->assertTrue($manifest['settings']['text']['translatable']);
		$text = "<p>Erste Zeile<br><strong>Text</strong></p>\n<p style=\"text-align: center\">Zweite Zeile &amp; Text</p>";
		$translations = ['DE' => ['active' => true, 'options' => ['text' => $text]]];
		$localized = \fremeo\core\Component::localizedOptions($manifest, [], $translations, 'DE');
		$this->assertTrue($localized['visible']);
		$this->assertSame($text, $localized['options']['text']);
		$this->assertFalse(\fremeo\core\Component::localizedOptions($manifest, [], $translations, 'EN')['visible']);
		$this->D['MODULE']['D']['fremeo/core'] = $provider;
		$this->smarty->assign('textOptions', $localized['options']);
		$html = $this->render('{component module="fremeo/core" type="text" data=[] options=$textOptions}');
		$this->assertStringContainsString('component component-text', $html);
		$this->assertStringContainsString('white-space: pre-wrap', $html);
		$this->assertStringContainsString('<p>Erste Zeile<br><strong>Text</strong></p>', $html);
		$this->assertStringContainsString('Zweite Zeile &amp; Text', $html);
		$this->assertStringNotContainsString('<script>', $html);
		$formatted = '<blockquote style="margin: 0 0 0 40px; border: none; padding: 0px"><ul><li><b>Fett</b></li></ul></blockquote>'
			. '<a href="/info" target="_blank">Info</a><img src="https://example.test/image.png" alt="Bild">';
		$this->assertSame($formatted, \fremeo\core\Component::options($manifest, ['text' => $formatted])['text']);
		$translations['DE']['options']['text'] = '';
		$this->assertSame(['options' => ['text' => ''], 'visible' => true], \fremeo\core\Component::localizedOptions($manifest, [], $translations, 'DE'));
		foreach ([
			'<script>alert(1)</script>', '<img src="x" onerror="alert(1)">',
			'<a href="javascript:alert(1)">Link</a>', '<a href="java&#10;script:alert(1)">Link</a>',
			'<svg onload="alert(1)"></svg>', '<iframe src="https://example.test"></iframe>',
			'<p style="background-image:url(javascript:alert(1))">Text</p>', '<div style="position:fixed">Text</div>',
			'<img src="data:image/svg+xml,test">', '<style>body{display:none}</style>',
			'<p onclick="alert(1)">Text</p>', '<!-- hidden markup -->',
			'<body onload="alert(1)"><p>Text</p></body>', '<html onclick="alert(1)"><p>Text</p></html>',
			'<head onload="alert(1)"></head><p>Text</p>', '<a href="&#106;avascript:alert(1)">Link</a>',
		] as $unsafe) {
			$error = null;
			try {
				\fremeo\core\Component::options($manifest, ['text' => $unsafe]);
			} catch (\RuntimeException $exception) {
				$error = $exception;
			}
			$this->assertInstanceOf(\RuntimeException::class, $error, $unsafe);
			$this->assertStringContainsString('Text', $error->getMessage());
		}
	}

	public function testTextareaAndEditorLanguageMetadataRejectInvalidDefinitions(): void {
		$directory = $this->tmp . '/system/template/components/text';
		(new Filesystem())->mkdir($directory);
		file_put_contents($directory . '/text.tpl', '');
		$manifest = ['type' => 'text', 'defaults' => ['text' => ''], 'settings' => ['text' => ['type' => 'string', 'label' => 'Text', 'input' => 'textarea']]];
		foreach ([
			array_replace($manifest, ['editorLanguage' => 'de']),
			array_replace($manifest, ['editorLanguage' => null]),
			array_replace($manifest, ['editorLanguage' => ['DE']]),
			array_replace_recursive($manifest, ['settings' => ['text' => ['input' => 'invalid']]]),
			array_replace_recursive($manifest, ['settings' => ['text' => ['type' => 'boolean']]]),
			array_replace_recursive($manifest, ['settings' => ['text' => ['enum' => ['']]]]),
		] as $invalid) {
			file_put_contents($directory . '/component.json', json_encode($invalid, JSON_THROW_ON_ERROR));
			try {
				\fremeo\core\Component::load('test/provider', 'text', ['Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $this->tmp]]);
				$this->fail('Invalid editor metadata accepted.');
			} catch (\RuntimeException $error) {
				$this->assertStringContainsString('Ungueltig', $error->getMessage());
			}
		}
	}

	public function testCoreContainerValidatesBootstrapGridAndResponsiveInheritance(): void {
		$provider = ['Active' => 1, 'INFO' => ['Type' => 'core', 'ModulDir' => dirname(__DIR__)]];
		$definition = \fremeo\core\Component::load('fremeo/core', 'container', $provider);
		$this->assertSame('container', $definition['manifest']['builder']);
		$this->assertSame('Grid', $definition['manifest']['name']);
		$this->assertNotNull($definition['editor']);
		$options = \fremeo\core\Component::options($definition['manifest'], ['columns' => '8+4', 'sm' => '6+6', 'md' => '8+4', 'lg' => '9+3']);
		$this->assertSame(['col-12 col-sm-6 col-md-8 col-lg-9', 'col-12 col-sm-6 col-md-4 col-lg-3'], ($definition['data'])($options, [])['columnClasses']);
		$options = \fremeo\core\Component::options($definition['manifest'], ['columns' => '8+4', 'sm' => '6+6']);
		$this->assertSame(['col-12 col-sm-6', 'col-12 col-sm-6'], ($definition['data'])($options, [])['columnClasses']);
		$options = \fremeo\core\Component::options($definition['manifest'], ['columns' => '3+6+3']);
		$this->assertSame(['col-12 col-md-3', 'col-12 col-md-6', 'col-12 col-md-3'], ($definition['data'])($options, [])['columnClasses']);
		foreach ([
			['columns' => ''], ['columns' => '0+12'], ['columns' => '13'], ['columns' => '-1+12'],
			['columns' => '6.5+5.5'], ['columns' => implode('+', array_fill(0, 13, 1))], ['columns' => '6+6', 'lg' => '12'],
			['columns' => '6+6', 'md' => 'x'],
		] as $invalid) {
			try {
				\fremeo\core\Component::options($definition['manifest'], $invalid);
				$this->fail('Invalid Bootstrap grid accepted.');
			} catch (\RuntimeException $error) {
				$this->assertStringContainsString('Grid', $error->getMessage());
			}
		}
		$twelve = implode('+', array_fill(0, 12, 1));
		$options = \fremeo\core\Component::options($definition['manifest'], ['columns' => $twelve]);
		$this->assertCount(12, ($definition['data'])($options, [])['columnClasses']);
		$options = \fremeo\core\Component::options($definition['manifest'], ['columns' => '4+4+4', 'xs' => '12+12+12', 'sm' => '6+6+12', 'activeXs' => false]);
		$data = ($definition['data'])($options, ['nodes' => [['slot' => 2, 'id' => 'third']]]);
		$this->assertSame(['col-12 col-sm-6', 'col-12 col-sm-6', 'col-12 col-sm-12'], $data['columnClasses']);
		$this->assertSame('d-none d-sm-flex d-md-flex d-lg-flex', $data['gridVisibility']);
		$this->assertCount(3, $data['slots']);
		$this->assertSame([], $data['slots'][0]);
		$this->assertSame('third', $data['slots'][2][0]['id']);
		$data = ($definition['data'])($options, ['nodes' => [
			['slot' => 2, 'id' => 'first', 'general' => ['Viewports' => ['MD']]],
			['slot' => 2, 'id' => 'second', 'general' => ['Viewports' => ['XS']]],
		]]);
		$this->assertSame(['first', 'second'], array_column($data['slots'][2], 'id'));
		$this->assertFalse($data['slotHidden'][2]);
		$this->assertSame('d-block d-sm-none d-md-block d-lg-none', $data['slotVisibility'][2]);
		$this->D['MODULE']['D']['fremeo/core'] = $provider;
		$html = $this->render('{component module="fremeo/core" type="container" data=[] options=["columns"=>"8+4"]}');
		$this->assertStringContainsString('component-container', $html);
		$this->assertStringContainsString('row g-3', $html);
	}

	public function testNavigationSelectsManagedMenuAndPreservesExplicitTemplateData(): void {
		$source = '{component module="fremeo/frontend" type="navigation" data=$navigation options=$instanceOptions}';
		$menu = $this->navigation();
		$menu['Name'] = 'Managed menu';
		$menu['Tree'][0]['Title'] = 'Selected menu';
		$this->D['MODULE']['D']['fremeo/frontend']['NAVIGATION']['D'] = ['main' => $menu, 'inactive' => ['Active' => 0, 'Tree' => $menu['Tree']]];
		$this->smarty->assign('instanceOptions', ['menu' => 'main']);
		$html = $this->render($source, $this->navigation());
		$this->assertStringContainsString('Selected menu', $html);
		$this->assertStringContainsString('Kind', $html);
		$this->assertStringContainsString('rel="noopener noreferrer"', $html);
		$this->assertStringNotContainsString('&lt;Start&gt;', $html);
		$empty = clone $this->smarty;
		$empty->assign('instanceOptions', ['menu' => 'inactive']);
		$emptyHtml = $empty->fetch('string:' . $source);
		$this->assertStringNotContainsString('<a ', $emptyHtml);
		$separate = clone $this->smarty;
		$separateHtml = $separate->fetch('string:' . $source . '{component module="fremeo/frontend" type="navigation" data=$navigation}');
		$this->assertStringContainsString('Selected menu', $separateHtml);
		$this->assertStringContainsString('&lt;Start&gt;', $separateHtml);
		$definition = \fremeo\core\Component::load('fremeo/frontend', 'navigation', $this->D['MODULE']['D']['fremeo/frontend']);
		$this->assertSame(['value' => 'main', 'label' => 'Managed menu (main)'], $definition['manifest']['settings']['menu']['choices'][1]);
		$this->assertArrayNotHasKey('items', $definition['manifest']['settings']);
		$this->assertSame(['variant', 'menu'], array_keys($definition['manifest']['settings']));
		$this->expectException(\RuntimeException::class);
		\fremeo\core\Component::options($definition['manifest'], ['menu' => 'missing']);
	}

	public function testGenericTreeRejectsInvalidStructuresAndUnsafeLinks(): void {
		$manifest = ['defaults' => ['items' => []], 'settings' => ['items' => [
			'type' => 'tree', 'label' => 'Items', 'children' => 'Children', 'fields' => [
				'Title' => ['type' => 'string', 'label' => 'Title', 'default' => ''],
				'Url' => ['type' => 'string', 'label' => 'Url', 'format' => 'link', 'default' => ''],
				'Target' => ['type' => 'string', 'label' => 'Target', 'enum' => ['_self', '_blank'], 'default' => '_self'],
			],
		]]];
		$node = ['Title' => 'Item', 'Url' => 'path', 'Target' => '_self', 'Children' => []];
		$this->assertSame([$node], \fremeo\core\Component::options($manifest, ['items' => [$node]])['items']);
		foreach ([
			'not a list', ['not a node'], ['key' => $node], [array_diff_key($node, ['Title' => true])],
			[array_replace($node, ['Children' => null])], [array_replace($node, ['Target' => '_parent'])],
			[array_replace($node, ['Url' => 'javascript:alert(1)'])], [array_replace($node, ['Url' => "java\tscript:alert(1)"])],
			[array_replace($node, ['Url' => 'data:text/html,test'])], [array_replace($node, ['Other' => 'unknown'])],
			[array_replace($node, ['Children' => [array_replace($node, ['Url' => 'vbscript:test'])]])],
		] as $invalid) {
			try {
				\fremeo\core\Component::options($manifest, ['items' => $invalid]);
				$this->fail('Invalid tree accepted.');
			} catch (\RuntimeException $error) {
				$this->assertNotSame('', $error->getMessage());
			}

		}
		foreach (['', '#section', '?page=1', '/absolute', '../relative', '//example.test/', 'https://example.test/', 'mailto:a@example.test', 'tel:+123'] as $url) {
			$this->assertSame($url, \fremeo\core\Component::options($manifest, ['items' => [array_replace($node, ['Url' => $url])]])['items'][0]['Url']);
		}
	}

	public function testNavigationLoadsMenuChoicesAndCurrentDataFromItsOwnFrontendService(): void {
		$db = new \phploader\CData(['DB' => ['FILENAME' => $this->tmp . '/menus.db']]);
		$db->registerPattern(include dirname(__DIR__, 2) . '/frontend/system/default/default.pattern.php');
		$frontend = new \fremeo\frontend\Frontend($db);
		$tree = [['Title' => ['DE' => 'Verwaltet', 'EN' => 'Managed'], 'Url' => '/managed', 'Children' => []]];
		$frontend->set_navigation(['NAVIGATION' => ['D' => ['_new' => ['NewId' => 'managed', 'Name' => 'Managed menu', 'Active' => 1, 'Json' => json_encode($tree)]]]]);
		$services = new \fremeo\core\Container();
		$services->set('Frontend', $frontend, 'fremeo/frontend');
		try {
			$this->D['C'] = $services;
			$definition = \fremeo\core\Component::load('fremeo/frontend', 'navigation', $this->D['MODULE']['D']['fremeo/frontend'], $this->D);
			$this->assertSame('managed', $definition['manifest']['settings']['menu']['choices'][1]['value']);
			$this->assertStringContainsString('Verwaltet', $this->render('{component module="fremeo/frontend" type="navigation" data=[] options=["menu"=>"managed"]}'));
			$tree[0]['Title']['DE'] = 'Aktualisiert';
			$frontend->set_navigation(['NAVIGATION' => ['D' => ['managed' => ['Json' => json_encode($tree)]]]]);
			$next = clone $this->smarty;
			$this->assertStringContainsString('Aktualisiert', $next->fetch('string:{component module="fremeo/frontend" type="navigation" data=[] options=["menu"=>"managed"]}'));
			$next->clearAllAssign();
		} finally {
			$this->smarty->clearAllAssign();
			unset($services['fremeo/frontend']['Frontend']);
			unset($this->D['C'], $this->smarty, $next, $services, $frontend, $db, $definition);
			gc_collect_cycles();
		}
	}

	public function testComponentLocalPhpRequiresExplicitClosureContract(): void {
		$directory = $this->tmp . '/system/template/components/example';
		mkdir($directory, 0777, true);
		file_put_contents($directory . '/example.tpl', '');
		file_put_contents($directory . '/component.json', '{"type":"example"}');
		foreach (['return [];', 'return static fn() => "invalid";', 'return static fn() => ["data"=>"not a closure"];'] as $php) {
			file_put_contents($directory . '/example.php', '<?php ' . $php);
			try {
				\fremeo\core\Component::load('test/provider', 'example', ['Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $this->tmp]]);
				$this->fail('Invalid local PHP contract accepted.');
			} catch (\RuntimeException $error) {
				$this->assertStringContainsString('Komponenten-PHP', $error->getMessage());
			}
		}
	}

	public function testMalformedTreeDefinitionsAreRejectedBeforeOpeningEditor(): void {
		$directory = $this->tmp . '/system/template/components/example';
		mkdir($directory, 0777, true);
		file_put_contents($directory . '/example.tpl', '');
		$tree = ['type' => 'tree', 'label' => 'Tree', 'children' => 'Children',
			'fields' => ['Title' => ['type' => 'string', 'label' => 'Title', 'default' => '']]];
		foreach ([
			array_diff_key($tree, ['children' => true]),
			array_replace($tree, ['children' => 'Title']),
			array_replace($tree, ['fields' => []]),
			array_replace($tree, ['fields' => ['Title' => ['type' => 'string', 'label' => 'Title']]]),
			array_replace($tree, ['fields' => ['Title' => ['type' => 'string', 'label' => 'Title', 'default' => 42]]]),
			array_replace($tree, ['fields' => ['Title' => ['type' => 'string', 'label' => 'Title', 'default' => '', 'enum' => ['', 42]]]]),
		] as $definition) {
			file_put_contents($directory . '/component.json', json_encode([
				'type' => 'example', 'defaults' => ['items' => []], 'settings' => ['items' => $definition],
			], JSON_THROW_ON_ERROR));
			try {
				\fremeo\core\Component::load('test/provider', 'example', [
					'Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $this->tmp],
				]);
				$this->fail('Invalid tree definition accepted.');
			} catch (\RuntimeException $error) {
				$this->assertNotSame('', $error->getMessage());
			}
		}
	}
}
