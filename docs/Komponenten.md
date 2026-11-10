# Smarty-Komponenten

Core registriert das Smarty-Plugin `component` aus
`system/core/smarty_plugin/function.component.php` an seiner Smarty-Instanz.
Der Aufruf benoetigt Core ab `0.0.8`; das Frontend nutzt ihn ab `0.0.2`.
Aktive Pakete vom Typ `core`, `template` und `module` koennen Komponenten liefern:

```text
system/template/components/<type>/component.json
system/template/components/<type>/<type>.tpl
```

Der Aufruf nennt immer `module` und `type`. Es gibt keine automatische Suche,
Vererbung oder Ueberschreibung durch andere Anbieter. Ein Child-Template kann
die Komponente des Elternmoduls aufrufen oder ausdruecklich seine eigene waehlen.
Fehlende oder deaktivierte Anbieter, fehlende Dateien und ungueltige Parameter
erzeugen einen Fehler statt einer stillen Ersatzausgabe.

```smarty
{component module="fremeo/frontend" type="navigation"
    data=$D.MODULE.D['fremeo/frontend'].NAVIGATION.D.top_navigation
    options=['variant' => 'horizontal', 'language' => $D.R.Frontend.language]}
```

`data` ist der vorhandene Pattern-konforme Daten-Teilbaum, kein neues
Datenbankformat. PHP laedt und bereitet ihn vor, bei Bedarf ueber den
komponentenlokalen Datenanbieter (siehe unten). `options` enthaelt getrennte
Darstellungsoptionen. Beide Werte sind Arrays.

Das Manifest enthaelt den passenden `type`, optional `name`, `defaults`
(Standardoptionen) und `variants` (erlaubte Werte fuer `options.variant`).
Das Komponenten-Template erhaelt lokale Variablen `data`, `options` und
`component` mit `id`, `type` und `module`. Diese ueberschreiben keine
gleichnamigen Variablen im aufrufenden Template. Komponenten muessen Texte und
Attribute passend escapen.

## Einstellungsfelder im Builder

Die Datei heisst weiterhin `component.json` (Singular). Das optionale Objekt
`settings` definiert die im Popup bearbeitbaren Optionen; andere Defaults
bleiben erhalten und werden nicht automatisch zu Formularfeldern.

```json
{
  "type": "navigation",
  "name": "Navigation",
  "defaults": { "variant": "horizontal", "language": "DE" },
  "variants": ["horizontal", "vertical"],
  "settings": {
    "variant": {
      "type": "string",
      "label": "Ausrichtung",
      "enum": ["horizontal", "vertical"]
    },
    "language": { "type": "string", "label": "Sprache" }
  }
}
```

Jedes Einstellungsfeld benoetigt `type`, `label` und einen gleichnamigen
Standardwert in `defaults`. Unterstuetzt: `string` (Text), `boolean`
(Checkbox), `integer` und `number` (Zahl). `enum` erzeugt eine Auswahl mit
typgetreuen Werten; fuer Zahlen sind `min` und `max` moeglich.
Core `Component::load` und `Component::options` pruefen Manifest und Werte
gemeinsam fuer Bibliothek, normalen Renderer und Editor-Vorschau.
Ohne `settings` bleibt ein bestehendes Manifest renderbar, bietet aber keine
automatisch erfundenen Einstellungsfelder. PHP-Aktionen werden nicht gestartet.
Popup-Uebernahme aendert nur die Vorschau und ist rueckgaengig machbar.

Die Manifestfelder stehen im Tab mit dem Komponentennamen. Der Tab Allgemein
enthaelt Builder-weite Instanzwerte (CustomCssClass, CustomCss-Deklarationen,
Active und Bildschirmgroessen). Sie werden als `node.general` gespeichert,
nicht in `options` eingeschleust und nicht je Manifest dupliziert.
Die Darstellung nutzt einen gemeinsamen Instanz-Wrapper. Details, Standardwerte
und Groessenbereiche stehen im [Builder-Konzept](Konzept_Page_Builder.md).
Die Formulare werden serverseitig als TPL gerendert und als inaktive
HTML-`template`-Vorlagen mit der Adminseite geladen. Beim Oeffnen klont der
Editor die passende Vorlage und bindet Werte und Ereignisse; kein erneuter
Formularabruf und kein Formularaufbau aus JavaScript-Zeichenketten.
`builder/field.tpl` verwendet die vorhandenen Controls aus `include/input.tpl`,
ohne eine zusaetzliche Builder-Schnittstelle im Helper. Auswahllisten entstehen direkt in der TPL:
So bleiben typgetreue Werte und beliebige Beschriftungen erhalten, ohne das
Pipe-/Gruppenformat des alten Select-Helpers vorauszusetzen.
`builder/forms.tpl` liefert gemeinsame Manifest-, Baum- und Allgemeinfelder.

Optional kann direkt neben dem Manifest `<type>.settings.tpl` liegen.
Core prueft dessen realen Pfad innerhalb des Komponentenordners und rendert
es mit der Katalogvariable `component`. Ein Wrapper
`data-builder-setting="<options-Schluessel>"` enthaelt das zu bindende Feld.
Beispiel aus der lokalen Text-Einstellung:

```smarty
<div data-builder-setting="text">
    {include file="../../builder/field.tpl" field=$component.Settings.text}
</div>
```

Komponenten ohne eigene Einstellungs-TPL nutzen die generischen Vorlagen.
Jede geklonte Form erhaelt eindeutige Eingabenamen; eingebettete Helper-Skripte
werden nicht ausgefuehrt. Die alte native Formularregistrierung, versteckte
Checkbox-Hilfsfelder und Aenderungshandler werden nicht fuer das Builder-
Speichern verwendet. JavaScript uebernimmt nur Bindung und Interaktion.

### Rekursive Listen (`tree`)

Generische Komponenten koennen bei Bedarf ein Baumfeld mit `defaults.items: null`
definieren. Die Navigationskomponente verwendet dieses Feld nicht mehr:

```json
"items": {
  "type": "tree",
  "label": "Navigationspunkte",
  "nullable": true,
  "children": "Children",
  "fields": {
    "Title": { "type": "string", "label": "Titel", "default": "" },
    "Url": { "type": "string", "label": "Link", "format": "link", "default": "" },
    "Target": {
      "type": "string", "label": "Linkziel",
      "enum": ["_self", "_blank"], "default": "_self"
    }
  }
}
```

`fields` beschreibt die skalaren Felder jedes Punktes, `children` den Schluessel
der rekursiven geordneten Unterpunktliste. Jedes Feld besitzt einen `default`
fuer neue Punkte. Das Popup erlaubt Hinzufuegen, Entfernen und Hoch-/Herunter-
Sortieren je Ebene. Die serverseitige Validierung verlangt alle definierten
Felder, eine geordnete Kinderliste und keine unbekannten Knotenschluessel.
`format: link` erlaubt relative Links, Anker sowie HTTP(S), mailto und tel;
ausfuehrbare Schemata, Steuer-/Leerzeichen und Backslashes werden abgelehnt.
Leerzeichen in URLs muessen URL-kodiert sein.

Ein unveraendertes nullable Baumfeld behaelt `null`; eine ausdruecklich leere
Liste bleibt `[]`. Navigationen werden hingegen ausschliesslich in der
Menueverwaltung gepflegt und im Builder ausgewaehlt.

Jede Ausgabe erhaelt einen aeusseren `div` mit eindeutiger ID, den Klassen
`component component-<type>` und `data-component-module`. Standard-IDs werden
pro Smarty-Instanz fortlaufend vergeben. Fuer stabile CSS-Selektoren kann der
Aufruf `id="footer-links"` setzen. Explizite IDs muessen mit einem Buchstaben
beginnen, duerfen nur Buchstaben, Ziffern, `_` und `-` enthalten und innerhalb
derselben Smarty-Instanz nicht doppelt vorkommen. Automatische IDs sind keine
dauerhaften Instanz-IDs eines spaeteren Page Builders.

## Uebersetzbare Builder-Einstellungen

String-Felder koennen im Manifest `"translatable": true` erhalten:

```json
{
  "type": "string",
  "label": "Text",
  "translatable": true
}
```

Im Builder bleiben Layout, Datenquellen und alle anderen Optionen gemeinsam.
Nur markierte Felder werden pro Komponenteninstanz und Sprache gespeichert.
Das Popup bietet fuer die aktuelle Seitensprache Aktivierung und eigene
Uebersetzung an. Eine aktivierte Sprache ohne eigenen Inhalt verwendet
aktiviertes, veroeffentlichtes EN; eine fehlende/deaktivierte Sprache nie.
Bewusst leer gespeicherte Werte bleiben leer. Der Editor behaelt auch
deaktivierte Inhalte, die saubere Vorschau blendet sie aus.
Verschachtelte Baumfelder und fachliche `data`-Teilbaeume werden dadurch
nicht automatisch uebersetzt. Datenanbieter behalten ihre eigene Sprachlogik.
Direkte `{component ...}`-Aufrufe ausserhalb des Builders bleiben unveraendert.
Der Speichervertrag steht in [Konzept_Page_Builder.md](Konzept_Page_Builder.md).
Benannte Builder-Bereiche verwenden dagegen die aktive Vererbungsfolge
einzelne Seite (Page+Id), Seitentyp (Page), allgemein. ModuleId und Sprache
trennen diese Layout-Zuordnung nicht; Komponentenuebersetzungen bleiben erhalten.
Ohne Id gibt es keine individuelle Ebene. Frontend und Admin bleiben getrennt.

## Core-Komponente Text

`fremeo/core:text` liegt unter `system/template/components/text/` und erscheint
automatisch als Text in der Komponentenbibliothek. `options.text` enthaelt
HTML, bearbeitet mit dem vorhandenen WYSIWYG-Control aus input.tpl.
`text.settings.tpl` definiert das Formular. Die HTML-Quelltextansicht bleibt
verfuegbar. Zeilenumbrueche einfacher Texte bleiben durch pre-wrap erhalten.

Das Manifest definiert den Text als uebersetzbaren String mit `input: "wysiwyg"`.
Diese Darstellungsangabe ist nur fuer String-Felder ohne enum/choices erlaubt;
auch `input: "textarea"` bleibt fuer einfache Mehrzeilenfelder verfuegbar.
Datentyp und bestehender Sprachvertrag bleiben unveraendert.
Komponentenspezifisches JavaScript ist nicht erforderlich.
Die gemeinsame WYSIWYG-Werkzeugleiste verwendet kompakte 24px-Buttons;
`css/inputs.css` im Basistheme nutzt Bootstrap-Farbvariablen fuer Hell/Dunkel.
Schreibflaeche und Quelltextansicht uebernehmen ebenfalls die Theme-Farben.

Die lokale text.php validiert HTML vor Vorschau, Speicherung, Veroeffentlichung
und Ausgabe. Erlaubt sind uebliche Textformatierung, Ueberschriften, Listen,
Links, Bilder und Tabellen mit geprueften Attributen und begrenzten
Formatierungs-CSS-Deklarationen. Skripte, Ereignisattribute, unsichere URLs,
eingebettete Frames, SVG und beliebiges CSS werden mit sichtbarem Fehler
abgelehnt, nicht stillschweigend entfernt. Erst danach gibt text.tpl HTML
unescaped aus. Der WYSIWYG-iframe erlaubt gleichen Ursprung fuer Bearbeitung,
aber keine Skriptausfuehrung. Quelltext und Formatansicht werden synchronisiert;
Abbrechen verwirft nicht uebernommene Eingaben.

`editorLanguage: "DE"` legt vorerst die Bearbeitungssprache dieser Komponente
fest, auch wenn die Vorschauseite eine andere Sprache hat. Der generische
Katalog uebergibt diesen optionalen zweistelligen Grossbuchstabencode als
EditorLanguage. Ohne diese Angabe bearbeiten bestehende Komponenten weiterhin
die aktuelle Seitensprache. Eine Sprachauswahl ist noch nicht enthalten.
Text wird bereits unter der Instanz in den DE-Sprach-Snapshots gespeichert,
nicht als sprachunabhaengige Option. Nicht aktivierte andere Sprachen zeigen
den deutschen Text nicht automatisch an. Die spaetere Sprachauswahl kann
denselben Speichervertrag verwenden.

Uebernehmen/OK aktualisieren nur den Editor; Speichern sichert den Entwurf,
Veroeffentlichen schaltet ihn oeffentlich. Eigene leere Texte bleiben leer.

## Navigation aus Frontend

`options.menu` waehlt die ID eines unter `admin__menu.list` gepflegten Menues.
Das Popup bietet dafuer ein Dropdown mit Namen und ID, keine Punktbearbeitung.
`navigation.php` im Komponentenordner laedt die Auswahl und aktuelle Daten
ueber Frontend::get_navigation. Im Layout kann `menu` leer bleiben; dann erwartet
die Navigation wie bisher den explizit uebergebenen Eintrag aus `NAVIGATION.D.<id>`:
`Active` steuert die Ausgabe; `Tree` enthaelt den von `Frontend::get_navigation`
aus `Json` aufbereiteten Baum mit `Title`, `Url`, `Target` und `Children`.
Die bestehende Pattern- und Speicherstruktur bleibt unveraendert.
Alte instanzeigene `options.items` werden nicht mehr gerendert; sie erzeugen
eine ausdrueckliche Fehlermeldung. Es gibt keine automatische Menue-Migration.

## Komponentenlokale PHP-Datenanbieter

Optional liegt neben `<type>.tpl` eine gleichnamige `<type>.php`. Core prueft
den realen Pfad innerhalb des expliziten Anbieterverzeichnisses; Dateipfade
kommen nicht aus Request oder Layout-JSON. Die Datei liefert eine Closure:

```php
return static function (array $provider, array $context): array {
    return [
        'settings' => ['selection' => [
            'choices' => [['value' => '', 'label' => 'Bitte waehlen']],
        ]],
        'data' => static function (array $options, array $data): array {
            return $data;
        },
        'validate' => static function (array $options): void {
            // Bei ungueltigen Kombinationen RuntimeException ausloesen.
        },
    ];
};
```

`provider` ist der Modul-Datensatz, `context` der vorhandene D-Kontext samt
Diensten unter C. `settings` ergaenzt ausschliesslich bereits deklarierte
Manifestfelder. `choices` definiert typgetreue Werte mit separaten Labels;
Manifest und Auswahlwerte werden wie feste enums geprueft. `data` bereitet
die Renderdaten vor und muss ein Array liefern. Alle Eintraege sind optional.
`validate` prueft zusammenhaengende Optionen nach der allgemeinen Typpruefung;
die Closure wird bei Katalogpruefung, Rendering, Speichern und Veroeffentlichen
ausgefuehrt und meldet ungueltige Werte mit RuntimeException.
Die Factory wird fuer Katalog, Speichervalidierung und Rendering geladen.
Sie darf nur lesen und keine Mail-, Schreib- oder sonstigen Aktionen ausloesen.
Fachlogik bleibt im Komponentenordner, nicht in der generischen Builder-UI.
Fuer Navigation ist kein eigenes JavaScript erforderlich; Core zeichnet nur
das generische choices-Dropdown.

## Core-Komponente Grid

`fremeo/core:container` liegt unter `system/template/components/container/`.
Der technische Typ/Pfad `container` bleibt fuer bestehende Layouts stabil;
in Bibliothek, Werkzeugleiste und Dialog heisst die Komponente Grid.
Die Bibliothek zeigt genau einen Grid-Button unter fremeo/core. Die
frueheren festen Auswahlpunkte Stapel/Eine Spalte/Zwei Spalten entfallen.
`builder: "container"` kennzeichnet den verschachtelbaren Builder-Knotentyp.
Neue Knoten speichern `kind: "container"`, Modul, Typ, Optionen und children.
Alte Layoutwerte bleiben lesbar: stack/one-column entsprechen `12`,
two-columns entspricht `6+6`. Beim naechsten Speichern bleibt der Inhalt
erhalten und die Optionen werden im neuen Format gespeichert.

`columns` beschreibt individuelle Bootstrap-Breiten mit `+`, etwa `8+4`
oder `3+6+3`. Jede Breite liegt zwischen 1 und 12, maximal zwoelf Spalten
sind in einer Rasterfolge moeglich. Summen ueber 12 brechen in weitere
Bootstrap-Zeilen um, etwa `12+12+12` oder `6+6+12`.
Eine Summe unter 12 laesst bewusst Platz frei. Die Folge wiederholt sich,
wenn weitere Spalten eine neue Zeile bilden. Eine Spalte kann mehrere
Elemente untereinander enthalten; verschachtelte Grids bleiben moeglich.
Ein Wechsel der Breiten entfernt keine Kinder.

XS (<576px), SM (ab 576px), MD (ab 768px) und LG (ab 992px, einschliesslich
XL/XXL) koennen eigene Folgen erhalten. Alle expliziten Folgen muessen dieselbe
Spaltenanzahl wie columns haben.
Leere Werte erben die zuletzt ausdruecklich gesetzte kleinere Groesse.
Ohne Bildschirm-Overrides stehen Kinder auf XS/SM untereinander (`col-12`);
ab MD gilt columns, etwa `col-md-8` und `col-md-4`. Wurde bereits XS oder SM
ausdruecklich gesetzt, erbt auch ein leeres MD diese Aufteilung. Um ab MD
wieder die allgemeine Aufteilung zu verwenden, wird diese dort eingetragen.
LG erbt ohne eigenen Wert. Es gibt keine erzeugten row-cols-N-Klassen:
der Container verwendet Bootstrap row/g-3 und individuelle col-*-N-Klassen.

Das lokale Einstellungsfenster bietet ein Dropdown fuer 1-12 Spalten.
Eine neue Auswahl verteilt die 12 Rastereinheiten moeglichst gleichmaessig
und traegt die Folge in alle vier Textfelder ein; bei drei Spalten `4+4+4`.
Die Vorschauen, Beschriftungen, Textfelder und Active-Haken stehen von
LG nach XS. Eingaben aktualisieren die Vorschau sofort.
Die Vorschaubreiten sind proportional zu 992/768/576/375 skaliert;
XS erscheint als schmales Smartphone im Hochformat. Eine gemeinsame
Vorschauzeile haelt Beschriftungen und Eingabefelder ausgerichtet.
LG/MD/SM zeigen in der Beschriftung `>` statt `ab`; die inklusiven
Bootstrap-Breakpoints aendern sich dadurch nicht.
Beschriftung, Vorschaubreite, Seitenverhaeltnis und Active-Schluessel stehen
je Bildschirmgroesse gemeinsam in der lokalen Einstellungs-TPL.
Der Editor haelt die Spaltenfolge nur in den Optionen; ein zusaetzliches
Hidden-Feld oder ein zweiter Spaltenzustand ist nicht erforderlich.
`activeXs`, `activeSm`, `activeMd`, `activeLg` (Standard true) steuern die
Sichtbarkeit des gesamten Grids einschliesslich aller Inhalte je Bereich.
Im Editor bleiben auch deaktivierte Grids bearbeitbar; saubere Vorschau
und oeffentliche Ausgabe beachten die Haken.

Apply/OK erzeugen mindestens die gewaehlte Zahl leerer Spalten mit eigenen
Drop-Zielen. Oberhalb, zwischen und unterhalb der Elemente jeder Spalte
erscheinen Ablageflaechen; eine leere Spalte hat eine Flaeche.
Alle Flaechen sind textlos, leicht gelb-transparent und kompakt beabstandet.
Ein aria-label erhaelt ihre Beschriftung fuer assistive Technik.
`slot` am Kind speichert dessen nullbasierte Spalte auch dann, wenn vorherige
Spalten leer sind. Mehrere Kinder mit demselben slot stehen in Listenreihenfolge
untereinander; Speichern, Veroeffentlichen und Undo/Redo erhalten diese Ordnung.
Die Sichtbarkeit einer Spalte beruecksichtigt alle Kinder, nicht nur das erste.
Alte Kinder ohne slot werden in ihrer bisherigen Reihenfolge zugeordnet.
Bei weniger Spalten werden belegte Zusatzplaetze nicht geloescht; ihre
Breitenfolge wiederholt sich. Leere Zusatzplaetze werden entfernt.
Uebernehmen schreibt wie bisher nur in den Editor, nicht in die Datenbank.

Die lokale container.php prueft Optionen und bereitet Rasterklassen auf.
container.tpl rendert die Zeile und ihre Kinder ueber den gemeinsamen
Builder-Knotenrenderer. container.js aktualisiert im Editor die Klassen
vorhandener Spalten, ohne deren DOM-Inhalte zu ersetzen. Keine Rasterlogik
liegt im allgemeinen Editor-JavaScript.

## Komponentenlokaler Editorcode

Eine optionale gleichnamige `<type>.js` wird wie PHP/TPL auf einen realen Pfad
innerhalb des Komponentenordners geprueft. Der Katalog bindet sie vor dem
Editor ueber die bestehende Admin-Route ACTION=editor ein. Diese liefert nur
registrierten Code aktiver lokaler Anbieter, verlangt eine angemeldete
Admin-Sitzung ueber die vorhandene Middleware und schreibt keine Daten.
Keine Request-Dateipfade, Inline-Auswertung von JSON oder gespeicherter
JavaScript-Code. Der Code laeuft im Backend, nicht in der Besucherausgabe.

Die lokale Datei registriert fuer ihren expliziten Schluessel
`window.BuilderComponentEditors["<module>:<type>"]` synchrone Methoden:
`create(document, options, id)` baut die leere Containerausgabe,
`validate(options)` prueft Optionen fuer die unmittelbare Fehlermeldung,
`layout(node, options)` aktualisiert Klassen der vorhandenen direkten
Spalten und ergaenzt benoetigte leere Ablageplaetze.
Optional bindet `settings(section, options, bindControl)` die bereits aus
`<type>.settings.tpl` geladene Oberflaeche. `bindControl(element, field, value)`
verknuepft ein vorhandenes Feld mit Metadaten und Startwert und liefert
`{element, read, disable}`. Der Hook liefert `{read}` fuer die durch das
Manifest definierten Optionen beim Uebernehmen. Grid nutzt ihn fuer Auswahl,
Live-Vorschauen und gekoppelte Breitenfelder; Formularelemente bleiben in TPL.
Der Server bleibt fuer die verbindliche Validierung zustaendig.
Fehlender benoetigter Editorcode erzeugt einen sichtbaren Fehler.

Varianten: `horizontal` (Standard, Bootstrap-Navigationsliste mit Dropdowns)
und `vertical` (verschachtelte Linkliste). Beide Varianten koennen an beliebiger
Position eingesetzt werden; sie sind nicht an Header oder Footer gebunden.
`language` ist standardmaessig `DE`.
Bei mehrsprachigen Titeln wird die ausgewaehlte Sprache, sonst der erste
vorhandene Titel verwendet. Der Aufrufer stellt den semantischen
`nav`-Bereich und dessen Beschriftung bereit.

Im Frontend-Hauptlayout werden Haupt- und Footer-Navigation ueber Komponenten
ausgegeben. Die bisherige Smarty-Funktion `frontend_menu` entfaellt;
Child-Templates muessen auf `{component ...}` umgestellt werden.
`navigation/navigation.tpl` enthaelt die gesamte Ausgabe inklusive einer
rekursiven Smarty-Funktion fuer Menuepunkte. Interne Funktionen erhalten einen
anbieterspezifischen Namen, damit unterschiedliche Komponenten nicht kollidieren.
Focusbox ab `0.1.2` verwendet ebenfalls die Navigationskomponente aus Frontend
ab `0.0.2`, mit eigenem Navigationsrahmen und Theme-CSS.
