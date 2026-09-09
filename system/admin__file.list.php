<?php

if(($D['ACTION']??null) == 'save' && $R['activeModuleId']??null) {
	$C[ $R['activeModuleId']??null ]['CData']->set_object($D);
}
if(($D['ACTION']??null) == 'upload' && $R['activeModuleId']??null) {
	foreach((array) $_FILES['file']['tmp_name'] AS $kFile => $File) {
		if(is_uploaded_file($File)) {
			#$platform_id = 'shop';
			#$CFile->copy($File,"data_c/PLATFORM/{$platform_id}/file/");
			
			$md5_File = md5_file($File);
			$basename = pathinfo($_FILES['file']['name'][$kFile], PATHINFO_FILENAME);
			$ext = strtolower(pathinfo($_FILES['file']['name'][$kFile], PATHINFO_EXTENSION));
			$fileSize = filesize($File);
			#$C['CFile']->move($File,"data/fremeo~core/file/{$md5_File}.{$ext}");
			
			$_ModId = str_replace('/','~',$R['activeModuleId']);
			$C['Filesystem']->copy($File,"data/{$_ModId}/file/{$md5_File}.{$ext}", true);

			$d['FILE']['D'][$md5_File]['Name'] = $basename;
			$d['FILE']['D'][$md5_File]['Size'] = $fileSize;
			$d['FILE']['D'][$md5_File]['Extension'] = $ext;
		}
	}
	##$C['fremeo/core']['CData']->set_object($d);
	$C[ $R['activeModuleId'] ]['CData']->set_object($d);
}
#$F['PLATFORM']['PAGE']['W'][0]['ID'] = [$D['ID']];
$f['FILE'] = [];

if(!empty($D['MODULE']['D'])) {
	foreach((array)$D['MODULE']['D'] AS $kMOD => $MOD) {


		if(isset($C[$kMOD]['CData'])) {
			$C[$kMOD]['CData']->get_object($D['MODULE']['D'][$kMOD],$f);
		}
	}
}