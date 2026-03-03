<?php

if(($D['ACTION']??null) == 'save') {
	$C['CData']->set_object($D);
}
if(($D['ACTION']??null) == 'upload') {
	foreach((array) $_FILES['file']['tmp_name'] AS $kFile => $File) {
		if($File) {
		#$platform_id = 'shop';
		#$CFile->copy($File,"data_c/PLATFORM/{$platform_id}/file/");
		
		$md5_File = md5_file($File);
		$basename = pathinfo($_FILES['file']['name'][$kFile], PATHINFO_FILENAME);
		$ext = pathinfo($_FILES['file']['name'][$kFile], PATHINFO_EXTENSION);
		$C['CFile']->move($File,"data/papp_phpapp/file/{$md5_File}.{$ext}");
		
		$d['FILE']['D'][$md5_File]['Name'] = $basename;
		$d['FILE']['D'][$md5_File]['Size'] = filesize("data/papp_phpapp/file/{$md5_File}.{$ext}");
		$d['FILE']['D'][$md5_File]['Extension'] = $ext;
		}
	}
	$C['CData']->set_object($d);
}
#$F['PLATFORM']['PAGE']['W'][0]['ID'] = [$D['ID']];
$F['FILE'] = [];