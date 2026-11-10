## Beschreibung
Das phpApp‑Modul bildet das offizielle Framework der phpApp‑Software. 
Es stellt die grundlegende Funktionalität bereit, auf der alle kompatiblen phpApp‑Module aufbauen.

### User‑Verwaltung
Effiziente Verwaltung von Benutzerkonten, Profilen und Zugangsdaten für eine klare und strukturierte Systemorganisation.

### Rechte‑Verwaltung
Feingranulare Steuerung von Berechtigungen, um sicherzustellen, dass jeder Nutzer nur auf die für ihn vorgesehenen Funktionen zugreifen kann.

### Sicherheitsmaßnahmen
Integrierte Schutzmechanismen wie Zugriffskontrollen, Validierungen und Monitoring sorgen für eine sichere und stabile Systemumgebung.

### Datenbank
Zuverlässige und performante Datenhaltung mit optimierten Abfragen für schnelle Ladezeiten und hohe Skalierbarkeit.

### Caching‑Management
Intelligente Zwischenspeicherung von Daten reduziert Serverlast und verbessert die Reaktionsgeschwindigkeit der gesamten Anwendung.

### Modul‑Verwaltung
Flexible Verwaltung einzelner Softwaremodule ermöglicht eine einfache Erweiterung, Aktualisierung und Anpassung des Systems.

#### Updates und Wiederherstellung

- Der Modul-Store fuegt ausgewaehlte Pakete dem Hauptprojekt mit `*` hinzu. Die angezeigte neueste Version wird nicht festgeschrieben; konkrete installierte Versionen stehen in `composer.lock`, Kompatibilitaetsgrenzen in den Modulmanifesten. Bestehende feste Root-Constraints werden nicht automatisch geaendert.
- „Alle aktualisieren“ fuehrt `composer update` aus. Einzel- und Sammelaktionen nutzen ebenfalls Composer; es gibt keine vorgelagerte eigene Loeschroutine.
- Composer-Klasse und PHAR liegen ausschliesslich im Loader unter `setup/Composer.php` und `setup/composer.phar`, ausserhalb von `vendor`. Der Core-Dienst `PackageManager` liest installierte Pakete selbst und delegiert Schreibaktionen an diese optionale Setup-Klasse. Es gibt keine Core-Kopie und keine Laufzeitkopien mehr.
- Fehlt eine der beiden lesbaren Setup-Dateien, funktionieren Website, Modulliste, Suche, Aktivierung und Cacheleeren weiter. Installation, Update, Reinstall und Deinstallation sind mit sichtbarem Hinweis deaktiviert und serverseitig gesperrt. Das geschuetzte Setup kann zur Wiederfreigabe wieder bereitgestellt werden. Setup-Dateien niemals waehrend laufender Paketaktionen ersetzen oder entfernen.
- Composer laedt die benoetigten Pakete vor dem Installationsschritt. Beim Ersetzen werden die alten Dateien entfernt; scheitert danach z.B. das Entpacken, kann ein Modul trotzdem fehlen. Composer-Updates sind keine atomare Transaktion und haben keinen vollstaendigen automatischen Rollback. Vorher Dateien und Datenbanken sichern.
- Der Wrapper wertet Exitcodes und Skriptfehler aus, uebernimmt stdout/stderr in die Backend-Ausgabe und verhindert parallele Paketaktionen desselben Projekts. Er beendet einen Dateiaustausch nicht nach einem eigenen 300-Sekunden-Limit und laesst ihn bei einem Browserabbruch weiterlaufen. Harte Grenzen des Hosters, Speicher-/Platzmangel und externe SSH-Composer-Aufrufe bleiben davon unberuehrt.
- Eine PHP-CLI der eingesetzten Hosting-Version verwenden; CloudLinux/alt-php wird bei der Erkennung beruecksichtigt.

Fehlt nach einem abgebrochenen Update bereits `fremeo/core/init.php`, ist das Backend nicht mehr verwendbar. Nach einer Sicherung im Projektordner per SSH/Cron (mit der passenden PHP-CLI) reparieren:

```sh
php setup/composer.phar reinstall fremeo/core fremeo/frontend --prefer-dist --no-interaction --no-scripts
php setup/composer.phar install --prefer-dist --no-interaction --no-scripts
php index.php migration
```

Jeden Befehl nur nach erfolgreichem Abschluss des vorherigen ausfuehren. `reinstall` stellt die laut installierten Composer-Metadaten vorhandenen Pakete wieder her; `install` gleicht anschliessend den Stand mit `composer.lock` ab. Sind auch die Composer-Metadaten beschaedigt, die Sicherung wiederherstellen. Nicht zuerst `composer.lock` oder den gesamten Vendor-Ordner loeschen. Ein einfaches `install` allein repariert als installiert vermerkte, aber leere Paketordner nicht zuverlaessig. `index.php` muss dafuer nicht angepasst werden.

### SEO‑Management
Zentrale Werkzeuge zur Optimierung von Suchmaschinen‑Relevanz sorgen dafür, dass Inhalte besser gefunden werden und die Sichtbarkeit der Anwendung nachhaltig steigt.

### Medienverwaltung (z. B. Bilder)
Eine strukturierte Verwaltung von Bildern und anderen Medien ermöglicht schnelles Hochladen, Organisieren und Bereitstellen visueller Inhalte im gesamten System.

### Statistiken
Aussagekräftige Analyse‑ und Auswertungsfunktionen liefern wertvolle Einblicke in Nutzung, Performance und Benutzerverhalten, um fundierte Entscheidungen treffen zu können.

### Sprachverwaltung

## Architekturkonzepte

- [Templates, Zweige und Themes](docs/Konzept_Template_Theme.md) - geplante Vererbung, Einstellungen, Asset-Verarbeitung und statische Theme-Auslieferung.

## Installationsanleitung
Für die Installation des phpApp‑Systems wird der phpApp Loader benötigt. Dieser muss als erstes auf dem System eingerichtet werden. Beim Ausführen installiert der Loader automatisch das phpApp‑Framework, legt alle notwendigen Standardwerte an und erzeugt einen initialen Administrator‑Zugang.

Die eigentliche Installation erfolgt über die Datei setup.php, die im Root‑Verzeichnis abgelegt und anschließend über den Browser aufgerufen wird. Der Installationsprozess läuft vollständig automatisiert ab.

Nach erfolgreicher Einrichtung meldet man sich im Backend an und ändert das Standardpasswort des Administrator‑Kontos, um die Sicherheit des Systems zu gewährleisten.

Die Grundinstallation entspricht einer Art „Stammzelle“. Erst durch die individuelle Installation der benötigten Module entwickelt sich das System zu der Lösung, die für das jeweilige Projekt vorgesehen ist.
Die Modulinstallation erfolgt bequem über das Backend. Es stehen verschiedene Module zur Verfügung, die – sofern nicht anders definiert – frei miteinander kombiniert werden können. So lassen sich beispielsweise die Module Blog, Page und Shop gemeinsam nutzen, um eine funktionsreiche Website aufzubauen.