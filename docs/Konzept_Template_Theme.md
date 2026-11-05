# Konzept: Templates, Zweige und Themes

## Status und Ziel

Dieses Dokument beschreibt das abgestimmte Zielbild und den jeweiligen
Implementierungsstand. Bei Aenderungen am Template-/Theme-Code oder an dessen Regeln
wird es im selben Arbeitsschritt aktualisiert.

**Stand: Etappe 1 umgesetzt.** Die Core-Klasse `Template` ist als globaler
Container-Dienst registriert. `start.php` verwendet ihre Methode `getExtends()`.
Der bisherige Algorithmus und das Rueckgabeformat wurden unveraendert uebernommen.
Theme-Aufloesung, lokale Asset-Ausgabe, Auswahl und Performance-Verarbeitung sind
noch nicht implementiert; das bisherige Erscheinungsbild bleibt bestehen.

Ziel ist ein allgemeiner Mechanismus fuer beliebige Template-Zweige, vererbbare
Themes und oeffentlich auslieferbare Assets. Es gibt keine fest programmierte
Sonderbehandlung fuer `admin`, `frontend` oder spaetere Bereiche wie `account`.

## 1. Begriffe und Verantwortlichkeiten

| Begriff | Bedeutung |
|---|---|
| Template-Kette | Geordnete Vererbung der TPL-Dateien und ihrer begleitenden PHP-Dateien. |
| Zweig | Durch die Template-Vererbung gewaehlter Darstellungsbereich, beispielsweise `admin` oder `frontend`. |
| Theme-Anbieter | Template-Modul, dessen zweigbildende Vorlage das Layout bereitstellt; Core darf ebenfalls diese Rolle uebernehmen. |
| Template-Erweiterung | Modul, das das Haupt-Template erweitert und passende Theme-Beitraege liefern kann. |
| Haupt-Theme | Unabhaengige Gestaltungsfamilie, beispielsweise `basic` oder `classic`. |
| Child-Theme | Ableitung innerhalb einer Gestaltungsfamilie, beispielsweise `basic__dark`. |
| Veroeffentlichung | Generierte statische Dateien unter `data_c\public\theme`, erreichbar ueber `/file/theme/`. |

Normale Funktionsmodule liefern Seiteninhalte, bestimmen dadurch aber nicht das
Theme. Themes werden grundsaetzlich nur von Modulen mit Composer-Typ `template`
bereitgestellt. Core ist als Basisanbieter die ausdrueckliche Ausnahme und wird
fuer die Theme-Aufloesung wie ein Template-Anbieter behandelt.

## 2. Template-Wurzel und gemeinsame Dokumentstruktur

Ausgangspunkt ist die vorhandene Funktion [getExtends()](Funktion_getExtends.md).
Eine beispielhafte Kette lautet:

```text
Core:              index.tpl
Core:              index__index.tpl
Frontend-Template: index__frontend.tpl
Funktionsmodul:    frontend__article.tpl
```

`index.tpl` befindet sich zunaechst nur in Core. Dies ist eine Projektkonvention,
keine zusaetzlich zu erzwingende Einschraenkung. Die bisherige Root-Suche bleibt
unveraendert. Andere Module duerfen spaeter eigene Wurzeln mitbringen, soweit das
bestehende Vererbungssystem dies unterstuetzt.

Core stellt mit `index__index.tpl` die gemeinsame HTML-Dokumentstruktur und
erweiterbare Bloecke fuer Titel, Metadaten, Stylesheets, Scripts und Inhalt bereit.
Zweigvorlagen wie `index__admin.tpl` und `index__frontend.tpl` ergaenzen das jeweilige
Layout. Die duplizierte Dokumentvorlage `index__index.tpl` des Frontend-Moduls soll
bei der spaeteren Umsetzung entfallen, sobald ihre benoetigten Bloecke zentral
bereitstehen. Die allgemeine Moeglichkeit der Template-Vererbung bleibt erhalten.

## 3. Zweig und Theme-Anbieter aus der Vererbung ableiten

Die tatsaechlich aufgeloeste Template-Kette und die Herkunft ihrer Dateien sind
massgeblich, nicht die angefragte URL und nicht das aktive Funktionsmodul.

- `index__frontend.tpl` bildet im Beispiel den Zweig `frontend`.
- Stammt diese Vorlage aus dem Frontend-Template, liefert dieses den Theme-Anbieter.
- Stammt die zweigbildende Backend-Vorlage aus Core, ist Core der Anbieter.
- Weitere Inhaltsvorlagen wechseln den Theme-Anbieter nicht.
- Erweiterungen desselben Layouts bleiben Beitraege zum Haupt-Template; sie
  ersetzen nicht automatisch dessen Identitaet.

Die Abwesenheit von `index__frontend.tpl` in Core verdeutlicht im Beispiel die
Zustaendigkeit des Frontend-Moduls. Allgemein entscheidet jedoch die tatsaechliche
Herkunft in der aufgeloesten Vererbung, nicht allein ein Existenztest in Core.

Die Aufloesung muss Zweig, Hauptanbieter und geordnete Template-Erweiterungen
erhalten. Nicht jedes `__` in einem Seiten- oder Dateinamen eroeffnet einen neuen
Darstellungszweig. Gemeinsame Vorlagen wie `index__index.tpl` sind kein eigener Zweig.
Eine mehrdeutige Zuordnung wird gemeldet, nicht durch zufaellige Modulreihenfolge
entschieden.

Wird das Backend spaeter in ein separates Template-Modul ausgelagert, uebernimmt
dieses aufgrund der Vorlagenherkunft die Anbieterrolle. Der Theme-Mechanismus
braucht dafuer keine neue Backend-Sonderregel.

## 4. Template-Erweiterungen

Nur Erweiterungen, die zur aufgeloesten aktiven Template-Vererbung gehoeren, liefern
Theme-Beitraege. Die blosse Installation eines unabhaengigen Template-Moduls reicht
nicht aus.

Die Reihenfolge folgt der Template-Vererbung: Eltern-Template vor Kind-Template.
Gleichnamige Theme-Ordner werden nur innerhalb dieser Kette zusammengefuehrt.

Als moegliche Namenskonvention fuer Template-Module ist
`frontend__<erweiterung>` vorgesehen. Dies ist noch keine verbindlich eingefuehrte
Composer-Paketregel. Entscheidend bleibt eine eindeutig aufgeloeste Elternbeziehung.
Soll diese Konvention zur Erkennung verwendet werden, muessen ihre Regeln vor der
Implementierung verbindlich festgelegt werden; eine zufaellige Installations- oder
alphabetische Reihenfolge ersetzt keine Vererbungsbeziehung.

## 5. Theme-Verzeichnisse und Ableitungen

Die Quellen eines Anbieters liegen unter `system\template\theme`.
Mehrere voneinander unabhaengige Familien koennen nebeneinander existieren:

```text
system\template\theme\
    basic\
    basic__dark\
    basic__white\
    dark__contrast\
    classic\
    classic__gray\
    classic__blue\
```

Die Konvention `<parent>__<child>` bezeichnet die unmittelbare Elternbeziehung.
Damit kann `dark__contrast` die Kette `basic -> dark -> contrast` verlaengern.
`classic -> blue` ist davon unabhaengig.

Die interne Identitaet umfasst Anbieter und vollstaendige Abstammung. Kurznamen
wie `dark` muessen nicht global eindeutig sein. Fehlende Eltern, Zyklen und
mehrdeutige Zuordnungen sind Konfigurationsfehler und werden ausdruecklich gemeldet.

Es wird nur die ausgewaehlte Ableitung mit ihren Vorfahren verwendet. Bei Auswahl
von `dark` wird `white` nicht mitgeladen. Erweiternde Template-Module duerfen
Dateien zu bestehenden Theme-Ebenen sowie passende weitere Ableitungen beitragen.

Innerhalb eines Theme-Ordners werden eigene Dateien und Bibliotheken getrennt:

```text
css\
js\
images\
fonts\
vendor\<bibliothek>\<version>\
```

Originaldateien und Bibliotheksversionen bleiben bei ihren Anbietern. Generierte
Dateien werden nicht als neue Theme-Quelle in die Module zurueckgeschrieben.

## 6. Zusammenfuehrung und Asset-Reihenfolge

Die Aufloesung erfolgt von der Basis zur ausgewaehlten Ableitung. Innerhalb jeder
Theme-Ebene gilt die Reihenfolge der Template-Anbieter:

```text
1. basic aus dem Haupt-Template
2. basic aus dessen Template-Erweiterungen
3. dark aus dem Haupt-Template
4. dark aus dessen Template-Erweiterungen
5. Custom CSS der Familie basic
```

Geschwister-Ableitungen werden nicht gemeinsam einsortiert. Wenn eine feinere
Reihenfolge benoetigt wird, wird die Ableitungskette verlaengert, statt zusaetzliche
Theme-Prioritaeten einzufuehren.

Zwei Mechanismen sind zu unterscheiden:

- Gleicher relativer Dateipfad: Die spaetere Ebene ersetzt die fruehere Datei.
- Unterschiedliche Asset-Dateien: Sie werden in ihrer deklarierten Reihenfolge
  ausgegeben beziehungsweise zusammengefuehrt.

Eine kleine geordnete Asset-Deklaration je Theme-Ebene legt CSS- und JS-Eintraege
sowie deren Ausgabe im Kopf oder am Dokumentende fest. Ein Verzeichnisscan oder
alphabetische Dateisortierung reicht insbesondere fuer JavaScript nicht aus.
Basis und Anpassungen, die beide ausgefuehrt werden sollen, benoetigen
unterschiedliche relative Dateipfade.

## 7. Einstellungen und Custom CSS

Es wird ausschliesslich die vorhandene Einstellungsverwaltung verwendet:
`Setting`, die modulbezogene Datenbank und die Definitionen in
`system\default\default.config.php`. Es entsteht kein paralleles Einstellungssystem.

| Einstellung | Ablage |
|---|---|
| Template-uebergreifende Auswahl und Zuordnungen | Core-Datenbank |
| Globale Performance-Schalter | Core-Datenbank |
| Haupt-Theme und Standardableitung eines Template-Anbieters, bei Bedarf je Zweig | Datenbank dieses Anbieters |
| Template-spezifische Gestaltungsoptionen | Datenbank dieses Template-Moduls |
| Custom CSS je Haupt-Theme | Datenbank des besitzenden Haupt-Template-Anbieters |

Bei Core als Anbieter liegen dessen Einstellungen ebenfalls in der Core-Datenbank.
Werte und Defaults werden ueber den vorhandenen Einstellungsdienst gelesen;
gespeicherte ausgeschaltete Optionen muessen dabei wirksam bleiben.

Custom CSS ist an den Anbieter und das Haupt-Theme gebunden:

```text
Frontend-Anbieter / basic   -> Custom CSS fuer basic und alle seine Ableitungen
Frontend-Anbieter / classic -> separates Custom CSS
```

Der Wechsel zwischen `basic__white` und `basic__dark` behaelt dasselbe Custom CSS.
Der Wechsel von `basic` zu `classic` uebernimmt es nicht. Ein Erweiterungsmodul
wechselt diese Zuordnung nicht. Custom CSS wird als letzte CSS-Schicht verarbeitet.
Seine Bearbeitung ist auf entsprechend berechtigte Benutzer beschraenkt.

## 8. Performance-Optionen

Kategorie der vorhandenen Einstellungsoberflaeche: **Template -> Performance**.
Drei unabhaengige Schalter sind vorgesehen:

| Schalter | Wirkung |
|---|---|
| CSS minimieren | Stylesheets verkleinern. |
| JavaScript minimieren | Scripts verkleinern, ohne ihre Ladereihenfolge zu aendern. |
| CSS zusammenfassen | Kompatible Stylesheets in deklarierter Reihenfolge buendeln. |

CSS-Zusammenfuehrung und Minimierung koennen unabhaengig aktiviert werden.
Eine JS-Zusammenfuehrung ist in der ersten Stufe nicht vorgesehen.

Bei CSS sind relative URLs zu Fonts und Bildern, `@import`, Zeichencodierungen
und unterschiedliche `media`-Bedingungen zu beruecksichtigen. Nicht kompatible
Stylesheets bleiben getrennt. CSS und JavaScript werden nicht mit einfachen
regulaeren Ausdruecken minimiert, sondern mit geeigneten Parser-/Minifier-Werkzeugen.

Bereits minimierte Bibliotheksdateien bleiben bei deaktivierter Minimierung
unveraendert; der Schalter erzeugt keine unminifizierten Originale.
Minimierung ist von HTTP-Transportkompression unabhaengig.

## 9. Veroeffentlichung und URLs

Physischer Zielpfad:

```text
data_c\public\theme\<ausgabe-id>\<theme>\<inhaltshash>\...
```

Oeffentliche Adresse desselben Ergebnisses:

```text
https://www.test.de/file/theme/<ausgabe-id>/<theme>/<inhaltshash>/css/app.min.css
```

`<ausgabe-id>` ist ein neutraler, stabiler Namensraum fuer die aufgeloeste
Darstellung. Er verhindert Kollisionen zwischen Anbietern und Zweigen, ohne
Modulnamen in der URL offenzulegen. Der Theme-Name darf sichtbar sein.
Auch die Seiten-URL benoetigt keine Angabe des Template-Moduls.

Der Inhaltshash beruecksichtigt Quellen und Begleitdateien, Vererbung und
Asset-Reihenfolge, relevante Einstellungen, Custom CSS und verwendete
Build-Werkzeugversionen. Aenderungen erzeugen einen neuen Veroeffentlichungsstand.
Ein gueltiger Stand wird wiederverwendet, nicht bei jedem Request neu gebaut.

Die ausgewaehlten Dateien werden kopiert beziehungsweise verarbeitet. Dabei
bleiben relative Verweise gueltig oder werden beim Buendeln angepasst.
Nur benoetigte statische Dateien gelangen in das Ergebnis; keine kompletten
ungeprueften Modulverzeichnisse.

Neue Staende werden vollstaendig in einem temporaeren Verzeichnis erstellt und
erst danach atomar veroeffentlicht. Parallele Requests duerfen keinen
unvollstaendigen Stand sehen. Fehler werden protokolliert und im Backend sichtbar
gemacht; ein bisheriger gueltiger Stand wird nicht stillschweigend als erfolgreicher
Neubau ausgegeben. Ein nicht ersetzbarer fehlerhafter Erstaufbau ist ein Fehler.
Alte Staende bleiben fuer bereits ausgelieferte Seiten voruebergehend erhalten
und koennen anschliessend gezielt bereinigt werden.

## 10. Zugriff ueber .htaccess

Die Projekt-`.htaccess` bildet `/file/theme/...` intern ausschliesslich auf
`data_c\public\theme\...` ab. Eine Aenderung der VirtualHost-Konfiguration ist
nicht Teil des Konzepts.

Die Regeln muessen vor dem allgemeinen Seitenrouting:

1. Den Theme-Zugang auf eine explizite Positivliste benoetigter statischer
   Dateitypen begrenzen, beispielsweise CSS, JS, Bilder und Fonts.
2. Nur vorhandene Dateien innerhalb des vorgesehenen Theme-Verzeichnisses
   ausliefern; keine Verzeichniswechsel oder zusaetzlichen PATH_INFO-Segmente.
3. Nicht erlaubte Dateinamen und ausfuehrbare Endungen ablehnen. Auch
   Mehrfachendungen duerfen keine ausfuehrbaren Handler aktivieren.
4. Direkte externe Zugriffe auf `data_c` ablehnen, ohne den erlaubten internen
   Rewrite zu blockieren.
5. Fehlende oder unzulaessige `/file/theme/`-Anfragen mit einem passenden
   HTTP-Fehler beenden, statt sie als normale Seite zu behandeln.

PHP, serverseitig ausfuehrbare Dateien und fremde `.htaccess`-Dateien werden bereits
beim Veroeffentlichen ausgeschlossen. Im Theme-Ziel muss zusaetzlich ueber
projektseitige `.htaccess`-Regeln eine statische Behandlung sichergestellt werden;
eine erlaubte letzte Dateiendung allein garantiert keine deaktivierte Ausfuehrung.

Voraussetzung ist, dass Apache die erforderlichen `.htaccess`-Direktiven und
Rewrite-Regeln auswertet. Dies muss bei der Installation geprueft werden.
Ignoriert der Server `.htaccess` oder erzwingt nicht ueberschreibbare Handler,
kann das Projekt ohne Serveranpassung keine entsprechende Absicherung garantieren.
Diese Voraussetzung darf nicht als stillschweigend erfuellt gelten.

## 11. Umsetzung als Core-Klasse

Implementiert ist `fremeo\core\Template` in `system\core\Template.php`.
Der Name deckt die spaetere gemeinsame Verantwortung fuer Template-Vererbung
und Theme-Aufbereitung ab.

Die Registrierung erfolgt wie bei bestehenden Diensten zentral in Core-`init.php`
ueber `$C->set('Template', fn () => new \fremeo\core\Template())`, mit Zugriff ueber
`$C['Template']`. Die Methode `getExtends()` bekommt die vorhandenen Modulmetadaten
weiterhin als Parameter. Spaetere Theme-Methoden verwenden den vorhandenen
Einstellungsdienst. Es werden keine neuen
Service-Locator- oder Registrierungsmechanismen eingefuehrt.

Die Klasse wird schrittweise um wenige abgeschlossene Methoden fuer konkrete
Gesamtaufgaben erweitert:

- Template-Vererbung bestimmen (umgesetzt): Die bisherige Funktion `getExtends()`
  ist inhaltlich 1:1 als Methode uebernommen, ohne gleichzeitigen Umbau ihres
  Algorithmus oder Rueckgabeformats. Es gibt keine parallele globale Funktion.
- Darstellung aufloesen (geplant): Aus der vorhandenen Kette Anbieter, Zweig,
  Template-Erweiterungen und gewaehlte Theme-Abstammung bestimmen.
- Assets vorbereiten (geplant): Einstellungen und Custom CSS beruecksichtigen,
  Veroeffentlichung erstellen beziehungsweise wiederverwenden und geordnete
  Asset-URLs samt Ausgabeattributen bereitstellen.

Keine Zergliederung in zahlreiche kleine Hilfsfunktionen, keine separate
Manager-/Factory-Hierarchie. Jede Methode erfuellt eine zusammenhaengende
Aufgabe. Bestehende Dienste und geeignete Build-Bibliotheken werden wiederverwendet.

PHP fuehrt die Aufloesung und Dateiverarbeitung vor der Smarty-Ausgabe durch.
Core-`index__index.tpl` gibt nur die vorbereiteten CSS-/JS-Verweise in den
vorgesehenen Bloecken aus. Datenbankzugriffe, Modulermittlung, Kopieren und
Minimierung gehoeren nicht in TPL-Dateien.

## 12. Umsetzungsstufen

Die Umsetzung erfolgt in pruefbaren Etappen. Nach jeder Etappe wird vor der
naechsten zur fachlichen Pruefung und fuer moegliche Anpassungen angehalten.

| Etappe | Inhalt | Stand |
|---|---|---|
| 1 | Core-Klasse, Container-Einbindung, unveraenderter Umzug von `getExtends()` und Regressionstests | Umgesetzt |
| 2 | Theme-Aufloesung, lokale Core-/Frontend-Assets, Administration, Custom CSS, Rewrite-Auslieferung und Dokumentzentralisierung | Geplant |
| 3 | CSS-/JS-Minimierung, CSS-Zusammenfuehrung und Performance-Schalter | Geplant |
| 4 | Optionale Besucherwahl freigegebener Ableitungen | Spaetere Stufe 2 |

Etappe 1 ist mit `TemplateTest` und `ContainerTest` geprueft: 11 Tests mit
48 Assertions. Geprueft sind Root-Auswahl, getrennte Core-/Frontend-Ketten,
Sub-Roots, TPL-/PHP-Erweiterungen, Container, Smarty-Ausgabe und vorhandene
Modulpfade. Ein Vergleich mit dem bisherigen Funktionscode bestaetigt die
Uebernahme ohne inhaltliche Aenderung. Ein HTTP-Aufruf der Anwendung mit echter
Initialisierung liefert Status 200 und ein HTML-Dokument ohne PHP-Fehlerausgabe.
Eine visuelle Browser-/Darstellungspruefung ist damit noch nicht erfolgt.

### Stufe 1: Grundfunktion

- Automatische Anbieter- und Zweigzuordnung anhand der Template-Kette.
- Themes aus Template-Modulen und Core als Basis-Ausnahme.
- Haupt-Themes, ausgewaehlte Ableitungen und passende Template-Erweiterungen.
- Administration der Auswahl und familienbezogenes Custom CSS.
- Statische Veroeffentlichung mit erlaubtem Zugriff ueber `/file/theme/`.
- Unabhaengige Performance-Schalter und reproduzierbare Asset-Reihenfolge.
- Gemeinsame Dokumentstruktur statt duplizierter Frontend-Dokumentvorlage.

Fuer Core und Frontend wird in Etappe 2 jeweils die Bootstrap-basierte
Testableitung `basic__dark_mode` bereitgestellt. Sie wird administrativ
auswaehlbar; eine Besucher-/Session-Auswahl gehoert noch nicht zu dieser Etappe.
Die momentan extern oder ueber alte Pfade geladenen statischen Dateien werden
in die Theme-Quellen uebernommen und ueber den neuen Veroeffentlichungspfad
eingebunden. Bereits vorhandene lokale Bibliotheksdateien werden wiederverwendet.

### Stufe 2: Besucherwahl einer Ableitung

Die Administration legt weiterhin das Haupt-Theme, eine Standardableitung und die
fuer Besucher freigegebenen Child-Themes fest. Besucher duerfen ausschliesslich
innerhalb dieser Familie wechseln, beispielsweise zwischen `basic__white` und
`basic__dark`, nicht zu `classic`.

Die Session speichert eine validierte Variantenkennung, keinen Dateipfad.
Die Auswahl wird dem Anbieter, Zweig und Haupt-Theme zugeordnet. Ist sie nach
einem Update nicht mehr erlaubt oder verfuegbar, gilt die administrative
Standardauswahl. Assets werden je Variante gemeinsam genutzt, nicht je Session
kopiert. Ein HTML-Seitencache muss die Variantenwahl beruecksichtigen.

## 13. Abnahmekriterien fuer die spaetere Implementierung

- Eine Frontend-Inhaltsseite verwendet den Anbieter ihres Layout-Zweigs,
  nicht den ihres Funktionsmoduls.
- Backend und weitere Zweige funktionieren ohne fest codierte Bereichsliste;
  eine spaetere Backend-Auslagerung aendert den allgemeinen Mechanismus nicht.
- Unabhaengige installierte Templates werden nicht in die Theme-Kette gemischt.
- `basic -> dark -> contrast` laedt keine Geschwister wie `white`.
- Gleichnamige Theme-Dateien werden reproduzierbar ueberschrieben; unterschiedliche
  Assets behalten ihre deklarierte Reihenfolge.
- Custom CSS steht zuletzt und bleibt beim Variantenwechsel derselben Familie
  erhalten, wechselt aber nicht zwischen `basic` und `classic`.
- Alle acht Kombinationen der drei Performance-Schalter funktionieren.
- Fonts, Bilder und relative CSS-Verweise funktionieren auch nach dem Buendeln.
- Settings- und Quellenaenderungen erzeugen neue Asset-URLs; parallele Requests
  sehen keine teilweise erzeugten Dateien.
- Erlaubte Assets sind ausschliesslich ueber `/file/theme/` erreichbar.
  PHP, Mehrfachendungen mit ausfuehrbaren Bestandteilen, direkte `data_c`-Aufrufe,
  Verzeichniswechsel und PATH_INFO-Varianten werden abgelehnt.
- Der Umzug von `getExtends()` und die Dokumentzentralisierung erhalten die
  bestehende TPL-/PHP-Vererbung und die benoetigten Erweiterungsbloecke.
