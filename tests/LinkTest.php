<?php
namespace fremeo\core\tests;

use fremeo\core\Link;
use PHPUnit\Framework\TestCase;
use phploader\CData;

class LinkTest extends TestCase {

	private string $tmp;

	protected function setUp(): void {
		$this->tmp = sys_get_temp_dir() . '/fremeo_link_' . uniqid();
		mkdir($this->tmp);
	}

	protected function tearDown(): void {
		(new \Symfony\Component\Filesystem\Filesystem())->remove($this->tmp);
	}

	private function db(string $name): CData {
		$db = new CData(['DB' => ['FILENAME' => "{$this->tmp}/{$name}.db", 'FILENAME_C' => "{$this->tmp}/{$name}_c.db"]]);
		$db->registerPattern(['LINK' => [
			'Active'  => ['Type' => 'checkbox'],
			'FromURL' => ['Type' => 'text'],
			'ToURL'   => ['Type' => 'text'],
		]]);
		return $db;
	}

	private function entry(string $seo): array {
		return ['SeoURL' => $seo, 'Page' => 'test__page', 'ModuleId' => 'fremeo/test'];
	}

	private function exists(Link $link, string $seo): bool {
		return !empty($link->getBySeo($seo)['LINK']['D']);
	}

	public function testTakenUrlGetsSuffix(): void {
		$link = new Link($this->db('a'));

		$this->assertSame('blog',   $link->create($this->entry('blog'))[0]['SeoUrl']);
		$this->assertSame('blog_1', $link->create($this->entry('blog'))[0]['SeoUrl']);
		$this->assertSame('blog_2', $link->create($this->entry('blog'))[0]['SeoUrl']);
	}

	public function testSameUrlTwiceInOneCall(): void {
		$link = new Link($this->db('a'));

		$result = $link->create([$this->entry('blog'), $this->entry('blog')]);

		$this->assertSame(['blog', 'blog_1'], array_column($result, 'SeoUrl'));
	}

	public function testCheckAcrossModules(): void {
		$a = $this->db('a');
		$b = $this->db('b');
		$all = [$a, $b];

		(new Link($a, $all))->create($this->entry('shop'));

		$this->assertSame('shop_1', (new Link($b, $all))->create($this->entry('shop'))[0]['SeoUrl']);
	}

	public function testWithoutAllDbOnlyOwnModuleIsChecked(): void {
		$a = $this->db('a');
		$b = $this->db('b');

		(new Link($a))->create($this->entry('shop'));

		$this->assertSame('shop', (new Link($b))->create($this->entry('shop'))[0]['SeoUrl']);
	}

	public function testRenameKeepsTargetAndUsesSuffixIfTaken(): void {
		$link = new Link($this->db('a'));
		$link->create($this->entry('old'));
		$link->create($this->entry('new'));

		$this->assertTrue($link->renameBySeo('old', 'new'));
		$this->assertFalse($this->exists($link, 'old'));
		$this->assertTrue($this->exists($link, 'new_1'));
		$this->assertFalse($link->renameBySeo('does-not-exist', 'x'));
	}

	public function testDelete(): void {
		$link = new Link($this->db('a'));
		$link->create($this->entry('blog'));

		$link->deleteBySeo('blog');

		$this->assertFalse($this->exists($link, 'blog'));
	}

	public function testCreateOneReturnsLinkId(): void {
		$link = new Link($this->db('a'));

		$this->assertSame($link->id('blog'), $link->createOne('blog', 'test__page', 'fremeo/test'));
	}
}
