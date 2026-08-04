<?php
/*
if(($R['ACTION']??null) == 'save') {
	$C['fremeo/core']['CData']->set_object($D); 
}
*/

if(($R['ACTION']??null) == 'update-module') {
	$C['Composer']->run("update {$R['Module']['Id']}"); # --with-dependencies : Dieses Flag erlaubt Composer, auch die Pakete zu aktualisieren, von denen Ihr gewähltes Modul direkt abhängt.
	#--dry-run : Simulation des Updates, ohne dass tatsächlich Änderungen vorgenommen werden. Dies ist nützlich, um zu sehen, welche Pakete aktualisiert werden würden, bevor Sie die eigentliche Aktualisierung durchführen.
}
else if(($R['ACTION']??null) == 'update-all') {
	$C['Composer']->run("update");
}
else if(($R['ACTION']??null) == 'reinstall-module') {
	$C['Composer']->run("require {$R['Module']['Id']}");
}
else if(($R['ACTION']??null) == 'remove-module') {
	$C['Composer']->run("remove {$R['Module']['Id']}");
}
else if(($R['ACTION']??null) == 'migration-run') {
	$_a= $C['Composer']->run('run-script post-update-cmd');
	echo '<pre>'; print_r($_a['output']); echo '</pre>';
}
#$F['PLATFORM']['PAGE']['W'][0]['ID'] = [$D['ID']];
$F['SETTING'] = [];

$D['R']['Module']['D'] = $C['ComposerManager']->getInstalledPackages(); #Todo: ComposerManager in Composer verschieben und diese klasse entfernen

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
		$D['R']['Module']['D'][$kMOD]['version_latest'] = $package['version_latest'];
		#Speichergröße des module ordner ermitteln in byte
		
		$D['R']['Module']['D'][$kMOD]['size'] = $C['CFile']->getFolderSize("system/vendor/{$kMOD}");
		$D['R']['Module']['D'][$kMOD]['size_cache'] = $C['CFile']->getFolderSize("data_c/".str_replace("/", "~", $kMOD));
		$D['R']['Module']['D'][$kMOD]['size_data'] = $C['CFile']->getFolderSize("data/".str_replace("/", "~", $kMOD));
		
		$C['CCache']->set_cache([ $kMOD => [ 'Tag' => 'Package', 'Data' =>  serialize($D['R']['Module']['D'][$kMOD]) ] ]);
	}
	
}





