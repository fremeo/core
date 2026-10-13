<?php
use Intervention\Image\Format;

$id  = $R['id'];
$x   = (int)$R['x'];
$y   = (int)$R['y'];
$ext = strtolower($R['extension']);
$ext = in_array($ext, ['avif', 'webp', 'png', 'gif', 'jpg', 'jpeg'], true) ? $ext : 'jpg'; #alles andere wird JPEG
$fs  = $C['Filesystem'];

#Modul suchen, dem die Datei gehört
$F['FILE']['W'][0]['ID'] = $id;
$source = null;
foreach ($D['MODULE']['D'] as $moduleId => $info) {
	if (!isset($C[$moduleId]['CData'])) continue;

	$d = [];
	$C[$moduleId]['CData']->get_object($d, $F);
	if (!empty($d['FILE']['D'][$id])) {
		$source = 'data/' . str_replace('/', '~', $moduleId) . "/file/{$id}.{$d['FILE']['D'][$id]['Extension']}";
		if ($fs->exists($source)) break;
		$source = null;
	}
}

if ($source && $x > 0 && $y > 0) {
	#Zentraler Temp Ordner im Core. Bereits vorhandene Dateien liefert die .htaccess direkt aus, ohne PHP.
	$target = "data_c/fremeo~core/file/{$id}_{$x}x{$y}.{$ext}";

	// Intervention Image 4 (PHP >= 8.3): decodePath() / encodeUsingFormat(). Version 3 (l\u00e4uft ab PHP 8.1): read() / toAvif() usw.
	$v4    = method_exists($C['ImageManager'], 'decodePath');
	$image = $v4 ? $C['ImageManager']->decodePath($source) : $C['ImageManager']->read($source);
	$image->contain(width: $x, height: $y, background: 'ffffff');

	$encoded = match ($ext) {
		'avif'  => $v4 ? $image->encodeUsingFormat(Format::AVIF, quality: 80) : $image->toAvif(80),
		'webp'  => $v4 ? $image->encodeUsingFormat(Format::WEBP, quality: 90) : $image->toWebp(90),
		'png'   => $v4 ? $image->encodeUsingFormat(Format::PNG) : $image->toPng(),
		'gif'   => $v4 ? $image->encodeUsingFormat(Format::GIF) : $image->toGif(),
		default => $v4 ? $image->encodeUsingFormat(Format::JPEG, quality: 90) : $image->toJpeg(90),
	};
	$fs->dumpFile($target, (string)$encoded);

	header('Content-Type: image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
	echo $encoded;
	exit;
}
