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

