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
		$this->assertStringContainsString('dropdown-menu', $html);
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
}
