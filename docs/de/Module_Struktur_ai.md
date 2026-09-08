# Modul-Struktur und Aufbau eines fremeo-Moduls

## Zweck dieser Beschreibung

Diese Doku beschreibt die verbindlichen Regeln für die Erstellung eines Moduls im fremeo-Ökosystem. Sie fasst die Struktur und die technischen Konventionen zusammen, die für ein korrektes, kompatibles und wartbares Modul relevant sind.

Sie basiert auf den bestehenden Konstrukten im Core und auf den realen Referenzmodulen wie `fremeo/blog`, `fremeo/page`, `fremeo/shop` sowie auf dem Core selbst.

Das Ziel ist nicht, einen technischen Sonderweg zu definieren, sondern eine klare Beschreibung der Standards, nach denen Module in diesem System aufgebaut werden.

---

## 1. Grundprinzip: Ein Modul entsteht als eigenständiges Repository

Ein fremeo-Modul wird in der Regel als eigenes Repository entwickelt. Es folgt den Composer- und Paketregeln von PHP/Packagist und wird erst durch die Installation bzw. den Composer-Workflow in das Projekt eingebunden.

Das bedeutet:

- Das Modul selbst liegt nicht als „inneres Projekt“ im eigenen Projektordner.
- Es wird als separates Paket gepflegt.
- Beim Installieren via Composer wird es in das Laufzeitverzeichnis des Projekts übernommen.
- Im laufenden Projekt erscheint es dann in einer Struktur wie `system/vendor/...`, aber das ist nur der installierte Runtime-Stand, nicht der eigentliche Entwicklungsort.

Das Core erkennt Module automatisch aus dem installierten Paketbestand und liest dabei die Metadaten aus den Composer- und Projektinformationen.

Wichtige Folge:

- Der eigentliche Modul-Code sollte in einem eigenen Repository entstehen.
- `system/vendor` ist ein Installations-/Laufzeitbereich, kein normaler Erstellungsort für neue Module.
- Der Paketname, der Composer-Name und die Modul-ID müssen zueinander passen.

---

## 2. Verbindliche Modul-Struktur

Ein gültiges Modul besteht in der Regel aus folgenden Einträgen:

```text
{vendor}/{package}/
├── docs/
│   ├── de/
│   ├── en/
│   └── ...
├── system/
│   ├── core/                  (optional)
│   ├── migration/             (optional, aber standardisiert)
│   ├── template/              (standardisiert für Smarty)
│   ├── *.php                  (modulspezifische Seiten-/Logikdateien)
│   └── ...
├── CHANGELOG                  (optional)
├── composer.json
├── init.php                   (standardisiert für Initialisierung)
├── README.md                  (optional, aber empfohlen)
├── cli.php                    (optional, wenn CLI-Funktionen erforderlich sind)
├── start.php                  (optional, für eigene Startlogik)
└── ...
```

### Detaillierte Bedeutung

- `docs/`
  Enthält Dokumentation zum Modul. Sprachordner wie `de/`, `en/` sind üblich.

- `system/`
  Enthält die eigentliche Modul-Logik und die Dateien, die zu den Templates gehören.

- `system/core/`
  Optional. Hier können Klassen, Hilfsfunktionen und eigene Modul-Services liegen.

- `system/migration/`
  Enthält Migrationsdateien. Diese werden beim Installieren oder Aktualisieren des Moduls ausgeführt.

- `system/template/`
  Enthält Smarty-Templates. Dateinamen folgen festen Namensmustern, damit das Core sie automatisch findet und vererben kann.

- `composer.json`
  Enthält die Meta-Informationen des Moduls und die Abhängigkeiten.

- `init.php`
  Wird vom Core automatisch geladen. Hier werden Konfigurationen, Datenmodelle, CData-Patterns und Modulregistrierungen gesetzt.

- `README.md`
  Beschreibung des Moduls, Installationshinweise und Nutzung.

- `CHANGELOG`
  Optionale Versionshistorie.

---

## 3. Composer-Konventionen

Die `composer.json` ist verpflichtend. Diese Datei definiert den Modultyp, den Paketnamen, die Abhängigkeiten und das PSR-4-Namespace-Mapping.

### Pflichtfelder

```json
{
  "name": "fremeo/blog",
  "description": "Blog",
  "license": "proprietary",
  "type": "module",
  "keywords": ["fremeo", "fremeo-module", "blog"],
  "require": {
    "php": "^8.0",
    "fremeo/core": "^v0.0"
  },
  "prefer-stable": true,
  "psr-4": {
    "fremeo\\blog\\": "system/fremeo/"
  }
}
```

### Pflicht-Interpretation

- `name`
  Muss das Format `vendor/package` verwenden.
  Beispiel: `fremeo/blog`

- `description`
  Kurzbeschreibung des Moduls.

- `license`
  Typischerweise `proprietary` oder eine passende Lizenz.

- `type`
  Muss einer der bekannten Typen sein:
  - `module`
  - `core`
  - `template`
  - `library` (in der Praxis wird häufig `module` oder `template` verwendet)

- `keywords`
  Die ersten beiden Keywords sind besonders wichtig:
  - `fremeo`
  - `fremeo-module` oder `fremeo-core` oder `fremeo-template`

  Danach folgt der modul-spezifische Name, z. B.:
  - `blog`
  - `page`
  - `shop`

- `require`
  Enthält die technischen Abhängigkeiten.
  In der Praxis ist `fremeo/core` typischerweise zwingend.

- `prefer-stable`
  Muss normalerweise `true` sein.

- `psr-4`
  Definiert das Namespace-Mapping für PHP-Klassen.
  Beispiel:
  `"fremeo\\blog\\": "system/fremeo/"`

### Wichtige Regeln für die Paketdefinition

1. `name` = `{vendor}/{package}`
2. `keywords[0]` = `fremeo`
3. `keywords[1]` = `fremeo-{type}`
4. `keywords[2]` = modul-spezifischer Name
5. `require.php` muss gesetzt sein
6. `require.fremeo/core` muss gesetzt sein, wenn das Modul auf das Core angewiesen ist
7. `psr-4` muss mit dem Modulnamen zusammenpassen

---

## 4. Modultypen und ihre Bedeutung

### 4.1 `module`
Ein Modul erweitert die Anwendung mit zusätzlicher Funktionalität.

Beispiele:

- Blog
- Shop
- CMS-Seiten
- Benutzerverwaltung
- API-Module

### 4.2 `core`
Das Core-Modul bildet das Grundgerüst der Anwendung und enthält die zentralen Funktionen.

Es definiert:

- allgemeine Datenmodellregeln
- Modulregistrierung
- Template-Erweiterung
- Link- und SEO-Mechanismen
- Globales CData-Setup

### 4.3 `template`
Ein Template-Modul stellt nur die visuelle Darstellung bereit.

Es enthält in der Regel:

- Templates
- Stylesheets
- JavaScript
- Layouts

### 4.4 `library`
Ein Library-Modul stellt wiederverwendbare Funktionen oder Klassen bereit, die von anderen Modulen verwendet werden können.

---

## 5. Initialisierungslogik: `init.php`

Die Datei `init.php` ist entscheidend. Sie wird durch das Core nach dem Moduls-Scan geladen.

Das Core erkennt dabei die Module, verarbeitet ihre Metadaten und lädt anschließend für jedes Modul, das nicht das Core selbst ist, die Datei `init.php`.

Damit werden registrierbare Konfigurationen, Datenmodelle und Parameter für das Modul aktiv.

### Grundprinzip

```php
<?php

$Pattern = [];

$Pattern['BLOG'] = [
    'Active'    => ['Type' => 'checkbox'],
    'DateTime'  => ['Type' => 'text'],
    'MainImg'   => ['Type' => 'id'],
];

$Pattern['BLOG']['D']['LANGUAGE'] = [
    'Title'     => ['Type' => 'text'],
    'Text'      => ['Type' => 'text'],
    'ShortText' => ['Type' => 'text'],
    'LinkId'    => ['Type' => 'id'],
];

$C['fremeo/blog']['CData'] = new \phploader\CData([
    'DB' => [
        'FILENAME' => PROJECT_ROOT . 'data/fremeo~blog/data.db',
        'FILENAME_C' => PROJECT_ROOT . 'data_c/fremeo~blog/data.db'
    ]
]);

$C['fremeo/blog']['CData']->registerPattern($Pattern);
```

### Regeln für `init.php`

1. Die Datei muss PHP-Code enthalten.
2. Sie kann CData-Patterns definieren.
3. Sie kann moduleigene Datenobjekte instanziieren.
4. Sie muss mit dem Modul-`Id` als Schlüssel arbeiten.
5. Sie darf kein globales Verhalten anderer Module überschreiben.
6. Sie muss sicherstellen, dass der Modulpfad in `$C[$moduleId]` konsistent bleibt.

Leere `init.php`-Dateien sind nicht zulässig, wenn das Modul Daten, CData-Patterns oder Modul-Setups benötigt.

---

## 6. CData und Datenmodell

CData ist ein zentrales Element im System. Das Core registriert globale Muster und Module können eigene Muster ebenfalls definieren.

### Beispielmuster

```php
$Pattern = [
    'SETTING' => [
        'Active' => ['Type' => 'checkbox'],
        'ParentId' => ['Type' => 'id', 'ForeignKey' => 1],
        'Value' => ['Type' => 'text'],
    ],
    'USER' => [
        'Active' => ['Type' => 'checkbox'],
        'Name' => ['Type' => 'text'],
        'Mail' => ['Type' => 'text'],
        'Password' => ['Type' => 'text'],
    ],
];
```

### Regeln

- Jedes relevante Modul-Datenmodell sollte in `$Pattern` registriert werden.
- Eine Datentabelle wird oft als Top-Level-Muster definiert.
- Untergeordnete Datenstrukturen können mit `['D'][...]` ergänzt werden.
- Felder sollten exemplarisch mit `Type`-Angaben versehen werden.

### Typen

Gängige Feldtypen im System sind:

- `checkbox`
- `text`
- `number`
- `id`

Eine genaue Datenstruktur sollte immer mit den tatsächlichen Bedürfnissen des Moduls übereinstimmen. Wenn keine Felder definiert sind, sollte ein minimales, sinnvolles Grundmodell verwendet werden, nicht eine unklare oder generische Annahme.

---

## 7. Template-Namenskonventionen

Das Core nutzt eine automatische Template- und PHP-Vererbung basierend auf Dateinamen.

### Grundprinzip

- `system/template/{page}.tpl`
- `system/template/{base}__{sub}.tpl`
- `system/template/{base}__{base}.tpl`

### Beispiel

- `frontend__blog.list.tpl`
- `admin__blog.list.tpl`
- `admin__admin.tpl`
- `index.tpl`

Das Core verwendet eine Funktion `getExtends()`, die Templates und passende PHP-Dateien automatisch ermittelt und vererbt.

### Regeln

1. Dateinamen müssen mit den Seiten-/Routennamen übereinstimmen.
2. Das Core erkennt Typen wie `admin__...`, `frontend__...`, `account__...`.
3. Eine Seite kann durch Oberklassen vererbt werden.
4. PHP-Dateien folgen automatisch dem Template-Namensschema.

### Beispielmapping

```text
template/frontend__blog.list.tpl
=> system/frontend__blog.list.php
```

Ein Modul muss zu jeder Seite ein passendes Template anlegen und dabei die vorhandene Namenskonvention einhalten. Wenn ein Modul nur Teilfunktionen enthält, sind die Standardmuster ausreichend und sollten nicht künstlich überladen werden.

---

## 8. Seiten- und Routing-Konventionen

Im Core werden Seiten über `R['Page']` und `R['ModuleId']` gesteuert.

Beispiele:

- `R[Page] = admin__blog.list`
- `R[Page] = frontend__blog`
- `R[Page] = account__order`

### Routing-Regel

Ein Modul muss für seine eigenen Seiten logisch benannte Dateien enthalten, damit das Core sie in den Standard-Template-Pfad laden kann.

Das Modul kann also z. B. folgende Dateien haben:

```text
system/template/
├── admin__admin.tpl
├── admin__blog.list.tpl
├── admin__blog.edit.tpl
├── frontend__blog.list.tpl
├── frontend__blog.tpl
└── index.tpl
```

Das entspricht dem allgemeinen Muster in realen Modulen.

---

## 9. Migrationen

Migrationsdateien liegen unter:

```text
system/migration/
```

Das Core erkennt sie automatisch über den Dateinamen und führt sie per Migration-Mechanismus aus.

### Beispielnamen

- `00000000000000_install.php`
- `20260219000000_update.php`

### Regeln

- Jede Migration muss als PHP-Datei vorliegen.
- Der Dateiname sollte eindeutig und zeitlich sortierbar sein.
- Die Migration darf nur das eigene Modul betreffen.
- Die Migration muss den Status in der Datenbank hinterlegen, damit sie nicht doppelt ausgeführt wird.

Wenn ein Modul neue Datenmodelle oder Tabellen definiert, muss eine erste Migrationsdatei mit Standard-Installationslogik angelegt werden.

---

## 10. Modul-Initialisierung: Mindestanforderungen

Ein Modul kann nur dann als gültig gelten, wenn die folgenden Elemente vorhanden sind:

### Pflicht

- `composer.json`
- `init.php`
- `system/`
- `system/template/`
- passende Template-Dateien
- optional `system/migration/`
- passende Migrationsdateien, falls Datenbank-Initialisierung nötig ist

### Empfohlen

- `README.md`
- `CHANGELOG`
- `docs/de/`
- `docs/en/`
- `cli.php` nur wenn CLI-Funktionen benötigt werden
- `start.php` nur wenn eigene Bootlogik nötig ist

---

## 11. Reale Referenzmuster aus dem Projekt

Die Struktur wird durch reale Module bestätigt:

- `fremeo/blog` enthält:
  - `composer.json`
  - `init.php`
  - `system/template/...`
  - `system/migration/...` (falls vorhanden)

- `fremeo/page` enthält:
  - modulare administrative Seiten
  - Migrationen
  - Template-Namen mit `admin__...` und `frontend__...`

- `fremeo/shop` enthält ähnliche Modul-Mechaniken mit eigenen Seiten und Datenmustern.

- `fremeo/core` bildet den zentralen Referenzpunkt:
  - Module werden automatisch erkannt
  - `init.php` lädt alle Module
  - `start.php` ersetzt Templates basierend auf `getExtends()`
  - Routing und CData-Registrierung werden hier definiert

Diese Referenzen zeigen: Ein Modul ist nur dann korrekt, wenn es die Namens- und Verzeichnisregeln des Core respektiert.

---

## 12. Regeln für die Modul-Erstellung

Ein neues Modul muss die folgenden Regeln einhalten:

### 12.1 Ordnerregeln

- Das Modul wird als eigenständiges Paket entwickelt.
- Der Paketname, der Ordnername und der Namespace dürfen nicht widersprüchlich sein.
- `system/vendor` ist kein normaler Entwicklungsort; nur der installierte Zustand liegt dort.

### 12.2 Composer-Regeln

- `name` muss exakt in `vendor/package` Form sein
- `keywords` müssen mit `fremeo` beginnen
- `type` muss einem gültigen Modultyp entsprechen
- `require` darf nicht fehlen

### 12.3 Initialisierungsregeln

- `init.php` muss das Modul für das Core initialisieren
- CData-Patterns nur dann, wenn erforderlich
- `$C`-Instanz mit Modul-ID anlegen

### 12.4 Template-Regeln

- Dateinamen entsprechend der Seite oder des Moduls wählen
- `__`-Vererbung respektieren
- `admin__...`, `frontend__...`, `account__...` als Standardmuster verwenden

### 12.5 Migrationen

- nur dann anlegen, wenn wirklich nötig
- Zeitstempel-/Präfix-Namenskonvention einhalten

### 12.6 Dokumentation

- `README.md` und `docs/de/` sollten das Modul beschreiben
- Die Dokumentation muss klar, technisch und verständlich für Entwickler sein

---

## 13. Beispiel: Standard-Entwurf für ein neues Modul

```text
{vendor}/{package}/
├── composer.json
├── init.php
├── README.md
├── CHANGELOG
├── docs/
│   └── de/
│       └── README.md
├── system/
│   ├── core/
│   │   └── MyFeature.php
│   ├── migration/
│   │   └── 00000000000000_install.php
│   ├── template/
│   │   ├── admin__admin.tpl
│   │   ├── admin__myfeature.list.tpl
│   │   ├── admin__myfeature.edit.tpl
│   │   ├── frontend__myfeature.tpl
│   │   └── index.tpl
│   └── myfeature.php
└── cli.php
```

### Beispiel `composer.json`

```json
{
  "name": "fremeo/myfeature",
  "description": "MyFeature",
  "license": "proprietary",
  "type": "module",
  "keywords": ["fremeo", "fremeo-module", "myfeature"],
  "require": {
    "php": "^8.0",
    "fremeo/core": "^v0.0"
  },
  "prefer-stable": true,
  "psr-4": {
    "fremeo\\myfeature\\": "system/core/"
  }
}
```

### Beispiel `init.php`

```php
<?php

$Pattern = [];

$Pattern['MYFEATURE'] = [
    'Active' => ['Type' => 'checkbox'],
    'DateTime' => ['Type' => 'text'],
    'Title' => ['Type' => 'text'],
    'Text' => ['Type' => 'text'],
];

$C['fremeo/myfeature']['CData'] = new \phploader\CData([
    'DB' => [
        'FILENAME' => PROJECT_ROOT . 'data/fremeo~myfeature/data.db',
        'FILENAME_C' => PROJECT_ROOT . 'data_c/fremeo~myfeature/data.db'
    ]
]);

$C['fremeo/myfeature']['CData']->registerPattern($Pattern);
```

---

## 14. Abschlussregel

Ein Modul ist in fremeo nur dann korrekt, wenn es:

- als eigenes Paket/Repository entwickelt wird,
- den Composer- und Namespace-Konventionen folgt,
- `init.php` korrekt initialisiert,
- Templates nach dem Core-Namensschema benennt,
- gegebenenfalls Migrationsdateien und CData-Patterns enthält,
- und nach der Installation automatisch vom Core erkannt wird.

`system/vendor` ist dabei die Laufzeitumgebung des installierten Moduls, nicht der eigentlich zu pflegende Entwicklungsort.

---

## 15. Kurzform zur praktischen Verwendung

Für ein neues Modul sollten die wesentlichen Angaben grundsätzlich so aussehen:

```text
Modulname: {vendor}/{package}
Typ: module|core|template|library
Beschreibung: kurze Beschreibung
Keywords: fremeo, fremeo-{type}, {package}
Abhängigkeit: fremeo/core
Namespace: fremeo\{package}
Repository-Root: eigenes Modul-Repository
Installationsort im Projekt: system/vendor/{vendor}/{package}
Dateien: composer.json, init.php, system/template/
Optionale Daten: CData-Pattern, Migrationen, README.md, docs/
```

Damit ist das Modul nach den vorhandenen fremeo-Konventionen korrekt aufgebaut und kann über Composer im Projekt verfügbar gemacht werden.
