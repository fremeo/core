<?php
namespace fremeo\core\tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class BuilderAdminTest extends TestCase {
	public function testAdminOpensHomepageWithoutTargetSelectionOrPreview(): void {
		if (!defined('SCRIPT_NAME')) define('SCRIPT_NAME', '/test/');
		$R = ['BuilderTarget' => ['Module' => 'fremeo/core', 'Page' => 'admin__user.list', 'Language' => 'DE']];
		include __DIR__ . '/../system/admin__builder.php';
		$this->assertSame(SCRIPT_NAME, $R['BuilderView']['PreviewUrl']);
		$tmp = sys_get_temp_dir() . '/builder_editor_' . bin2hex(random_bytes(6));
		mkdir($tmp);
		try {
			$smarty = new \Smarty();
			$smarty->setCompileDir($tmp);
			$policy = new \Smarty_Security($smarty);
			$policy->secure_dir = [dirname(__DIR__)];
			$policy->php_functions = ['md5', 'count', 'explode', 'strpos', 'str_replace', 'trim', 'in_array', 'date', 'isset', 'empty', 'json_decode'];
			$policy->php_modifiers = ['in_array', 'strlen', 'strstr'];
			$smarty->enableSecurity($policy);
			$smarty->assign('D', ['R' => $R, 'C' => ['Security' => new class {
				public function registerSecurityD(string $name): string { return $name; }
			}]]);
			$html = $smarty->fetch(__DIR__ . '/../system/template/admin__builder.tpl');
			$this->assertStringContainsString('title="Builder-Seitenvorschau"', $html);
			$this->assertStringContainsString('src="' . SCRIPT_NAME . '"', $html);
			$this->assertStringContainsString('sandbox="allow-same-origin allow-scripts allow-forms"', $html);
			$this->assertStringNotContainsString('name="R[BuilderTarget]', $html);
			$this->assertStringContainsString('id="builder-edit"', $html);
			$this->assertStringContainsString('class="btn btn-sm btn-primary"', $html);
			$this->assertStringContainsString('id="builder-cancel"', $html);
			$this->assertStringContainsString('id="builder-component-tab"', $html);
			$this->assertStringContainsString('id="builder-general-tab"', $html);
			$this->assertStringNotContainsString('builder-input-textarea', $html);
			$this->assertStringContainsString('id="builder-field-general-CustomCss"', $html);
			$this->assertStringContainsString('id="builder-save" type="button" class="btn btn-sm btn-success" disabled', $html);
			$this->assertStringContainsString('id="builder-publish" type="button" class="btn btn-sm btn-outline-success" disabled hidden', $html);
			$this->assertStringNotContainsString('data-builder-layout=', $html);
			$this->assertStringNotContainsString('>Stapel</button>', $html);
			$this->assertStringContainsString('data-builder-region', $html);
			$this->assertStringNotContainsString('Zur gewuenschten Seite navigieren', $html);
			foreach (['frontend', 'admin'] as $branch) {
				$smarty->assign('D', ['R' => ['Theme' => ['Branch' => $branch], 'Frontend' => ['language' => 'DE']]]);
				$html = $smarty->fetch(__DIR__ . '/../system/template/index__index.tpl');
				if ($branch === 'frontend') {
					$this->assertStringContainsString('data-builder-language="DE"', $html);
				} else {
					$this->assertStringNotContainsString('data-builder-language=', $html);
				}
			}
		} finally {
			(new Filesystem())->remove($tmp);
		}
	}

	public function testLibraryUsesOnlyValidComponentsOfActiveProviders(): void {
		if (!defined('SCRIPT_NAME')) define('SCRIPT_NAME', '/test/');
		$tmp = sys_get_temp_dir() . '/builder_catalog_' . bin2hex(random_bytes(6));
		$fs = new Filesystem();
		$fs->mkdir($tmp . '/system/template/components/example');
		$fs->dumpFile($tmp . '/system/template/components/example/component.json', '{"type":"example","name":"Example"}');
		$fs->dumpFile($tmp . '/system/template/components/example/example.tpl', 'example');
		$fs->mkdir($tmp . '/system/template/components/broken');
		$fs->dumpFile($tmp . '/system/template/components/broken/component.json', '{broken');
		$fs->dumpFile($tmp . '/system/template/components/broken/broken.tpl', '');
		try {
			$provider = ['Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $tmp]];
			$D['MODULE']['D'] = [
				'test/active' => $provider,
				'test/disabled' => array_replace($provider, ['Active' => 0]),
				'test/library' => ['Active' => 1, 'INFO' => ['Type' => 'library', 'ModulDir' => $tmp]],
			];
			$R = [];
			include __DIR__ . '/../system/admin__builder.php';
			$this->assertSame(['test/active' => ['example' => ['Name' => 'Example', 'Defaults' => [], 'Settings' => []]]], $R['BuilderView']['Components']);
			$this->assertCount(1, $R['BuilderView']['Errors']);
			$this->assertStringContainsString('test/active:broken', $R['BuilderView']['Errors'][0]);
		} finally {
			$fs->remove($tmp);
		}
	}

	public function testComponentPreviewRequiresPostSessionAndTokenAndRendersThroughSmarty(): void {
		$tmp = sys_get_temp_dir() . '/builder_render_' . bin2hex(random_bytes(6));
		$fs = new Filesystem();
		$fs->mkdir($tmp . '/system/template/components/example');
		$fs->dumpFile($tmp . '/system/template/components/example/component.json', '{"type":"example","name":"Example","defaults":{"text":"<default>"},"settings":{"text":{"type":"string","label":"Text"}}}');
		$fs->dumpFile($tmp . '/system/template/components/example/example.tpl', '{$options.text|escape} {$component.id|escape} {$data.Name|default:""|escape}');
		$controller = var_export(realpath(__DIR__ . '/../system/admin__builder.php'), true);
		$autoload = var_export(realpath(__DIR__ . '/../../../autoload.php'), true);
		$plugins = var_export(realpath(__DIR__ . '/../system/core/smarty_plugin'), true);
		try {
			foreach (['valid', 'options', 'invalid-options', 'array-json', 'scalar-data', 'general-array', 'general-invalid', 'get', 'guest', 'token', 'missing', 'id'] as $case) {
				$R = ['ACTION' => 'render', 'BuilderToken' => $case === 'token' ? 'wrong' : 'valid-token',
					'BuilderComponent' => ['Module' => 'test/provider', 'Type' => $case === 'missing' ? 'missing' : 'example', 'Id' => $case === 'id' ? '../bad' : 'node-a']];
				if ($case === 'options') {
					$R['BuilderComponent']['OptionsJson'] = '{"text":"<edited>"}';
					$R['BuilderComponent']['DataJson'] = '{"Name":"<data>"}';
				}
				if ($case === 'invalid-options') $R['BuilderComponent']['OptionsJson'] = '{"text":42}';
				if ($case === 'array-json') $R['BuilderComponent']['OptionsJson'] = [];
				if ($case === 'scalar-data') $R['BuilderComponent']['DataJson'] = '"bad"';
				if ($case === 'general-array') $R['BuilderComponent']['GeneralJson'] = [];
				if ($case === 'general-invalid') $R['BuilderComponent']['GeneralJson'] = '{"Active":"yes"}';
				$D = ['SESSION' => $case === 'guest' ? [] : ['UserId' => 'admin'],
					'MODULE' => ['D' => ['test/provider' => ['Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $tmp]]]]];
				$script = '<?php require ' . $autoload . '; define("SCRIPT_NAME","/test/");'
					. 'register_shutdown_function(function(){echo "\n".http_response_code();});'
					. '$_SESSION=["BuilderToken"=>"valid-token"]; $_SERVER["REQUEST_METHOD"]=' . var_export($case === 'get' ? 'GET' : 'POST', true) . ';'
					. '$R=' . var_export($R, true) . '; $D=' . var_export($D, true) . ';'
					. '$smarty=new Smarty(); $smarty->setCompileDir(' . var_export($tmp, true) . '); $smarty->addPluginsDir(' . $plugins . ');'
					. '$C=["Smarty"=>$smarty]; include ' . $controller . ';';
				$fs->dumpFile($tmp . '/render.php', $script);
				$output = [];
				exec('"' . PHP_BINARY . '" "' . $tmp . '/render.php"', $output, $code);
				$this->assertSame(0, $code, $case);
				$result = json_decode($output[0], true, 512, JSON_THROW_ON_ERROR);
				if ($case === 'valid' || $case === 'options') {
					$this->assertStringContainsString($case === 'valid' ? '&lt;default&gt; node-a' : '&lt;edited&gt; node-a &lt;data&gt;', $result['html']);
					$this->assertStringContainsString('class="component component-example"', $result['html']);
				} else {
					$this->assertArrayHasKey('error', $result, $case);
					$this->assertGreaterThanOrEqual(400, (int)end($output), $case);
					$this->assertArrayNotHasKey('html', $result);
				}
			}
		} finally {
			$fs->remove($tmp);
		}
	}

	public function testLocalizedRenderUsesActivationFallbackAndTypedTranslations(): void {
		$tmp = sys_get_temp_dir() . '/builder_translation_' . bin2hex(random_bytes(6));
		$fs = new Filesystem();
		$fs->mkdir($tmp . '/system/template/components/example');
		$fs->dumpFile($tmp . '/system/template/components/example/component.json', '{"type":"example","defaults":{"text":"Default"},"settings":{"text":{"type":"string","label":"Text","translatable":true}}}');
		$fs->dumpFile($tmp . '/system/template/components/example/example.tpl', '{$options.text|escape}');
		try {
			foreach (['fallback', 'empty', 'disabled', 'absent', 'array-json', 'scalar-json', 'language', 'typed-active', 'unknown-field'] as $case) {
				$translations = ['EN' => ['active' => true, 'options' => ['text' => '<English>']], 'DE' => ['active' => true]];
				if ($case === 'empty') $translations['DE']['options'] = ['text' => ''];
				if ($case === 'disabled') $translations['DE']['active'] = false;
				if ($case === 'absent') unset($translations['DE']);
				if ($case === 'typed-active') $translations['DE']['active'] = 1;
				if ($case === 'unknown-field') $translations['DE']['options'] = ['text' => 'x', 'unknown' => 'x'];
				$R = ['ACTION' => 'render', 'BuilderToken' => 'valid-token', 'BuilderComponent' => [
					'Module' => 'test/provider', 'Type' => 'example', 'Id' => 'example-a',
					'Language' => $case === 'language' ? 'de' : 'DE',
					'TranslationsJson' => $case === 'array-json' ? [] : ($case === 'scalar-json' ? '42' : json_encode($translations, JSON_THROW_ON_ERROR)),
				]];
				$D = ['SESSION' => ['UserId' => 'admin'], 'MODULE' => ['D' => [
					'test/provider' => ['Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $tmp]],
				]]];
				$script = '<?php require ' . var_export(realpath(__DIR__ . '/../../../autoload.php'), true) . ';define("SCRIPT_NAME","/test/");'
					. '$_SESSION=["BuilderToken"=>"valid-token"];$_SERVER["REQUEST_METHOD"]="POST";'
					. '$R=' . var_export($R, true) . ';$D=' . var_export($D, true) . ';'
					. '$smarty=new Smarty();$smarty->setCompileDir(' . var_export($tmp, true) . ');'
					. '$smarty->addPluginsDir(' . var_export(realpath(__DIR__ . '/../system/core/smarty_plugin'), true) . ');'
					. '$C=["Smarty"=>$smarty];include ' . var_export(realpath(__DIR__ . '/../system/admin__builder.php'), true) . ';';
				$fs->dumpFile($tmp . '/render.php', $script);
				$output = [];
				exec('"' . PHP_BINARY . '" "' . $tmp . '/render.php"', $output, $code);
				$this->assertSame(0, $code, $case);
				$response = json_decode($output[0], true, 512, JSON_THROW_ON_ERROR);
				if (in_array($case, ['fallback', 'empty', 'disabled', 'absent'], true)) {
					$this->assertSame(in_array($case, ['fallback', 'empty'], true), $response['visible'], $case);
					if ($case === 'fallback') $this->assertStringContainsString('&lt;English&gt;', $response['html']);
					if ($case === 'empty') $this->assertStringNotContainsString('English', $response['html']);
				} else {
					$this->assertArrayHasKey('error', $response, $case);
					$this->assertArrayNotHasKey('html', $response, $case);
				}
			}
		} finally {
			$fs->remove($tmp);
		}
	}

	public function testCoreContainerCatalogEditorScriptAndServerGridValidation(): void {
		$tmp = sys_get_temp_dir() . '/builder_container_' . bin2hex(random_bytes(6));
		$fs = new Filesystem();
		$fs->mkdir($tmp);
		try {
			foreach (['catalog', 'editor', 'guest-editor', 'traversal', 'render', 'invalid-grid'] as $case) {
				$R = ['ACTION' => in_array($case, ['render', 'invalid-grid'], true) ? 'render' : 'editor',
					'BuilderToken' => 'valid-token', 'BuilderEditorModule' => 'fremeo/core',
					'BuilderEditorType' => $case === 'traversal' ? '../container' : 'container',
					'BuilderComponent' => ['Module' => 'fremeo/core', 'Type' => 'container', 'Id' => 'container-a',
						'OptionsJson' => json_encode(['columns' => $case === 'invalid-grid' ? implode('+', array_fill(0, 13, 1)) : '8+4'])]];
				if ($case === 'catalog') unset($R['ACTION']);
				$D = ['SESSION' => $case === 'guest-editor' ? [] : ['UserId' => 'admin'], 'MODULE' => ['D' => [
					'fremeo/core' => ['Active' => 1, 'INFO' => ['Type' => 'core', 'ModulDir' => dirname(__DIR__)]],
				]]];
				$script = '<?php require ' . var_export(realpath(__DIR__ . '/../../../autoload.php'), true) . ';define("SCRIPT_NAME","/test/");'
					. '$_SESSION=["BuilderToken"=>"valid-token"];$_SERVER["REQUEST_METHOD"]="POST";'
					. '$R=' . var_export($R, true) . ';$D=' . var_export($D, true) . ';'
					. '$smarty=new Smarty();$smarty->setCompileDir(' . var_export($tmp, true) . ');'
					. '$smarty->addPluginsDir(' . var_export(realpath(__DIR__ . '/../system/core/smarty_plugin'), true) . ');'
					. '$C=["Smarty"=>$smarty];include ' . var_export(realpath(__DIR__ . '/../system/admin__builder.php'), true) . ';'
					. 'echo json_encode($R["BuilderView"]);';
				$fs->dumpFile($tmp . '/container.php', $script);
				$output = [];
				exec('"' . PHP_BINARY . '" "' . $tmp . '/container.php"', $output, $code);
				$this->assertSame(0, $code, $case);
				$text = implode("\n", $output);
				if ($case === 'catalog') {
					$view = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
					$this->assertSame(['container', 'text'], array_keys($view['Components']['fremeo/core']));
					$this->assertSame('DE', $view['Components']['fremeo/core']['text']['EditorLanguage']);
					$this->assertSame('wysiwyg', $view['Components']['fremeo/core']['text']['Settings']['text']['input']);
					$this->assertStringEndsWith('text.settings.tpl', $view['FormTemplates']['fremeo/core']['text']);
					$this->assertSame('container', $view['Components']['fremeo/core']['container']['Builder']);
					$this->assertCount(1, $view['EditorScripts']);
				} elseif ($case === 'editor') {
					$this->assertStringContainsString("window.BuilderComponentEditors['fremeo/core:container']", $text);
				} elseif ($case === 'render') {
					$this->assertStringContainsString('builder-container row g-3', json_decode($text, true, 512, JSON_THROW_ON_ERROR)['html']);
				} elseif ($case === 'invalid-grid') {
					$this->assertStringContainsString('maximal 12', json_decode($text, true, 512, JSON_THROW_ON_ERROR)['error']);
				} else {
					$this->assertStringNotContainsString('window.BuilderComponentEditors', $text);
					$this->assertStringNotContainsString('<?php', $text);
				}
			}
		} finally {
			$fs->remove($tmp);
		}
	}

	public function testSavePersistsOnlyAuthorizedValidatedDraftsAndNeverChangesPublication(): void {
		$tmp = sys_get_temp_dir() . '/builder_save_' . bin2hex(random_bytes(6));
		$fs = new Filesystem();
		$fs->mkdir($tmp . '/system/template/components/example');
		$fs->dumpFile($tmp . '/system/template/components/example/component.json', '{"type":"example","defaults":{"text":""},"settings":{"text":{"type":"string","label":"Text"}}}');
		$fs->dumpFile($tmp . '/system/template/components/example/example.tpl', '{$options.text|escape}');
		$target = ['module' => 'test/provider', 'page' => 'frontend__page', 'region' => 'content', 'entity' => '', 'language' => 'DE'];
		$node = ['id' => 'example-a', 'kind' => 'component', 'module' => 'test/provider', 'type' => 'example', 'options' => ['text' => 'new'],
			'general' => ['Active' => false, 'CustomCssClass' => 'custom', 'CustomCss' => 'padding: 2px;', 'Viewports' => ['MD']]];
		try {
			foreach (['valid', 'empty', 'scoped', 'scope-id-missing', 'scope-shared-id', 'scope-branch', 'token', 'guest', 'get', 'unsigned', 'tampered', 'invalid-second', 'duplicate', 'invalid-options', 'array-json', 'mixed-page', 'invalid-region-active', 'translations-neutral', 'translations-null'] as $case) {
				$first = json_encode($target, JSON_THROW_ON_ERROR);
				$second = json_encode(array_replace($target, ['region' => 'after']), JSON_THROW_ON_ERROR);
				if ($case === 'scoped' || str_starts_with($case, 'scope-')) {
					$scoped = array_diff_key($target, ['entity' => true]) + ['scope' => 'page', 'branch' => 'frontend', 'contextEntity' => '0'];
					if ($case === 'scope-id-missing') $scoped['scope'] = 'individual';
					if ($case === 'scope-shared-id') $scoped['entity'] = '0';
					if ($case === 'scope-branch') $scoped['branch'] = 'admin';
					$first = json_encode($scoped, JSON_THROW_ON_ERROR);
					$second = json_encode(array_replace($scoped, ['region' => 'after', 'scope' => 'individual', 'entity' => '0']), JSON_THROW_ON_ERROR);
				}
				$regions = [
					['target' => $first, 'token' => hash_hmac('sha256', $first, 'context-key'), 'nodes' => $case === 'empty' ? [] : [$node]],
					['target' => $second, 'token' => hash_hmac('sha256', $second, 'context-key'), 'nodes' => []],
				];
				if ($case === 'unsigned') $regions[0]['token'] = 'wrong';
				if ($case === 'tampered') $regions[0]['target'] = str_replace('content', 'other', $first);
				if ($case === 'invalid-second') $regions[1]['nodes'] = [['id' => 'bad', 'kind' => 'missing']];
				if ($case === 'duplicate') $regions[1] = $regions[0];
				if ($case === 'invalid-options') $regions[0]['nodes'][0]['options']['text'] = 42;
				if ($case === 'invalid-region-active') $regions[0]['active'] = null;
				if ($case === 'translations-neutral') $regions[0]['nodes'][0]['translations'] = ['DE' => ['active' => true, 'options' => ['text' => 'not translatable']]];
				if ($case === 'translations-null') $regions[0]['nodes'][0]['translations'] = null;
				if ($case === 'mixed-page') {
					$regions[1]['target'] = json_encode(array_replace($target, ['page' => 'frontend__other']), JSON_THROW_ON_ERROR);
					$regions[1]['token'] = hash_hmac('sha256', $regions[1]['target'], 'context-key');
				}
				$R = ['ACTION' => 'save', 'BuilderToken' => $case === 'token' ? 'wrong' : 'valid-token',
					'BuilderRegionsJson' => $case === 'array-json' ? [] : json_encode($regions, JSON_THROW_ON_ERROR)];
				$D = ['SESSION' => $case === 'guest' ? [] : ['UserId' => 'admin'],
					'MODULE' => ['D' => ['test/provider' => ['Active' => 1, 'INFO' => ['Type' => 'module', 'ModulDir' => $tmp]]]]];
				$script = '<?php require ' . var_export(realpath(__DIR__ . '/../../../autoload.php'), true) . '; define("SCRIPT_NAME","/test/");'
					. '$db=new \phploader\CData(["DB"=>["FILENAME"=>' . var_export($tmp . '/' . $case . '.db', true) . ']]);'
					. '$db->registerPattern(include ' . var_export(realpath(__DIR__ . '/../system/default/default.pattern.php'), true) . ');'
					. '$builder=new \fremeo\core\Builder($db);'
					. '$builder->saveDraft("test/provider","frontend__page","content","DE",[]);'
					. '$builder->publish($builder->placeId("test/provider","frontend__page","content"),"DE");'
					. 'register_shutdown_function(function() use ($builder){$public=$builder->getRegion("test/provider","frontend__page","content","DE");'
					. '$builder->enablePreview("DE");echo "\n".json_encode(["status"=>http_response_code(),"public"=>$public,'
					. '"draft"=>$builder->getRegion("test/provider","frontend__page","content","DE"),'
					. '"after"=>$builder->getRegion("test/provider","frontend__page","after","DE",' . var_export($case === 'scoped' ? '0' : '', true) . ')]);});'
					. '$_SESSION=["BuilderToken"=>"valid-token","BuilderContextKey"=>"context-key"]; $_SERVER["REQUEST_METHOD"]=' . var_export($case === 'get' ? 'GET' : 'POST', true) . ';'
					. '$R=' . var_export($R, true) . ';$D=' . var_export($D, true) . ';$C=["Builder"=>$builder];include '
					. var_export(realpath(__DIR__ . '/../system/admin__builder.php'), true) . ';';
				$fs->dumpFile($tmp . '/save.php', $script);
				$output = [];
				exec('"' . PHP_BINARY . '" "' . $tmp . '/save.php"', $output, $code);
				$this->assertSame(0, $code, $case);
				$response = json_decode($output[0], true, 512, JSON_THROW_ON_ERROR);
				$stored = json_decode($output[1], true, 512, JSON_THROW_ON_ERROR);
				$this->assertSame([], $stored['public']['nodes'], $case);
				if (in_array($case, ['valid', 'empty', 'scoped'], true)) {
					$this->assertTrue($response['saved'], $case);
					$this->assertSame(2, $response['regions'], $case);
					$this->assertCount(2, $response['revisions'], $case);
					$this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $response['revisions'][0]);
					$this->assertSame(in_array($case, ['valid', 'scoped'], true) ? [$node] : [], $stored['draft']['nodes'], $case);
					$this->assertSame([], $stored['after']['nodes'], $case);
				} else {
					$this->assertArrayHasKey('error', $response, $case);
					$this->assertGreaterThanOrEqual(400, $stored['status'], $case);
					$this->assertSame([], $stored['draft']['nodes'], $case);
					$this->assertSame([], $stored['after'], $case);
				}
			}
		} finally {
			$fs->remove($tmp);
		}
	}

	public function testPublishRequiresAuthorizedTargetsAndCurrentSavedRevisions(): void {
		$tmp = sys_get_temp_dir() . '/builder_publish_' . bin2hex(random_bytes(6));
		$fs = new Filesystem();
		$fs->mkdir($tmp);
		try {
			foreach (['valid', 'stale', 'unsigned', 'token', 'guest', 'get', 'duplicate', 'missing-revision', 'mixed-page'] as $case) {
				$script = '<?php require ' . var_export(realpath(__DIR__ . '/../../../autoload.php'), true) . ';define("SCRIPT_NAME","/test/");'
					. '$case=' . var_export($case, true) . ';'
					. '$db=new \phploader\CData(["DB"=>["FILENAME"=>' . var_export($tmp . '/' . $case . '.db', true) . ']]);'
					. '$db->registerPattern(include ' . var_export(realpath(__DIR__ . '/../system/default/default.pattern.php'), true) . ');'
					. '$builder=new \fremeo\core\Builder($db);'
					. <<<'PHP'
					$target=['module'=>'test/provider','page'=>'frontend__page','region'=>'content','entity'=>'','language'=>'DE'];
					$id=$builder->placeId('test/provider','frontend__page','content');
					$builder->saveDrafts([['target'=>$target,'nodes'=>[],'active'=>false]]);
					$targetJson=json_encode($target);
					$regions=[['target'=>$targetJson,'token'=>hash_hmac('sha256',$targetJson,'context-key'),'revision'=>$builder->draftRevision($id)]];
					if($case==='stale') $builder->saveDrafts([['target'=>$target,'nodes'=>[],'active'=>true]]);
					if($case==='unsigned') $regions[0]['token']='wrong';
					if($case==='duplicate') $regions[]=$regions[0];
					if($case==='missing-revision') unset($regions[0]['revision']);
					if($case==='mixed-page') {
						$target['page']='frontend__other';$json=json_encode($target);
						$regions[]=['target'=>$json,'token'=>hash_hmac('sha256',$json,'context-key'),'revision'=>'wrong'];
					}
					$_SESSION=['BuilderToken'=>'valid-token','BuilderContextKey'=>'context-key'];
					$_SERVER['REQUEST_METHOD']=$case==='get'?'GET':'POST';
					$R=['ACTION'=>'publish','BuilderToken'=>$case==='token'?'wrong':'valid-token','BuilderRegionsJson'=>json_encode($regions)];
					$D=['SESSION'=>$case==='guest'?[]:['UserId'=>'admin'],'MODULE'=>['D'=>['test/provider'=>['Active'=>1,'INFO'=>['Type'=>'module','ModulDir'=>__DIR__]]]]];
					$C=['Builder'=>$builder];
					register_shutdown_function(function()use($db,$id){
						$filter=['BUILDER'=>['LANGUAGE'=>[]]];$db->get_object($data,$filter);
						echo "\n".json_encode(['status'=>http_response_code(),'row'=>$data['BUILDER']['D'][$id]]);
					});
					PHP;
				$script .= 'include ' . var_export(realpath(__DIR__ . '/../system/admin__builder.php'), true) . ';';
				$fs->dumpFile($tmp . '/publish.php', $script);
				$output = [];
				exec('"' . PHP_BINARY . '" "' . $tmp . '/publish.php"', $output, $code);
				$this->assertSame(0, $code, $case);
				$response = json_decode($output[0], true, 512, JSON_THROW_ON_ERROR);
				$stored = json_decode($output[1], true, 512, JSON_THROW_ON_ERROR);
				if ($case === 'valid') {
					$this->assertTrue($response['published']);
					$this->assertSame(1, $response['regions']);
					$this->assertSame($stored['row']['DraftJson'], $stored['row']['PublishedJson']);
					$this->assertEquals(0, $stored['row']['Active']);
				} else {
					$this->assertArrayHasKey('error', $response, $case);
					$this->assertGreaterThanOrEqual(400, $stored['status'], $case);
					$this->assertEmpty($stored['row']['PublishedJson'] ?? null, $case);
				}
			}
		} finally {
			$fs->remove($tmp);
		}
	}

	public function testPreviewRequiresAuthenticatedAdminRightsInRealMiddleware(): void {
		$tmp = sys_get_temp_dir() . '/builder_auth_' . bin2hex(random_bytes(6));
		mkdir($tmp);
		$middleware = var_export(realpath(__DIR__ . '/../system/index.php'), true);
		try {
			foreach (['guest', 'user', 'admin'] as $role) {
				$script = '<?php $role=' . var_export($role, true) . ';' . <<<'PHP'
				register_shutdown_function(function () { echo '|status=' . http_response_code(); });
				$R = ['Page'=>'frontend__page','BuilderPreview'=>1,'BuilderLanguage'=>'DE'];
				$D = ['SESSION'=>$role === 'guest' ? [] : ['UserId'=>'person']];
				$C = ['fremeo/core'=>['CData'=>new class($role) {
					function __construct(private string $role) {}
					function get_object(&$d,$f) {
						$rights=['frontend__'=>['Active'=>1]];
						if($this->role==='admin') $rights['admin__']=['Active'=>1];
						$d=['USER'=>['D'=>['person'=>['GROUP'=>['D'=>['group'=>[]]]]]],
							'USER_GROUP'=>['D'=>['group'=>['PAGE'=>['D'=>$rights]]]]];
					}
				}], 'Builder'=>new class {
					public string $language='';
					function enablePreview($language) { $this->language=$language; }
				}];
				PHP;
				$script .= 'include ' . $middleware . '; echo "preview=" . $C["Builder"]->language;';
				file_put_contents($tmp . '/auth.php', $script);
				$output = [];
				exec('"' . PHP_BINARY . '" "' . $tmp . '/auth.php"', $output, $code);
				$this->assertSame(0, $code);
				$text = implode("\n", $output);
				if ($role === 'admin') {
					$this->assertStringContainsString('preview=DE', $text);
				} else {
					$this->assertStringContainsString('status=403', $text);
					$this->assertStringNotContainsString('preview=DE', $text);
				}
			}
		} finally {
			(new Filesystem())->remove($tmp);
		}
	}
}
