<?php
/*
if(($R['ACTION']??null) == 'save') {
	$C['fremeo/core']['CData']->set_object($D); 
}
*/

$_action = $R['ACTION'] ?? null;
$R['ComposerAvailable'] = $C['Composer']->isAvailable();
$_id     = (string)($R['Module']['Id'] ?? '');
$_idOk   = (bool)preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $_id); # nur gültige Paketnamen, der Wert landet in einer Composer-Kommandozeile
$_result = null;
if (!$R['ComposerAvailable'] && in_array($_action, ['update-module', 'update-all', 'reinstall-module', 'remove-module', 'uninstall-module'], true)) {
	$R['ComposerOutput'] = ['success' => false, 'output' => \fremeo\core\PackageManager::UNAVAILABLE];
	$_action = null;
}
$_moduleRows = $D['MODULE']['D'];
$_moduleFilter['MODULE'] = [];
$C['fremeo/core']['CData']->get_object($_moduleState, $_moduleFilter);

# Aktionen ändern das System: nur per POST (nicht per Link/GET). Migrationen laufen nach install/update/remove automatisch (Composer-Skripte der composer.json)
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
	$_action = null;
}

if ($_action === 'save') {
	$_selected = array_keys(array_filter((array)($D['_MODULE_SELECTED'] ?? []), static fn ($value) => $value === '1' || $value === 1));
	$_bulkAction = $D['_MODULE_ACTION'] ?? '';
	$_invalid = array_filter($_selected, static fn ($id) => !preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $id) || !isset($_moduleRows[$id]) || !in_array($_moduleRows[$id]['INFO']['Type'], ['core', 'template', 'module'], true));
	if (!in_array($_bulkAction, ['activate', 'deactivate', 'update', 'reinstall', 'uninstall', 'clear-cache'], true)) {
		$R['ComposerOutput'] = ['success' => false, 'output' => 'Bitte eine Aktion waehlen.'];
	} elseif (!$_selected || $_invalid) {
		$R['ComposerOutput'] = ['success' => false, 'output' => 'Bitte vorhandene Module auswaehlen. Fuer Libraries sind keine Aktionen erlaubt.'];
	} elseif (in_array($_bulkAction, ['deactivate', 'uninstall'], true) && array_filter($_selected, static fn ($id) => $id === 'fremeo/core' || $_moduleRows[$id]['INFO']['Type'] === 'core')) {
		$R['ComposerOutput'] = ['success' => false, 'output' => 'Core darf nicht deaktiviert oder deinstalliert werden. Bitte die Auswahl anpassen.'];
	} elseif ($_bulkAction === 'clear-cache') {
		$_cacheLines = [];
		$_cacheSuccess = true;
		foreach ($_selected as $_moduleId) {
			// Nur den kanonischen Modulordner loeschen, niemals einen uebergebenen Pfad.
			$_cachePath = PROJECT_ROOT . 'data_c/' . str_replace('/', '~', $_moduleId);
			try {
				$C['Filesystem']->remove($_cachePath);
				$_cacheLines[] = "Cache fuer $_moduleId geleert.";
			} catch (\Symfony\Component\Filesystem\Exception\IOExceptionInterface $e) {
				$_cacheSuccess = false;
				$_cacheLines[] = "Cache fuer $_moduleId konnte nicht vollstaendig geleert werden: " . $e->getMessage();
			}
		}
		$R['ComposerOutput'] = ['success' => $_cacheSuccess, 'output' => implode("\n", $_cacheLines)];
		$C['LanguageVariable']->clearCache();
	} elseif (in_array($_bulkAction, ['activate', 'deactivate'], true)) {
		$_update = [];
		foreach ($_selected as $_moduleId) {
			$_update['MODULE']['D'][$_moduleId]['Active'] = $_bulkAction === 'activate' ? 1 : 0;
		}
		$C['fremeo/core']['CData']->set_object($_update);
		$C['LanguageVariable']->clearCache();
		$_moduleState = null;
		$C['fremeo/core']['CData']->get_object($_moduleState, $_moduleFilter);
		$R['ComposerOutput'] = ['success' => true, 'output' => count($_selected) . ' Modulstatus gespeichert. Die Aenderung gilt ab der naechsten Anfrage.'];
	} elseif (!$R['ComposerAvailable']) {
		$R['ComposerOutput'] = ['success' => false, 'output' => \fremeo\core\PackageManager::UNAVAILABLE];
	} else {
		$_command = ['update' => 'update', 'reinstall' => 'reinstall', 'uninstall' => 'remove'][$_bulkAction];
		$_result = $C['Composer']->run($_command . ' ' . implode(' ', array_map('escapeshellarg', $_selected)) . ($_bulkAction === 'update' ? ' --with-dependencies' : ''));
	}
}

# Einzelaktionen bleiben erhalten; Libraries und der Core-Uninstall sind auch serverseitig gesperrt.
if (in_array($_action, ['update-module', 'reinstall-module', 'remove-module', 'uninstall-module'], true)
	&& (!$_idOk || !isset($_moduleRows[$_id]) || !in_array($_moduleRows[$_id]['INFO']['Type'], ['core', 'template', 'module'], true)
		|| (in_array($_action, ['remove-module', 'uninstall-module'], true) && ($_id === 'fremeo/core' || $_moduleRows[$_id]['INFO']['Type'] === 'core')))) {
	$R['ComposerOutput'] = ['success' => false, 'output' => 'Diese Aktion ist fuer das ausgewaehlte Paket nicht erlaubt. Core kann nicht deinstalliert werden; Libraries werden ueber ihre abhaengigen Module verwaltet.'];
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

// Lokale Modultypen und gespeicherter Status duerfen nicht aus dem Paket-Cache stammen.
$R['ModuleTabs'] = [
	'active' => ['Label' => 'Aktive Module', 'Items' => []],
	'inactive' => ['Label' => 'Inaktive Module', 'Items' => []],
	'libraries' => ['Label' => 'Libraries', 'Items' => []],
];
foreach ($D['R']['Module']['D'] as $_moduleId => &$_package) {
	$_package['type'] = $_moduleRows[$_moduleId]['INFO']['Type'] ?? $_package['type'];
	$_package['Active'] = $_package['type'] === 'core' ? 1 : (int)($_moduleState['MODULE']['D'][$_moduleId]['Active'] ?? 1);
	$_group = !in_array($_package['type'], ['core', 'template', 'module'], true) ? 'libraries' : ($_package['Active'] ? 'active' : 'inactive');
	$R['ModuleTabs'][$_group]['Items'][$_moduleId] = $_package;
}
unset($_package);
