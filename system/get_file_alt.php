<?php

$F['FILE']['W'][0]['ID'] = $R['id'];

$C['fremeo/core']['CData']->get_object($d,$F);

if(isset($d['FILE']['D'])) {
	$key = array_keys($d['FILE']['D']);
	$File_id = $key[0];

	$D['IMAGE'] = [
		'SOURCE_FILE'	=> "data/fremeo~core/file/{$File_id}.{$d['FILE']['D'][$File_id]['Extension']}",
		'TARGET_DIR'	=> "data_c/fremeo~core/file/",
		'TARGET_FILE'	=> "{$File_id}_{$R['x']}x{$R['y']}.{$R['extension']}",
		'X'				=> $R['x'],
		'Y'				=> $R['y'],
		'SHOW'			=> true, #gibt das Bild sofort aus
		#'TARGET_QUALITY'=> 90,
		'BACKGROUND'	=> 'FFF',
		'SCALE'			=> 'absolute-relative',
	];

	$C['CFile']->image($D['IMAGE']);
}
