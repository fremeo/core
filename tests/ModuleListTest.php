<?php
namespace fremeo\core\tests;

use fremeo\core\Container;
use fremeo\core\Security;
use phploader\CData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ModuleListTest extends TestCase {
	private string $tmp;
	private CData $db;
	private Container $C;
	private array $modules;
	private array $server;

	protected function setUp(): void {
		$this->server = $_SERVER;
		$this->tmp = sys_get_temp_dir() . '/fremeo_modules_' . bin2hex(random_bytes(8));
		mkdir($this->tmp);
		$this->db = new CData(['DB' => ['FILENAME' => "{$this->tmp}/data.db", 'FILENAME_C' => "{$this->tmp}/cache.db"]]);
		$this->db->registerPattern(include __DIR__ . '/../system/default/default.pattern.php');
		$this->C = new Container();
		$this->C->set('CData', $this->db, 'fremeo/core');
		$this->C->set('Security', new Security());
		$this->modules = [];
		$packages = [];
		foreach (['fremeo/core' => 'core', 'fremeo/frontend' => 'template', 'fremeo/shop' => 'module', 'vendor/library' => 'library'] as $id => $type) {
			$this->modules[$id] = ['INFO' => ['Type' => $type]];
			$packages[$id] = ['type' => 'library', 'version' => '1.0', 'version_latest' => '1.0', 'description' => $id, 'url' => '', 'size' => 1, 'size_cache' => 0, 'size_data' => 0];
		}
		$this->C->set('Composer', new class($packages) {
			public array $commands = [];
			function __construct(private array $packages) {}
			function getInstalledPackages() { return $this->packages; }
			function run($command) {
				$this->commands[] = $command;
				return ['success' => true, 'output' => $command];
			}
		});
		$this->C->set('CCache', new class($packages) {
			function __construct(private array $packages) {}
			function get_cache($id) { return [$id => ['Data' => serialize($this->packages[$id])]]; }
			function flush($filter) {}
		});
	}

	protected function tearDown(): void {
		$_SERVER = $this->server;
		unset($this->C['fremeo/core']['CData']);
		unset($this->C, $this->db);
		gc_collect_cycles();
		(new Filesystem())->remove($this->tmp);
	}

	private function page(array $R = [], string $method = 'POST', array $input = []): array {
		$_SERVER['REQUEST_METHOD'] = $method;
		$C = $this->C;
		$D = ['C' => $C, 'MODULE' => ['D' => $this->modules], 'R' => &$R] + $input;
		$F = [];
		include __DIR__ . '/../system/admin__module.list.php';
		return $D;
	}

	private function bulk(string $action, array $selected, string $method = 'POST'): array {
		return $this->page(['ACTION' => 'save'], $method, ['_MODULE_ACTION' => $action, '_MODULE_SELECTED' => $selected]);
	}

	private function states(): array {
		$f['MODULE'] = [];
		$this->db->get_object($data, $f);
		return $data['MODULE']['D'] ?? [];
	}

	public function testSelectedModuleFlagsPersistAndTabsUseLocalTypes(): void {
		$initial = $this->page([], 'GET');
		$this->assertCount(3, $initial['R']['ModuleTabs']['active']['Items']);
		$this->assertSame(['vendor/library'], array_keys($initial['R']['ModuleTabs']['libraries']['Items']));
		$data = $this->bulk('deactivate', ['fremeo/shop' => '1', 'fremeo/frontend' => '1', 'fremeo/core' => '0']);
		$this->assertTrue($data['R']['ComposerOutput']['success']);
		$this->assertCount(2, $this->states());
		$this->assertEquals(0, $this->states()['fremeo/shop']['Active']);
		$this->assertCount(2, $data['R']['ModuleTabs']['inactive']['Items']);
		$this->assertSame(['fremeo/core'], array_keys($data['R']['ModuleTabs']['active']['Items']));
		$this->bulk('activate', ['fremeo/shop' => '1']);
		$this->assertEquals(1, $this->states()['fremeo/shop']['Active']);
		$this->assertEquals(0, $this->states()['fremeo/frontend']['Active']);
		$this->assertSame([], $this->C['Composer']->commands);
	}

	public function testInvalidSelectionsAndGetNeverChangeState(): void {
		foreach ([
			['Action' => 'deactivate', 'Selected' => ['vendor/library' => '1', 'fremeo/shop' => '1']],
			['Action' => 'activate', 'Selected' => ['unknown/module' => '1']],
			['Action' => 'activate', 'Selected' => []],
			['Action' => 'uninstall', 'Selected' => ['fremeo/core' => '1']],
			['Action' => 'deactivate', 'Selected' => ['fremeo/core' => '1']],
			['Action' => '', 'Selected' => ['fremeo/shop' => '1']],
		] as $request) {
			$data = $this->bulk($request['Action'], $request['Selected']);
			$this->assertFalse($data['R']['ComposerOutput']['success']);
			$this->assertSame([], $this->states());
		}
		$this->bulk('deactivate', ['fremeo/shop' => '1'], 'GET');
		$this->assertSame([], $this->states());
		$this->assertSame([], $this->C['Composer']->commands);
	}

	public function testBulkComposerCommandsUseOnlyValidatedPackages(): void {
		foreach (['update' => 'update', 'reinstall' => 'reinstall', 'uninstall' => 'remove'] as $action => $command) {
			$data = $this->bulk($action, ['fremeo/shop' => '1', 'fremeo/frontend' => '1']);
			$this->assertTrue($data['R']['ComposerOutput']['success']);
			$this->assertSame($command . ' ' . escapeshellarg('fremeo/shop') . ' ' . escapeshellarg('fremeo/frontend') . ($action === 'update' ? ' --with-dependencies' : ''), end($this->C['Composer']->commands));
		}
		$this->assertSame([], $this->states());
	}

	public function testClearCacheOnlyRemovesSelectedModuleFolders(): void {
		if (!defined('PROJECT_ROOT')) define('PROJECT_ROOT', dirname(__DIR__, 5) . '/');
		$fs = new Filesystem();
		foreach (['fremeo~shop', 'fremeo~frontend', 'public/theme'] as $folder) {
			$fs->dumpFile("{$this->tmp}/data_c/$folder/keep.txt", 'cache');
		}
		$this->C->set('Filesystem', new class($this->tmp) {
			public array $paths = [];
			function __construct(private string $tmp) {}
			function remove(string $path): void {
				$this->paths[] = $path;
				(new Filesystem())->remove($this->tmp . '/data_c/' . basename($path));
			}
		});
		$result = $this->bulk('clear-cache', ['fremeo/shop' => '1']);
		$this->assertTrue($result['R']['ComposerOutput']['success']);
		$this->assertSame([PROJECT_ROOT . 'data_c/fremeo~shop'], $this->C['Filesystem']->paths);
		$this->assertDirectoryDoesNotExist("{$this->tmp}/data_c/fremeo~shop");
		$this->assertFileExists("{$this->tmp}/data_c/fremeo~frontend/keep.txt");
		$this->assertFileExists("{$this->tmp}/data_c/public/theme/keep.txt");
		$this->assertSame([], $this->C['Composer']->commands);
		$this->assertSame([], $this->states());
		$this->assertTrue($this->bulk('clear-cache', ['fremeo/shop' => '1'])['R']['ComposerOutput']['success']);
		$this->assertFalse($this->bulk('clear-cache', ['vendor/library' => '1'])['R']['ComposerOutput']['success']);
		unset($this->C['Filesystem']);
		$this->C->set('Filesystem', new class {
			function remove(string $path): void { throw new \Symfony\Component\Filesystem\Exception\IOException('Datei ist gesperrt.', 0, null, $path); }
		});
		$error = $this->bulk('clear-cache', ['fremeo/shop' => '1']);
		$this->assertFalse($error['R']['ComposerOutput']['success']);
		$this->assertStringContainsString('Datei ist gesperrt.', $error['R']['ComposerOutput']['output']);
	}

	public function testRealInitializationSkipsInactiveModulesAndTemplateDescendants(): void {
		$root = "{$this->tmp}/runtime";
		$fs = new Filesystem();
		foreach (['core' => 'core', 'frontend' => 'template', 'frontend__child' => 'template', 'shop' => 'module', 'page' => 'module'] as $name => $type) {
			$dir = "$root/system/vendor/test/$name";
			$fs->dumpFile("$dir/composer.json", json_encode(['type' => $type]));
			$fs->dumpFile("$dir/init.php", '<?php $GLOBALS["loaded"][] = ' . var_export($name, true) . ';');
			$fs->dumpFile("$dir/cli.php", '<?php $GLOBALS["cliLoaded"][] = ' . var_export($name, true) . ';');
		}
		$fs->mkdir("$root/data/fremeo~core");
		$db = new CData(['DB' => ['FILENAME' => "$root/data/fremeo~core/data.db", 'FILENAME_C' => "$root/seed.db"]]);
		$db->registerPattern(include __DIR__ . '/../system/default/default.pattern.php');
		$update['MODULE']['D'] = ['test/core' => ['Active' => 0], 'test/shop' => ['Active' => 0], 'test/frontend' => ['Active' => 0]];
		$db->set_object($update);
		unset($db);
		$script = '<?php define("PROJECT_ROOT", ' . var_export("$root/", true) . '); $_SERVER["SCRIPT_NAME"]="/index.php"; require '
			. var_export(realpath(__DIR__ . '/../init.php'), true)
			. '; echo json_encode([$GLOBALS["loaded"], $GLOBALS["cliLoaded"], isset($C["test/shop"]["CData"]), $D["MODULE"]["D"]["test/core"]["Active"]]);';
		$fs->dumpFile("$root/check.php", $script);
		$process = proc_open([PHP_BINARY, "$root/check.php"], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 5));
		$this->assertIsResource($process);
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$this->assertSame(0, proc_close($process), $error);
		$this->assertSame([['page'], ['core', 'page'], false, 1], json_decode($output, true), $output . $error);
	}

	public function testExistingSingleActionsRejectLibrariesAndCoreRemoval(): void {
		foreach (['uninstall-module' => 'fremeo/core', 'reinstall-module' => 'vendor/library', 'update-module' => 'unknown/module'] as $action => $id) {
			$data = $this->page(['ACTION' => $action, 'Module' => ['Id' => $id]]);
			$this->assertFalse($data['R']['ComposerOutput']['success']);
		}
		$this->assertSame([], $this->C['Composer']->commands);
		$this->page(['ACTION' => 'update-module', 'Module' => ['Id' => 'fremeo/shop']]);
		$this->assertSame(['update fremeo/shop --with-dependencies'], $this->C['Composer']->commands);
	}

	#[\PHPUnit\Framework\Attributes\WithoutErrorHandler]
	public function testTemplateRendersTabsSelectionAndExternalFooterControls(): void {
		if (!defined('SCRIPT_NAME')) define('SCRIPT_NAME', '/test/');
		$smarty = new \Smarty();
		$smarty->setCompileDir($this->tmp);
		$smarty->error_reporting = E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED;
		$smarty->assign('D', $this->page([], 'GET'));
		$tpl = __DIR__ . '/../system/template/';
		$html = $smarty->fetch("extends:{$tpl}index__index.tpl|{$tpl}index__admin.tpl|{$tpl}admin__module.list.tpl|{$tpl}include/input.tpl");
		$smarty->clearAllAssign();
		$this->assertStringContainsString('id="modules-active"', $html);
		$this->assertStringContainsString('id="modules-inactive"', $html);
		$this->assertStringContainsString('id="modules-libraries"', $html);
		$this->assertStringContainsString($this->C['Security']->registerSecurityD('D[_MODULE_SELECTED][fremeo/shop]'), $html);
		$this->assertStringNotContainsString($this->C['Security']->registerSecurityD('D[_MODULE_SELECTED][vendor/library]'), $html);
		$this->assertStringContainsString('<select form="modForm"', $html);
		$this->assertStringContainsString('name="' . $this->C['Security']->registerSecurityD('D[_MODULE_ACTION]') . '"', $html);
		$this->assertStringContainsString('<option value="">---</option>', $html);
		$this->assertStringContainsString('value="uninstall">Uninstall', $html);
		$this->assertStringNotContainsString('disabled value="uninstall"', $html);
		$this->assertStringContainsString('form="modForm" class="btn btn-primary btn-sm">Speichern', $html);
		$this->assertStringContainsString('>Abbrechen</a>', $html);
		$this->assertStringNotContainsString('sticky-bottom', $html);
	}
}
