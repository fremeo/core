<?php

if(($R['ACTION']??null) == 'save') {
	$C['CData']->set_object($D); 
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
		
		$C['CCache']->set_cache([ $kMOD => [ 'Tag' => 'Package', 'Data' =>  serialize($D['R']['Module']['D'][$kMOD]) ] ]);
	}
	
}


