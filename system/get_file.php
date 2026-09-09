<?php
$F['FILE']['W'][0]['ID'] = $R['id'];

foreach ($D['MODULE']['D'] as $moduleDir => $info) {
	if(isset($C[$moduleDir]['CData']) ) {
		$C[ $moduleDir ]['CData']->get_object($d, $F);
		if($d['FILE']['D'][ $F['FILE']['W'][0]['ID'] ]??null) {
			break;
		}
	}
}

if (isset($d['FILE']['D'])) {
    $key = array_keys($d['FILE']['D']);
    
	$_ModId = str_replace('/','~',$moduleDir);
    // --- KORREKTUR HIER: [0] wieder hinzufügen, da array_keys() ein Array liefert ---
    $File_id = $key[0]; 

    $fs = $C['Filesystem'];
    // Pfade und Parameter definieren
    $sourceFile = "data/{$_ModId}/file/{$File_id}.{$d['FILE']['D'][$File_id]['Extension']}";
    $targetDir  = "data_c/fremeo~core/file/"; #Speichert extra in core Ordner, als zentraler temp Ordner
    $targetFile = $targetDir . "{$File_id}_{$R['x']}x{$R['y']}.{$R['extension']}";
    
    $width      = (int)$R['x'];
    $height     = (int)$R['y'];
    $extension  = strtolower($R['extension']);


    if ($fs->exists($sourceFile)) {
        
        $fs->mkdir($targetDir);

       # $manager = ImageManager::usingDriver(Driver::class);
		$manager = $C['ImageManager'];
        $image = $manager->decodePath($sourceFile);

        $image->contain(
            width: $width, 
            height: $height, 
            background: 'ffffff'
        );

        // 4. Format-spezifische Encodierung für V4 (Nutzt Enums)
        switch ($extension) {
            case 'avif':
                $encoded = $image->encodeUsingFormat(\Intervention\Image\Format::AVIF, quality: 80);
                break;
            case 'webp':
                $encoded = $image->encodeUsingFormat(\Intervention\Image\Format::WEBP, quality: 90);
                break;
            case 'png':
                $encoded = $image->encodeUsingFormat(\Intervention\Image\Format::PNG);
                break;
            case 'gif':
                $encoded = $image->encodeUsingFormat(\Intervention\Image\Format::GIF);
                break;
            case 'jpg':
            case 'jpeg':
            default:
                $encoded = $image->encodeUsingFormat(\Intervention\Image\Format::JPEG, quality: 90);
                break;
        }

        $fs->dumpFile($targetFile, (string)$encoded);

        header("Content-Type: image/{$extension}");
        echo $encoded;
        exit;
    }
}