<?php
namespace fremeo\core\tests;

use PHPUnit\Framework\TestCase;

class ModuleStoreTest extends TestCase {
	private function search(string $query, bool $cached): array {
		$packages = [
			'nirgendheim/frontend-focusbox' => ['type' => 'template', 'keywords' => ['fremeo', 'photobooth']],
			'fremeo/core' => ['type' => 'core', 'keywords' => ['fremeo', 'fremeo-core']],
			'other/loader' => ['type' => 'project', 'keywords' => ['fremeo']],
			'fremeo/untagged' => ['type' => 'module', 'keywords' => []],
			'other/partial' => ['type' => 'module', 'keywords' => ['fremeo-module']],
		];
		$cache = new class($cached ? $packages : null) {
			public array $writes = [];
			function __construct(private ?array $packages) {}
			function get_cache($key) {
				return $this->packages === null ? [] : [$key => ['Data' => serialize($this->packages)]];
			}
			function set_cache($data) { $this->writes[] = $data; }
		};
		$packagist = new class($packages) {
			public array $calls = [];
			function __construct(private array $packages) {}
			function getProjectList($tag, $query) {
				$this->calls[] = [$tag, $query];
				return $this->packages;
			}
		};
		$C = [
			'CCache' => $cache,
			'Packagist' => $packagist,
			'Composer' => new class {
				function isAvailable() { return true; }
				function getInstalledPackages() {
					return ['nirgendheim/frontend-focusbox' => ['version' => '0.1.1']];
				}
			},
		];
		$R = ['Search' => $query];
		$D = ['R' => ['Action' => 'search']];
		$server = $_SERVER;
		$_SERVER['REQUEST_METHOD'] = 'GET';
		try {
			include __DIR__ . '/../system/admin__module.store.php';
		} finally {
			$_SERVER = $server;
		}
		$this->assertSame($cached ? [] : [['fremeo', '']], $packagist->calls);
		$this->assertCount($cached ? 0 : 1, $cache->writes);
		return $D['R']['Module']['D'];
	}

	public function testOnlyExactKeywordRestrictsCachedAndFreshResults(): void {
		foreach ([true, false] as $cached) {
			$this->assertSame(
				['nirgendheim/frontend-focusbox', 'fremeo/core', 'other/loader'],
				array_keys($this->search('', $cached))
			);
		}
	}

	public function testSearchFindsOtherVendorAndMarksInstalledPackage(): void {
		$result = $this->search('focusbox photobooth', true);
		$this->assertSame(['nirgendheim/frontend-focusbox'], array_keys($result));
		$this->assertTrue($result['nirgendheim/frontend-focusbox']['installed']);
		$this->assertSame('0.1.1', $result['nirgendheim/frontend-focusbox']['installed_version']);
	}

	public function testInstallationAlwaysUsesWildcardInsteadOfPostedVersion(): void {
		foreach (['', '0.1.1', 'v0.1.2'] as $version) {
			$composer = new class {
				function isAvailable() { return true; }
				public array $commands = [];
				function run($command) {
					$this->commands[] = $command;
					return ['success' => true, 'output' => $command];
				}
				function getInstalledPackages() { return []; }
			};
			$C = ['Composer' => $composer, 'CCache' => new class {
				function flush($filter) {}
			}];
			$R = ['Module' => ['Id' => 'nirgendheim/frontend-focusbox', 'Version' => $version]];
			$D = ['R' => ['Action' => 'install']];
			$server = $_SERVER;
			$_SERVER['REQUEST_METHOD'] = 'POST';
			try {
				include __DIR__ . '/../system/admin__module.store.php';
			} finally {
				$_SERVER = $server;
			}
			$this->assertSame(['require ' . escapeshellarg('nirgendheim/frontend-focusbox:*')], $composer->commands);
			$this->assertTrue($R['ComposerOutput']['success']);
		}
	}

	public function testMissingSetupRejectsInstallationWithoutCallingComposer(): void {
		$composer = new class {
			public array $commands = [];
			function isAvailable() { return false; }
			function run($command) { $this->commands[] = $command; }
			function getInstalledPackages() { return []; }
		};
		$C = ['Composer' => $composer];
		$R = ['Module' => ['Id' => 'nirgendheim/frontend-focusbox']];
		$D = ['R' => ['Action' => 'install']];
		$server = $_SERVER;
		$_SERVER['REQUEST_METHOD'] = 'POST';
		try {
			include __DIR__ . '/../system/admin__module.store.php';
		} finally {
			$_SERVER = $server;
		}
		$this->assertFalse($R['ComposerAvailable']);
		$this->assertFalse($R['ComposerOutput']['success']);
		$this->assertSame([], $composer->commands);
	}

	public function testStoreTemplateDisablesInstallationButPreservesSearch(): void {
		$tmp = sys_get_temp_dir() . '/fremeo_store_tpl_' . bin2hex(random_bytes(6));
		mkdir($tmp);
		try {
			$smarty = new \Smarty();
			$smarty->setCompileDir($tmp);
			$smarty->assign('D', ['R' => [
				'ComposerAvailable' => false, 'ComposerOutput' => null, 'Search' => '',
				'Module' => ['D' => [[
					'name' => 'test/module', 'description' => 'Test',
					'version_latest' => 'v1.0.0', 'installed' => false,
				]]],
			]]);
			$html = $smarty->fetch(__DIR__ . '/../system/template/admin__module.store.tpl');
			$this->assertStringContainsString('Paketverwaltung nicht verfuegbar', $html);
			$this->assertStringContainsString('disabled>Installieren', $html);
			$this->assertStringContainsString('class="btn btn-primary">Suchen', $html);
		} finally {
			(new \Symfony\Component\Filesystem\Filesystem())->remove($tmp);
		}
	}
}
