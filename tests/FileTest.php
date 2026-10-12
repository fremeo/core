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

	/** File mit Upload-Check für normale Dateien (is_uploaded_file ist in Tests immer false). */
	private function fileWithUploads(): File {
		return new class($this->C, 'fremeo/test') extends File {
			protected function isUpload(string $tmp): bool {
				return is_file($tmp);
			}
		};
	}

	private function upload(string $name, string $content): array {
		$tmp = "{$this->tmp}/" . md5($name);
		file_put_contents($tmp, $content);
		return ['name' => $name, 'tmp_name' => $tmp];
	}

	public function testAddSavesFileAndDatabaseEntry(): void {
		$u = $this->upload('Bild.JPG', 'content');
		$id = md5('content');

		$result = $this->fileWithUploads()->add(['name' => [$u['name']], 'tmp_name' => [$u['tmp_name']]]);

		$this->assertSame(['Name' => 'Bild', 'Size' => 7, 'Extension' => 'jpg'], $result[$id]);
		$this->assertFileExists("{$this->tmp}/data/fremeo~test/file/{$id}.jpg");
		$F['FILE']['W'][0]['ID'] = [$id];
		$this->C['fremeo/test']['CData']->get_object($D, $F);
		$this->assertSame('Bild', $D['FILE']['D'][$id]['Name']);
	}

	public function testAddAcceptsSingleFileFormat(): void {
		$u = $this->upload('einzeln.png', 'single');

		$result = $this->fileWithUploads()->add($u); // $_FILES-Format ohne []: name/tmp_name sind Strings

		$this->assertArrayHasKey(md5('single'), $result);
	}

	public function testAddSkipsBlockedAndNotAllowedExtensions(): void {
		$a = $this->upload('code.php', 'a');
		$b = $this->upload('text.txt', 'b');
		$c = $this->upload('foto.png', 'c');
		$upload = ['name' => [$a['name'], $b['name'], $c['name']], 'tmp_name' => [$a['tmp_name'], $b['tmp_name'], $c['tmp_name']]];

		$all = $this->fileWithUploads()->add($upload);
		$this->assertSame([md5('b'), md5('c')], array_keys($all)); // php ist immer gesperrt

		$images = $this->fileWithUploads()->add($upload, ['png', 'jpg']);
		$this->assertSame([md5('c')], array_keys($images));
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
