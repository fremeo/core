# Konzept: Sprachvariablen, Smarty und ICU

Status: Grundlage implementiert; als erste Seite verwendet ausschliesslich
`system/template/index__login.tpl` die neue Uebersetzung. Weitere bestehende
Seiten werden bei ihrer naechsten Ueberarbeitung schrittweise umgestellt.
Aktuelle Sprache und Ersatzsprache sind vorerst
fest `DE`; eine spaetere Benutzerauswahl ist noch nicht angebunden.

## 1. Verbindlichkeit und Pflege

Dieses Dokument ist die verbindliche Konzeptbeschreibung fuer Sprachvariablen.
Bei jeder Aenderung an Regeln, Datenhaltung, Aufloesung, Template-Syntax oder
Cache-Verhalten muss diese Beschreibung im selben Arbeitsschritt angepasst werden.
Ueberholte Aussagen ersetzen, nicht durch widersprechende Zusaetze ergaenzen.
Die Pflege ist eine Entwicklungsregel, kein zur Laufzeit erzeugtes Dokument.

### Texte bei Entwicklung, Ueberarbeitung und Veroeffentlichung

- Bei neuen Projekten, Modulen und Seiten alle mitgelieferten Oberflaechentexte
  direkt als Sprachvariablen im jeweils zustaendigen Modul unter
  `system/template/language/de.php` hinterlegen. Templates verwenden `{i18n}`
  statt fest eingebauter Texte.
- Waehrend der Entwicklung genuegt die deutsche Fassung.
- Vor jeder Veroeffentlichung die mitgelieferten Texte zusaetzlich sinngemaess
  ins Englische uebersetzen und in `system/template/language/en.php` desselben
  Moduls unter identischen Schluesseln ergaenzen. ICU-Platzhalter und deren
  Bedeutung beibehalten; englische Formulierungen und Pluralformen passend
  zur Sprache waehlen. Die englische Fassung ist Teil der Release-Pruefung.
- Bei jeder erneuten Ueberarbeitung einer bestehenden Seite deren
  Oberflaechentexte beruecksichtigen und noch fest eingebaute Texte in die
  Sprachdatei des zustaendigen Moduls portieren sowie die Ausgabe auf `{i18n}`
  umstellen. Unberuehrte Seiten nicht pauschal nebenbei migrieren.
- Diese Dateipflicht gilt fuer mitgelieferte Anwendungstexte, nicht fuer
  individuelle Benutzerinhalte oder bewusst nur in der Datenbank gepflegte
  kundenspezifische Sprachvariablen.

## 2. Bestehende Grundlage

- Die Backend-Maske verwaltet Sprachvariablen je aktivem Modul und Sprache.
- Mitgelieferte Standards liegen unter `system/template/language/<sprache>.php`,
  beispielsweise `de.php`.
- Sprachdateien liefern Arrays mit Gruppen und darin Schluessel/Text-Paaren.
  Gruppen dienen der Organisation und sind kein Bestandteil der Variablen-ID.
- Die bestehende Datenbankadresse lautet
  `LANGUAGE.D[<ISO2>].I18N.D[<schluessel>].Value` in der jeweiligen Modul-Datenbank.
- Aktivierte Sprachen stammen aus der Core-Einstellung `enable_languages`.
  Intern Sprachcodes einheitlich normalisieren; Dateinamen bleiben kleingeschrieben.
- Vererbung, Resolver-Anbindung und die Unterscheidung automatisch erfasster
  Eintraege von bewussten Ueberschreibungen werden durch den Core-Dienst
  `LanguageVariable` bereitgestellt.
- Die Formatierung verwendet ICU MessageFormat ueber PHP `MessageFormatter`.
  Composer verlangt `ext-intl`; kein eingeschraenkter Polyfill und kein
  eigener Nachbau von Plural- oder Formatierungsregeln.

## 3. Adressen und Sprachdateien

Ein Schluessel muss innerhalb eines Moduls eindeutig sein, auch ueber Gruppen hinweg.
Seine vollstaendige Adresse besteht aus Modul-ID und Schluessel:

```text
fremeo/gallery:gallery_upload_result
```

Beispiel einer mitgelieferten Sprachdatei:

```php
return [
	'gallery' => [
		'gallery_title' => 'Bildergalerie',
		'gallery_upload_result' => '{count, plural, =0 {Keine Bilder wurden hochgeladen.} one {Ein Bild wurde hochgeladen.} other {# Bilder wurden hochgeladen.}}',
		'gallery_notice' => 'Bitte den <u>Titel</u> pruefen.',
	],
];
```

Neue Schluessel duerfen ausschliesslich in der Datenbank existieren; eine
Dateideklaration ist keine Voraussetzung fuer ihre Verwendung.
Modulupdates ersetzen mitgelieferte Dateien, nicht individuelle Datenbankwerte.

## 4. Smarty-Deklaration und Platzhalter

Syntax:

```smarty
{i18n key="gallery_title"}
{i18n key="gallery_upload_result" values=['count' => $uploaded]}
{i18n module="fremeo/core" key="login_title"}
```

- Modul und Schluessel sind feste Zeichenketten, damit die Aufloesung beim
  Kompilieren erfolgen kann.
- Ohne `module` gilt das Ursprungsmodul der betreffenden Template-Datei,
  nicht automatisch das Modul der aufgerufenen Seite oder eines anderen Includes.
- `key`, optional `module` und optional `values` sind die einzigen Attribute.
  Platzhalter ausschliesslich als Array unter `values` uebergeben. So bleiben
  auch Platzhalternamen wie `key`, `module` und `locale` verwendbar.
- Sprachtexte erhalten nur diese Werte, nicht den Seiten-Datenbaum oder Container.
- ICU-Platzhalter verwenden beispielsweise `{name}`, `{price, number, ::currency/EUR}`
  oder `{count, plural, one {...} other {...}}`. Keine Smarty-Variablen, Modifier
  oder Smarty-Bedingungen in Sprachtexten ausfuehren.
- Singular, Plural, `select` und Zahlen-/Datumsformatierung verarbeitet ICU anhand
  der Ausgabesprache. Keine eigene PHP-Pluralregel erzeugen.
- Zahlen als PHP-Integer oder Float uebergeben, z.B. `12.33` statt `"12,33"`.
  Numerische ICU-Argumente akzeptieren keine Textwerte; sonstige Platzhalter
  akzeptieren Text oder Zahlen, keine Arrays, Objekte, Nullwerte oder Booleans.
- Alle im Muster vorkommenden Platzhalter muessen im Array vorhanden sein,
  auch solche innerhalb alternativer Zweige. Fehlende Werte sind Fehler,
  nicht die von ICU sonst moegliche Ausgabe eines ungefuellten Platzhalters.
- ICU-Apostrophregeln gelten auch fuer statische Texte: `'{name}'` gibt
  woertlich `{name}` aus, `''` ein Apostroph. Eingesetzte Werte werden niemals
  erneut als ICU-, Smarty- oder PHP-Quelltext interpretiert.

## 5. Vererbung und Prioritaeten

Die vorhandene explizite Modul-Elternkette wird wiederverwendet. Keine zweite
Vererbungsdefinition und keine Prioritaet aufgrund zufaelliger Ladereihenfolge.
Nur aktive, zum ausgewaehlten Template-Kontext gehoerende Anbieter beteiligen.

Fuer einen Schluessel innerhalb einer Sprache gilt, von hoher zu niedriger Prioritaet:

1. Individuelle Datenbankueberschreibung im ausgewaehlten Child-Template.
2. Individuelle Datenbankueberschreibungen entlang der passenden Elternkette,
   vom naechsten Elternteil bis zum Ursprungsmodul.
3. Sprachdatei des ausgewaehlten Child-Templates.
4. Sprachdateien entlang derselben Elternkette bis zum Ursprungsmodul.

Datenbankanpassungen haben damit Vorrang vor mitgelieferten Dateistandards.
Nicht verwandte Module duerfen sich nicht gegenseitig implizit ueberschreiben.
Ein Child adressiert fremde Schluessel ausdruecklich vollstaendig, beispielsweise:

```php
return [
	'navigation' => [
		'fremeo/frontend:navigation_home' => 'Startseite',
	],
];
```

Die vollstaendige Adresse bleibt auch bei Ueberschreibungen erhalten. Ein
Datenbank-Override im Child wird in dessen Modul-Datenbank gespeichert.
Eine beliebige Template-Ableitung erweitert nicht automatisch alle Funktionsmodule.
Backend- und Frontend-Kontext bleiben getrennt: ein Frontend-Child ueberschreibt
keine Core-Backendtexte. Themes erhalten keine eigenen Sprachkataloge;
Texte gehoeren zu Modulen und Templates, Themes zur Darstellung.

Zuerst die gesamte Quellenkette fuer die gewuenschte Sprache pruefen.
Erst wenn dort kein wirksamer Eintrag existiert, dieselbe Kette fuer die
konfigurierte Standardsprache pruefen. Eine Uebersetzung der gewuenschten
Sprache hat Vorrang vor einer Ueberschreibung in der Standardsprache.
Die Standardsprache ist derzeit fest `DE`, unabhaengig von der Reihenfolge
aktivierter Sprachen. `configure()` nimmt eine Ausgabesprache entgegen; die
Anwendung uebergibt bis zur spaeteren Benutzeranbindung ebenfalls fest `DE`.
Bei einer spaeteren konfigurierbaren Ersatzsprache diese Stelle mitanpassen.

## 6. Fehlende Schluessel und leere Werte

Fehlt der Schluessel ueberall, einschliesslich Standardsprache:

- Beim Kompilieren einen leeren, als fehlend gekennzeichneten Eintrag in der
  Sprachverwaltung des Ursprungsmoduls fuer die angefragte Sprache erfassen.
- Den angefragten Schluessel als sichtbaren Ersatztext ausgeben, beispielsweise
  `gallery_upload_result`, statt die Stelle leer zu lassen.
- Erfassung idempotent und auch bei paralleler Kompilierung duplikatfrei ausfuehren.
  Vorhandene Werte niemals ueberschreiben.
- Fehler beim Anlegen ausdruecklich melden/protokollieren, nicht still verschlucken.
- Automatische Erfassung allein loest keine erneute Cache-Loeschung aus.

Folgende Zustaende muessen unterscheidbar sein; `Value` allein reicht dafuer nicht:

| Zustand | Wirkung |
|---|---|
| Automatisch erfasst, unbearbeitet | Kein Override; spaetere Dateistandards werden verwendet, sonst Schluesselausgabe |
| Individuell ueberschrieben | Datenbankwert verwenden |
| Bewusst leer ueberschrieben | Leer ausgeben, nicht weiter zurueckfallen |
| Auf Standard zurueckgesetzt | Override entfernen; Vererbung und Dateistandards gelten wieder |

Ein bewusst leerer Dateistandard gilt ebenfalls als vorhandener Wert.
Gespeichert werden `Value`, `Missing` und optional `Source` (verwendende
Template-Datei). Nur `Missing=1` kennzeichnet einen unbearbeiteten automatisch
erfassten Eintrag. Bestehende Datensaetze ohne dieses Kennzeichen bleiben
Overrides; fehlendes `Value` entspricht dabei einem bewusst leeren Wert, da
CData leere Textattribute entfernt. Das gemeinsame Pattern definiert die Felder
fuer alle Module. Erfassung und Backend-Speichern verwenden dieselbe modulweise
Dateisperre unter `<DataDir>/language.lock`.

## 7. HTML und Fehlerbehandlung

- HTML ist in Sprachtexten erlaubt, beispielsweise `<u>Wort</u>` oder
  `<strong>Hinweis</strong>`. Sprachtexte werden daher nicht pauschal HTML-escaped.
- Sprachtexte innerhalb von HTML-Attributen vor der Ausgabe vollstaendig escapen.
  Der Login verwendet dafuer `{capture}` und `|escape` bei den Platzhaltern.
  Der Editor escaped den Quelltext innerhalb seiner Textareas, ohne dessen
  spaetere HTML-Ausgabe zu veraendern.
- Dynamische Textwerte standardmaessig mit
  `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` maskieren.
  Numerische Werte bleiben fuer ICU numerisch. HTML im Sprachmuster bleibt
  erhalten; Benutzereingaben duerfen dadurch kein HTML einschleusen.
  Dies ist HTML-Text-Maskierung, kein Schutz fuer JavaScript-, CSS- oder URL-Kontexte.
- Sprachtexte enthalten HTML und ICU-Muster, keinen ausfuehrbaren Smarty-Code.
  Auch PHP-Oeffnungstags aus Sprachtexten bleiben woertliche Ausgabe.
- Sprachmuster sind vertrauenswuerdiger Backend-Inhalt, nicht Benutzereingaben.
  Keine neue Berechtigungsstufe oder gesonderte Rechteverwaltung einfuehren:
  die vorhandene Backend-Zugriffskontrolle gilt weiter.
- Keine zusaetzliche Syntaxpruefung beim Backend-Speichern. ICU validiert
  Muster beim Kompilieren; Formatierungsfehler und fehlende/falsch typisierte
  Werte werden ausdruecklich als Fehler mit Schluessel und Locale gemeldet.
  Keine leere Ausgabe oder stiller Ersatz fuer ungueltige Muster.

## 8. Kompilierung und Cache

`{i18n}` ist ein Compiler-Plugin, keine zur Ausgabe aufgerufene Smarty-Funktion.
Der Resolver liefert beim Kompilieren das bereits aufgeloeste ICU-Muster.

- Ohne ICU-Argumente und ohne `values` wird das Muster einmal durch ICU
  verarbeitet und als fester Text/HTML ausserhalb der PHP-Bloecke eingesetzt.
  Kein `echo` fuer normalen statischen Text. Nur PHP-Tags im Text
  erfordern eine geschuetzte Ausgabe, damit sie kein Code werden.
- Dynamische Muster werden ebenfalls fest in das Kompilat eingebettet.
  Nur `MessageFormatter` wird bei der Ausgabe mit den aktuellen `values`
  aufgerufen. Keine selbst erzeugten PHP-Bedingungen fuer ICU-Pluralregeln.
- Formatter und Argumentmetadaten werden je Locale/Muster im PHP-Prozess
  wiederverwendet. Die Argumentanalyse dient ausschliesslich der Erkennung
  statischer Muster und der Wertepruefung, nicht der ICU-Formatierung.
- Normale Ausgabe bereits kompilierter Templates liest dafuer weder
  Sprachdateien noch Sprachvariablen aus der Datenbank.
- Sprache und relevanter Template-/Backend-/Frontend-Kontext trennen die
  Kompilate ueber eine stabile `compile_id`. Keine Revisionsnummern verwenden.
- Ein zusammengefuehrter Sprachkatalog kann intern zwischengespeichert werden,
  wenn dies fuer die Kompilierung sinnvoll ist; derzeit ist keine zusaetzliche
  Katalogdatei noetig. Keine zweite Versionslogik.
- Kompilierte PHP-Templates sind nicht mit dem HTML-Ausgabecache gleichzusetzen.
  Bei aktiviertem HTML-Caching muessen benutzerabhaengige Werte weiterhin
  passende Cache-IDs oder Smartys `{nocache}`-Bereiche verwenden.

Bei Aenderungen Sprachkatalog verwerfen, sofern vorhanden, und die kompilierten
Smarty-Templates mit `clearCompiledTemplate()` loeschen. Falls HTML-Ausgabecaching
aktiv ist, dieses ebenfalls mit `clearAllCache()` leeren. Nur den HTML-Cache
zu loeschen reicht nicht. Smarty erzeugt benoetigte Kompilate beim naechsten
Aufruf automatisch neu.

Ausloeser:

- Sprachvariablen speichern, zuruecksetzen oder loeschen.
- Sprachdateien aendern sowie beteiligte Module installieren oder aktualisieren.
- Beteiligte Module aktivieren/deaktivieren oder deinstallieren.
- Template-Vererbung, Auswahl oder Standardsprache aendern.
- Manueller Cache-Leerbefehl.

Bestehende geladene Sprachdateien werden als Smarty-Dateiabhaengigkeiten
registriert. Bei aktivem `compile_check` prueft Smarty deren Aenderungszeit,
ohne den Sprachtext erneut einzulesen. Eine Liste der vorhandenen Sprachdateien
unter `<compile_dir>/language-files.json` erkennt hinzugefuegte/entfernte
Sprachpakete beim naechsten Aufruf und leert die Caches. Diese Liste enthaelt
nur Dateinamen, keine Texte und keine Revision; die `compile_id` bleibt stabil.
Die Pruefung fuehrt keine Sprachdatenbankabfragen aus.

Mit deaktiviertem `compile_check` muessen manuelle Inhaltsaenderungen ueber
Deployment-/Update-Hook oder explizites Cache-Leeren veroeffentlicht werden.
Smarty erkennt Datenbankaenderungen nicht allein durch die Pruefung der TPL-Dateien.
Backend-Speichern, Modulstatuswechsel, Theme-Auswahl, der Modul-Cache-Leerbefehl
sowie Paketmutationen ueber `PackageManager` loeschen die Smarty-Caches.
Die CLI-Kommandos `migration` und `uninstall` leeren sie ebenfalls fuer externe
Composer-Updates mit den vorhandenen Projekt-Hooks. Auch fehlgeschlagene
Paketmutationen invalidieren, weil Composer bereits Dateien ersetzt haben kann.

## 9. Backend-Verwaltung

Die vorhandene Maske wird weiterverwendet:

- Vollstaendiger Schluessel und Sprache.
- Je Sprache Quelltext aus der individuellen Datenbankueberschreibung, sonst
  aus der eigenen Modul-Sprachdatei; HTML/ICU-Quelltext wird nicht ausgefuehrt.
- Kennzeichnung individueller Overrides gegenueber lokalen Dateistandards.
- Kennzeichnung automatisch erfasster, noch fehlender Uebersetzungen.
- Bewusst leer ueberschreiben und auf Standard zuruecksetzen unterscheiden.
- Die verwendende Template-Datei bei automatisch erfassten Eintraegen als
  Herkunftshinweis im Status anzeigen.
- Zaehler als Badge je Modul-Tab, analog zur Link-Verwaltung. Gezaehlt werden
  unterschiedliche Schluessel, nicht deren Sprachversionen; leere Module ohne Badge.
- Mehrzeilige Textareas fuer HTML und ICU. `Standard` setzt nur die gewaehlte
  Sprachueberschreibung zurueck, `Leer` speichert eine ausdruecklich leere
  Ueberschreibung. Beide werden erst mit Speichern wirksam. Die bestehende
  Zeilen-Loeschaktion entfernt Datenbankwerte dieses Schluessels in allen Sprachen.

Die Bearbeitung zeigt bewusst die Quellen je Modul, nicht eine ausgefuehrte
Vorschau aller moeglichen Frontend-/Backend-Vererbungs-Kontexte.
Die effektive Vererbung findet beim Kompilieren im ausgewaehlten Kontext statt.

Kein eigener zweiter Editor und keine zusaetzliche Berechtigungsverwaltung.

## 10. Umsetzung und Abnahme

Die Klasse `fremeo\core\LanguageVariable` liegt im Core unter
`system/core/LanguageVariable.php` und ist als `LanguageVariable` im Container
registriert. `resolveTemplate()` bestimmt den Modulkontext, loest Texte auf
und registriert Sprachdateien als Smarty-Abhaengigkeiten.

Das allgemeine Plugin liegt vorerst in
`system/core/smarty_plugin/compiler.i18n.php`. Die darin enthaltene Klasse
`Smarty_I18n` kennt weder Fremeo noch Container, Datenbank oder Modulvererbung.
Sie kann zusammen mit ihrem ICU-Laufzeitformatierer spaeter nach Smarty4life
verschoben werden, ohne die Template-Syntax zu aendern.
Der alte unbenutzte `{function name=i18n}`-Platzhalter in `include/input.tpl`
ist entfernt: Smarty-Templatefunktionen haben Vorrang vor registrierten Plugins
und wuerden die neue Compiler-Anbindung verdecken. Den Namen nicht erneut als
Templatefunktion definieren.

Allgemeine Registrierung fuer andere Projekte:

```php
require_once '/path/to/compiler.i18n.php';
$plugin = new Smarty_I18n('de_DE', $resolver);
$smarty->registerPlugin(Smarty::PLUGIN_COMPILER, 'i18n', [$plugin, 'compile']);
```

Der Resolver ist ein Callable mit
`(string $key, ?string $module, Smarty_Internal_TemplateCompilerBase $compiler): string`.
Er liefert das Muster, nicht bereits formatierten Text. Der Aufrufer sorgt fuer
eine nach Locale/Quellenkontext getrennte stabile `compile_id`, Abhaengigkeiten
und Cache-Loeschung bei Quellenaenderungen. Fremeo uebernimmt dies in
`LanguageVariable::configure()` und `resolveTemplate()`.
Das Kompilat bindet bei dynamischer Ausgabe den mitgelieferten Formatierer ein;
es ruft keinen Resolver auf. Nach einem Umzug der Plugin-Datei Kompilate leeren.

Keine Klasse oder Abstraktionsschicht allein der Struktur wegen einfuehren.
Einmalig verwendete oder triviale Hilfsfunktionen moeglichst vermeiden;
zusammengehoerige kurze Logik direkt lesbar halten.

Bei Weiterentwicklungen mindestens pruefen:

- Gleichnamige Schluessel verschiedener Module bleiben getrennt.
- Datei-, Datenbank- und Child-Prioritaeten sowie Sprachfallback funktionieren.
- Fehlender Schluessel wird sichtbar ausgegeben und genau einmal leer erfasst.
- Automatisch erfasste Eintraege blockieren spaetere Dateistandards nicht.
- Bewusst leere Werte und Zuruecksetzen sind unterscheidbar.
- HTML, maskierte Textwerte, Zahlen und sprachabhaengige ICU-Regeln funktionieren.
- Statischer Text steht direkt im Kompilat; Sprachtexte fuehren weder Smarty
  noch PHP aus. Dynamische Muster bleiben ohne Resolver zur Laufzeit nutzbar.
- Sprach-/Template-Kontexte vermischen sich nicht.
- Bereits kompilierte Sprachausgabe benoetigt keine erneuten Sprachdatenabfragen.
- Aenderungen werden nach Cache-Loeschung ohne Revision automatisch sichtbar.
- Bestehende Sprachverwaltung und Modulupdates behalten individuelle Werte.
