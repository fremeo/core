<?php
namespace fremeo\core;

/**
 * Die Klasse Migrations ist für die Verwaltung und Ausführung von Datenbankmigrationen innerhalb eines bestimmten Moduls verantwortlich. 
 * Sie ermöglicht das Auffinden, Ausführen und Zurücksetzen von Migrationen, die in einem definierten Verzeichnis innerhalb des Moduls gespeichert sind. 
 * Die Klasse verwendet die CData-Komponente, um den Status der Migrationen in der Datenbank zu speichern und sicherzustellen, dass Migrationen nicht mehrfach ausgeführt werden.
 */
class Migrations {
    function __construct($ModuleId, &$D) {
        $this->ModuleId = $ModuleId;
		$this->D = &$D;
		$this->C = $D['C'];
		$this->CData = $this->C[$ModuleId]['CData'];
    }

	/**
	 * Gibt eine Liste aller Migrationsdateien im Migrationsverzeichnis des Moduls zurück.
	 * @return array Eine Liste der Migrationsdateien ohne Dateiendung.
	 */
	function getMigrations() {
		$migrationPath = "system/vendor/{$this->ModuleId}/system/migration";
		$migrations = [];
		if (is_dir($migrationPath)) {
			foreach (scandir($migrationPath) as $file) {
				if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
					$migrations[] = pathinfo($file, PATHINFO_FILENAME);
				}
			}
		}
		return $migrations;
	}


	/**
	 * Finde die Migrationsdatei anhand der Migrations-ID. Die Migrations-ID ist der erste Teil des Dateinamens vor dem Unterstrich.
	 * @param string $migrationId Die Migrations-ID, die im Dateinamen der Migrationsdatei enthalten ist.
	 * @return string|null Der vollständige Dateiname der Migrationsdatei oder null, wenn keine Datei gefunden wurde.
	 */
	function findMigrationFile($migrationId) {
		$migrationPath = "system/vendor/{$this->ModuleId}/system/migration";
		if (is_dir($migrationPath)) {
			foreach (scandir($migrationPath) as $file) {
				if (pathinfo($file, PATHINFO_EXTENSION) === 'php' && strpos($file, $migrationId) === 0) {
					return $file;
				}
			}
		}
		return null;
	}

	function runMigrationUp($migrationId) {
		$migrationPath = "system/vendor/{$this->ModuleId}/system/migration/";
		$migrationFile = $this->findMigrationFile($migrationId);
		#Überprüfe ob die Migration bereits in der Datenbank gespeichert ist, wenn ja, dann nicht erneut ausführen.
		$f['MIGRATION']['W'][0]['Id'] = [$migrationId];
		$this->CData->get_object($_d, $f);
		if (empty($_d['MIGRATION']['D'][$migrationId])) {

			if (file_exists($migrationPath.$migrationFile)) {
				$D = &$this->D; #Damit die Migration auf die Daten zugreifen kann.
				$migration = require $migrationPath.$migrationFile;
				
				#if (class_exists('Migration')) {
					#$migration = new Migration($this->C);
					if (method_exists($migration, 'up')) {
						
						$migration->up();
						// Speichern in der Datenbank
						$d['MIGRATION']['D'][$migrationId] = [
							'File' => pathinfo($migrationPath.$migrationFile, PATHINFO_FILENAME),
							'Timestamp' => time()
						];
						$this->CData->set_object($d);
						return true;
					}
				#}
			}
		}
		return false;
	}

	function runMigrationDown($migrationId) {
		$migrationPath = $this->findMigrationFile($migrationId);
		#Überprüfe ob die Migration bereits in der Datenbank gespeichert ist, wenn ja, dann zurücksetzen.
		$f['MIGRATION']['W'][0]['Id'] = [$migrationId];
		$this->CData->get_object($_d, $f);
		if (!empty($_d['MIGRATION']['D'][$migrationId])) {

			if (file_exists($migrationPath)) {
				require_once $migrationPath;
				if (class_exists('Migration')) {
					$migration = new Migration($this->C);
					if (method_exists($migration, 'down')) {
						$migration->down();
						// Löschen aus der Datenbank
						$d['MIGRATION']['D'][$migrationId] = '__DELETE__';
						$this->CData->set_object($d);
						return true;
					}
				}
			}
		}
		return false;
	}
}