# Klasse `File` (Dateien eines Moduls hochladen und löschen)

`\fremeo\core\File` – Datei: `system/core/File.php` (PSR-4 Autoload `fremeo\core\`, kein `include` nötig).

| Abschnitt | Beschreibung |
|----------|--------------|
| **Zweck** | Dateien aus `$_FILES` in den Datenordner **des eigenen Moduls** speichern und in dessen CData-Datenbank (`FILE`) eintragen. Löschen entfernt Datei, DB-Eintrag und erzeugte Bildvarianten. |
| **Instanz** | Jedes Modul mit CData bekommt in `init.php` (Phase 2) eine an das Modul **gebundene** Instanz: `$C['vendor/modul']['File']`. Die Modul-ID wird nicht übergeben, sondern steht im Objekt. Ein Modul nutzt nur seine eigene Instanz. |
| **Speicherort** | `data/{vendor}~{modul}/file/{md5}.{ext}` – Dateiname ist der md5 des Inhalts (= Datei-ID, gleicher Inhalt = gleiche ID). |
| **Datenbank** | `FILE` → `D[{md5}] = ['Name' => ohne Endung, 'Size' => Bytes, 'Extension' => klein]` (globales Pattern, gilt für alle Module). |

## Verwendung

```php
// Hinzufügen: $_FILES['images'] einzeln oder mehrfach (multiple)
$files = $C['fremeo/blog']['File']->add($_FILES['images']);
// ['<md5>' => ['Name' => 'bild', 'Size' => 1234, 'Extension' => 'jpg'], ...]

// Löschen: eine ID oder Array von IDs
$C['fremeo/blog']['File']->delete($id);
$C['fremeo/blog']['File']->delete([$id1, $id2]);
```

| Methode | Rückgabe | Verhalten |
|---------|----------|-----------|
| `add(array $upload)` | `ID => [Name, Size, Extension]` | Speichert nur echte Uploads (`is_uploaded_file`). Endungen wie `php`, `phtml`, `phar`, `htaccess` werden übersprungen. Ein einziger `set_object`-Aufruf für alle Dateien. |
| `delete(string\|array $ids)` | `void` | Löscht nur IDs, die in der **eigenen** Modul-DB stehen: Datei, `FILE`-Eintrag und alle Varianten `{id}_*` in `data_c/fremeo~core/file/`. Unbekannte IDs werden ignoriert. |

## Wichtig
* **Nur eigener Ordner:** Technisch erzwungen wird es nicht (`$C` ist global), es ist eine Konvention. Nie in `data/` anderer Module schreiben.
* **Verweise** auf die Datei-ID in eigenen Tabellen (z. B. `FileId` in der Galerie) muss das Modul selbst entfernen.
* **Bildausgabe:** Skalierte Bilder liefert `get_file.php` über `/file/{id}_{x}x{y}.{ext}`; erzeugte Varianten liegen zentral in `data_c/fremeo~core/file/` (Apache liefert vorhandene Dateien direkt aus).
* Ersetzt die frühere globale Funktion `FileUpload()` und die frühere Klasse `CFile` (stattdessen `$C['Filesystem']` und `$C['Finder']` von Symfony).
* Pfade sind relativ zum Projektroot (Arbeitsverzeichnis).
* Tests: `tests/FileTest.php`.
