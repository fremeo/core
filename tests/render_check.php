<?php
error_reporting(E_ALL & ~E_DEPRECATED);
chdir('C:/xampp/htdocs/test3/test-setup2');
require 'system/vendor/autoload.php';
$base = 'C:/xampp/htdocs/test3/test-setup2/system/vendor/fremeo/';
$s = new Smarty();
$tmp = sys_get_temp_dir() . '/smarty_' . uniqid();
mkdir($tmp);
$s->setCompileDir($tmp);
$s->addTemplateDir($base . 'core/system/template/');
$s->error_reporting = E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED;

$R = [
	'GalleryAlbums' => ['a1' => ['Title' => 'Sommer <b>2026</b>', 'DateFrom' => '01.06.2026', 'DateTo' => '', 'FileId' => '', 'Locked' => true, 'Url' => 'gallery/sommer']],
	'Gallery'       => ['Id' => 'a1', 'Back' => 'gallery', 'NotFound' => false, 'Locked' => true, 'Error' => null, 'Title' => 'Geheim', 'DateFrom' => '', 'DateTo' => '', 'Images' => []],
	'PageEdit'      => ['Info' => ['Doppelt vergeben'], 'SEO' => ['p1' => ['DE' => 'meine_seite']]],
	'GalleryAdmin'  => ['Rows' => ['a1' => ['Title' => 'Album Eins', 'Active' => 1, 'DateFrom' => '', 'DateTo' => '', 'FileId' => '', 'Locked' => false]], 'Total' => 1, 'Page' => 1, 'Pages' => 1, 'PageList' => [1], 'From' => 1, 'To' => 1],
	'GalleryEdit'   => ['Id' => 'a1', 'Msg' => 'Gespeichert', 'Info' => null, 'Error' => null, 'Album' => ['Active' => 1, 'Title' => 'Mein Album'], 'Images' => [], 'Cover' => [], 'SeoUrl' => 'gallery/mein_album'],
];
$D = ['R' => $R, 'PAGE' => ['D' => ['p1' => ['Active' => 1, 'LANGUAGE' => ['D' => ['DE' => ['Title' => 'T', 'Text' => 'x']]]]]]];
$s->assign('D', $D);

$out = [];
foreach ([
	['gallery', 'frontend__gallery.album.tpl', 'Sommer'],
	['gallery', 'frontend__gallery.tpl',       'Geheim'],
	['gallery', 'admin__gallery.list.tpl',     'Album Eins'],
	['gallery', 'admin__gallery.edit.tpl',     'Mein Album'],
	['page',    'admin__page.edit.tpl',        'meine_seite'],
] as [$mod, $tpl, $expect]) {
	$s->addTemplateDir($base . "$mod/system/template/");
	try {
		$html = $s->fetch('extends:' . $base . "$mod/system/template/$tpl|include/input.tpl");
		$out[] = sprintf("%-30s %s", $tpl, str_contains($html, $expect) ? "OK (enthält \"$expect\")" : "FEHLT: $expect");
	} catch (Throwable $e) {
		$out[] = sprintf("%-30s FEHLER: %s", $tpl, substr($e->getMessage(), 0, 160));
	}
}
file_put_contents(sys_get_temp_dir() . '/render_result.txt', implode("\n", $out) . "\n");
