# Konzept: Templates, Zweige und Themes

## Status und Ziel

Dieses Dokument beschreibt das abgestimmte Zielbild und den jeweiligen
Implementierungsstand. Bei Aenderungen am Template-/Theme-Code oder an dessen Regeln
wird es im selben Arbeitsschritt aktualisiert.

**Stand: Etappen 1 bis 3 umgesetzt.** Die Core-Klasse `Template` ermittelt
Vererbung, Anbieter und Theme-Kette und veroeffentlicht lokale Assets.
Core und Frontend besitzen jeweils `basic` und die auswaehlbare Ableitung
`basic__dark_mode`. Die Administration bietet eine gemeinsame Theme-Auswahl und Custom CSS.
CSS-/JS-Minimierung und CSS-Buendelung sind getrennt schaltbar.
Die Besucherwahl ist noch nicht implementiert.

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

Verbindliche Namenskonvention fuer Module mit Typ `template`:
`<parent>__<child>`, beispielsweise `fremeo/frontend__shop`. Weitere Ableitungen
koennen `fremeo/shop__customer` heissen. Der Elternname bezeichnet den letzten
Namensbestandteil eines Anbieters desselben Herstellers. Er muss eindeutig sein.
Ein normales Funktionsmodul `fremeo/shop` wird dabei nicht als Theme-Anbieter
beruecksichtigt.

Die Modul-Ableitung wird geprueft und in der TPL-/PHP-Vererbung in Elternreihenfolge
verarbeitet. Theme-Beitraege werden nur fuer Anbieter aufgenommen, die tatsaechlich
in der aktuellen TPL-Kette vorkommen. Die Namen allein aktivieren keinen Beitrag.
Zwei gleichzeitig beitragende Geschwister-Erweiterungen werden nicht willkuerlich
sortiert: Wenn beide erforderlich sind, muessen sie eine eindeutige Kette bilden.

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

Implementiert ist ein optionales `theme.json` direkt im jeweiligen Theme-Ordner:

```json
{
  "color_scheme": "light",
  "css": [
    "css/layout.css",
    {"path": "css/print.css", "media": "print"},
    {"path": "css/admin.css", "branches": ["admin"]}
  ],
  "head_js": ["vendor/jquery/3.7.1/jquery.min.js"],
  "body_js": ["js/layout.js"]
}
```

Bootstrap-JavaScript wird in Core und Frontend ueber `body_js` am Body-Ende
geladen, vor davon abhaengigen Skripten wie `admin.js` und vor `body_end`.
Dies entspricht der Bootstrap-5.3-Empfehlung und blockiert nicht den vorherigen
HTML-Aufbau. Direkte Bootstrap-Initialisierung gehoert in nachfolgende Skripte
oder einen `DOMContentLoaded`-Handler, nicht in sofort ausgefuehrten Head-Code.
`bootstrap.bundle.min.js` enthaelt bereits Popper; die separate alte Popper-Datei
wird deshalb nicht zusaetzlich im Head geladen. jQuery bleibt wegen vorhandener
Inline-Skripte vorerst synchron im Head; eine pauschale Umstellung auf `defer`
waere ohne Anpassung dieser Skripte nicht kompatibel.
Die Ladereihenfolge ist mit echten Core-/Frontend-Templates automatisiert
geprueft. Ein Browsertest bestaetigt Bootstrap 5.3.8 und ein funktionierendes
Dropdown mit dem gebuendelten Popper, ohne separate Popper-Anfrage oder JS-Fehler.

`color_scheme` ist `light` oder `dark` und setzt das Bootstrap-Farbschema am
HTML-Wurzelelement. Eine Ableitung erbt das Schema, sofern sie es nicht aendert.
`branches` begrenzt einen Asset-Eintrag auf die genannten, frei benennbaren Zweige.
Ohne diese Angabe gilt der Eintrag fuer alle Zweige des Anbieters. So bleiben
Backend-Styles und Sidebar-Scripts im Zweig `admin`, ohne Sonderlogik im Resolver.

Gleichnamige Asset-Eintraege erscheinen nur einmal; spaetere Dateien ueberschreiben
den Inhalt am gleichen relativen Pfad. Fonts und Bilder behalten ihre Pfade.
`_custom.css` ist fuer das generierte Custom CSS reserviert.

Die ehemals in der gemeinsamen Dokumentvorlage verlinkten Bibliotheken liegen
lokal und versioniert in beiden Basis-Themes. Die zuvor mit `media="bogus"`
deaktivierte Legacy-Datei `main.css` bleibt zur Wahrung des bisherigen Verhaltens
deaktiviert. Das fuer die Gestaltung aktive Backend-CSS liegt separat in
`css/admin.css` und verwendet Bootstrap-Farbvariablen.

## 7. Einstellungen und Custom CSS

Es wird ausschliesslich die vorhandene Einstellungsverwaltung verwendet:
`Setting`, die modulbezogene Datenbank und die Definitionen in
`system\default\default.config.php`. Es entsteht kein paralleles Einstellungssystem.

| Einstellung | Ablage |
|---|---|
| Template-uebergreifende Auswahl und Zuordnungen | Core-Datenbank |
| Performance-Schalter je Theme-Anbieter | Datenbank dieses Anbieters |
| Gewaehltes Theme inklusive optionaler Ableitung eines Template-Anbieters, bei Bedarf je Zweig | Datenbank dieses Anbieters |
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

### Bedienung in Etappe 2

Unter **Einstellungen -> Template -> Core / Frontend** stehen je Anbieter:

- `theme`: Konkretes Theme; Standard `basic`. Die Liste enthaelt Familien und
  Ableitungen gemeinsam, beispielsweise `basic`, `basic__dark_mode` und `classic`.
  Die Haupt-Familie wird aus der Theme-Abstammung ermittelt, nicht separat gewaehlt.
- `theme_css_<haupt-theme>`: Custom CSS dieser Familie, beispielsweise
  `theme_css_basic`. Felder werden fuer die Haupt-Theme-Ordner des Anbieters erzeugt.

Die Theme-Auswahllisten setzen `AllowEmpty => false`; `input.tpl` blendet damit
ueber `allow_empty=false` die automatische `---`-Option aus. Eine gesonderte
Standardoption ist unnoetig: `basic` waehlt die Basisdarstellung direkt.
Andere Select-Felder behalten ohne diese Angabe ihr bisheriges Verhalten.
Die serverseitige Pruefung auf vorhandene Themes bleibt erhalten.

Bestehende Datenbanken mit `theme_variant` bleiben kompatibel: `Setting::get()`
liefert fuer `theme` die bisher gewaehlte Ableitung. Beim expliziten Speichern
von `theme` wird der alte Eintrag geloescht; kuenftig ist nur `theme` massgeblich.
Es erfolgt keine Schreibmigration bei einem reinen Seitenaufruf.

Die Optionen werden aus den Theme-Ordnern ermittelt. Die Auswahl gilt derzeit
anbieterweit; Core-Layouts wie Admin und Login teilen die Core-Auswahl. Das
Frontend bleibt davon unabhaengig. Eine zusaetzliche Auswahl pro Zweig ist noch
nicht implementiert.

Die Einstellungsbloecke folgen der zentralen Modulreihenfolge aus `init.php`:
Core, Template-Module, normale Module, uebrige Typen. Innerhalb eines Typs gilt
die Verzeichnis-Scanreihenfolge. Der Scan baut die Liste unabhaengig von der
POST-Reihenfolge auf und uebernimmt Formulardaten nur zu den gefundenen Modulen.
Dadurch bleiben die Bloecke auch beim Speichern einzelner Felder an ihrem Platz.
Die leere Frontend-Testableitung `basic__modern` wurde entfernt; fuer Darkmode
steht `basic__dark_mode` bereit.

Der Wechsel zwischen Familien und Ableitungen erfolgt mit derselben Auswahl.
Unbekannte oder leere Theme-Werte werden mit einer Meldung abgelehnt und fuer
das betroffene Modul nicht gespeichert.
Es werden nur bekannte Einstellungsfelder per POST gespeichert. Standardwerte
entfernen entsprechende Datenbank-Abweichungen; fehlende Felder bleiben unveraendert.
Auch explizit leere oder ausgeschaltete Datenbankwerte werden vom Einstellungsdienst
gegenueber Defaults beruecksichtigt.

## 8. Performance-Optionen

Kategorie der vorhandenen Einstellungsoberflaeche:
**Template -> Core / Frontend -> Performance**.
Die drei unabhaengigen Schalter werden in der Datenbank des jeweiligen
Theme-Anbieters gespeichert. Core und jedes Template-Modul koennen dieselben
Schluessel in ihrer `default.config.php` unter der eigenen Performance-Gruppe
anbieten. Alle sind standardmaessig ausgeschaltet:

| Schalter | Wirkung |
|---|---|
| CSS minimieren (`theme_minify_css`) | Stylesheets verkleinern. |
| JavaScript minimieren (`theme_minify_js`) | Scripts verkleinern, ohne ihre Ladereihenfolge zu aendern. |
| CSS zusammenfassen (`theme_combine_css`) | Kompatible Stylesheets in deklarierter Reihenfolge buendeln. |

CSS-Zusammenfuehrung und Minimierung koennen unabhaengig aktiviert werden.
Eine JS-Zusammenfuehrung ist in der ersten Stufe nicht vorgesehen.

Die Verarbeitung liest ausschliesslich die Einstellungen von
`$theme['Provider']`; es gibt keinen Rueckfall auf Core-Einstellungen.
Eine Frontend-Optimierung veraendert daher weder Admin-/Login-Assets noch deren
Cache-Hash. Auch weitere Layout-Anbieter und Zweige werden generisch behandelt.
Beitragende Template-Erweiterungen verwenden die Auswahl des aufgeloesten
Haupt-Anbieters, damit die gesamte aktive Theme-Kette einheitlich gebaut wird.
Vorhandene Core-Schalter bleiben fuer Core erhalten; Frontend startet mit eigenen
ausgeschalteten Defaults, ohne die frueher globalen Werte zu uebernehmen.
Ein fehlerhafter Frontend-Build verhindert somit keinen Core-Asset-Build.
Eine fehlerhafte aktivierte Core-Optimierung selbst bleibt weiterhin ein Fehler.

Bei CSS sind relative URLs zu Fonts und Bildern, `@import`, Zeichencodierungen
und unterschiedliche `media`-Bedingungen zu beruecksichtigen. Nicht kompatible
Stylesheets bleiben getrennt. CSS und JavaScript werden nicht mit einfachen
regulaeren Ausdruecken minimiert, sondern mit geeigneten Parser-/Minifier-Werkzeugen.

Die Composer-Abhaengigkeiten `matthiasmullie/minify` und
`wikimedia/css-sanitizer` sind im Core und fuer den lokalen Loader deklariert.
Von Wikimedia werden ausschliesslich Syntaxparser, Tokens und Serialisierung
verwendet, kein Sanitizer mit eingeschraenkter CSS-Eigenschaftsliste.
So bleiben auch Bootstrap-Variablen, calc-Ausdruecke und moderne At-Rules erhalten.
Der Path-Converter aus der Minify-Abhaengigkeit passt relative URLs an.

Es werden nur unmittelbar aufeinanderfolgende Stylesheets mit identischem
`media` zusammengefasst. Weder Mediengrenzen noch separat belassene Dateien
werden uebersprungen. Reihenfolge: Basis, aktive Ableitungen, Custom CSS zuletzt.
`url(...)` und relative Strings in `image-set(...)` werden anhand ihres
urspruenglichen Stylesheetpfads umgeschrieben; Query und Fragment bleiben erhalten.
Absolute URLs, Data-URLs und reine Fragmentreferenzen werden nicht umgebogen.

Dateien mit `@import`, `@namespace`, anderem deklariertem Zeichensatz als UTF-8
oder nicht UTF-8-kodiertem Inhalt bleiben separat und werden nicht minimiert,
auch bei aktivierter Minimierung. Dadurch wird weder Import-Reihenfolge noch
Namespace-Geltung veraendert. UTF-8-Charset-Direktiven werden beim Verarbeiten
entfernt; jede erzeugte CSS-Datei bekommt genau eine UTF-8-Direktive am Anfang.
Ungueltige CSS-Syntax bricht den Build explizit ab, ohne Regeln zu verschlucken.
Lizenz-/Copyright-Kommentare bleiben in erzeugtem CSS erhalten.

JS wird dateiweise in derselben Head-/Body-Position minimiert, nie zusammengefasst.
Dateien mit `.min.js` bleiben unveraendert. Bei CSS werden `.min.css`-Dateien
ohne Buendelung nicht erneut minimiert; relative Abhaengigkeiten bekommen jedoch
wie in anderen UTF-8-Stylesheets den Versionsparameter. Der Schalter erzeugt
keine unminifizierten Originale.
Minimierung ist von HTTP-Transportkompression unabhaengig.

## 9. Veroeffentlichung und URLs

Physischer Zielpfad:

```text
data_c\public\theme\<ausgabe-id>\<theme>\...
```

Oeffentliche Adresse desselben Ergebnisses:

```text
https://www.test.de/file/theme/<ausgabe-id>/<theme>/_build/css-0.min.css?v=<version>
```

`<ausgabe-id>` ist ein neutraler, stabiler Namensraum fuer die aufgeloeste
Darstellung. Er verhindert Kollisionen zwischen Anbietern und Zweigen, ohne
Modulnamen in der URL offenzulegen. Der Theme-Name darf sichtbar sein.
Auch die Seiten-URL benoetigt keine Angabe des Template-Moduls.

Der Inhaltshash beruecksichtigt Quellen und Begleitdateien, Vererbung und
Asset-Reihenfolge, relevante Einstellungen, Custom CSS und verwendete
Build-Werkzeugversionen. Der Hash dient intern zur Aenderungserkennung und als
stabiler `v`-Wert, nicht mehr als Verzeichnisname. Aenderungen ersetzen den
Veroeffentlichungsstand im festen Theme-Ordner.
Ein gueltiger Stand wird wiederverwendet, nicht bei jedem Request neu gebaut.
Die drei Schalter und die Composer-Referenzen der Build-Bibliotheken gehen in
den Hash ein. `_build/` und `_custom.css` sind fuer generierte Dateien reserviert.
Die intern gespeicherte `_assets.json` enthaelt Pruefsumme und fertige Asset-Liste, damit
Cache-Treffer weder erneut parsen noch minimieren muessen. Sie ist ueber die
Endungs-Positivliste nicht oeffentlich abrufbar.

Die ausgewaehlten Dateien werden kopiert beziehungsweise verarbeitet. Dabei
bleiben relative Verweise gueltig oder werden beim Buendeln angepasst.
Nur benoetigte statische Dateien gelangen in das Ergebnis; keine kompletten
ungeprueften Modulverzeichnisse.

Neue Staende werden vollstaendig in einem temporaeren Verzeichnis erstellt.
Unter der Anbieter-/Zweigsperre wird der bisherige Ordner kurz in einen privaten
Backup-Ordner umbenannt und der fertige neue Stand eingesetzt. Scheitert das
Einsetzen, wird der vorige Ordner zurueckbenannt und der Fehler weitergegeben.
Nach Erfolg wird das Backup geloescht. Entfernte Quelldateien und alte erzeugte
Bundles verschwinden damit automatisch; es entstehen keine Versionsordner mehr.
Auch die bisherigen Hash-Unterordner des ersetzten Themes werden entfernt.
Der Austausch besteht aus zwei Rename-Schritten, ist also kein atomarer
Verzeichnistausch: statische Anfragen koennen im kurzen Zwischenraum 404 erhalten.

Alle ausgegebenen CSS-/JS-URLs tragen `?v=<version>`. `versionCss()` passt
relative `url(...)`, `image-set(...)` und Importverweise in UTF-8-CSS an:
vorhandene Queryparameter und Fragmente bleiben erhalten, ein bisheriges `v`
wird ersetzt. Absolute/externe URLs, Data-URLs und reine Fragmente bleiben gleich.
Nicht UTF-8-kodiertes CSS oder CSS mit anderem deklariertem Zeichensatz bleibt
unveraendert; dessen Abhaengigkeiten erhalten keinen Versionsparameter.
Die CSS-Syntax bleibt erhalten, die Serialisierung kann Formatierung aendern;
Lizenzhinweise bleiben erhalten, auch ohne aktivierte Minimierung.

Alte `?v=`-Werte liefern ebenfalls den aktuellen Dateiinhalt; historische
Dateistaende werden bewusst nicht vorgehalten. Bei entfernten Dateien koennen
bereits geoeffnete oder HTML-gecachte Seiten bis zum Neuladen 404 erhalten.
Ein fehlerhafter Neubau wird nicht als Erfolg ausgegeben; die bisherige
Veroeffentlichung bleibt bei Fehlern vor dem Austausch bestehen.

## 10. Zugriff ueber .htaccess

Die Projekt-`.htaccess` bildet `/file/<bereich>/...` intern ausschliesslich auf
`data_c\public\<bereich>\...` ab, beispielsweise `/file/theme/...` auf
`data_c\public\theme\...`. Die Regeln gelten fuer alle oeffentlichen Cache-Bereiche
und deren Unterordner, nicht nur Themes. Eine Aenderung der VirtualHost-
Konfiguration ist nicht Teil des Konzepts.

Die Regeln muessen vor dem allgemeinen Seitenrouting:

1. Den Public-Zugang auf eine explizite Positivliste benoetigter statischer
   Dateitypen begrenzen, beispielsweise CSS, JS, Bilder und Fonts.
2. Nur vorhandene Dateien innerhalb des vorgesehenen Public-Verzeichnisses
   ausliefern; keine Verzeichniswechsel oder zusaetzlichen PATH_INFO-Segmente.
3. Nicht erlaubte Dateinamen und ausfuehrbare Endungen ablehnen. Auch
   Mehrfachendungen duerfen keine ausfuehrbaren Handler aktivieren.
4. Direkte externe Zugriffe auf `data_c` ablehnen, ohne den erlaubten internen
   Rewrite zu blockieren.
5. Fehlende oder unzulaessige `/file/<bereich>/`-Anfragen mit einem passenden
   HTTP-Fehler beenden, statt sie als normale Seite zu behandeln.

PHP, serverseitig ausfuehrbare Dateien und fremde `.htaccess`-Dateien werden bereits
beim Veroeffentlichen ausgeschlossen. Fuer den Public-Cache muss zusaetzlich ueber
projektseitige `.htaccess`-Regeln eine statische Behandlung sichergestellt werden;
eine erlaubte letzte Dateiendung allein garantiert keine deaktivierte Ausfuehrung.

Voraussetzung ist, dass Apache die erforderlichen `.htaccess`-Direktiven und
Rewrite-Regeln auswertet. Dies muss bei der Installation geprueft werden.
Ignoriert der Server `.htaccess` oder erzwingt nicht ueberschreibbare Handler,
kann das Projekt ohne Serveranpassung keine entsprechende Absicherung garantieren.
Diese Voraussetzung darf nicht als stillschweigend erfuellt gelten.

Implementiert sind eine Endungs-/Pfad-Positivliste und die Existenzpruefung in
der Projekt-`.htaccess`. Nicht vorhandene oder unpassende Public-Anfragen liefern
404; ausfuehrbare Namensbestandteile und direkte `data_c`-Aufrufe werden gesperrt.
Eine dateisystembezogene `<If>`-Bedingung in der Root-`.htaccess` setzt fuer vorhandene
Dateien unter `data_c/public/` `SetHandler default-handler`,
deaktiviert CGI, MultiViews und Verzeichnislisten und verbietet PATH_INFO.
Es wird keine `.htaccess` im Public-/Theme-Ordner benoetigt oder erzeugt;
die fruehere Core-Vorlage und die generierte Theme-Schutzdatei wurden entfernt.
Die bestehende dynamische Bildroute `/file/<id>_<groesse>.jpg` bleibt getrennt:
ohne Bereichs-Unterordner darf sie bei fehlendem Cache weiterhin PHP aufrufen.
Quellseitige PHP-Dateien, Manifeste und fremde `.htaccess`-Dateien werden nicht
veroeffentlicht. Symlinks werden als Fehler abgelehnt.

## 11. Umsetzung als Core-Klasse

Implementiert ist `fremeo\core\Template` in `system\core\Template.php`.
Der Name deckt die spaetere gemeinsame Verantwortung fuer Template-Vererbung
und Theme-Aufbereitung ab.

Die Registrierung erfolgt wie bei bestehenden Diensten zentral in Core-`init.php`
ueber eine Closure mit `use ($C, &$D)` und `new \fremeo\core\Template($C, $D)`,
mit Zugriff ueber `$C['Template']`. Die Methode `getExtends()` bekommt die vorhandenen
Modulmetadaten weiterhin als Parameter. Theme-Methoden verwenden den vorhandenen
Einstellungsdienst. Es werden keine neuen
Service-Locator- oder Registrierungsmechanismen eingefuehrt.

Die Klasse wird schrittweise um wenige abgeschlossene Methoden fuer konkrete
Gesamtaufgaben erweitert:

- Template-Vererbung bestimmen (umgesetzt): `getExtends()` behaelt Algorithmus
  und Rueckgabeformat. In Etappe 2 liest sie den Modultyp korrekt aus `INFO.Type`
  und beruecksichtigt die Elternreihenfolge von Template-Modul-Ableitungen.
  Die bisherige Root-Auswahl bleibt bestehen; es gibt keine globale Parallelfunktion.
- Anbieter und Themes ermitteln (umgesetzt): `getThemeModules()` liefert die
  geordnete Anbieter-Abstammung; `getThemes()` ermittelt Theme-Familien und deren
  Ableitungen fuer Aufloesung und Einstellungsoptionen.
- Darstellung aufloesen (umgesetzt): `resolveTheme()` bestimmt Anbieter, Zweig,
  Template-Erweiterungen und gewaehlte Theme-Abstammung.
- Assets vorbereiten (umgesetzt): `publishTheme()` beruecksichtigt Custom CSS,
  erstellt eine Veroeffentlichung beziehungsweise verwendet sie wieder und
  liefert geordnete Asset-URLs samt Ausgabeattributen. Die Dateiverarbeitung
  verwendet den vorhandenen `Filesystem`-Dienst aus dem Container.
- Assets optimieren (umgesetzt): `optimizeAssets()` verarbeitet die geordnete
  Asset-Liste im privaten Build-Verzeichnis anhand der drei Performance-Schalter
  und liefert die tatsaechlich auszuliefernden Pfade zurueck.
- CSS-Abhaengigkeiten versionieren (umgesetzt): `versionCss()` ergaenzt relative
  Verweise mit dem `v`-Parameter, unabhaengig von Minimierung und Buendelung.

`start.php` bereitet nach den PHP-Seiten die Darstellung auf und stellt sie unter
`$R['Theme']` beziehungsweise `$D.R.Theme` bereit. Das Ergebnis enthaelt Anbieter,
Zweig, Haupt-Theme, Ableitung, Farbschema, Inhaltshash sowie `Assets.css`,
`Assets.head_js` und `Assets.body_js`.

Die Container-Closures fuer `Template` und `Setting` muessen `$D` per Referenz
erfassen, damit spaeter geladene Module sichtbar bleiben. Die referenzierte
Eigenschaft ist nullable: `CData::get_object()` setzt den Datenbaum waehrend einer
Abfrage kurz auf null und stellt ihn danach wieder her.

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
| 2 | Theme-Aufloesung, lokale Core-/Frontend-Assets, Administration, Custom CSS, Rewrite-Auslieferung und Dokumentzentralisierung | Umgesetzt |
| 3 | CSS-/JS-Minimierung, CSS-Zusammenfuehrung und Performance-Schalter | Umgesetzt |
| 4 | Optionale Besucherwahl freigegebener Ableitungen | Spaetere Stufe 2 |

Etappe 1 ist mit `TemplateTest` und `ContainerTest` geprueft: 11 Tests mit
48 Assertions. Geprueft sind Root-Auswahl, getrennte Core-/Frontend-Ketten,
Sub-Roots, TPL-/PHP-Erweiterungen, Container, Smarty-Ausgabe und vorhandene
Modulpfade. Ein Vergleich mit dem bisherigen Funktionscode bestaetigt die
Uebernahme ohne inhaltliche Aenderung. Ein HTTP-Aufruf der Anwendung mit echter
Initialisierung liefert Status 200 und ein HTML-Dokument ohne PHP-Fehlerausgabe.
Eine visuelle Browser-/Darstellungspruefung war nicht Bestandteil von Etappe 1.

### Stufe 1: Grundfunktion

Der in der Core-Datenbank gespeicherte Modulstatus `MODULE.D[<id>].Active`
gilt ab der naechsten Anfrage. Inaktive Module liefern weder Initialisierung
noch Routing, Einstellungen oder Template-/Theme-Beitraege. Bei deaktivierten
Template-Eltern werden auch deren Ableitungen nicht geladen. Core bleibt immer
aktiv und verwaltbar; ohne aktives Template-Modul meldet der Frontend-Zweig
HTTP 503 statt eines unvollstaendigen Layouts.

Die Admin-Sidebar nutzt Bootstrap Collapse fuer Untermenues im ausgeklappten
Navigationsbaum. Der Schalter in der oberen Leiste wechselt zur schmalen Icon-Leiste;
bei Eintraegen ohne Untermenue zeigen native Hover-Titel die Namen.
Untermenues oeffnen dort bei Hover, Fokus oder
Klick als seitliche Fenster. Diese sind an die Viewport-Hoehe begrenzt und scrollbar.
Ein kleines Admin-Skript steuert nur Kompaktmodus und Flyout-Positionierung;
Bootstrap-Attribute und der geoeffnete Baumzustand bleiben erhalten.
Flyouts zeigen den Hauptmenue-Namen als Ueberschrift statt als Hover-Titel.
Sie schliessen ohne Zwischenraum an das Symbol an; eine kurze Schliessverzoegerung
erleichtert den Mausuebergang, ohne Scrollpositionen beim Fokuswechsel zurueckzusetzen.

- Automatische Anbieter- und Zweigzuordnung anhand der Template-Kette.
- Themes aus Template-Modulen und Core als Basis-Ausnahme.
- Haupt-Themes, ausgewaehlte Ableitungen und passende Template-Erweiterungen.
- Administration der Auswahl und familienbezogenes Custom CSS.
- Statische Veroeffentlichung mit erlaubtem Zugriff ueber `/file/theme/`.
- Reproduzierbare Asset-Reihenfolge und unabhaengige Performance-Schalter.
- Gemeinsame Dokumentstruktur statt duplizierter Frontend-Dokumentvorlage.

Fuer Core und Frontend wird in Etappe 2 jeweils die Bootstrap-basierte
Testableitung `basic__dark_mode` bereitgestellt. Sie wird administrativ
auswaehlbar; eine Besucher-/Session-Auswahl gehoert noch nicht zu dieser Etappe.
Die bisher extern oder ueber alte Pfade im gemeinsamen Dokument geladenen
CSS-/JS-Dateien sind in die Theme-Quellen uebernommen und ueber den neuen
Veroeffentlichungspfad eingebunden. Bereits vorhandene lokale Bibliotheksdateien
werden wiederverwendet. Die bisher deaktivierte Legacy-Datei `main.css` bleibt
mit `media="bogus"` inaktiv; ihre Regeln werden durch den Umzug nicht aktiviert.

### Pruefung von Etappe 2

- `ThemeTest`, `TemplateTest` und `ContainerTest`: 33 Tests, 242 Assertions.
  Geprueft werden unter anderem Anbieter-/Theme-Abstammung, Zweige, konkrete
  Core-/Frontend-Quellen, Dark-Mode-Ausgabe, Custom CSS, Einstellungsformular,
  Datenbankzuordnung, statische Dateifilter und gleichzeitiges Veroeffentlichen.
  Der echte Core-Initialisierungspfad wird mit temporaeren Modulen und
  unterschiedlichen POST-Reihenfolgen geprueft: Ladefolge, Einstellungsbloecke
  und gespeicherte Werte bleiben konsistent.
  Gerenderte Select-Felder pruefen die optionale Leeroption, konkrete
  gemeinsame Theme-Auswahl ohne Leeroption sowie die Uebernahme und Bereinigung
  bisher getrennt gespeicherter Ableitungen.
- Frontend-Tests: 9 Tests, 31 Assertions.
- PHP-Syntax, neues Admin-JavaScript und Git-Whitespace-Pruefungen ohne Fehler.
- Live-Frontend: HTTP 200 ohne PHP-Fehlerausgabe; alle 16 eingebundenen CSS-/JS-
  Dateien sowie Font-Awesome-Schrift und jQuery-UI-Icon werden lokal ausgeliefert.
  Im Browser sind alle Stylesheets geladen; keine JavaScript- oder HTTP-Fehler.
- Gesperrte einfache und zusammengesetzte PHP-Endungen liefern 403.
  Fehlende Assets, Manifeste und PATH_INFO liefern 404; direkter `data_c`-Zugriff
  ist gesperrt. Der Schutz liegt ausschliesslich in der Root-`.htaccess`.

Die Testlaeufe melden eine bestehende PHP-8.5-Deprecation in
`phploader/cdata/lib/CData.php`; die Bibliothek wird in dieser
Etappe nicht geaendert. Ein angemeldeter Admin-Browsertest ist nicht erfolgt;
Einstellungsformular und dunkle Core-/Frontend-Ausgabe sind mit echten Quellen
und temporaeren Datenbanken automatisiert geprueft.

### Pruefung von Etappe 3

- Core-Theme-/Template-/Container-Tests: 39 Tests, 482 Assertions;
  Frontend-Tests: 9 Tests, 31 Assertions. Die bekannte CData-Deprecation bleibt bestehen.
- Alle acht Schalterkombinationen pruefen unabhaengige Wirkung, unterschiedliche
  Versionswerte, unveraendertes JavaScript bei ausgeschalteten Schaltern, Cache-Wiederverwendung,
  CSS-Reihenfolge und Custom CSS zuletzt.
- Tests fuer Mediengrenzen, Imports, Namespaces, UTF-8-/Legacy-Zeichensaetze,
  Lizenzkommentare, relative Font-/Bild-URLs und CSS-Fehler ohne Teilpublikation.
- Echte Core-/Frontend-Bibliotheken lassen sich optimiert veroeffentlichen;
  die CSS-Gesamtgroesse sinkt und die Skriptreihenfolge bleibt erhalten.
  Gleichzeitige optimierte Builds liefern dieselbe vollstaendige Version.
- Die Performance-Einstellungen werden ueber die echte Einstellungsseite in
  temporaeren Datenbanken gespeichert und beim Ausschalten auf Defaults zurueckgesetzt.
  Core und Frontend besitzen getrennte Performance-Gruppen mit jeweils drei
  modulgebundenen Feldern. Ein weiterer Account-Anbieter bestaetigt die generische
  Trennung: Jede Schalteraenderung laesst Hash und Ausgabe anderer Anbieter gleich.
  Auch bei ungueltigem Frontend-CSS bleibt der Core-Build unveraendert nutzbar.
- Optimierte Dateien werden im lokalen Apache mit HTTP 200 ausgeliefert;
  Fonts und Icons sind erreichbar, die interne Asset-Liste liefert 404.
  Alle ausgelieferten Skripte wurden zusaetzlich mit `node --check` geprueft.
- Browserpruefung mit isoliert gebauten Darkmode-Assets auf der echten
  Frontend-Seite: drei statt acht CSS-Verweise, alle Stylesheets geladen,
  Bootstrap-Abstaende/Farben und Font Awesome korrekt, keine JavaScript-/HTTP-Fehler.
  Die Preview tauscht nur im Browser die Asset-Verweise aus; echte Einstellungen
  und Benutzerdaten werden nicht veraendert.
- Feste Theme-Pfade mit `?v=` sind automatisiert und im Browser geprueft:
  Quellenaenderungen wechseln den Versionswert, nicht den Dateipfad; obsolete
  Dateien und alte Hash-Unterordner verschwinden beim Austausch. Ein fehlgeschlagener
  Neubau erhaelt den vorherigen Stand. Relative Bild-/Font-/Importverweise bekommen
  ebenfalls die Version; echte Icons und Font Awesome laden ohne Browserfehler.
- Die Root-Regeln sind ohne Theme-`.htaccess` am lokalen Apache geprueft:
  statisches CSS auch in einem anderen Public-Bereich mit Unterordner liefert 200,
  darin enthaltene PHP-Tags werden nicht ausgefuehrt. Mehrfachendungen und direkter
  Cache-Zugriff liefern 403, fehlende Dateien und PATH_INFO 404. Alle 15 aktiven
  Theme-Assets bleiben erreichbar; die dynamische Bildroute bleibt funktionsfaehig.
  Auch bei einem im Test-Unterordner gesetzten PHP-Handler bleibt CSS statisch.
  SEO-Pfade mit `file/` oder `data_c/public/` im Namen aktivieren den Schutz nicht.
- Composer-Manifeste sind gueltig und die neuen Bibliotheken lokal installiert.
  Der bereits vorhandene Loader-Lock verweist noch auf Core v0.0.2 statt der
  geforderten Version >=0.0.4. Nur die neuen Bibliotheken wurden installiert,
  keine bestehenden Modul-Quellen ersetzt. Composer meldet ausserdem einen
  bestehenden Advisory fuer `symfony/filesystem`; ein Fremdbibliotheks-Update
  gehoert nicht zu dieser Etappe.

### Stufe 2: Besucherwahl einer Ableitung

Die Administration legt weiterhin das Standard-Theme und die fuer Besucher
freigegebenen Child-Themes fest. Die Haupt-Familie folgt aus dem Standard-Theme.
Besucher duerfen ausschliesslich
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
