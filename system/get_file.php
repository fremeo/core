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

	$image = $C['ImageManager']->decodePath($source);
	$image->contain(width: $x, height: $y, background: 'ffffff');

	$encoded = match ($ext) {
		'avif'  => $image->encodeUsingFormat(Format::AVIF, quality: 80),
		'webp'  => $image->encodeUsingFormat(Format::WEBP, quality: 90),
		'png'   => $image->encodeUsingFormat(Format::PNG),
		'gif'   => $image->encodeUsingFormat(Format::GIF),
		default => $image->encodeUsingFormat(Format::JPEG, quality: 90),
	};
	$fs->dumpFile($target, (string)$encoded);

	header('Content-Type: image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
	echo $encoded;
	exit;
}
