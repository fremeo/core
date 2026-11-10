<?php
namespace fremeo\core\tests;

use fremeo\core\Builder;
use fremeo\core\Container;
use phploader\CData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class BuilderTest extends TestCase {
	private string $tmp;
	private CData $db;
	private Builder $builder;

	protected function setUp(): void {
		$this->tmp = sys_get_temp_dir() . '/fremeo_builder_' . bin2hex(random_bytes(8));
		mkdir($this->tmp);
		$this->db = new CData(['DB' => ['FILENAME' => $this->tmp . '/data.db', 'FILENAME_C' => $this->tmp . '/cache.db']]);
		$this->db->registerPattern(include __DIR__ . '/../system/default/default.pattern.php');
		$this->builder = new Builder($this->db);
	}

	protected function tearDown(): void {
		unset($this->builder, $this->db);
		gc_collect_cycles();
		(new Filesystem())->remove($this->tmp);
	}

	private function nodes(): array {
		return [['kind' => 'container', 'id' => 'row-a', 'layout' => 'two-columns', 'children' => [
			['kind' => 'component', 'id' => 'menu-a', 'module' => 'fremeo/frontend', 'type' => 'navigation',
				'source' => ['NAVIGATION', 'D', 'main'], 'options' => ['variant' => 'vertical']],
		]]];
	}

	public function testScopeInheritanceUsesPublishedActiveLevelsAndKeepsEmptyOverrides(): void {
		$directory = $this->tmp . '/system/template/components/example';
		(new Filesystem())->mkdir($directory);
		file_put_contents($directory . '/component.json', '{"type":"example","defaults":{"text":""},"settings":{"text":{"type":"string","label":"Text"}}}');
		file_put_contents($directory . '/example.tpl', '{$options.text|escape}');
		$providers = ['test/provider' => ['Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $this->tmp]]];
		$save = function (string $scope, string $text, bool $active = true, bool $publish = true) use ($providers): void {
			$target = ['module' => 'test/provider', 'page' => 'frontend__page', 'region' => 'content',
				'language' => 'DE', 'scope' => $scope, 'branch' => 'frontend'];
			if ($scope === 'individual') $target['entity'] = 'kontakt';
			$this->builder->saveDrafts([['target' => $target, 'active' => $active, 'nodes' => $text === '' ? [] : [
				['id' => 'text-a', 'kind' => 'component', 'module' => 'test/provider', 'type' => 'example', 'options' => ['text' => $text]],
			]]], $providers);
			if ($publish) $this->builder->publish($this->builder->placeId($target['module'], $target['page'], $target['region'], $target['entity'] ?? '', $scope), 'DE');
		};
		$smarty = new \Smarty();
		$smarty->setCompileDir($this->tmp);
		$smarty->addPluginsDir(__DIR__ . '/../system/core/smarty_plugin');
		$C = new Container();
		$C->set('Builder', $this->builder);
		$render = function (?string $entity, string $page = 'frontend__page', string $module = 'test/provider') use ($smarty, $C, $providers): string {
			$smarty->assign('D', ['C' => $C, 'MODULE' => ['D' => $providers], 'R' => [
				'ModuleId' => $module, 'Page' => $page, 'Frontend' => ['language' => 'DE'],
				...($entity !== null ? ['Id' => $entity] : []),
			]]);
			return $smarty->fetch('string:{builder name="content"}');
		};
		try {
			$save('global', 'Global');
			$this->assertStringContainsString('Global', $render('kontakt'));
			$save('page', 'Seitentyp');
			$this->assertStringContainsString('Seitentyp', $render('kontakt'));
			$this->assertStringNotContainsString('Global', $render('kontakt'));
			$save('individual', 'Kontakt');
			$this->assertStringContainsString('Kontakt', $render('kontakt'));
			$this->assertStringContainsString('Seitentyp', $render('start'));
			$this->assertStringContainsString('Kontakt', $render('kontakt', 'frontend__page', 'test/extension'));
			$this->assertStringContainsString('Global', $render('kontakt', 'frontend__gallery'));
			$save('individual', 'Kontakt', false, false);
			$this->assertStringContainsString('Kontakt', $render('kontakt'), 'An unpublished deactivation must not change public inheritance.');
			$this->builder->publish($this->builder->placeId('test/provider', 'frontend__page', 'content', 'kontakt'), 'DE');
			$this->assertStringContainsString('Seitentyp', $render('kontakt'));
			$save('page', 'Seitentyp', false);
			$this->assertStringContainsString('Global', $render('kontakt'));
			$save('global', 'Global', false);
			$this->assertSame('', $render('kontakt'));
			$save('global', 'Global');
			$save('individual', '');
			$this->assertStringNotContainsString('Global', $render('kontakt'), 'An active empty override stops inheritance.');
			$this->assertStringContainsString('Global', $render(null));
			$this->builder->saveDrafts([['target' => ['module' => 'test/provider', 'page' => 'frontend__page',
				'region' => 'content', 'language' => 'DE', 'scope' => 'individual', 'entity' => 'kontakt'],
				'nodes' => [], 'active' => false, 'deleted' => true]], $providers);
			$this->assertSame('', $render('kontakt'), 'A pending deletion must preserve the published empty override.');
			$this->builder->publish($this->builder->placeId('test/provider', 'frontend__page', 'content', 'kontakt'), 'DE');
			$this->assertStringContainsString('Global', $render('kontakt'), 'Publishing the deletion must restore inheritance.');
			$save('individual', 'Kontakt');
			file_put_contents($directory . '/example.php', '<?php return static fn() => ["data" => static function (array $options, array $data): array { if ($options["text"] === "Kontakt") throw new RuntimeException("Scope rendering failure"); return $data; }];');
			$this->assertStringContainsString('Global', $render('kontakt'), 'A failed level must fall back immediately after its automatic deactivation.');
			(new Filesystem())->remove($directory . '/example.php');
			$this->builder->enablePreview('DE');
			$this->assertSame(2, substr_count($render(null), 'data-builder-scope='));
			$preview = $render('0');
			$this->assertSame(3, substr_count($preview, 'data-builder-scope='));
			$this->assertStringContainsString('&quot;entity&quot;:&quot;0&quot;', $preview);
		} finally {
			$smarty->clearAllAssign();
			unset($C['Builder']);
		}
	}

	public function testScopeDeletionIsDraftOnlyUntilPublicationAndRemovesTheWholeStoredTree(): void {
		$target = ['module' => 'test/provider', 'page' => 'frontend__page', 'region' => 'content',
			'language' => 'DE', 'scope' => 'page', 'branch' => 'frontend'];
		$id = $this->builder->placeId($target['module'], $target['page'], $target['region']);
		$nodes = $this->nodes();
		$nodes[0]['children'][0]['translations'] = [
			'DE' => ['active' => true, 'options' => ['text' => 'Deutsch']],
			'EN' => ['active' => true, 'options' => ['text' => 'English']],
		];
		$this->builder->saveDrafts([['target' => $target, 'nodes' => $nodes]]);
		$this->builder->publish($id, 'DE');
		$revisions = $this->builder->saveDrafts([['target' => $target, 'nodes' => [], 'active' => false, 'deleted' => true]]);
		$this->assertSame($nodes, $this->builder->getRegion('test/provider', 'frontend__page', 'content', 'DE')['nodes']);
		$this->builder->enablePreview('DE');
		$preview = $this->builder->getRegion('test/provider', 'frontend__page', 'content', 'DE');
		$this->assertTrue($preview['deleted']);
		$this->assertFalse($preview['active']);
		$this->assertSame($revisions[0], $preview['revision']);
		try {
			$this->builder->publishDrafts([['id' => $id, 'revision' => $revisions[0]], ['id' => $id, 'revision' => $revisions[0]]]);
			$this->fail('Duplicate deletion accepted.');
		} catch (\RuntimeException $error) {
			$this->assertStringContainsString('doppelte', $error->getMessage());
		}
		$this->builder->publishDrafts([['id' => $id, 'revision' => $revisions[0]]]);
		$filter = ['BUILDER' => ['W' => [0 => ['ID' => $id]], 'LANGUAGE' => []]];
		$this->db->get_object($stored, $filter);
		$this->assertEmpty($stored['BUILDER']['D'][$id] ?? []);
		$this->assertNull($this->builder->draftRevision($id));
		$this->assertSame([], $this->builder->getRegion('test/provider', 'frontend__page', 'content', 'DE'));
	}

	public function testSharedAppearanceValidatesTypesClassesDeclarationsAndExactViewports(): void {
		$this->assertSame(['XS', 'SM', 'MD', 'LG'], Builder::appearance()['values']['Viewports']);
		$appearance = Builder::appearance(['CustomCssClass' => 'my-panel extra', 'CustomCss' => 'color: red; padding: 2px;', 'Active' => false, 'Viewports' => ['MD']]);
		$this->assertSame('builder-element my-panel extra d-none d-sm-none d-md-block d-lg-none', $appearance['classes']);
		foreach ([
			null, ['Unknown' => 1], ['Active' => 0], ['CustomCssClass' => 'bad"class'],
			['CustomCss' => 'body { color:red }'], ['CustomCss' => '</style><script>'],
			['CustomCss' => 'background:expression(alert(1))'],
			['Viewports' => ['XXL']], ['Viewports' => ['MD', 'MD']], ['Viewports' => [[]]],
		] as $invalid) {
			try {
				Builder::appearance($invalid);
				$this->fail('Invalid shared settings accepted.');
			} catch (\RuntimeException $error) {
				$this->assertNotSame('', $error->getMessage());
			}
		}
	}

	public function testSharedLayoutDraftPublicationAndGlobalRegionDeactivation(): void {
		$before = $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content');
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'EN', $this->nodes());
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'DE'));
		$this->builder->publish($before, 'EN');
		$this->assertSame('DE', $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'DE')['language']);
		$this->assertSame($this->nodes(), $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'FR')['nodes']);
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'EN', []);
		$this->assertSame($this->nodes(), $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN')['nodes']);
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'DE', []);
		$this->builder->publish($before, 'DE');
		$after = $this->builder->placeId('fremeo/page', 'frontend__page', 'after_content');
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'after_content', 'DE', []);
		$this->builder->publish($after, 'DE');
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'DE')['nodes']);
		$this->builder->deactivate($before, 'DE', 'Testkonflikt');
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'DE'));
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN'));
		$this->assertSame('DE', $this->builder->getRegion('fremeo/page', 'frontend__page', 'after_content', 'DE')['language']);
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'after_content', 'EN')['nodes']);
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'DE', $this->nodes());
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'DE'));
	}

	public function testPageEntityAndRegionProduceStableIdsIndependentOfModule(): void {
		$a = $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content', 'a/b');
		$this->assertSame($a, $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content', 'a/b'));
		foreach ([
			['fremeo/page', 'frontend__page', 'before_content', 'a-b'],
			['fremeo/page', 'frontend__other', 'before_content', 'a/b'],
			['fremeo/page', 'frontend__page', 'after_content', 'a/b'],
		] as $identity) $this->assertNotSame($a, $this->builder->placeId(...$identity));
		$this->assertSame($a, $this->builder->placeId('fremeo/gallery', 'frontend__page', 'before_content', 'a/b'));
		$this->assertSame(
			$this->builder->placeId('fremeo/page', 'frontend__page', 'before_content', '', 'global'),
			$this->builder->placeId('fremeo/gallery', 'frontend__gallery', 'before_content', '', 'global')
		);
		$this->assertNotSame($a, $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content', 'a/b', 'individual', 'admin'));
		$this->assertNotSame($this->builder->placeId('fremeo/page', 'frontend__page', 'before_content', '0'), $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content'));
	}

	public function testInvalidPublishedDataIsPersistentlyDeactivatedWithoutDeletingDraft(): void {
		$id = $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content');
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'EN', []);
		$update['BUILDER']['D'][$id] = ['Active' => 1, 'PublishedJson' => '{broken'];
		$this->db->set_object($update);
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN'));
		$f['BUILDER']['LANGUAGE'] = [];
		$this->db->get_object($data, $f);
		$this->assertEquals(0, $data['BUILDER']['D'][$id]['Active']);
		$this->assertSame([], json_decode($data['BUILDER']['D'][$id]['DraftJson'], true)['nodes']);
	}

	public function testDuplicateInstancesCannotBeSaved(): void {
		$this->expectException(\RuntimeException::class);
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'EN', array_merge($this->nodes(), $this->nodes()));
	}

	public function testCommonLayoutAndInstanceTranslationsPersistWithoutLeakingDrafts(): void {
		$fs = new Filesystem();
		$fs->mkdir($this->tmp . '/system/template/components/text');
		$fs->dumpFile($this->tmp . '/system/template/components/text/component.json', '{"type":"text","defaults":{"text":"Default"},"settings":{"text":{"type":"string","label":"Text","translatable":true}}}');
		$fs->dumpFile($this->tmp . '/system/template/components/text/text.tpl', '{$options.text|escape}');
		$providers = [
			'test/provider' => ['Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $this->tmp]],
			'fremeo/frontend' => ['Active' => 1, 'INFO' => ['Type' => 'template', 'ModulDir' => dirname(__DIR__, 2) . '/frontend']],
		];
		$target = ['module' => 'test/provider', 'page' => 'frontend__page', 'region' => 'content', 'entity' => '', 'language' => 'EN'];
		$id = $this->builder->placeId('test/provider', 'frontend__page', 'content');
		$nodes = [
			['kind' => 'component', 'id' => 'menu-a', 'module' => 'fremeo/frontend', 'type' => 'navigation',
				'data' => ['Active' => 1, 'Tree' => [['Title' => 'Shared menu', 'Url' => 'menu', 'Children' => []]]]],
			['kind' => 'component', 'id' => 'text-a', 'module' => 'test/provider', 'type' => 'text', 'options' => [],
				'translations' => [
					'EN' => ['active' => true, 'options' => ['text' => 'English']],
					'DE' => ['active' => true], 'FR' => ['active' => false],
					'ES' => ['active' => true, 'options' => ['text' => '']],
				]],
			['kind' => 'component', 'id' => 'text-b', 'module' => 'test/provider', 'type' => 'text', 'options' => [],
				'translations' => ['FR' => ['active' => true, 'options' => ['text' => 'Francais']]]],
		];
		$this->builder->saveDrafts([['target' => $target, 'nodes' => $nodes]], $providers);
		$this->assertSame([], $this->builder->getRegion('test/provider', 'frontend__page', 'content', 'DE'));
		$this->builder->publish($id, 'EN');
		$filter = ['BUILDER' => ['LANGUAGE' => []]];
		$this->db->get_object($stored, $filter);
		$row = $stored['BUILDER']['D'][$id];
		$common = json_decode($row['PublishedJson'], true);
		$this->assertSame(2, $common['version']);
		$this->assertTrue($common['active']);
		$this->assertArrayNotHasKey('translations', $common['nodes'][1]);
		$this->assertArrayNotHasKey('text', $common['nodes'][1]['options']);
		$this->assertArrayNotHasKey('menu-a', json_decode($row['LANGUAGE']['D']['EN']['PublishedJson'], true)['components']);
		$this->assertSame($nodes[1]['translations'], $this->builder->getRegion('test/provider', 'frontend__page', 'content', 'DE')['nodes'][1]['translations']);

		$C = new Container();
		$C->set('Builder', $this->builder);
		$smarty = new \Smarty();
		$smarty->setCompileDir($this->tmp);
		$smarty->addPluginsDir(__DIR__ . '/../system/core/smarty_plugin');
		$D = ['C' => $C, 'MODULE' => ['D' => $providers], 'R' => ['ModuleId' => 'test/provider', 'Page' => 'frontend__page']];
		$render = function (string $language) use ($smarty, $D): string {
			$next = clone $smarty;
			$D['R']['Frontend']['language'] = $language;
			$next->assign('D', $D);
			$html = $next->fetch('string:{builder name="content"}');
			$next->clearAllAssign();
			return $html;
		};
		try {
			foreach (['EN', 'DE', 'FR', 'ES', 'IT'] as $language) {
				$html = $render($language);
				$this->assertStringContainsString('Shared menu', $html, $language);
				$this->assertSame(in_array($language, ['EN', 'DE'], true), str_contains($html, 'English'), $language);
				$this->assertSame($language === 'FR', str_contains($html, 'Francais'), $language);
				$this->assertStringNotContainsString('data-builder-config', $html);
			}
			$nodes[1]['translations']['DE']['options'] = ['text' => 'Deutsch'];
			$nodes[0]['general'] = ['Active' => false];
			$this->builder->saveDrafts([['target' => $target, 'nodes' => $nodes, 'active' => false]], $providers);
			$this->assertStringContainsString('English', $render('DE'));
			$this->assertStringContainsString('Shared menu', $render('DE'));
			$this->builder->enablePreview('DE');
			$this->assertFalse($this->builder->getRegion('test/provider', 'frontend__page', 'content', 'DE')['active']);
			$this->assertStringContainsString('Deutsch', $render('DE'));
			$this->assertStringContainsString('data-builder-active="0"', $render('DE'));
			$this->builder->publish($id, 'DE');
			$public = new Builder($this->db);
			$this->assertSame([], $public->getRegion('test/provider', 'frontend__page', 'content', 'EN'));
			$this->builder->saveDrafts([['target' => $target, 'nodes' => [$nodes[0]], 'active' => true]], $providers);
			$this->assertSame([], $public->getRegion('test/provider', 'frontend__page', 'content', 'DE'));
			$this->builder->publish($id, 'EN');
			$this->assertCount(1, $public->getRegion('test/provider', 'frontend__page', 'content', 'FR')['nodes']);
			$this->db->get_object($stored, $filter);
			$this->assertSame([], json_decode($stored['BUILDER']['D'][$id]['LANGUAGE']['D']['DE']['PublishedJson'], true)['components']);
		} finally {
			$smarty->clearAllAssign();
			unset($C['Builder']);
		}
	}

	public function testLegacyLayoutsArePreservedUntilSafeConversionAndDivergenceBlocksWholeBatch(): void {
		$id = $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content');
		$old = json_encode($this->nodes(), JSON_THROW_ON_ERROR);
		$update['BUILDER']['D'][$id]['LANGUAGE']['D'] = [
			'EN' => ['Active' => 1, 'DraftJson' => $old, 'PublishedJson' => $old],
			'DE' => ['Active' => 1, 'DraftJson' => $old, 'PublishedJson' => $old],
		];
		$this->db->set_object($update);
		$this->assertTrue($this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'DE')['legacy']);
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'DE', []);
		$this->assertSame($this->nodes(), $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN')['nodes']);
		$this->builder->publish($id, 'DE');
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'FR')['nodes']);
		$conflict = $this->builder->placeId('fremeo/page', 'frontend__page', 'after_content');
		$update = ['BUILDER' => ['D' => [$conflict => ['LANGUAGE' => ['D' => [
			'EN' => ['Active' => 1, 'DraftJson' => $old, 'PublishedJson' => $old],
			'DE' => ['Active' => 1, 'DraftJson' => '[]', 'PublishedJson' => '[]'],
		]]]]]];
		$this->db->set_object($update);
		$target = ['module' => 'fremeo/page', 'page' => 'frontend__page', 'region' => 'before_content', 'entity' => '', 'language' => 'DE'];
		try {
			$this->builder->saveDrafts([
				['target' => $target, 'nodes' => $this->nodes()],
				['target' => array_replace($target, ['region' => 'after_content']), 'nodes' => []],
			]);
			$this->fail('Divergent old layouts silently converted.');
		} catch (\RuntimeException $error) {
			$this->assertStringContainsString('Abweichende alte Sprachlayouts', $error->getMessage());
		}
		$this->builder->enablePreview('EN');
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN')['nodes']);
		$this->assertTrue($this->builder->getRegion('fremeo/page', 'frontend__page', 'after_content', 'EN')['legacy']);
	}

	public function testInvalidLanguageSnapshotCannotBePublishedOrDestroyValidPublication(): void {
		$id = $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content');
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'EN', $this->nodes());
		$this->builder->publish($id, 'EN');
		$update['BUILDER']['D'][$id]['LANGUAGE']['D']['DE']['DraftJson'] = json_encode([
			'version' => 2, 'components' => ['menu-a' => ['active' => 'not a boolean']],
		], JSON_THROW_ON_ERROR);
		$this->db->set_object($update);
		try {
			$this->builder->publish($id, 'DE');
			$this->fail('Invalid language snapshot published.');
		} catch (\RuntimeException $error) {
			$this->assertStringContainsString('Sprachdaten', $error->getMessage());
		}
		$this->assertSame($this->nodes(), $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'DE')['nodes']);
	}

	public function testPublicationValidatesWholeBatchRevisionsAndProvidersBeforeWriting(): void {
		$target = ['module' => 'fremeo/page', 'page' => 'frontend__page', 'region' => 'before_content', 'entity' => '', 'language' => 'EN'];
		$id = $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content');
		$after = $this->builder->placeId('fremeo/page', 'frontend__page', 'after_content');
		$this->builder->saveDrafts([['target' => $target, 'nodes' => $this->nodes()], [
			'target' => array_replace($target, ['region' => 'after_content']), 'nodes' => [],
		]]);
		$entries = [['id' => $id, 'revision' => $this->builder->draftRevision($id)], ['id' => $after, 'revision' => $this->builder->draftRevision($after)]];
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'after_content', 'EN', $this->nodes());
		try {
			$this->builder->publishDrafts($entries);
			$this->fail('Stale batch published.');
		} catch (\RuntimeException $error) {
			$this->assertStringContainsString('zwischenzeitlich', $error->getMessage());
		}
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN'));
		$entries[1]['revision'] = $this->builder->draftRevision($after);
		try {
			$this->builder->publishDrafts($entries, []);
			$this->fail('Unavailable provider published.');
		} catch (\RuntimeException $error) {
			$this->assertStringContainsString('nicht verfuegbar', $error->getMessage());
		}
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN'));
		$providers = ['fremeo/frontend' => ['Active' => 1, 'INFO' => ['Type' => 'template', 'ModulDir' => dirname(__DIR__, 2) . '/frontend']]];
		$this->builder->publishDrafts($entries, $providers);
		$this->assertSame($this->nodes(), $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'DE')['nodes']);
		$this->assertSame($this->nodes(), $this->builder->getRegion('fremeo/page', 'frontend__page', 'after_content', 'FR')['nodes']);
		$this->assertSame($entries[0]['revision'], $this->builder->draftRevision($id));
		$update['BUILDER']['D'][$id]['LANGUAGE']['D']['DE']['DraftJson'] = '{"version":2,"components":{}}';
		$this->db->set_object($update);
		$this->assertNotSame($entries[0]['revision'], $this->builder->draftRevision($id));
	}

	public function testCoreTextPersistsGermanContentSeparatelyAndPublishesOnlySavedDrafts(): void {
		$providers = ['fremeo/core' => ['Active' => 1, 'INFO' => ['Type' => 'core', 'ModulDir' => dirname(__DIR__)]]];
		$target = ['module' => 'fremeo/core', 'page' => 'frontend__page', 'region' => 'content', 'entity' => '', 'language' => 'DE'];
		$id = $this->builder->placeId($target['module'], $target['page'], $target['region']);
		$nodes = [['kind' => 'component', 'id' => 'text-a', 'module' => 'fremeo/core', 'type' => 'text',
			'options' => [], 'data' => [], 'translations' => ['DE' => ['active' => true, 'options' => ['text' => "Hallo\nWelt"]]]]];
		$revisions = $this->builder->saveDrafts([['target' => $target, 'nodes' => $nodes]], $providers);
		$this->assertSame([], $this->builder->getRegion($target['module'], $target['page'], $target['region'], 'DE'));
		$filter = ['BUILDER' => ['W' => [0 => ['ID' => $id]], 'LANGUAGE' => []]];
		$this->db->get_object($stored, $filter);
		$common = json_decode($stored['BUILDER']['D'][$id]['DraftJson'], true, 512, JSON_THROW_ON_ERROR);
		$this->assertArrayNotHasKey('text', $common['nodes'][0]['options']);
		$german = json_decode($stored['BUILDER']['D'][$id]['LANGUAGE']['D']['DE']['DraftJson'], true, 512, JSON_THROW_ON_ERROR);
		$this->assertSame("Hallo\nWelt", $german['components']['text-a']['options']['text']);
		$this->builder->publishDrafts([['id' => $id, 'revision' => $revisions[0]]], $providers);
		$this->assertSame($nodes, $this->builder->getRegion($target['module'], $target['page'], $target['region'], 'DE')['nodes']);
		$nodes[0]['translations']['DE']['options']['text'] = '';
		$this->builder->saveDrafts([['target' => $target, 'nodes' => $nodes]], $providers);
		$this->assertSame("Hallo\nWelt", $this->builder->getRegion($target['module'], $target['page'], $target['region'], 'DE')['nodes'][0]['translations']['DE']['options']['text']);
		$this->builder->enablePreview('DE');
		$this->assertSame('', $this->builder->getRegion($target['module'], $target['page'], $target['region'], 'DE')['nodes'][0]['translations']['DE']['options']['text']);
	}

	public function testGridSlotsRejectInvalidPositionsAndAllowStackedChildren(): void {
		foreach ([null, -1, 13, '2', 1.5] as $slot) {
			$nodes = $this->nodes();
			$nodes[0]['children'][0]['slot'] = $slot;
			try {
				$this->builder->validate($nodes);
				$this->fail('Invalid grid slot accepted.');
			} catch (\RuntimeException $error) {
				$this->assertStringContainsString('Grid-Ablageplatz', $error->getMessage());
			}
		}
		$nodes = $this->nodes();
		$child = $nodes[0]['children'][0];
		$child['id'] = 'menu-b';
		$child['slot'] = 0;
		$nodes[0]['children'][] = $child;
		$this->builder->validate($nodes);
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'content', 'DE', $nodes);
		$this->builder->publish($this->builder->placeId('fremeo/page', 'frontend__page', 'content'), 'DE');
		$this->assertSame($nodes, $this->builder->getRegion('fremeo/page', 'frontend__page', 'content', 'DE')['nodes']);
	}

	public function testResponsiveCoreContainerPersistsNestedChildrenAndRejectsOversizedGrids(): void {
		$nodes = $this->nodes();
		unset($nodes[0]['layout']);
		$nodes[0]['module'] = 'fremeo/core';
		$nodes[0]['type'] = 'container';
		$nodes[0]['options'] = ['columns' => '8+4', 'sm' => '6+6', 'lg' => '9+3'];
		$nested = $nodes[0];
		$nested['id'] = 'nested';
		$nested['options'] = ['columns' => '3+6+3'];
		$nested['children'] = [$nodes[0]['children'][0]];
		$nested['children'][0]['id'] = 'menu-b';
		$nested['children'][0]['slot'] = 2;
		$nested['children'][] = array_replace($nested['children'][0], ['id' => 'menu-c']);
		$nodes[0]['children'][] = $nested;
		$id = $this->builder->placeId('fremeo/page', 'frontend__page', 'content');
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'content', 'DE', $nodes);
		$this->builder->publish($id, 'DE');
		$this->assertSame($nodes, $this->builder->getRegion('fremeo/page', 'frontend__page', 'content', 'FR')['nodes']);
		$invalid = $nodes;
		$invalid[0]['children'][1]['options']['columns'] = implode('+', array_fill(0, 13, 1));
		try {
			$this->builder->saveDraft('fremeo/page', 'frontend__page', 'content', 'DE', $invalid);
			$this->fail('Invalid nested grid persisted.');
		} catch (\RuntimeException $error) {
			$this->assertStringContainsString('maximal 12', $error->getMessage());
		}
		$this->builder->enablePreview('DE');
		$this->assertSame($nodes, $this->builder->getRegion('fremeo/page', 'frontend__page', 'content', 'DE')['nodes']);
		$smarty = new \Smarty();
		$smarty->setCompileDir($this->tmp);
		$smarty->addPluginsDir(__DIR__ . '/../system/core/smarty_plugin');
		$C = new Container();
		$C->set('Builder', $this->builder);
		$smarty->assign('D', ['C' => $C, 'R' => ['ModuleId' => 'fremeo/page', 'Page' => 'frontend__page'], 'MODULE' => ['D' => [
			'fremeo/core' => ['Active' => 1, 'INFO' => ['Type' => 'core', 'ModulDir' => dirname(__DIR__)]],
			'fremeo/frontend' => ['Active' => 1, 'INFO' => ['Type' => 'template', 'ModulDir' => dirname(__DIR__, 2) . '/frontend'],
				'NAVIGATION' => ['D' => ['main' => ['Active' => 1, 'Tree' => [['Title' => 'Nested menu', 'Url' => 'menu', 'Children' => []]]]]]],
		]]]);
		try {
			$html = $smarty->fetch('string:{builder name="content"}');
			$this->assertSame(2, substr_count($html, 'component component-container'));
			$this->assertSame(3, substr_count($html, 'href="menu"'));
			$dom = new \DOMDocument();
			$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
			$xpath = new \DOMXPath($dom);
			$this->assertSame(1, $xpath->query('//*[@data-builder-node="menu-b"]/following-sibling::*[@data-builder-node="menu-c"]')->length);
			$this->assertStringContainsString('col-12 col-sm-6 col-lg-9', $html);
			$this->assertStringContainsString('col-12 col-md-3', $html);
			$this->assertStringContainsString('data-builder-node="menu-b"', $html);
			$this->assertStringNotContainsString('BuilderComponentEditors', $html);
		} finally {
			$smarty->clearAllAssign();
			unset($C['Builder']);
		}
	}

	public function testLegacyTranslatedInstanceRequiresExplicitMigrationWithoutLosingLanguageData(): void {
		$fs = new Filesystem();
		$fs->mkdir($this->tmp . '/system/template/components/text');
		$fs->dumpFile($this->tmp . '/system/template/components/text/component.json', '{"type":"text","defaults":{"text":""},"settings":{"text":{"type":"string","label":"Text","translatable":true}}}');
		$fs->dumpFile($this->tmp . '/system/template/components/text/text.tpl', '{$options.text|escape}');
		$nodes = [['id' => 'text-a', 'kind' => 'component', 'module' => 'test/provider', 'type' => 'text', 'options' => ['text' => 'Old']]];
		$id = $this->builder->placeId('test/provider', 'frontend__page', 'content');
		$old = json_encode($nodes, JSON_THROW_ON_ERROR);
		$update['BUILDER']['D'][$id]['LANGUAGE']['D']['EN'] = ['Active' => 1, 'DraftJson' => $old, 'PublishedJson' => $old];
		$this->db->set_object($update);
		try {
			$this->builder->saveDrafts([[
				'target' => ['module' => 'test/provider', 'page' => 'frontend__page', 'region' => 'content', 'entity' => '', 'language' => 'DE'],
				'nodes' => $nodes,
			]], ['test/provider' => ['Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $this->tmp]]]);
			$this->fail('Old translated content silently reassigned.');
		} catch (\RuntimeException $error) {
			$this->assertStringContainsString('Sprachwerte', $error->getMessage());
		}
		$this->builder->enablePreview('EN');
		$region = $this->builder->getRegion('test/provider', 'frontend__page', 'content', 'EN');
		$this->assertTrue($region['legacy']);
		$this->assertSame($nodes, $region['nodes']);
	}

	public function testNeutralComponentsHaveNoLanguageRestriction(): void {
		$id = $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content');
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'DE', $this->nodes());
		$this->builder->publish($id, 'DE');
		$this->assertSame($this->nodes(), $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'FR')['nodes']);
		$this->assertNotEmpty($this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'DE'));
	}

	public function testPreviewReadsDraftWithoutPublishingOrReactivating(): void {
		$id = $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content');
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'EN', []);
		$this->builder->publish($id, 'EN');
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'EN', $this->nodes());
		$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN')['nodes']);
		$this->builder->deactivate($id, 'EN', 'Test');
		$this->builder->enablePreview('EN');
		$this->assertSame($this->nodes(), $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN')['nodes']);
		$public = new Builder($this->db);
		$this->assertSame([], $public->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN'));
	}

	public function testEmptyPreviewRegionsHaveStableDiscoverableDropTargets(): void {
		$smarty = new \Smarty();
		$smarty->setCompileDir($this->tmp);
		$smarty->addPluginsDir(__DIR__ . '/../system/core/smarty_plugin');
		$C = new Container();
		$C->set('Builder', $this->builder);
		$smarty->assign('D', ['C' => $C, 'R' => ['ModuleId' => 'fremeo/page', 'Page' => 'frontend__page']]);
		$source = 'string:{builder name="before_content"}{builder name="after_content"}';
		try {
			$this->assertSame('', $smarty->fetch($source));
			$this->builder->enablePreview('DE');
			$html = $smarty->fetch($source);
			$this->assertStringContainsString('data-builder-region="before_content"', $html);
			$this->assertStringContainsString('data-builder-region="after_content"', $html);
			$this->assertSame(4, substr_count($html, 'builder-dropzone'));
			$this->assertStringContainsString('2px dashed #198754', $html);
			preg_match('/data-builder-target="([^"]+)" data-builder-token="([^"]+)"/', $html, $context);
			$targetJson = html_entity_decode($context[1], ENT_QUOTES, 'UTF-8');
			$this->assertSame([
				'module' => 'fremeo/page', 'page' => 'frontend__page', 'region' => 'before_content', 'language' => 'DE', 'scope' => 'page', 'branch' => 'frontend',
			], json_decode($targetJson, true, 512, JSON_THROW_ON_ERROR));
			$this->assertSame(hash_hmac('sha256', $targetJson, $_SESSION['BuilderContextKey']), $context[2]);
			preg_match_all('/id="([^"]+)"/', $html, $ids);
			$this->assertCount(4, array_unique($ids[1]));
		} finally {
			$smarty->clearAllAssign();
			unset($C['Builder']);
		}

	}

	public function testPreviewErrorsDoNotDeactivatePublishedLanguage(): void {
		$id = $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content');
		$update['BUILDER']['D'][$id]['LANGUAGE']['D']['EN'] = ['Active' => 1, 'PublishedJson' => '{broken'];
		$this->db->set_object($update);
		$this->builder->enablePreview('EN');
		try {
			$this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN');
			$this->fail('Invalid JSON must be reported.');
		} catch (\JsonException $error) {
			$this->assertNotSame('', $error->getMessage());
		}
		$f['BUILDER']['LANGUAGE'] = [];
		$this->db->get_object($data, $f);
		$this->assertEquals(1, $data['BUILDER']['D'][$id]['LANGUAGE']['D']['EN']['Active']);
	}

	public function testRealSmartyRendersPublishedComponentsAndReusesCompilation(): void {
		$id = $this->builder->placeId('fremeo/page', 'frontend__page', 'before_content');
		$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'EN', $this->nodes());
		$this->builder->publish($id, 'EN');
		$smarty = new \Smarty();
		$smarty->setCompileDir($this->tmp);
		$smarty->addPluginsDir(__DIR__ . '/../system/core/smarty_plugin');
		$policy = new \Smarty_Security($smarty);
		$policy->secure_dir = [dirname(__DIR__, 2)];
		$smarty->enableSecurity($policy);
		$C = new Container();
		$C->set('Builder', $this->builder);
		$D = ['C' => $C, 'R' => ['ModuleId' => 'fremeo/page', 'Page' => 'frontend__page', 'Frontend' => ['language' => 'DE']],
			'MODULE' => ['D' => ['fremeo/frontend' => [
				'Active' => 1, 'INFO' => ['Type' => 'template', 'ModulDir' => dirname(__DIR__, 2) . '/frontend'],
				'NAVIGATION' => ['D' => ['main' => ['Active' => 1, 'Tree' => [
					['Title' => '<Menu>', 'Url' => 'menu', 'Children' => []],
				]]]],
			], 'fremeo/core' => ['Active' => 1, 'INFO' => ['Type' => 'core', 'ModulDir' => dirname(__DIR__)]]]]];
		try {
			$smarty->assign('D', $D);
			$source = 'string:{builder name="before_content"}{builder name="after_content"}';
			$html = $smarty->fetch($source);
			$this->assertStringContainsString('id="builder-' . $id . '"', $html);
			$this->assertStringContainsString('component component-navigation', $html);
			$this->assertStringContainsString('&lt;Menu&gt;', $html);
			$this->assertStringContainsString('col-12 col-md-6', $html);
			$this->assertStringNotContainsString('dropzone', $html);
			$this->assertStringNotContainsString('data-builder-node', $html);
			$compiled = glob($this->tmp . '/*.php');
			$this->assertNotEmpty($compiled);
			$times = array_map('filemtime', $compiled);
			// Ein neuer Seitenaufruf hat eine neue Smarty-Instanz, aber denselben Compile-Ordner.
			$next = clone $smarty;
			$this->assertSame($html, $next->fetch($source));
			$this->assertSame($compiled, glob($this->tmp . '/*.php'));
			$this->assertSame($times, array_map('filemtime', $compiled));
			$this->builder->enablePreview('EN');
			$preview = clone $smarty;
			$previewHtml = $preview->fetch($source);
			$this->assertStringContainsString('data-builder-node="row-a"', $previewHtml);
			$this->assertStringContainsString('data-builder-node="menu-a"', $previewHtml);
			$this->assertStringContainsString('data-builder-children', $previewHtml);
			$this->assertStringContainsString('data-builder-type="container"', $previewHtml);
			$this->assertStringContainsString('data-builder-render-id="builder-' . $id . '-menu-a"', $previewHtml);
			preg_match_all('/data-builder-config="([^"]+)"/', $previewHtml, $configs);
			$config = [1 => $configs[1][1]];
			$metadata = json_decode(html_entity_decode($config[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
			$this->assertSame(['variant' => 'vertical'], $metadata['options']);
			$this->assertSame(['NAVIGATION', 'D', 'main'], $metadata['source']);
			$this->assertSame('<Menu>', $metadata['data']['Tree'][0]['Title']);
			$nodes = $this->nodes();
			$nodes[0]['general'] = ['CustomCssClass' => 'custom-row', 'CustomCss' => 'padding: 7px;', 'Viewports' => ['MD']];
			$nodes[0]['children'][0]['general'] = ['Active' => false];
			$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'EN', $nodes);
			$this->builder->publish($id, 'EN');
			$hiddenPreview = clone $smarty;
			$hiddenHtml = $hiddenPreview->fetch($source);
			$this->assertStringContainsString('&lt;Menu&gt;', $hiddenHtml);
			$this->assertStringContainsString('custom-row d-none d-sm-none d-md-block d-lg-none', $hiddenHtml);
			$this->assertStringContainsString('style="padding: 7px;"', $hiddenHtml);
			$hiddenPreview->clearAllAssign();
			$preview->clearAllAssign();
			// A later public request does not retain the preview context.
			$this->builder = new Builder($this->db);
			unset($C['Builder']);
			$C->set('Builder', $this->builder);
			$publicHidden = clone $smarty;
			$hiddenHtml = $publicHidden->fetch($source);
			$this->assertStringNotContainsString('&lt;Menu&gt;', $hiddenHtml);
			$this->assertStringContainsString('class="col col-12 col-md-6 d-block', $hiddenHtml);
			$this->assertStringNotContainsString('hidden', $hiddenHtml);
			$publicHidden->clearAllAssign();
			$this->builder->saveDraft('fremeo/page', 'frontend__page', 'before_content', 'EN', $this->nodes());
			$this->builder->publish($id, 'EN');
			$D['MODULE']['D']['fremeo/frontend']['Active'] = 0;
			$smarty->assign('D', $D);
			$this->assertSame('', $smarty->fetch($source));
			$this->assertSame([], $this->builder->getRegion('fremeo/page', 'frontend__page', 'before_content', 'EN'));
		} finally {
			$smarty->clearAllAssign();
			if (isset($next)) $next->clearAllAssign();
			unset($C['Builder']);
		}
	}
}
