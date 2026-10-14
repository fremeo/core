<?php
/*
if(($R['ACTION']??null) == 'save') {
	$C['fremeo/core']['CData']->set_object($D); 
}
*/

$_action = $R['ACTION'] ?? null;
$_id     = (string)($R['Module']['Id'] ?? '');
$_idOk   = (bool)preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $_id); # nur gültige Paketnamen, der Wert landet in einer Composer-Kommandozeile
$_result = null;

# Aktionen ändern das System: nur per POST (nicht per Link/GET). Migrationen laufen nach install/update/remove automatisch (Composer-Skripte der composer.json)
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
	$_action = null;
}

if($_action == 'update-module' && $_idOk) {
	$_result = $C['Composer']->run("update {$_id} --with-dependencies"); # --with-dependencies : Dieses Flag erlaubt Composer, auch die Pakete zu aktualisieren, von denen Ihr gewähltes Modul direkt abhängt.
	#--dry-run : Simulation des Updates, ohne dass tatsächlich Änderungen vorgenommen werden. Dies ist nützlich, um zu sehen, welche Pakete aktualisiert werden würden, bevor Sie die eigentliche Aktualisierung durchführen.
}
else if($_action == 'update-all') {
	$_result = $C['Composer']->run("update");
}
else if($_action == 'reinstall-module' && $_idOk) {
	$_result = $C['Composer']->run("reinstall {$_id}"); # Paket löschen und neu installieren (Version laut composer.lock)
}
else if(in_array($_action, ['remove-module', 'uninstall-module'], true) && $_idOk) { # das Template sendet uninstall-module
	$_result = $C['Composer']->run("remove {$_id}");
}
else if($_action == 'migration-run') {
	# Nur die Migrationsdateien der Module durchgehen (ohne Composer): ausgegeben werden die Migrationen, die wirklich ausgeführt wurden
	include_once __DIR__ . '/core/Migrations.php';
	$_lines = [];
	try {
		foreach($D['MODULE']['D'] as $_kModule => $_info) {
			if(!isset($C[$_kModule]['CData'])) continue;
			$_migrations = new \fremeo\core\Migrations($_kModule, $D);
			foreach($_migrations->getMigrations() as $_file) {
				if($_migrations->runMigrationUp($_file)) {
					$_lines[] = "Migration [{$_file}] ({$_kModule}) ausgeführt.";
				}
			}
		}
		$R['ComposerOutput'] = ['success' => true, 'output' => $_lines ? implode("\n", $_lines) : 'Keine neuen Migrationen.'];
	}
	catch(\Throwable $e) {
		$R['ComposerOutput'] = ['success' => false, 'output' => implode("\n", $_lines) . "\nFehler: " . $e->getMessage()];
	}
}

if($_result !== null) {
	$R['ComposerOutput'] = $_result; # Ausgabe im Template
	$C['CCache']->flush(['Tag' => 'Package']); # Versionen und Größen neu ermitteln
}
#$F['PLATFORM']['PAGE']['W'][0]['ID'] = [$D['ID']];
$F['SETTING'] = [];

$D['R']['Module']['D'] = $C['Composer']->getInstalledPackages();

$folderSize = function(string $dir): int {
	$size = 0;
	if(is_dir($dir)) {
		foreach((new \Symfony\Component\Finder\Finder())->files()->ignoreDotFiles(false)->ignoreVCS(false)->in($dir) as $file) {
			$size += $file->getSize();
		}
	}
	return $size;
};

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

		$D['R']['Module']['D'][$kMOD]['description'] = $package['description'] ?: $MOD['description'];
		$D['R']['Module']['D'][$kMOD]['url']  = $package['url'];
		$D['R']['Module']['D'][$kMOD]['type'] = $package['type'] ?: $MOD['type']; # nicht auf Packagist gelistete Pakete behalten die lokalen Angaben
		$D['R']['Module']['D'][$kMOD]['version_latest'] = $package['version_latest'] ?? $MOD['version'];
		#Speichergröße des module ordner ermitteln in byte
		
		$D['R']['Module']['D'][$kMOD]['size'] = $folderSize("system/vendor/{$kMOD}");
		$D['R']['Module']['D'][$kMOD]['size_cache'] = $folderSize("data_c/".str_replace("/", "~", $kMOD));
		$D['R']['Module']['D'][$kMOD]['size_data'] = $folderSize("data/".str_replace("/", "~", $kMOD));
		
		$C['CCache']->set_cache([ $kMOD => [ 'Tag' => 'Package', 'Data' =>  serialize($D['R']['Module']['D'][$kMOD]) ] ]);
	}
	
}





