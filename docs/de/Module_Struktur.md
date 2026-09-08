## Modul-Struktur

Wenn das Core-Modul als Basis für die Abhängigkeiten aller Module verwendet wird, müssen die folgenden Regeln strikt eingehalten werden:

* **Ein-Core-Prinzip:** Es ist nicht möglich, mehrere Core-Module miteinander zu kombinieren oder zu harmonisieren. Jedes Core-Modul setzt eigene Regeln voraus, die potenziell im Konflikt mit anderen Core-Modulen stehen. Daher darf stets **nur ein Core-Modul pro Projekt** verwendet werden.
* **Abhängigkeiten definieren:** Alle weiteren Module müssen dieses Core-Modul unter dem Key `require` in ihrer eigenen `composer.json` angeben.

---

## Dateistruktur eines Moduls

Ein Modul, das auf dem Core aufbaut, muss folgender Struktur folgen:

* **docs/** – Beinhaltet die Dokumentation des Moduls.
    * ∟ **de / en / ...** – Sprachspezifische Unterordner.
* **system/** – Beinhaltet die PHP-Dateien, die primär zu den dazugehörigen Template-Dateien gehören.
    * **|- core/** *(optional)* – Beinhaltet die benötigten Klassen für das Modul.
    * **|- migration/** – Beinhaltet Migrationsdateien, die bei einer Neuinstallation oder einem Update ausgeführt werden.
    * **|- template/** – Beinhaltet die Smarty-Templates.
* **CHANGELOG** *(optional)* – Informationen zur Versionierung des Moduls.
* **composer.json** – Beinhaltet Metadaten zum Modul und dessen Abhängigkeiten.
* **ini.php** – Beinhaltet PHP-Code für die Initialisierung des Moduls.
* **README.md** *(optional)* – Beschreibung des Moduls.
* **cli.php** *(optional)* – Bestimmt für den Aufruf über das Command Line Interface (CLI), damit das Modul per Terminal-Befehl gesteuert werden kann. Dies ist wichtig für Backend-Steuerungen, die direkte CLI-Befehle absetzen, um Aktionen auszuführen.

---

## Namenskonvention für Templates und PHP-Dateien

### Smarty-Templates (`system/template/`)
Es wurde explizit auf **Smarty in der Version 4** gesetzt, da diese Version die Verknüpfung mehrerer Templates über die Funktion `display()` erlaubt. Dank dieser Verkettung lässt sich eine flexible Vererbungsstruktur über die Namensgebung definieren:
* **Vererbung:** Alle Templates erben standardmäßig von der `index.tpl`. Ein Child-Template muss daher beispielsweise `index__admin.tpl` benannt werden.
* **Tiefe Verkettung:** Weitere Verschachtelungen sind möglich. So ist `admin__startpage.tpl` wiederum ein Child-Template von `index__admin.tpl`.
* **Modul-Überlagerung:** Andere Module können diese spezifischen Templates überlagern. Wenn ein zweites Modul ebenfalls eine `admin__startpage.tpl` bereitstellt, werden dessen zusätzliche Informationen flexibel auf der Seite eingeblendet.

### PHP-Dateien im `system/`-Ordner
Für PHP-Dateien gilt dasselbe Prinzip. Wird eine `index__admin.php` definiert, wird diese bei der Anforderung von `index_admin.php` automatisch mit aufgerufen. 
* Die PHP-Datei ist **optional** und muss nicht zwingend als "Schatten" der Template-Datei angelegt werden.
* Gleichnamige PHP-Dateien aus verschiedenen Modulen werden ebenfalls sequenziell getriggert.

---

## Aufbau der `composer.json`

* **name** – Beinhaltet die Hersteller- und Modulbezeichnung im Format `Hersteller/Modul` (z. B. `"fremeo/blog"`).
* **description** – Beschreibt die Funktionalität des Moduls.
* **license** – Definiert die Lizenz des Moduls (z. B. `"proprietary"`).
* **type** – Bestimmt den Typ des Moduls. Erlaubt sind: `module`, `core`, `template`, `library`.
* **keywords** – Keywords zur Identifikation. Die ersten beiden Keywords müssen zwingend `"fremeo"` und `"fremeo-module"` lauten.
* **require** – Definiert die System- und Modulabhängigkeiten (z. B. `"php": "^8.0"`, `"fremeo/core": "^v0.0"`).
* **prefer-stable** – Standardmäßig auf `true` zu setzen.
* **psr-4** – Definiert das Autoloading für die Core-Klassen (z. B. `"fremeo\\blog\\": "system/fremeo/"`).

### Zulässige Modultypen (`type`)
* **module:** Erweiterungen der Anwendung, die zusätzliche Features oder Dienste bereitstellen und in die Hauptanwendung integriert werden.
* **core:** Das Herzstück der Anwendung. Es enthält die grundlegenden Kernfunktionen, die von allen anderen Modulen genutzt werden.
* **template:** Stellt die visuelle Darstellung (Layouts, Stylesheets, Skripte) bereit und definiert das Erscheinungsbild der Anwendung.
* **library:** Bietet wiederverwendbare Hilfsfunktionen, Dienstprogramme oder Klassen, die von anderen Modulen konsumiert werden können.

### Richtlinien für Keywords
Keywords steuern das Filtern und Suchen im **Module Store**. Sie müssen in exakt dieser Reihenfolge vergeben werden:
1. **Keyword 1:** `"fremeo"` – Kennzeichnet die Zugehörigkeit zur Plattform.
2. **Keyword 2:** `"fremeo-[Type]"` – Kennzeichnet den Typ des Moduls (z. B. `fremeo-module`).
3. **Keyword 3:** Der spezifische Name des Moduls zur eindeutigen Identifikation.
4. **Weitere Keywords (optional):** Freie Keywords, die den Zweck oder die Features des Moduls beschreiben.

---

## Richtlinien für die `cli.php`

Die CLI-Datei des Moduls ist in ihrer inneren Logik frei gestaltbar. Es muss jedoch zwingend sichergestellt werden, dass sie die Ausführung nachfolgender CLI-Dateien anderer Module **nicht beeinträchtigt**. 
* **Wichtig:** Die Verwendung von Funktionen wie `die()` oder `exit()` ist untersagt, wenn ein übergebener CLI-Befehl nicht dem eigenen Modul entspricht, da dies den gesamten CLI-Prozess vorzeitig abbricht.

---

## Core-Vorgaben & Datenbank-Pattern

Der Core gibt bestimmte Datenbank-Pattern (z. B. für *SEO-Links*, *Settings*, *Files*, etc.) **1:1 für alle Plugins** verbindlich vor.

* **Modulübergreifender Zugriff:** Dadurch wird sichergestellt, dass der Core einheitlich und modulübergreifend auf diese Daten zugreifen kann (z. B. für eine aggregierte Darstellung im Backend).
* **Gemeinsame Logik:** Zentrale Kernfunktionen verlassen sich darauf, dass diese spezifischen Daten in jedem Modul dieselbe Struktur aufweisen.
* **Wichtiger Hinweis:** Bei der Entwicklung eines Moduls dürfen diese globalen Pattern keinesfalls mit modul-spezifischen oder abweichenden Strukturen überschrieben werden.
