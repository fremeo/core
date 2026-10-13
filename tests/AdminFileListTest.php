<?php
namespace fremeo\core\tests;

use fremeo\core\File;
use PHPUnit\Framework\TestCase;
use phploader\CData;
use Symfony\Component\Filesystem\Filesystem;

/** Integrationstest: admin__file.list.php lädt nur über File->add() hoch und zeigt keine Bibliotheken. */
class AdminFileListTest extends TestCase {

	private string $tmp;
	private string $oldCwd;
	private array $C;

	protected function setUp(): void {
		$this->tmp = sys_get_temp_dir() . '/fremeo_filelist_' . uniqid();
		mkdir($this->tmp);
		$this->oldCwd = getcwd();
		chdir($this->tmp); // File arbeitet mit relativen Pfaden (data/...)

		$db = new CData(['DB' => ['FILENAME' => "{$this->tmp}/a.db", 'FILENAME_C' => "{$this->tmp}/a_c.db"]]);
		$db->registerPattern(['FILE' => [
			'Name' => ['Type' => 'text'], 'Size' => ['Type' => 'number'], 'Extension' => ['Type' => 'text'], 'Active' => ['Type' => 'checkbox'],
		]]);

		$this->C = ['Filesystem' => new Filesystem(), 'fremeo/a' => ['CData' => $db]];
		// File mit Upload-Freigabe für normale Dateien (is_uploaded_file ist in Tests immer false)
		$this->C['fremeo/a']['File'] = new class($this->C, 'fremeo/a') extends File {
			protected function isUpload(string $tmp): bool {
				return is_file($tmp);
			}
		};
	}

	protected function tearDown(): void {
		$this->C = [];
		gc_collect_cycles();
		chdir($this->oldCwd);
		(new Filesystem())->remove($this->tmp);
	}

	private function upload(array $names): array {
		$up = ['name' => [], 'tmp_name' => []];
		foreach ($names as $i => $name) {
			$tmp = "{$this->tmp}/up{$i}";
			file_put_contents($tmp, "inhalt-{$i}-{$name}");
			$up['name'][] = $name;
			$up['tmp_name'][] = $tmp;
		}
		return $up;
	}

	/** Führt die Seite aus und gibt $D zurück ($D['R'] verweist wie im Core auf $R). */
	private function page(string $action, ?string $moduleId, array $files = [], array $delete = []): array {
		$C = &$this->C;
		$D = ['ACTION' => $action, 'MODULE' => ['D' => [
			'fremeo/a'          => ['Type' => 'module'],
			'phploader/cdata'   => ['Type' => 'library'],
			'fremeo/ohne'       => ['Type' => 'module'],
		]]];
		$R = ['activeModuleId' => $moduleId, 'delete' => $delete];
		$D['R'] = &$R;
		$_FILES = $files ? ['file' => $files] : [];
		(function () use (&$C, &$D, &$R) {
			include __DIR__ . '/../system/admin__file.list.php';
		})();
		return $D;
	}

	private function storedFiles(): array {
		$dir = "{$this->tmp}/data/fremeo~a/file";
		return is_dir($dir) ? array_map('basename', glob("$dir/*")) : [];
	}

	public function testUploadStoresFileThroughFileService(): void {
		$D = $this->page('upload', 'fremeo/a', $this->upload(['bild.jpg']));

		$this->assertCount(1, $this->storedFiles());
		$entries = $D['MODULE']['D']['fremeo/a']['FILE']['D'];
		$this->assertCount(1, $entries);
		$this->assertSame('jpg', array_values($entries)[0]['Extension']);
	}

	public function testExecutableAndTrickyExtensionsAreRejected(): void {
		$this->page('upload', 'fremeo/a', $this->upload(['shell.php', 'shell.PHTML', 'x.php::$DATA', 'noext', 'ok.txt']));

		$this->assertCount(1, $this->storedFiles());
		$this->assertStringEndsWith('.txt', $this->storedFiles()[0]);
	}

	public function testModuleIdCannotBeUsedAsPath(): void {
		$this->page('upload', '..', $this->upload(['x.jpg']));
		$this->page('upload', '../x', $this->upload(['y.jpg']));
		$this->page('upload', 'fremeo/ohne', $this->upload(['z.jpg']));

		$this->assertDirectoryDoesNotExist("{$this->tmp}/file");
		$this->assertDirectoryDoesNotExist("{$this->tmp}/data/x");
		$this->assertSame([], $this->storedFiles());
	}

	public function testLibrariesAndModulesWithoutFileServiceAreNotListed(): void {
		$D = $this->page('', null);

		$this->assertSame(['fremeo/a'], $D['R']['FileModules']);
		$this->assertArrayNotHasKey('FILES_ADMIN', $D); // $D bleibt die Datenbankstruktur
	}

	public function testMarkedFilesAreDeletedOnSaveWithEntryAndFile(): void {
		$D = $this->page('upload', 'fremeo/a', $this->upload(['a.txt', 'b.txt']));
		$ids = array_keys($D['MODULE']['D']['fremeo/a']['FILE']['D']);
		$this->assertCount(2, $ids);

		$D = $this->page('save', 'fremeo/a', [], ['fremeo/a' => [$ids[0]]]);

		$this->assertCount(1, $this->storedFiles());
		$this->assertSame([$ids[1]], array_keys($D['MODULE']['D']['fremeo/a']['FILE']['D']));
	}

	public function testNothingIsDeletedWithoutSave(): void {
		$D = $this->page('upload', 'fremeo/a', $this->upload(['a.txt']));
		$id = array_key_first($D['MODULE']['D']['fremeo/a']['FILE']['D']);

		$this->page('', 'fremeo/a', [], ['fremeo/a' => [$id]]);

		$this->assertCount(1, $this->storedFiles());
	}

	public function testInvalidDeleteIdsAndUnknownModulesAreIgnored(): void {
		$this->page('upload', 'fremeo/a', $this->upload(['a.txt']));

		$this->page('save', 'fremeo/a', [], ['fremeo/a' => ['../x', "1' OR '1'='1", ['array']], 'fremeo/unbekannt' => ['x'], '..' => ['x']]);

		$this->assertCount(1, $this->storedFiles());
	}
}
