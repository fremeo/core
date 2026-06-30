<?php

if(($R['ACTION']??null) == 'save') {
	$C['fremeo/core']['CData']->set_object($D); 
}
if(($R['ACTION']??null) == 'regenerateAutoload') { 
	$C['ComposerManager']->regenerateAutoload();
}
#$F['PLATFORM']['PAGE']['W'][0]['ID'] = [$D['ID']];
$F['SETTING'] = [];

$D['R']['Module']['D'] = $C['ComposerManager']->getInstalledPackages();

foreach( $D['R']['Module']['D'] AS $kMOD => $MOD) {
	
	$cache = $C['CCache']->get_cache($kMOD);//Todo: Cache in Packagist Klasse übertragen.
	if($cache) {
		$D['R']['Module']['D'][$kMOD] = unserialize($cache[$kMOD]['Data']);
	}
	else {
		$package = $C['Packagist']->getProject($kMOD);
		#$a['versions'][0]

		if(isset($package['versions'][ $D['R']['Module']['D'][$kMOD]['version'] ]['require'])) {
			$D['R']['Module']['D'][$kMOD]['require'] = $package['versions'][ $D['R']['Module']['D'][$kMOD]['version'] ]['require'];
		}

		$D['R']['Module']['D'][$kMOD]['description'] = $package['description'];
		$D['R']['Module']['D'][$kMOD]['url']  = $package['url'];
		$D['R']['Module']['D'][$kMOD]['type'] = $package['type'];
		#Speichergröße des module ordner ermitteln in byte
		
		$D['R']['Module']['D'][$kMOD]['size'] = $C['CFile']->getFolderSize("system/vendor/{$kMOD}");
		$D['R']['Module']['D'][$kMOD]['size_cache'] = $C['CFile']->getFolderSize("data_c/".str_replace("/", "~", $kMOD));
		$D['R']['Module']['D'][$kMOD]['size_data'] = $C['CFile']->getFolderSize("data/".str_replace("/", "~", $kMOD));
		
		$C['CCache']->set_cache([ $kMOD => [ 'Tag' => 'Package', 'Data' =>  serialize($D['R']['Module']['D'][$kMOD]) ] ]);
	}
	
}





