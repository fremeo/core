<?php

$F['FILE']['W'][0]['ID'] = $R['id'];

$C['CData']->get_object($d,$F);

if(isset($d['FILE']['D'])) {
	$key = array_keys($d['FILE']['D']);
	$File_id = $key[0];

	$D['IMAGE'] = [
		'SOURCE_FILE'	=> "data/papp_phpapp/file/{$File_id}.{$d['FILE']['D'][$File_id]['Extension']}",
		'TARGET_DIR'	=> "data_c/papp_phpapp/file/",
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
