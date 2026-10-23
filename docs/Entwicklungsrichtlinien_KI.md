# Entwicklungsrichtlinien (für KI und Entwickler)

Verbindliche Regeln für jede Weiterentwicklung im Projekt. **KI-Assistenten müssen dieses Dokument vor Änderungen lesen und befolgen.**
Ergänzend gilt [.github/Programmierleitfaden_Module.md](../../../../../.github/Programmierleitfaden_Module.md) (Modulstruktur, `$D`/`$R`, Formulareingaben, Tests). Bei Widerspruch gilt das jüngere, ausdrücklich vom Entwickler bestätigte Dokument, und der Widerspruch wird in beiden Dokumenten aufgelöst.

## 0. Pflege dieses Dokuments (Pflicht)

- Jede **neue Erkenntnis, die global gilt** (Architekturentscheidung, wiederkehrender Fehler, vom Entwickler festgelegte Regel, Eigenheit von `CData`/Smarty/`input.tpl`), wird **sofort hier eingetragen**, im passenden Abschnitt und kurz (eine Regel = ein bis drei Zeilen, mit dem Grund).
- **Veraltete oder widerlegte Regeln werden gelöscht oder korrigiert**, nicht durch Zusätze überlagert. Dieses Dokument beschreibt nur den aktuellen Stand.
- Nur Allgemeingültiges gehört hierher. Details einer einzelnen Klasse oder Seite stehen in `docs/Klasse_*.md` bzw. im Kopfkommentar der Datei.
- Wenn der Entwickler eine Entscheidung trifft oder eine Änderung ablehnt oder rückgängig machen lässt, ist das eine Regel: eintragen, und die abgelehnte Lösung nicht erneut vorschlagen.

## 1. Arbeitsweise

- **Nur ändern, was verlangt ist.** Keine zusätzlichen Features, keine Umbauten "nebenbei", keine neuen Dateien, wenn eine bestehende bearbeitet werden kann.
- **Rückgängig heißt rückgängig:** Verlangt der Entwickler, eine Änderung zurückzunehmen, wird sie vollständig entfernt (auch Folgeänderungen in Templates/Configs), ohne Ersatz im selben Zug. Eine andere Lösung wird erst nach seiner Vorgabe umgesetzt.
- **Abgelehnte Ansätze:** Validierung von Feldtypen (z.B. `rejected`-Logik) gehört **nicht** in `CData`. Sie bleibt Aufgabe der Seite (siehe Programmierleitfaden, Abschnitt 3).
- **Erst die Ursache, dann die Korrektur.** Fehler vor dem Ändern nachstellen (kleines Skript mit der echten Klasse), nicht raten. Beispiele aus der Praxis stehen in Abschnitt 9.
- **Immer prüfen, bevor "fertig" gemeldet wird:** `php -l`, dazu ein Lauf mit den echten Klassen (`CData`, `Container`, `Setting`, Smarty) und temporärer Datenbank. Was nicht geprüft werden konnte (Browser, JavaScript), wird so benannt.
- Testskripte und temporäre Datenbanken liegen in `sys_get_temp_dir()` bzw. `%TEMP%`, nie im Projekt. Dauerhafte Tests gehören nach `<modul>/tests/`.
- Rückfragen nur bei echter Unklarheit. Sonst die einfachere, gängige Variante wählen und die Entscheidung in der Antwort nennen.

## 2. Code-Stil (PHP)

- Kurz und lesbar, wie von einem Menschen geschrieben: kein Over-Engineering, keine Hilfsfunktionen für Einmaliges, keine Fehlerbehandlung für Fälle, die nicht vorkommen. Geprüft wird nur an den Grenzen (Formular, Dateien, Netz).
- Kleine Closures statt Wiederholung (`$item = fn ($key) => [...]`), `??` und `??=` statt verschachtelter `isset`, `array_diff_key` statt `if` in der Schleife, rekursive Closures mit `use (&$walk)`.
- Kommentare erklären das **Warum**, deutsch, einzeilig. Kein Kommentar zu Selbstverständlichem.
- Seiten laufen im gemeinsamen Scope von `start.php`: eigene Variablen am Anfang initialisieren, Namen mit Präfix oder in Closures kapseln, eigene Abfragefilter in `$F`-Unterschlüsseln pro Seite nur bewusst verwenden (am Ende fragt `start.php` mit `$F` die Core-Datenbank ab).
- **Konfigurationsdateien (`return [...]`) immer in isolierter Closure laden**: `(static fn (string $f): array => (array)include $f)($file)`. Sonst bleiben `$section`/`$setting` einer Datei im Scope und tauchen in der nächsten Datei auf (Fehler: Einstellung eines Moduls erschien bei einem anderen).
- Dateien enthalten **UTF-8**, Einrückung mit Tabs wie im Bestand.

## 3. Container (`$C`)

- Dienste sind **modulgebunden** oder **global**:
  - Global: `$C->set('Smarty', ...)`, Zugriff `$C['Smarty']`.
  - Modulgebunden: `$C->set('Link', ..., '<vendor/modul>')`, Zugriff **nur** `$C['<vendor/modul>']['Link']`. Jedes Modul hat seine eigene Instanz mit eigener Datenbank, genau wie `CData` und `File`.
- Modulgebundene Dienste **nie** zusätzlich global verfügbar machen und keinen "globalen Fallback" bauen. Das hebt die Modularität auf.
- Alle Module eines Dienstes: `$C->getAll('CData')` (liefert `[ModulId => Dienst]`, Libraries fehlen). Damit prüfen Funktionen modulübergreifend (z.B. SEO-Links in allen Datenbanken), geschrieben wird in die Datenbank des **aktiven** Moduls.
- `$C` ist ein `Container`-Objekt, kein Array: kein `array_filter($C, ...)`, `foreach` nur über `getIterator()`/`getAll()`.
- Einstellungen lesen/schreiben über `$C['Setting']->get('<vendor/modul>', '<id>')` bzw. `->set(...)`.

## 4. Daten und Datenbank (`CData`)

- Jedes Modul hat eine eigene Datenbank. In `init.php` wird das **globale Pattern** (`system/default/default.global.pattern.php`) bei **jedem** Modul registriert, auch beim Core (fehlt z.B. `LINK`, entsteht bei gelöschtem Cache ein SQL-Syntaxfehler).
- Globales Pattern (aktuell): `LINK`, `FILE`, `MIGRATION`, `SETTING` (`Value`), `LANGUAGE.D[<ISO2>].I18N.D[<id>].Value`.
- Speichern immer mit `set_object($Baum)`: eine **Variable** übergeben (Referenzparameter, kein Ausdruck). Löschen mit dem Wert `'__DELETE__'` an der Stelle des Datensatzes (`...['I18N']['D'][$id] = '__DELETE__'`), `set_object` entfernt Datensatz samt Unterdaten.
- **Verschachtelte Typen laden**: Kind-Typen müssen im Filter genannt werden, sonst fehlen sie im Ergebnis: `$F['LANGUAGE']['I18N'] = [];` (nicht nur `$F['LANGUAGE'] = []`).
- Nach dem Speichern die gepostete Struktur aus `$D` entfernen (`unset($D['MODULE']['D'][$kMOD]['LANGUAGE'])`) und neu aus der Datenbank laden. Sonst bleiben `__DELETE__`-Strings und Eingaben im Baum und erscheinen in der Anzeige (Fehler: gelöschte ID wurde mit leerem Wert weiter angezeigt).
- **Nur Abweichungen speichern**: Standardwerte stehen in Dateien (`default.config.php`, `template/language/<iso>.php`), die Datenbank enthält nur, was der Benutzer geändert hat. Ist ein gesendeter Wert gleich dem Standard, wird ein vorhandener Datenbankeintrag per `'__DELETE__'` entfernt (zurück auf Standard).
- Anzeige = Wert aus der Datenbank, sonst Standardwert aus der Datei.
- Mehrere Werte in **einer Spalte** (z.B. mehrere Sprachen): als Text mit **`|`** getrennt (`DE|EN|FR`). Das ist das Trennzeichen des `multiselect` in `input.tpl`. Beim Lesen mit `explode('|', ...)`, trimmen, Leere entfernen, Großschreibung vereinheitlichen.

## 5. Formulare und `input.tpl` (Pflicht)

- **Jedes Eingabefeld** wird mit `{input p=[...]}` aus `system/template/include/input.tpl` gerendert. Kein eigenes `<input>`/`<select>`/`<textarea>`, außer es gibt den Typ dort nicht. Fehlt ein Typ, wird er in `input.tpl` ergänzt, nicht in der Seite nachgebaut.
- Feldname = Pfad im Datenbaum: `D[MODULE][D][<modulid>][LANGUAGE][D][<ISO2>][I18N][D][<id>][Value]`, `D[MODULE][D][<modulid>][SETTING][D][<id>][Value]`.
- `input.tpl` ruft für jeden Namen `$D.C.Security->registerSecurityD(...)` auf (Namen werden bei aktivem `security_enable_hash_input_names` gehasht). **Selbst geschriebene Namen im Format `D[...]` immer ebenfalls durch `registerSecurityD` führen**, auch bei zusätzlichen Hidden-Feldern. Bei Pfaden mit Id: Präfix registrieren und `[{$id|escape}]` anhängen (wie `img-delflag` in `input.tpl`).
- Typen und ihre Besonderheiten (Auszug, Details im Kommentar von `input.tpl`):
  - `text`, `checkbox`, `select`, `radio`, `textarea`: Optionen als `option`.
  - `multiselect`: Parameter `option` als **Liste** `'DE|Deutsch'` (nicht assoziativ, `input.tpl` liest per Index), Wert `DE|EN` mit `|`.
  - `image`: `value` ist ein **Array** `Id => ['FileId' => ...]`, kein String (ein String führt zu `Cannot access offset of type string on string`). Einzelbild: `max=1`, `mainname` (Feld der Datei-Id), `main`, `upload` (Name in `$_FILES`).
- Optionen und Parameter eines Typs kommen aus der Config/dem Pattern und werden von der Seite **an `input` durchgereicht** (z.B. `'option' => $item.Meta.Options`).
- Das Formular sendet nur geänderte Felder (`ischanged`). Fehlende Felder bedeuten "unverändert". Eine nicht angehakte Checkbox wird nicht gesendet.

## 6. Löschen in Listen

- Papierkorb-Button je Zeile: blendet die Zeile **nur aus** (`d-none`), deaktiviert die übrigen Felder der Zeile (`prop('disabled', true)`) und aktiviert ein verstecktes Feld mit dem Namen des Datensatzes und dem Wert `__DELETE__`. Gelöscht wird erst beim Klick auf **Save**.
- Der Name des Lösch-Felds ist direkt der Datenbaum-Pfad (`D[MODULE][D][<modul>][...][D][<id>]`), **kein** eigener `R[delete]`-Parameter. `set_object` verarbeitet `__DELETE__` selbst, das hält die PHP-Seite kurz.
- Müssen weitere Sprachen/Varianten mitgelöscht werden, ergänzt die Seite die `__DELETE__`-Einträge vor `set_object` (Sprachvariablen: Löschen entfernt die Id in **allen** Sprachen der Modul-Datenbank).

## 7. Einstellungen, Sprachen, Konfigurationsdateien

- Jedes Modul darf `system/default/default.config.php` liefern: `return ['SECTION' => $section, 'SETTING' => $setting]`.
  - `$setting[<id>] = ['InputType' => ..., 'Value' => <Standard>, 'Label' => ..., 'Description' => ..., 'Options' => ...]`.
  - `$section[<Tab>]['D'][<Gruppe>]['D'][...][<id>] = 1`: Sections sind **beliebig tief** verschachtelt, ein Array mit `D` ist eine Untergruppe, ein Skalar (`1`) ist eine Einstellung. Die erste Ebene wird als **Tab** dargestellt, tiefere als Gruppen (rekursive Smarty-`{function}`).
  - Sections werden über **alle Module** zusammengeführt (mehrere Module dürfen in dieselbe Section schreiben). Eine Einstellung erscheint nur dort, wo ihr eigenes Modul sie einer Section zuordnet. Ohne Zuordnung: Tab `default`.
- Gespeichert wird in der Datenbank des Moduls, dem die Einstellung gehört (`SETTING.D[<id>].Value`). Lesen: `Setting->get()` (Datenbank, sonst Standard aus der Config).
- Aktive Sprachen: Einstellung `fremeo/core` → `enable_languages` (z.B. `DE|EN`). Alles, was Sprachen anzeigt (Sprachvariablen), richtet sich danach, Rückfall `DE`.
- Sprachvariablen: Standardtexte in `<modul>/system/template/language/<iso2>.php` (`'<gruppe>' => ['<schluessel>' => 'Text']`). Die **Id ist nur der Schlüssel** (`login_btn_login`), die Gruppe gehört nicht zur Id und ein Schlüssel muss innerhalb einer Sprachdatei eindeutig sein.
- Seiten der Verwaltung: `system/admin__<name>.php` + `system/template/admin__<name>.tpl`, Menüeintrag in `index__admin.tpl` (Block `sidebar_system`). Listen mit Modulen als **Tabs** (nur Module mit eigener Datenbank, `type: library` wird nicht angezeigt), wie bei Link und Dateien.

## 8. Smarty-Templates

- Die Smarty-Security erlaubt nur freigegebene PHP-Funktionen (Liste in `start.php`). **`min()`, `max()`-Varianten und andere fehlende Funktionen nicht im Template aufrufen**: Wert in der PHP-Seite vorbereiten oder mit `{if}` lösen (`{if $level < 6}{$next = $level + 1}{else}{$next = 6}{/if}`).
- `{function}` für wiederkehrende/rekursive Darstellung. Parameter explizit übergeben (Funktionen erben keine Variablen), Standardwerte im Kopf.
- Funktionen aus `input.tpl` sind im Template verfügbar, weil die Seitentemplates in der Kette stehen. Zum Testen mit Smarty den Text von `input.tpl` und `{input ...}` in **ein** Template schreiben (`{include}` registriert die Funktionen erst zur Laufzeit).
- Immer escapen (`|escape`), fehlende Werte mit `|default:''`. JavaScript in `{literal}`.
- Tabs/Layout: Bootstrap 5 (`data-bs-toggle`, `data-bs-target`), Icons Font Awesome.

## 9. Bekannte Stolpersteine (Kurzliste)

| Symptom | Ursache | Lösung |
|---|---|---|
| SQLite-Syntaxfehler nach Cache löschen | Pattern `LINK` im Core nicht registriert | globales Pattern bei jedem Modul registrieren (`init.php`) |
| Einstellung eines Moduls taucht bei anderem auf | `include` teilt Variablen-Scope | Config in isolierter Closure laden |
| `Argument #2 ($node) must be of type array, null given` | `array &$node` bei nicht vorhandenem Index | Referenzparameter ohne Typ, `$node ??= []` |
| `PHP function 'min' not allowed by security setting` | Smarty-Security | `{if}` oder Vorbereitung in PHP |
| `Cannot access offset of type string on string` bei `image` | `value` war String | Array `Id => ['FileId' => ...]` |
| Verschachtelte Daten fehlen nach `get_object` | Kind-Typ nicht im Filter | `$F['LANGUAGE']['I18N'] = []` |
| Gelöschte Id erscheint weiter in der Liste | gepostetes `__DELETE__` blieb in `$D` | nach `set_object` `unset(...)`, neu laden |
| `multiselect` zeigt keine Optionen | assoziatives Array statt Liste, `option` nicht durchgereicht | Liste `'KEY|Label'` und `'option' => ...` an `input` |
| Standardwert einer Einstellung `null` | `Setting` las alten Pfad | Config liegt unter `system/default/default.config.php` |

## 10. Checkliste vor jeder Abgabe

1. Habe ich nur geändert, was verlangt war, und keine abgelehnten Ansätze wieder eingebaut?
2. Eingabefelder über `input.tpl`, Namen = Datenbaum-Pfad, Namen mit `registerSecurityD`?
3. Speichern nur über `set_object` mit Variable, Löschen per `__DELETE__`, nur Abweichungen vom Standard gespeichert, danach gepostete Daten verworfen und neu geladen?
4. Dienste modulgebunden (`$C[<modul>][...]`), kein globaler Fallback?
5. `php -l` und Lauf mit echten Klassen und temporärer Datenbank erfolgt, Ergebnis ehrlich berichtet (auch was nicht getestet wurde)?
6. Dieses Dokument bei neuen globalen Erkenntnissen ergänzt und Veraltetes entfernt?
