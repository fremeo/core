<?php

namespace fremeo\core\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__ . '/../system/core/smarty_plugin/compiler.i18n.php';

class I18nTest extends TestCase {
	private string $tmp;
	private \Smarty $smarty;

	protected function setUp(): void {
		$this->tmp = sys_get_temp_dir() . '/smarty_i18n_' . bin2hex(random_bytes(6));
		$this->smarty = new \Smarty();
		$this->smarty->setCompileDir($this->tmp . '/compiled');
		$this->smarty->setCacheDir($this->tmp . '/cache');
	}

	protected function tearDown(): void {
		unset($this->smarty);
		gc_collect_cycles();
		(new Filesystem())->remove($this->tmp);
	}

	public function testStaticTranslationIsLiteralTextAndNeverExecutesSmarty(): void {
		$plugin = new \Smarty_I18n('de_DE', static fn () => "<u>Anmelden</u> '{\$secret}'");
		$this->smarty->registerPlugin(\Smarty::PLUGIN_COMPILER, 'i18n', [$plugin, 'compile']);
		$result = $this->smarty->fetch('string:<h1>{i18n key="title"}</h1>');
		$this->assertSame('<h1><u>Anmelden</u> {$secret}</h1>', $result);
		$compiled = implode('', array_map('file_get_contents', glob($this->tmp . '/compiled/*.php')));
		$this->assertStringContainsString('<h1><u>Anmelden</u>', $compiled);
		$this->assertStringNotContainsString('Smarty_I18n::format(', $compiled);
		$this->assertStringNotContainsString('echo \'<u>Anmelden', $compiled);
	}

	public function testResolverAndDynamicValuesHaveNoApplicationDependencies(): void {
		$calls = 0;
		$plugin = new \Smarty_I18n('de_DE', static function ($key, $module, $compiler) use (&$calls): string {
			$calls++;
			self::assertSame('result', $key);
			self::assertSame('example', $module);
			self::assertInstanceOf(\Smarty_Internal_TemplateCompilerBase::class, $compiler);
			return '<u>{count, plural, =0 {Keine Bilder} one {Ein Bild} other {# Bilder}}</u> {name}';
		});
		$this->smarty->registerPlugin(\Smarty::PLUGIN_COMPILER, 'i18n', [$plugin, 'compile']);
		$template = 'string:{i18n module="example" key="result" values=["count" => $count, "name" => $name]}';
		$this->smarty->assign(['count' => 1, 'name' => '<Anna>']);
		$this->assertSame('<u>Ein Bild</u> &lt;Anna&gt;', $this->smarty->fetch($template));
		$this->smarty->assign(['count' => 20, 'name' => '"Bob" & Co']);
		$this->assertSame('<u>20 Bilder</u> &quot;Bob&quot; &amp; Co', $this->smarty->fetch($template));
		$this->assertSame(1, $calls);
	}

	#[DataProvider('literalPhpTags')]
	public function testLiteralPhpTagsPreserveTheirText(string $text): void {
		$plugin = new \Smarty_I18n('de', static fn () => $text);
		$this->smarty->registerPlugin(\Smarty::PLUGIN_COMPILER, 'i18n', [$plugin, 'compile']);
		$this->assertSame($text, $this->smarty->fetch('string:{i18n key="literal"}'));
	}

	public static function literalPhpTags(): array {
		return [
			'newline' => ["vor<?\nnach"],
			'xml' => ['<?xml version="1.0"?>'],
			'short echo' => ['<?= "not executed" ?>'],
			'php' => ['<?php throw new Exception("executed"); ?>'],
			'closing tag' => ['Text ?>'],
		];
	}

	#[DataProvider('messages')]
	public function testIcuFormatting(string $locale, string $pattern, array $values, string $expected): void {
		$this->assertSame($expected, \Smarty_I18n::format($locale, $pattern, $values, 'test'));
	}

	public static function messages(): array {
		return [
			'empty' => ['de', '', [], ''],
			'apostrophes' => ['en', "It's '{name}' and ''{name}''", ['name' => 'Bob'], "It's {name} and 'Bob'"],
			'quoted hash outside plural' => ['de', "'# {name}", ['name' => 'Bob'], "'# Bob"],
			'reserved options' => ['de', '{key}/{module}/{locale}', ['key' => 'K', 'module' => 'M', 'locale' => 'L'], 'K/M/L'],
			'nested select' => ['de', '{gender, select, female {{count, plural, one {Sie hat ein Bild} other {Sie hat # Bilder}}} other {Andere: {name}}}',
				['gender' => 'female', 'count' => 2, 'name' => '<Anna>'], 'Sie hat 2 Bilder'],
			'plural offset' => ['en', '{n, plural, offset:1 =0 {Nobody} one {You and one other} other {You and # others}}', ['n' => 4], 'You and 3 others'],
			'ordinal' => ['en', '{n, selectordinal, one {#st} two {#nd} few {#rd} other {#th}}', ['n' => 22], '22nd'],
			'russian plural' => ['ru', '{n, plural, one {one} few {few} many {many} other {other}}', ['n' => 22], 'few'],
			'number' => ['de', '{n,number,#,##0.00}', ['n' => 1234.5], '1.234,50'],
			'currency' => ['de_DE', '{price, number, ::currency/EUR}', ['price' => 12.33], "12,33\u{00A0}\u{20AC}"],
			'select and escaped insertion' => ['en', '{name, select, Bob {Hi {name}} other {Hello {name}}}', ['name' => '<Bob>'], 'Hello &lt;Bob&gt;'],
			'choice' => ['en', '{n, choice, 0#none|1#one|1<{n,number} things}', ['n' => 3], '3 things'],
			'quoted plural hash' => ['en', "{n, plural, other {'#' {name} #}}", ['n' => 2, 'name' => '<B>'], '# &lt;B&gt; 2'],
		];
	}

	#[DataProvider('invalidMessages')]
	public function testInvalidMessagesFailExplicitly(string $pattern, array $values, string $message): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage($message);
		\Smarty_I18n::format('de', $pattern, $values, 'invalid');
	}

	public static function invalidMessages(): array {
		return [
			'pattern' => ['{n, plural, one {x}', ['n' => 1], 'i18n invalid (de)'],
			'missing' => ['{name}', [], 'Platzhalter name fehlt'],
			'wrong type' => ['{name}', ['name' => []], 'muss Text oder eine Zahl sein'],
			'numeric string' => ['{n, plural, other {#}}', ['n' => '2'], 'muss numerisch sein'],
			'reused numeric argument' => ['{n, number} {n}', ['n' => 'wrong'], 'muss numerisch sein'],
		];
	}

	#[DataProvider('invalidTags')]
	public function testInvalidTagsFailExplicitly(string $tag, string $message): void {
		$plugin = new \Smarty_I18n('de', static fn () => '{name}');
		$this->smarty->registerPlugin(\Smarty::PLUGIN_COMPILER, 'i18n', [$plugin, 'compile']);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage($message);
		$this->smarty->fetch('string:' . $tag);
	}

	public static function invalidTags(): array {
		return [
			'missing key' => ['{i18n}', 'benoetigt key'],
			'dynamic key' => ['{i18n key=$key}', 'feste Zeichenkette'],
			'old parameters' => ['{i18n key="test" name=$name}', 'values-Array'],
			'missing values' => ['{i18n key="test"}', 'Platzhalter benoetigen values'],
		];
	}

	public function testHtmlOutputCacheCanKeepExplicitNocacheValuesDynamic(): void {
		$plugin = new \Smarty_I18n('de', static fn () => 'Hallo {name}');
		$this->smarty->registerPlugin(\Smarty::PLUGIN_COMPILER, 'i18n', [$plugin, 'compile']);
		$this->smarty->caching = \Smarty::CACHING_LIFETIME_CURRENT;
		$template = 'string:{nocache}{i18n key="hello" values=["name" => $name]}{/nocache}';
		$this->smarty->assign('name', 'Anna');
		$this->assertSame('Hallo Anna', $this->smarty->fetch($template));
		$this->smarty->assign('name', '<Bob>');
		$this->assertSame('Hallo &lt;Bob&gt;', $this->smarty->fetch($template));
	}
}
