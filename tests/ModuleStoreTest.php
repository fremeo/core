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
}
