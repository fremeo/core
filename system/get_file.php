<?php
use Intervention\Image\Format;

$id   = $R['id'] ?? '';
$x    = (int)($R['x'] ?? 0);
$y    = (int)($R['y'] ?? 0);
$size = (int)($R['size'] ?? 0);
$ext  = strtolower((string)($R['extension'] ?? 'jpg'));
$ext  = in_array($ext, ['avif', 'webp', 'png', 'gif', 'jpg', 'jpeg'], true) ? $ext : 'jpg'; #alles andere wird JPEG
$fs   = $C['Filesystem'];

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

# Wenn keine Größe übergeben wurde, soll original Datei ausgegeben werden
if ($source && $x === 0 && $y === 0 && $size === 0) {
	$sourceExt = strtolower(pathinfo($source, PATHINFO_EXTENSION));
	$mime = match ($sourceExt) {
		'jpg', 'jpeg' => 'image/jpeg',
		'png' => 'image/png',
		'gif' => 'image/gif',
		'webp' => 'image/webp',
		'avif' => 'image/avif',
		default => 'image/jpeg',
	};

	header('Content-Type: ' . $mime);
	readfile($source);
	exit;
}

if ($source && (($x > 0 && $y > 0) || $size > 0)) {
	#Zentraler Temp Ordner im Core. Bereits vorhandene Dateien liefert die .htaccess direkt aus, ohne PHP.
	if ($x > 0 && $y > 0) {
		$target = "data_c/fremeo~core/file/{$id}_{$x}x{$y}.{$ext}";
	} else {
		$target = "data_c/fremeo~core/file/{$id}_{$size}.{$ext}";
	}

	// Intervention Image 4 (PHP >= 8.3): decodePath() / encodeUsingFormat(). Version 3 (l\u00e4uft ab PHP 8.1): read() / toAvif() usw.
	
	$v4    = method_exists($C['ImageManager'], 'decodePath');
	$image = $v4 ? $C['ImageManager']->decodePath($source) : $C['ImageManager']->read($source);
	
	if ($x > 0 && $y > 0) {
		$image->contain(width: $x, height: $y, background: 'ffffff');
	} else {
		$sourceWidth = $image->width();
		$sourceHeight = $image->height();
		if ($sourceWidth >= $sourceHeight) {
			$newWidth = $size;
			$newHeight = (int) round(($sourceHeight / $sourceWidth) * $size);
		} else {
			$newHeight = $size;
			$newWidth = (int) round(($sourceWidth / $sourceHeight) * $size);
		}
		$image->resize(max(1, $newWidth), max(1, $newHeight));
	}

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
