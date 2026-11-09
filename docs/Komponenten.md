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
Datenbankformat. PHP laedt und bereitet ihn vor. Die Komponente stellt ihn dar
und fuehrt keine Datenbankabfrage aus. `options` enthaelt getrennte
Darstellungsoptionen. Beide Werte sind Arrays.

Das Manifest enthaelt den passenden `type`, optional `name`, `defaults`
(Standardoptionen) und `variants` (erlaubte Werte fuer `options.variant`).
Das Komponenten-Template erhaelt lokale Variablen `data`, `options` und
`component` mit `id`, `type` und `module`. Diese ueberschreiben keine
gleichnamigen Variablen im aufrufenden Template. Komponenten muessen Texte und
Attribute passend escapen.

Jede Ausgabe erhaelt einen aeusseren `div` mit eindeutiger ID, den Klassen
`component component-<type>` und `data-component-module`. Standard-IDs werden
pro Smarty-Instanz fortlaufend vergeben. Fuer stabile CSS-Selektoren kann der
Aufruf `id="footer-links"` setzen. Explizite IDs muessen mit einem Buchstaben
beginnen, duerfen nur Buchstaben, Ziffern, `_` und `-` enthalten und innerhalb
derselben Smarty-Instanz nicht doppelt vorkommen. Automatische IDs sind keine
dauerhaften Instanz-IDs eines spaeteren Page Builders.

## Navigation aus Frontend

Die Navigation erwartet den bestehenden Eintrag aus `NAVIGATION.D.<id>`:
`Active` steuert die Ausgabe; `Tree` enthaelt den von `Frontend::get_navigation`
aus `Json` aufbereiteten Baum mit `Title`, `Url`, `Target` und `Children`.
Die bestehende Pattern- und Speicherstruktur bleibt unveraendert.

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
