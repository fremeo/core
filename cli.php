<?php
// 1. Sicherheits-Check: Darf NUR über die Kommandozeile ausgeführt werden
if (PHP_SAPI !== 'cli') {
    die("Fehler: Dieses Skript darf nur über das Terminal ausgeführt werden.\n");
}

#include_once __DIR__ . '/core/init.php'; 

/**
 * PHP füllt im CLI-Modus das Array $argv automatisch:
 * $argv[0] = "system/cli.php" (Der Dateiname selbst)
 * $argv[1] = "migration"      (Das erste Argument)
 * $argv[2] = "modulname"      (Das zweite Argument / Optional)
 */

$befehl = $argv[1] ?? null;
$target = $argv[2] ?? '*'; // Wenn kein Modul angegeben ist, gilt '*' für alle

// 2. Prüfen, ob der Befehl 'migration' lautet
if ($befehl === 'migration') {
    echo "----------------------------------------\n";
    echo " MIGRATION GESTARTET\n";
    echo "----------------------------------------\n";
    echo "Ziel-Bereich: " . $target . "\n";
 
    include_once __DIR__ . '/system/core/Migrations.php'; // Hier können Sie Ihre bestehende Migrationslogik einbinden
    
	
	if ($target === '*') {
        echo "-> Führe Migration für ALLE Module aus...\n";
        // Hier rufen Sie die Logik für alle Module auf
		foreach ($D['MODULE']['D'] as $kModule => $Module) {
			if( $C[$kModule]['CData']??false ) {
				$Migrations = new fremeo\core\Migrations($kModule, $D);
				echo "-> Führe Migration für Modul [" . $kModule . "] aus...\n";
				$_mig = $Migrations->getMigrations();
				
				foreach($_mig as $mig) {
					if($Migrations->runMigrationUp($mig) ) {
						echo "-> Migration [" . $mig . "] erfolgreich ausgeführt.\n";
					}
				}
			}
		}
    } else if (($D['MODULE']['D'][$target] ?? null) && ($D['MODULE']['D'][$target]['Active'] ?? 0) == 1) {
		$Migrations = new fremeo\core\Migrations($target, $D);
        echo "-> Führe Migration NUR für das Modul [" . $target . "] aus...\n";
        // Hier rufen Sie die Logik für das spezifische Modul auf
        $_mig = $Migrations->getMigrations();
        foreach($_mig as $mig) {
            echo "-> Führe Migration [" . $mig . "] aus...\n";
            $Migrations->runMigrationUp($mig);
        }
    } else {
        echo "Fehler: Das angegebene Modul [" . $target . "] existiert nicht oder ist nicht aktiv.\n";
    }
    echo "----------------------------------------\n";
}
else if ($befehl === 'uninstall') {
    # 2. Prüfen, ob der Befehl 'uninstall' lautet
    echo "----------------------------------------\n";
    echo " MODUL DEINSTALLATION GESTARTET\n";
    echo "----------------------------------------\n";
    
    # ToDo: Prüfe alle Ordner in data_c und vergleiche mit ordnern aus system/vendor. Wenn ein Modul nicht mehr existiert, dann lösche den Ordner in data_c reqursive.
    
	#1. gehe alle Ordner in data_c durch. Ersetze ~ durch / um richtigen Pfad zu erhalten. 
	#2. Prüfe ob der Ordner in system/vendor exsistiert. Wenn nicht, dann lösche den Ordner in data_c reqursive.
	$data_c_path =  PROJECT_ROOT . '/data_c/';
	$vendor_path = __DIR__ . '/system/vendor/';
	
	$data_c_dirs = scandir($data_c_path);
	foreach ($data_c_dirs as $dir) {
		if ($dir === '.' || $dir === '..' || !is_dir($data_c_path . $dir)) {
			continue;
		}
		
		$module_dir = str_replace('~', '/', $dir);
		if (!is_dir($vendor_path . $module_dir)) {
			echo "-> Modul [" . $module_dir . "] existiert nicht mehr. Lösche Temp Ordner '{$data_c_path}{$dir}'\n";
			// Lösche den Ordner in data_c rekursiv
			$C['CFile']::remove($data_c_path .'*');
			echo "-> Ordner [" . $dir . "] erfolgreich gelöscht.\n";
		}
	}
	
	echo "----------------------------------------\n";
	
}
