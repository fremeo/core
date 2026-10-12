<?php
namespace fremeo\core\tests;

use fremeo\core\File;
use PHPUnit\Framework\TestCase;
use phploader\CData;
use Symfony\Component\Filesystem\Filesystem;

class FileTest extends TestCase {

	private string $tmp;
	private string $oldCwd;
	private array $C;

	protected function setUp(): void {
		$this->tmp = sys_get_temp_dir() . '/fremeo_file_' . uniqid();
		mkdir($this->tmp);
		$this->oldCwd = getcwd();
		chdir($this->tmp); // File arbeitet mit relativen Pfaden (data/..., data_c/...)

		$db = new CData(['DB' => ['FILENAME' => "{$this->tmp}/data.db", 'FILENAME_C' => "{$this->tmp}/data_c.db"]]);
		$db->registerPattern(['FILE' => [
			'Name'      => ['Type' => 'text'],
			'Size'      => ['Type' => 'number'],
			'Extension' => ['Type' => 'text'],
		]]);

		$this->C = ['Filesystem' => new Filesystem(), 'fremeo/test' => ['CData' => $db]];
	}

	protected function tearDown(): void {
		$this->C = []; // DB-Dateihandles freigeben (Windows)
		gc_collect_cycles();
		chdir($this->oldCwd);
		(new Filesystem())->remove($this->tmp);
	}

	public function testAddIgnoresFilesThatAreNotUploads(): void {
		$tmpFile = "{$this->tmp}/fake.jpg";
		file_put_contents($tmpFile, 'x');

		$result = (new File($this->C, 'fremeo/test'))->add([
			'name' => ['fake.jpg'], 'tmp_name' => [$tmpFile], 'error' => [0], 'size' => [1],
		]);

		$this->assertSame([], $result);
		$this->assertDirectoryDoesNotExist("{$this->tmp}/data/fremeo~test/file");
	}

	public function testDeleteRemovesFileDatabaseEntryAndCachedVariants(): void {
		$id = md5('image');
		mkdir("{$this->tmp}/data/fremeo~test/file", 0777, true);
		mkdir("{$this->tmp}/data_c/fremeo~core/file", 0777, true);
		file_put_contents("{$this->tmp}/data/fremeo~test/file/{$id}.jpg", 'x');
		file_put_contents("{$this->tmp}/data_c/fremeo~core/file/{$id}_100x100.jpg", 'x');
		file_put_contents("{$this->tmp}/data_c/fremeo~core/file/{$id}_50x50.webp", 'x');
		file_put_contents("{$this->tmp}/data_c/fremeo~core/file/other_50x50.webp", 'x');
		$seed = ['FILE' => ['D' => [$id => ['Name' => 'image', 'Size' => 1, 'Extension' => 'jpg']]]];
		$this->C['fremeo/test']['CData']->set_object($seed);

		(new File($this->C, 'fremeo/test'))->delete($id);

		$this->assertFileDoesNotExist("{$this->tmp}/data/fremeo~test/file/{$id}.jpg");
		$this->assertFileDoesNotExist("{$this->tmp}/data_c/fremeo~core/file/{$id}_100x100.jpg");
		$this->assertFileDoesNotExist("{$this->tmp}/data_c/fremeo~core/file/{$id}_50x50.webp");
		$this->assertFileExists("{$this->tmp}/data_c/fremeo~core/file/other_50x50.webp");

		$F['FILE']['W'][0]['ID'] = [$id];
		$this->C['fremeo/test']['CData']->get_object($D, $F);
		$this->assertEmpty($D['FILE']['D'] ?? []);
	}

	public function testDeleteOnlyAffectsOwnModule(): void {
		$id = md5('image');
		$other = new CData(['DB' => ['FILENAME' => "{$this->tmp}/other.db", 'FILENAME_C' => "{$this->tmp}/other_c.db"]]);
		$other->registerPattern(['FILE' => [
			'Name'      => ['Type' => 'text'],
			'Size'      => ['Type' => 'number'],
			'Extension' => ['Type' => 'text'],
		]]);
		$this->C['fremeo/other'] = ['CData' => $other];

		mkdir("{$this->tmp}/data/fremeo~other/file", 0777, true);
		file_put_contents("{$this->tmp}/data/fremeo~other/file/{$id}.jpg", 'x');
		$seed = ['FILE' => ['D' => [$id => ['Name' => 'image', 'Size' => 1, 'Extension' => 'jpg']]]];
		$other->set_object($seed);

		(new File($this->C, 'fremeo/test'))->delete($id); // Modul 'test' kennt die ID nicht

		$this->assertFileExists("{$this->tmp}/data/fremeo~other/file/{$id}.jpg");
		$F['FILE']['W'][0]['ID'] = [$id];
		$other->get_object($D, $F);
		$this->assertNotEmpty($D['FILE']['D'] ?? []);
	}
}
