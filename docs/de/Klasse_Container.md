# Klasse `Container` (modulares Service- und Objekt-Registry)

`\fremeo\core\Container` – Datei: `system/core/Container.php` (PSR-4 Autoload `fremeo\core\`, kein `include` nötig).

| Abschnitt | Beschreibung |
|----------|--------------|
| **Zweck** | Die Klasse verwaltet globale oder modulbezogene Services und Objekte in einem zentralen Container. Sie dient als einfache Registry für Instanzen, Factory-Funktionen und Datenobjekte. |
| **Grundidee** | Ein Container kann beliebige Werte speichern. Dabei kann ein Eintrag global (`$C->set('Name', $value)`) oder eindeutig einem Modul zugeordnet werden (`$C->set('Name', $value, 'fremeo/blog')`). |
| **Zugriff** | Erlaubt sowohl Methodenaufrufe (`->set()`, `->get()`) als auch Array-Style-Zugriff (`$C['Name']`, `$C['fremeo/blog']['CData']`). |
| **Modul-Scoped** | Module können ihren eigenen Namespace innerhalb des Containers haben. Dadurch bleiben Services pro Modul sauber getrennt, ohne Schlüssel wie `fremeo/blog.CData` zusammensetzen zu müssen. |
| **Datenform** | Es können einfache Werte, Objekte, Klassennamen oder Closures gespeichert werden. Beim `get()`/`make()` wird ein Closure oder Klassenname nach Bedarf instanziiert. |

## Kernfunktionen

### `set(string $key, mixed $value, ?string $moduleId = null): self`

Legt einen Eintrag im Container an. Wenn `moduleId` gesetzt ist, wird der Eintrag im Modul-Container des angegebenen Moduls gespeichert.

```php
$C = new \fremeo\core\Container();

$C->set('Security', fn () => new \fremeo\core\Security());
$C->set('CData', $DB, 'fremeo/core');
$C->set('Link', new \fremeo\core\Link(), 'fremeo/blog');
```

Dabei entsteht keine zusammengesetzte Zeichenkette wie `fremeo/core.CData`. Der Modulname bleibt als eigener Container-Knoten erhalten.

### `get(string $key, ?string $moduleId = null, array $args = []): mixed`

Holt einen Eintrag aus dem Container. Wenn ein Wert als Closure oder Klassenname hinterlegt wurde, wird er beim Zugriff instanziiert.

```php
$security = $C->get('Security');
$cdata = $C->get('CData', 'fremeo/core');
```

### `getByModule(string $moduleId, string $key, array $args = []): mixed`

Kürzere, explizite Variante für modulbezogene Services.

```php
$CData = $C->getByModule('fremeo/core', 'CData');
$Link = $C->getByModule('fremeo/blog', 'Link');
```

### `make(string $key, array $args = [], ?string $moduleId = null): mixed`

Analoge Methode zu `get()`, aber semantisch etwas klarer, wenn ein Service bei Bedarf erzeugt werden soll.

```php
$service = $C->make('Security');
$service = $C->make('CData', [], 'fremeo/core');
```

## Array-Style-Zugriff

Die Klasse implementiert `ArrayAccess`, daher kann der Container wie ein Array benutzt werden.

```php
// Globaler Zugriff
$security = $C['Security'];

// Modulbezogener Zugriff
$moduleContainer = $C['fremeo/core'];
$cdata = $moduleContainer['CData'];

// Oder direkt über den Modul-Container
$cdata = $C['fremeo/core']['CData'];
```

Diese Schreibweise ist besonders nützlich, wenn Module und deren Services in einer strukturierten Form organisiert werden sollen.

## Property-Style-Zugriff

Zusätzlich zu `set()`/`get()` unterstützt die Klasse auch den klassischen Objektzugriff.

```php
$C->Security = fn () => new \fremeo\core\Security();
$security = $C->Security;
```

Das entspricht intern dem `__set()`/`__get()`-Mechanismus.

## Typische Einsatzmuster

### 1) Globale Services

```php
$C->set('Request', $request);
$C->set('Filesystem', $filesystem);

$request = $C->get('Request');
```

### 2) Modulgebundene Services

```php
$C->set('CData', $DB, 'fremeo/core');
$C->set('Link', new \fremeo\core\Link(), 'fremeo/blog');

$coreDb = $C->get('CData', 'fremeo/core');
$blogLink = $C->getByModule('fremeo/blog', 'Link');
```

### 3) Array-Style mit Modul-Container

```php
$C['fremeo/core']['CData'] = $DB;
$C['fremeo/blog']['Link'] = new \fremeo\core\Link();

$db = $C['fremeo/core']['CData'];
$link = $C['fremeo/blog']['Link'];
```

### 4) Factory/Closure-Registrierung

```php
$C->set('Security', fn () => new \fremeo\core\Security());

$security = $C->get('Security');
```

Hier wird die Factory erst beim ersten Zugriff ausgeführt. Das ist nützlich für Services, die nur bei Bedarf instanziiert werden sollen.

## Wichtigste Regeln

* `set()` ohne Modul-ID registriert den Service global.
* `set()` mit Modul-ID legt den Service im Modul-Container an.
* `get()` und `make()` arbeiten mit demselben Muster: global oder modulgebunden.
* Array-Style ist die kompakte Variante für modulare Strukturen: `$C['fremeo/blog']['Link']`.
* Der Container ist bewusst einfach gehalten und soll als zentrale Registry für den Rahmen des Projekts dienen, nicht als voller Dependency-Container.

## Beispiel: Verwendung in einem Modul

```php
$C = new \fremeo\core\Container();

$C->set('CData', $db, 'fremeo/blog');
$C->set('Config', new \fremeo\core\Config(), 'fremeo/blog');

$blogDb = $C->get('CData', 'fremeo/blog');
$config = $C['fremeo/blog']['Config'];
```

Damit lassen sich dieselben Daten und Services in einem Modul sauber kapseln, ohne globale Schlüssel oder Laufzeit-Fehler durch SQL/Array-Kollisionen.

## Fazit

Der Container ist die zentrale Stelle, um Services und Daten in einem modularen System zu registrieren und sauber wieder abzurufen. Er kombiniert die Vorteile einer einfachen Objekt-Registry mit einem moduligen Zugriffsmuster und lässt sich sowohl per Methoden als auch per Array-Style verwenden.
