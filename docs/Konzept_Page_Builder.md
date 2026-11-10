# Konzept: Page Builder im Core

## Status und Pflege

Dieses Dokument beschreibt den abgestimmten Entwurf und den Etappenstand.
Etappen 1 bis 6 (Grundlage, Template-Anbindung, Admin-Vorschau, Drag-and-drop,
Undo/Redo, Manifest-Einstellungen, Entwurfsspeicherung und gemeinsames Layout
mit instanzbezogenen Sprachwerten sowie Veroeffentlichungsoberflaeche) sind implementiert. Die Beschreibung der
Etappen 1 bis 4 dokumentiert den damaligen Stand; der aktuelle Speichervertrag
steht unter Etappe 5 am Ende dieses Dokuments.
Eine allgemeine Datenquellenauswahl fehlt noch. Die vorhandene
Komponenten-Grundlage ist in [Komponenten.md](Komponenten.md) beschrieben.
Nach jeder Etappe auf Pruefung und Freigabe des Entwicklers warten.
Galerie-Vorschauen, Formularbau und E-Mail-Versand dienen nur als Beispiele,
nicht als beauftragte Features.
Bei jeder Aenderung oder Erweiterung am Builder muss dieses Dokument im selben
Arbeitsschritt aktualisiert oder korrigiert werden. Vorschlaege sind keine
bereits verfuegbaren APIs; offene Punkte vor ihrer Umsetzung klaeren.

## 1. Zuständigkeiten

- Core stellt Komponentenregister, Manifestpruefung, gemeinsamen Renderer,
  Builder-Editor, Speicherung und Veroeffentlichung bereit.
- Bei Bedarf entsteht `fremeo\core\Builder`. Keine Hilfsmethoden fuer nur
  einmal verwendete interne Ablaeufe oder triviale Ausdruecke auslagern.
  Sinnvolle oeffentliche Operationen und tatsaechlich gemeinsam genutzte Logik
  bleiben davon unberuehrt.
- Frontend-/Child-Templates definieren erlaubte Bereiche und gestalten
  Container und Komponenten ueber Theme/CSS.
- Funktionsmodule liefern eigene Komponenten und fachliche Datenanbieter.
  Galerie-Abfragen und Formularaktionen bleiben beim zustaendigen Anbieter.
- Smarty-Plugin und Builder verwenden denselben Komponentenrenderer; keine
  zweite Rendering-Implementierung fuer den Editor.

## 2. Komponenten und PHP-Unterstützung

Komponenten werden durch Modul-ID und Typ eindeutig identifiziert:

```text
<modul>/system/template/components/<type>/
  component.json
  <type>.tpl
  <type>.php       (optional, lesender Datenanbieter)
```

Komponenten werden weder automatisch vererbt noch durch gleichnamige anderer
Module ueberschrieben. Nur registrierte Komponenten aktiver Anbieter sind
verwendbar. Interne Smarty-Funktionen brauchen anbieterspezifische Namen.
Jede Instanz besitzt eine stabile Builder-ID; die Ausgabe erhaelt eine
eindeutige HTML-ID, `component component-<type>` und die Modulzuordnung.

Eine gleichnamige PHP-Datei darf zur Komponente gehoeren, etwa fuer
Datenaufbereitung. Die nun implementierte rein lesende Closure-Schnittstelle
liefert dynamische Auswahlfelder und Renderdaten; sie ist in
[Komponenten.md](Komponenten.md) definiert. Formularversand und andere
Aktionsweiterleitungen sind weiterhin nicht implementiert.

Darstellung und schreibende Aktionen muessen getrennt sein: Seitenaufruf,
Smarty-Rendering und Editor-Vorschau duerfen keinen E-Mail-Versand oder andere
Schreibaktionen ausloesen. Aktionen benoetigen explizite Requests, serverseitige
Validierung, Berechtigungspruefung und CSRF-Schutz; Formulare gegebenenfalls
Spam-Schutz. Fehlgeschlagene Aktionen werden sichtbar gemeldet, nicht als
erfolgreiche Ausgabe kaschiert. PHP wird nur aus registrierten lokalen
Komponenten geladen, niemals aus gespeicherten Benutzereingaben.

Das Manifest `component.json` beschreibt neben `type`, `name`, `defaults`
und `variants` nun optionale `settings`: Datentyp, Beschriftung, erlaubte Werte
und numerische Grenzen. Daraus entstehen Editorfelder und serverseitige
Validierung. Details und Beispiel stehen in [Komponenten.md](Komponenten.md).
Ein umfassender PHP-/Datenquellenvertrag ist weiterhin nicht implementiert.

## 3. Daten und Patterns

Komponenten erhalten mit `data` einen vorhandenen Pattern-konformen Teilbaum
aus `$D`; Darstellungsoptionen werden getrennt als `options` uebergeben.
Keine neue, konkurrierende Datenstruktur fuer bestehende Moduldaten einfuehren.
PHP bereitet die Daten vor; Smarty stellt sie dar.

Der Builder speichert bei dynamischen Komponenten Datenquellen-Referenzen und
Abfrageoptionen, keine Kopien der fachlichen Datensaetze. Registrierte
Datenanbieter loesen diese in die benoetigten Teilbaeume auf. Beispielsweise
liefert Galerie eine Album-Vorschau mit Sortierung und Limit. Was "beliebt"
bedeutet und welche Abfragen erlaubt sind, bestimmt das Galerie-Modul.

## 4. Seiten, Regionen und Container

### Bedienung der Bereichsebenen

Jeder Template-Bereich erscheint links als aufklappbare Gruppe mit Herkunft:
Individuell, Geerbt: Seitentyp, Geerbt: Allgemein oder Kein aktiver Inhalt.
Aufgeklappt sind alle verfuegbaren Ebenen erreichbar. Ohne Id fehlt die
individuelle Zeile vollstaendig. Der Stift oeffnet vorhandene Ebenen oder
legt fehlende Ebenen an; ein eigener Anlegen-Button entfaellt.
Vorhandene Ebenen haben Aktiv-Haken und Papierkorb.
Deaktivierung erhaelt den Inhalt und laesst die naechste aktive Ebene greifen.
Ein aktiver, ausdruecklich leerer Stand blockiert dagegen die Vererbung.
Es gibt keine Zusammenfuehrung und keine zusaetzliche Zuordnungstabelle:
Die feste Ebenenfolge bestimmt den verwendeten Stand.

Anlegen uebernimmt einen vorhandenen aktiven Inhalt als unabhaengigen Entwurf
mit neuen Komponenten-/HTML-IDs. Ein belegter gemeinsamer Stand wird niemals
durch Umwandeln einer individuellen Ebene ueberschrieben; er wird separat
bearbeitet. Geerbte Inhalte sind gegen versehentliche direkte Bearbeitung
gesperrt, bis ihre Ebene ausdruecklich geoeffnet wird.

Der Papierkorb entfernt nach Bestaetigung die ganze Ebene einschliesslich
aller Komponenten und Sprachstaende. Zunaechst wird nur eine Loeschung im
Entwurf vorgemerkt; Undo/Redo bleibt moeglich und die Vorschau erbt weiter.
Speichern erhaelt den oeffentlichen Stand unveraendert. Erst Veroeffentlichen
loescht den gespeicherten Bereich samt Sprachdaten endgueltig. Bei gemeinsamen
Ebenen warnt die Bestaetigung vor Auswirkungen auf alle erbenden Seiten.

Bearbeiten zeigt voruebergehend die ausgewaehlte Ebene samt Kennzeichnung
in der Vorschau, ohne deren Aktivierung zu aendern. Gemeinsame Ebenen tragen
den Hinweis, dass Aenderungen andere erbende Seiten betreffen.
Zur Seitenansicht und die saubere Vorschau verwenden wieder die aktive
Vererbungsfolge. Deaktivierungswechsel verlassen die temporaere Ebenenansicht.
Undo/Redo umfasst Inhalte, Existenz und Aktivierung der Ebenen.
Speichern sichert nur vorhandene/angelegte Ebenen als Entwurf, samt allen
Sprachstaenden; erst Veroeffentlichen aendert die oeffentliche Vererbung.

Eine Zielseite verwendet den aufgeloesten Seitentyp `R[Page]` und optional
die Inhalts-ID `R[Id]`, nicht die SEO-URL. ModuleId ist Aufruf-/Berechtigungskontext,
kein Inhaltsfilter. Pro benanntem Bereich gilt die erste vorhandene aktive
Ebene: einzelne Seite, Seitentyp, allgemein. Frontend und Admin bleiben getrennt.

```text
Seite
  Region (vom Template freigegeben)
    Container (Stapel, Zeile oder Spalten)
      Komponente
      weiterer Container, soweit erlaubt
```

Der Editor darf nur freigegebene Regionen bearbeiten, nicht beliebige
DOM-Elemente. Container liefern gueltige HTML-Strukturen und responsive
Layoutregeln. Erlaubte Verschachtelung, Komponententypen und Layoutoptionen
werden auch serverseitig geprueft. Keine freie absolute Positionierung als
Grundlage. Leere Einfuegeplaetze zeigt der Editor automatisch an; sie sind
keine gespeicherten Inhaltskomponenten.

Festgelegt sind Regionen vor und nach dem bestehenden Seiteninhalt
(`before_content` und `after_content`). Der bestehende Inhalt bleibt erhalten.
Container werden als Stapel sowie ein- und zweispaltig vorgesehen.

Die Einfuegestellen werden ausschliesslich durch `{builder name="..."}` im
Template gesetzt. Keine zusaetzliche Regionsdatei, Beschriftung oder manuell
geschriebenen Drop-Divs im Seitentemplate verlangen. Der stabile Name
unterscheidet mehrere Stellen derselben Seite.

Im autorisierten Editormodus erzeugt der Renderer selbst die Drop-Divs mit
`data-builder-*`-Attributen. Das Editor-JavaScript erkennt diese automatisch und
markiert ihre Flaechen gruen; auch leere Bereiche erhalten eine anklickbare bzw.
belegbare Mindesthoehe. Im Besuchermodus entfallen Markierungen und Drop-Ziele.
Die vom Server beim Rendern ermittelten Stellen sind massgeblich; vom Client
uebermittelte DOM-Attribute allein autorisieren keinen Speicherort.

Eindeutige HTML-IDs werden deterministisch aus Branch, Ebene, deren Page-/Id-
Kriterien, Regionsname und bei Unterelementen stabilen Container-/
Komponenten-Instanz-IDs gebildet.
Werte kollisionsfrei kodieren oder einen Hash einer strukturierten Identitaet
verwenden, nicht nur Sonderzeichen durch Bindestriche ersetzen. Sprache gehoert
zur Auswahl des gespeicherten Sprachstands. Gleiche Stellen duerfen innerhalb
einer Seite nicht mit derselben Identitaet mehrfach ausgegeben werden.

## 5. Editor und Vorschau

Links erscheint die Komponentenbibliothek nach Modul gruppiert, beispielsweise
"Galerie / Galerie-Vorschau". Rechts befindet sich die echte Seitenvorschau
mit markierten erlaubten Ablagezielen. Instanzen lassen sich hinzufuegen,
konfigurieren, verschieben und entfernen. Der Editor erzeugt weitere
Einfuegeziele, sobald Inhalte vorhanden sind.

Die Vorschau verwendet ein iframe. Der Einstieg laedt automatisch die normale
Frontend-Startseite ohne Modul-, Routen- oder Sprachauswahl. Zunaechst ist die
Seite normal bedienbar; Links bestimmen die spaeter zu bearbeitende Seite.
Eine Werkzeugleiste oberhalb enthaelt Edit, Abbrechen und Speichern. Erst Edit
aktiviert die autorisierte Entwurfsvorschau fuer die aktuelle Seite und deren
Sprache. Abbrechen verwirft seit dem letzten Speichern nur die lokalen Aenderungen
und verlaesst den Editor. Speichern sichert den aktuellen Stand als Entwurf;
die oeffentliche Seite aendert sich nicht.
Editorrahmen und Drag-and-drop-Markierungen gehoeren nicht in die oeffentliche
Ausgabe. Entwurfsvorschau muss autorisiert sein und darf keine oeffentlichen
Inhalte oder Aktionen unbeabsichtigt veraendern.

## 6. Speicherung in der Core-Datenbank

Builder-Dokumente liegen ueber eigene CData-Patterns in der Core-Datenbank.
Galerie-, Page- und andere fachliche Daten bleiben in ihren Modul-Datenbanken.
Das geladene Dokument erscheint entsprechend dem Pattern im `$D`-Baum.

Sprachstaende werden je Dokument unter `LANGUAGE.D.<Sprachcode>` getrennt
gespeichert (bestehende Schreibweise `LANGUAGE`, nicht `LANGAUGE`).
Vorgeschlagene Felder, noch kein implementiertes Pattern:

```text
BUILDER.D.<Dokument-ID>
  ModuleId
  Route
  EntityId
  LANGUAGE.D.<DE|EN|...>
    DraftJson
    PublishedJson
    Revision
```

Die gespeicherte Beschreibung enthaelt Schema-Version, Regionen, geordnete
Container, stabile Instanz-IDs, Modul-/Komponententypen, Datenquellen und Optionen:

```json
{
  "schemaVersion": 1,
  "regions": {
    "before_content": [
      {
        "id": "container-a",
        "type": "stack",
        "children": [
          {
            "id": "gallery-preview-a",
            "module": "fremeo/gallery",
            "component": "gallery-preview",
            "source": {"type": "albums", "sort": "popular", "limit": 6},
            "options": {"columns": 3}
          }
        ]
      }
    ]
  }
}
```

Das Beispiel beschreibt die Zielrichtung, nicht schon erlaubte Galerieoptionen.
Keinen frei ausfuehrbaren Smarty-/PHP-Code oder beliebige Dateipfade speichern.
Textinhalte benoetigen ein festgelegtes sicheres Format. Festgelegt sind ein
getrennter Entwurf mit autorisierter Vorschau und explizites Veroeffentlichen.
Sprachfallback (festgelegt): Zuerst den veroeffentlichten Stand der angeforderten
Sprache verwenden, bei fehlender Uebersetzung ausschliesslich Englisch (`EN`).
Fehlt auch der englische Stand, wird die Builder-Erweiterung nicht ausgegeben.
Keine beliebige andere Sprache und keinen unveroeffentlichten Entwurf als
Fallback verwenden. Der normale Seiteninhalt bleibt davon unberuehrt.
Das konkrete Pattern und die Revisionierung sind noch offen.

### Berechtigungen (festgelegt)

Vorerst darf nur die Administration den Builder verwenden. Die vorhandene
Zuordnung Benutzer -> aktive Gruppen -> Seiten-/Praefixrechte bleibt massgeblich;
kein zweites Rollenmodell einfuehren. Editor- und Schreibendpunkte liegen unter
`admin__...`. Die bestehende Middleware in `system/index.php` prueft bereits
vor Ausfuehrung dieser Seiten die Gruppen-/Seitenrechte und lehnt unberechtigte
Requests ab. Keine zweite identische Rechtepruefung im Speicherhandler einfuehren.
CSRF-Schutz und Eingabevalidierung bleiben davon getrennte Anforderungen.

Eine Frontend-Vorschau im gleichurspruenglichen iframe verwendet weiterhin
dieselbe Sitzung; das Aufrufen einer Frontend-Seite entfernt keine Adminrechte.
Die Frontend-Praefixfreigabe allein erlaubt aber keinen Zugriff auf Entwuerfe.
Der Vorschaupfad muss zusaetzlich die Builder-Adminberechtigung pruefen.
Kein Queryparameter oder iframe-Status ersetzt diese serverseitige Pruefung.

### Schnittstellen (Vorschläge, noch nicht beschlossen)

Ein Manifest-Schema koennte jede Option mit `type`, `default`, `label` und
gegebenenfalls `enum` beschreiben. Der Editor erzeugt daraus Felder; der Server
prueft dieselben Regeln und weist unbekannte Optionen zurueck.

Die optionale gleichnamige PHP-Datei koennte ein Objekt mit getrennten
Operationen fuer Datenaufbereitung und explizite Aktionen zurueckgeben.
Datenaufbereitung erhaelt Instanzkonfiguration und Request-Kontext und liefert
einen Pattern-konformen Teilbaum. Aktionen erhalten zusaetzlich validierte
Eingaben und liefern ein ausdrueckliches Erfolgs-/Fehlerergebnis. Signaturen,
Lifecycle und genaue Fehlerstruktur vor Implementierung festlegen.

### Konfliktbehandlung (Grundregel festgelegt)

Konflikte koennen etwa bei paralleler Bearbeitung in mehreren Tabs, geloeschten
Quelldatensaetzen, deaktivierten Anbietermodulen, geaenderten Manifesten oder
beim Templatewechsel fehlenden Regionen entstehen.

Bei einem festgestellten Konflikt wird die betroffene Builder-Erweiterung
persistent in der Datenbank durch `Active = 0` deaktiviert und anschliessend
nicht mehr bei der Ausgabe beruecksichtigt. Die gespeicherten Inhalte bleiben
erhalten; keine stille Loeschung oder automatische Reaktivierung.
Die festgelegte Ebene ist eine Builder-Stelle je Sprachstand. Andere Stellen
derselben Seite und andere Sprachstaende werden nicht mit deaktiviert.

Der weitere Umgang mit Konflikten wird spaeter definiert, insbesondere
Erkennung, Meldungen, Wiederherstellung, Reaktivierung und paralleles Speichern.
Die bisher diskutierte Revisionspruefung ist damit keine bereits festgelegte
Konfliktloesung.

## 7. Smarty und Kompilierung (festgelegt)

Es werden feste Smarty-Templates fuer Regionen, Container und Komponenten
verwendet. Smarty kompiliert diese zu PHP und verwendet die kompilierten Dateien
weiter, solange keine Neukompilierung erforderlich ist.

Das gespeicherte Layout ist Dateninput, kein generiertes TPL-Dokument.
Komponenten verschieben oder Optionen aendern erzeugt deshalb keine neue
Smarty-Datei. Bestehende Modul-Templates bleiben bei ihrem Anbieter.

Implementierter Layout-Aufruf:

```smarty
{builder name="before_content"}
{block name="inner_body"}{/block}
{builder name="after_content"}
```

PHP laedt das veroeffentlichte Dokument und seine Datenquellen. Ein gemeinsamer
Renderer gibt den Baum ueber die festen Templates und den Komponentenrenderer
aus. Der manuelle `{component ...}`-Aufruf bleibt moeglich.

Kompilierung ist kein Ausgabe-Cache: Layoutverarbeitung und dynamische Abfragen
finden weiter statt. HTML-/Abfrage-Caching ist eine getrennte spaetere Aufgabe.
Dabei muessen Revision, Sprache, Theme, Datenaenderungen und Zugriffsrechte
beruecksichtigt werden. Geschuetzte Inhalte, Formulare und Entwurfsvorschauen
duerfen nicht ungeprueft oeffentlich gecacht werden.

## 8. Vor weiteren Etappen klären

1. Umfang eines spaeteren Implementierungsauftrags separat freigeben lassen;
   Beispielkomponenten sind nicht automatisch Teil dieses Auftrags.
2. Konkrete Abbildung der Seitenidentitaet und Active-Deaktivierung im Pattern.
3. Regionen/Container: maximale Verschachtelung und Verhalten beim Wechsel des
   aktiven Templates, insbesondere bei fehlenden Regionen.
4. PHP-Vertrag: Datenaufbereitung und getrennte, instanzbezogene Aktionen;
   Rueckgabewerte, Fehlerausgabe und erlaubte Aktionsziele.
5. Speicherung/Publikation: genaue Admin-Seitennamen und Revisionshistorie.
   Weitergehende Konfliktbehandlung wird separat spaeter definiert.
6. Manifest-Schema, Textformat und validierte Datenquellen gemeinsam festlegen.

## 9. Etappenstand und Pruefung

1. **Implementiert:** Speicherung, Sprachfallback, Active-Auswertung und
   Template-Anbindung. Noch keine Benutzeroberflaeche und keine Web-Schreibroute.
2. **Implementiert:** Admin-Einstieg, automatische Startseite und Seitensprache,
   normaler Navigationsmodus und autorisierte iframe-Vorschau nach Edit.
3. **Implementiert:** Komponentenbibliothek und Container per Drag-and-drop,
   vorerst ausschliesslich temporaer im iframe.
4. **Implementiert:** Manifest-/Allgemeineinstellungen und persistentes Speichern
   der aktuellen Seitenbereiche als Entwurf.
5. **Implementiert:** Gemeinsames Layout, Sprachaktivierung pro uebersetzbarer
   Komponenteninstanz und veroeffentlichungsgebundene globale Aktivierung.
6. **Implementiert:** Veroeffentlichen gespeicherter Seitenentwuerfe mit
   signierter Zielzuordnung und Pruefung des gespeicherten Entwurfsstands.
7. **Offen, nach Freigabe:** Allgemeine Datenquellenkonfiguration;
   offene Manifest-/PHP-Vertraege vorher klaeren.

### Etappe 1: tatsaechlicher Datenvertrag

Core `0.0.8` registriert `Builder` im Container und das Smarty-Plugin `builder`.
Frontend `0.0.2` und Focusbox `0.1.2` setzen vor und nach `inner_body` die
Aufrufe `{builder name="before_content"}` und `{builder name="after_content"}`.
Im Besuchermodus erzeugen leere Stellen kein HTML. Etappe 2 ergaenzt den
autorisierten Vorschaukontext, siehe unten.

`Builder::placeId(module, page, region, entity, scope, branch)` bildet einen
SHA-256-Schluessel aus Branch, Bereich, Ebene und deren Kriterien.
`individual` verwendet Page und Id, `page` nur Page, `global` keines von beiden.
ModuleId autorisiert den Aufruf, geht aber nicht in die Identitaet ein.
Ohne explizite Ebene gilt bei vorhandener entity individual, sonst page.
Das Smarty-Plugin verwendet standardmaessig die aufgeloeste `D.R.Id`;
`entity` kann im Template weiterhin ausdruecklich uebergeben werden.
Fehlende oder leere IDs erzeugen keine individuelle Ebene. Die tatsaechliche
ID `"0"` ist gueltig, aber niemals ein Ersatzwert fuer eine fehlende ID.
Gemeinsame Speicherziele enthalten ueberhaupt kein entity-Kriterium.
Die signierte contextEntity ist gegebenenfalls nur der aktuelle Seitenkontext.
Bestehende alte Speicher-Schluessel werden nicht migriert oder als Ersatz geladen.

Das implementierte Core-Pattern:

```text
BUILDER.D.<placeId>
  ModuleId, PageId, EntityId, Region, Scope, Branch
  LANGUAGE.D.<DE|EN|...>
    Active, DraftJson, PublishedJson
```

Entwurfs- und Veroeffentlichungswerte sind geordnete JSON-Knotenlisten.
`kind="container"` besitzt `id`, `layout` (`stack`, `one-column` oder
`two-columns`) und `children`. `kind="component"` besitzt `id`, `module`,
`type`, optionale `data`/`options` und optional `source`. Instanz-IDs muessen
innerhalb einer Stelle eindeutig sein.

`source` ist in dieser Etappe ausschliesslich ein Schluesselpfad zum bereits
geladenen Teilbaum des angegebenen Komponentenmoduls, beispielsweise
`["NAVIGATION", "D", "top_navigation"]`. Keine dynamischen Abfragen oder
ausfuehrbaren PHP-/Smarty-Inhalte. Fehlt der Quellbaum, wird die Stelle dieses
Sprachstands deaktiviert und der Grund ins PHP-Fehlerlog geschrieben.
Struktur-/Komponenten-Konfigurationskonflikte werden ebenfalls so behandelt.
Nicht unterstuetzte PHP-Komponenten und fachliche Datenanbieter bleiben offen.

Die PHP-Methoden sind interne Anwendungsoperationen, keine oeffentlichen
Endpunkte. Spaetere Admin-Handler muessen die bestehenden Rechte-/CSRF-Regeln
anwenden. `saveDraft` veraendert weder veroeffentlichte Inhalte noch den
Aktivstatus. `publish` uebernimmt einen validierten Entwurf und setzt fuer
diesen Sprachstand ausdruecklich `Active=1`. `deactivate` behaelt Inhalte.
Ein deaktivierter veroeffentlichter Sprachstand unterdrueckt auch den
englischen Fallback; eine fehlende Uebersetzung nutzt nur veroeffentlichtes EN.
Ein bewusst veroeffentlichtes leeres Array bleibt leer, ohne Sprachfallback.
Revisionen und die Erkennung paralleler Speichervorgaenge sind noch nicht
implementiert.

Beispiel fuer einen internen Test mit vorhandenen Navigationsdaten:

```php
$nodes = [[
    'kind' => 'container', 'id' => 'row-a', 'layout' => 'one-column',
    'children' => [[
        'kind' => 'component', 'id' => 'menu-a',
        'module' => 'fremeo/frontend', 'type' => 'navigation',
        'source' => ['NAVIGATION', 'D', 'top_navigation'],
        'options' => ['variant' => 'vertical', 'language' => 'EN'],
    ]],
]];
$builder = $C['Builder'];
$builder->saveDraft('fremeo/gallery', 'frontend__gallery.album', 'before_content', 'EN', $nodes);
$id = $builder->placeId('fremeo/gallery', 'frontend__gallery.album', 'before_content');
$builder->publish($id, 'EN');
```

Nicht als ungeschuetztes Webskript ablegen. Es werden keine Beispielinhalte in
der Live-Datenbank angelegt. Automatisierte Tests verwenden temporaere CData-
Datenbanken und echtes Smarty. Sie pruefen unter anderem Sprachfallback,
Sprach-/Stellenisolation, Konfliktdeaktivierung und die Wiederverwendung
kompilierter Dateien ohne Generierung von TPL-Code.

### Etappe 2: Admin-Vorschau

Ab Core `0.0.8` fuehrt **System -> Page Builder** zur Seite `admin__builder`.
Der Einstieg verwendet die vorhandene Admin-Middleware. Hier startet
das iframe direkt auf der normalen Startadresse der Anwendung (`SCRIPT_NAME`).
Die bisherige manuelle Zielauswahl entfaellt. Routing und aktive
Template-/Theme-Auswahl der Anwendung bestimmen die Startseite wie sonst auch.
Links und Formulare sind im normalen Navigationsmodus bedienbar.
Links mit Ziel `_blank` werden im iframe selbst geoeffnet.

Erst **Edit** oeffnet die aktuell geladene lokale Frontend-Adresse mit
`R[BuilderPreview]=1` und `R[BuilderLanguage]=DE` (bzw. aktueller Seitensprache).
SEO-Pfade, Queryparameter und Fragment bleiben erhalten. Die gemeinsame
HTML-Huelle liefert fuer Frontend-Seiten `data-builder-language` aus dem
serverseitig aufgeloesten Sprachkontext. Admin-/Fehlerseiten oder fremde
Urspruenge aktivieren Edit nicht. Zielmodul, Route und gegebenenfalls
Datensatzparameter werden weiterhin durch das normale Routing aufgeloest;
eine neue modulspezifische Datensatzaufloesung wird nicht eingefuehrt.
Die Frontend-Middleware
prueft neben dem normalen Seitenzugriff die angemeldete Benutzeridentitaet
gegen `admin__builder`. Erst danach aktiviert sie den Vorschauzustand in der
Builder-Serviceinstanz. Uebergebene D-Daten oder der Queryparameter allein
aktivieren keine Entwurfseinsicht. Antworten erhalten `Cache-Control: private,
no-store`. Die Sitzung bleibt gleich. Im Edit-Modus blockiert das
Editor-JavaScript Link-/Formularbedienung, damit die gewaehlte Seite bestehen
bleibt. **Abbrechen** laedt ihre urspruengliche normale Adresse erneut, ohne
Entwurfsspeicherung oder Veroeffentlichung. Die Sandbox erlaubt Skripte,
gleichen Ursprung und Formulare fuer den normalen Navigationsmodus; sie ist
keine Isolation gegen beliebigen vertrauenswuerdigen Anwendungscode.

In der Vorschau wird je Sprache ein vorhandener Entwurf vor dem aktiven
veroeffentlichten Stand verwendet, mit EN als einzigem Fallback. Auch
deaktivierte Entwuerfe koennen so inspiziert werden, ohne sie zu reaktivieren.
Vorschaufehler werden sichtbar und im PHP-Fehlerlog gemeldet; die Vorschau
deaktiviert keine Daten. Der Besuchermodus behaelt die bisherigen Regeln.

Jeder builder-Aufruf erzeugt in der Vorschau auch ohne Inhalt einen Div mit
stabiler ID, `builder-dropzone`, `data-builder-region`, gruener gestrichelter
Umrahmung und Mindesthoehe. Das Admin-JavaScript zaehlt die erkannten Bereiche
nach Laden des iframe. Keine zusaetzliche Regionsdatei erforderlich.
Die linke Leiste erscheint erst nach Edit und enthaelt ab Etappe 3 die
Komponentenbibliothek. Zum Seitenwechsel zuerst Abbrechen waehlen, dann
normal navigieren und auf der gewuenschten Seite erneut Edit aktivieren.
Seit Etappe 4 wird Speichern bei lokalen Aenderungen aktiviert und sichert nur
den Entwurf; unveraenderte Seiten benoetigen keinen Schreibzugriff.

Manuelle Pruefung:

1. Als berechtigter Admin **System -> Page Builder** oeffnen.
2. Die normale Startseite erscheint direkt. Ueber Links eine Unterseite
   oeffnen: noch keine gruenen Markierungen oder Entwurfsinhalte.
3. Edit waehlen: rechts erscheinen vor/nach dem Inhalt gruene Bereiche,
   Speichern ist bis zur ersten Aenderung deaktiviert.
4. Dieselbe Vorschauadresse als Gast oder Benutzer ohne Adminrecht aufrufen:
   keine Entwurfsausgabe, sondern Anmeldung oder HTTP 403.
5. Normalen Frontend-Aufruf ohne Vorschauparameter pruefen: keine gruenen
   Markierungen und keine unveroeffentlichten Daten.
6. Abbrechen waehlen: dieselbe Seite erscheint wieder normal, ohne Speicherung.
   Weitere Links sind wieder bedienbar. Edit erneut auf einer Unterseite testen.

UI-Zustandstests ohne zusaetzliche Pakete:
`node --test tests/BuilderAdminUiTest.cjs` im Core-Modul.

Stand nach Etappe 2: noch keine aktive Speicher-/Veroeffentlichungsfunktion,
kein Drag-and-drop, keine Galerie-/Formularkomponenten und kein PHP-
Komponentenvertrag. Die folgende Etappe erweitert nur die Layout-Vorschau.

### Etappe 3: Bibliothek und temporaere Layout-Bearbeitung

#### Einstellungsfenster und allgemeine Instanzeinstellungen

Das kompakte Popup hat einen festen Kopf mit Elementname, feste Tabs und einen
festen Fuss mit Abbrechen/Uebernehmen. Nur der Inhalt scrollt. Der erste Tab
heisst wie die Komponente (beim Raster: Grid) und enthaelt deren
Manifestfelder. Der zweite Tab Allgemein ist fuer alle Builder-Knoten gleich.
Die Reiter verwenden Bootstrap nav-tabs statt Button-Optik. Ziehen an der
Titelleiste verschiebt das Fenster innerhalb des sichtbaren Browserbereichs;
Pointer-Capture haelt den Drag auch ausserhalb der Leiste. Rechts schliesst
ein rotes X das Popup wie Abbrechen. Im Fuss steht zusaetzlich OK.

Die allgemeinen Werte stehen separat von Komponentenoptionen im Knoten:

```json
"general": {
  "CustomCssClass": "my-panel highlighted",
  "CustomCss": "color: red; padding: 8px;",
  "Active": true,
  "Viewports": ["XS", "SM", "MD", "LG"]
}
```

Fehlende Werte verwenden leere CSS-Werte, Active=true und alle Bildschirmgroessen.
CustomCssClass nimmt leerzeichengetrennte einfache Klassennamen entgegen.
CustomCss nimmt ausschliesslich Deklarationen ohne Selektoren, Regelbloecke,
HTML, At-Regeln oder ausfuehrbare CSS-Ausdruecke entgegen. Attribute werden beim
Rendern escaped; CSS gilt fuer den Instanz-Wrapper, nicht fuer die ganze Seite.
Active=false unterdrueckt die oeffentliche Ausgabe samt Inhalt; in der sauberen
Vorschau wird die Instanz ausgeblendet. Im Editor bleibt sie erreichbar.

Die Checkbox Alle schaltet alle vier Bildschirmgroessen; Einzelwahl aktualisiert
Alle. XS: unter 576px, SM: 576 bis 767px, MD: 768 bis 991px, LG: ab 992px
(einschliesslich groesserer Bootstrap-Groessen). Eine leere Auswahl blendet das
Element in allen Groessen aus. Responsive Bootstrap-Anzeigeklassen gelten auch
fuer die zugehoerige Gridzelle. Die konfigurierte Zahl leerer Ablageplaetze
bleibt inzwischen erhalten; siehe Grid-Komponente am Ende dieses Dokuments.

Die Popup-Eingaben entstehen serverseitig in `builder/forms.tpl` und
`builder/field.tpl`, einschliesslich rekursiver Listen und Allgemeinfelder.
Sie werden als inaktive HTML-template-Vorlagen mit der Adminseite geladen.
Optional ersetzt eine lokale `<type>.settings.tpl` die generische Darstellung.
Die TPL verwendet vorhandene Controls aus `include/input.tpl`, ohne eine
zusaetzliche Builder-API. Dessen WYSIWYG-Werkzeugleiste ist kompakt und
themeabhaengig gestaltet. JavaScript klont die Vorlagen, vergibt eindeutige Namen
und bindet Werte sowie Interaktionen, statt Formular-HTML zu erzeugen.
Alte native Submit-/Aenderungshandler werden dabei nicht ausgefuehrt.
Die kleinen Bootstrap-Feldklassen bleiben; Popup-Aenderungen bleiben bis zum
oberen Speichern lokal. Den Vertrag beschreibt [Komponenten.md](Komponenten.md).

Core Text verwendet eine lokale Einstellungs-TPL mit dem vorhandenen WYSIWYG
einschliesslich HTML-Quelltextansicht. Die Bearbeitungssprache ist vorerst DE.
Die lokale PHP-Validierung erlaubt Textformatierung mit sicheren Attributen,
URLs und eingeschraenktem CSS; ausfuehrbarer oder nicht erlaubter HTML-Code
erzeugt vor der Ausgabe einen sichtbaren Fehler. Die Bearbeitungsflaeche
selbst erlaubt keine Skriptausfuehrung. Sprach-Snapshots und getrenntes
Entwurfsspeichern/Veroeffentlichen bleiben unveraendert.

Uebernehmen nimmt beide Tabs gemeinsam in die Undo-/Redo-Historie auf.
Abbrechen verwirft beide Tabs. Speicherung und Veroeffentlichung werden dadurch
nicht aktiviert. Builder::appearance validiert allgemeine Werte beim Laden,
beim Entwurfsspeichern und fuer Komponenten-Neurenderanfragen.

Core `0.0.8` zeigt kleine Buttons rechts neben der Ueberschrift Page Builder.
Der Navigationstext im normalen Ansichtsmodus entfaellt. Statusmeldungen sind
nur bei Fehlern, einer Auswahl oder Aenderungen sichtbar.

Neben Next schaltet **Vorschau** auf die Darstellung des aktuellen, auch noch
ungespeicherten Seitenstands um: Bibliothek, gruene Rahmen, Drop-Ziele und
Elementwerkzeuge werden ausgeblendet, das iframe nutzt die volle Breite.
**Bearbeiten** schaltet ohne Neuladen zurueck; Knoten, Einstellungen und
Undo-/Redo-Historie bleiben erhalten. In dieser Ansicht sind Layoutaktionen,
Prev/Next sowie Links und Formularaktionen gesperrt, damit kein Seitenwechsel
die temporaeren Aenderungen verwirft. Zum normalen Navigieren weiterhin
Abbrechen verwenden. Der Schalter speichert oder veroeffentlicht nichts und
ist waehrend ausstehender Renderanfragen oder geoeffneter Einstellungen gesperrt.

Nach Edit erscheinen links:

- Stapel, einspaltige und zweispaltige Container.
- Komponenten aktiver Core-, Template- und Funktionsmodule, nach Anbieter-ID
  gruppiert. Grundlage sind vorhandene `component.json` und gleichnamige TPL,
  keine neue Bibliotheksdefinition und keine Komponentenvererbung.
- Fehlerhafte Manifeste werden sichtbar gemeldet und protokolliert; andere
  gueltige Komponenten bleiben verfuegbar. Deaktivierte Anbieter und Libraries
  erscheinen nicht.

Elemente koennen aus der Bibliothek auf automatisch erzeugte gruene
Einfuegeziele gezogen werden. Die Flaechen sind ausschliesslich Drop-Ziele,
kein Klick fuegt Elemente ein. Nach jedem Drop oder Drag-Ende wird die
Auswahl zurueckgesetzt. Einfuegeziele liegen vor/nach vorhandenen Knoten und innerhalb
von Containern; nach jeder Aenderung werden sie neu aufgebaut. Dadurch bleiben
auch leere Container und Stellen belegbar. Es gibt keine freie Platzierung in
beliebigem Seiten-HTML. Zweispaltige Container zeigen Inhaltszellen
nebeneinander, auf schmalen Vorschauen untereinander; Einfuegebuttons erzeugen
keine zusaetzlichen Inhaltsspalten.

Vorhandene Builder-Knoten erhalten nur im autorisierten Vorschaumodus
`data-builder-node`, Typ-/Anbieterzuordnung und Container-Kindziele.
Das Editor-JavaScript ergaenzt eine kompakte blaue Hover-/Fokusleiste oberhalb
jedes Elements mit Name, Verschieben-Icon, Einstellungen-Icon und Muelleimer.
Ganze
Container mitsamt Kindern oder einzelne Komponenten lassen sich innerhalb
einer Stelle oder zwischen erkannten Stellen verschieben. Ein Container
darf nicht in sich oder seine Nachkommen verschoben werden. Neue Instanzen
erhalten eindeutige IDs. Entfernen eines Containers entfernt auch dessen
Kinder aus der temporaeren Vorschau.

Neue Komponenten werden ueber **POST auf `admin__builder`, ACTION=render**
mit Modul, Typ und Instanz-ID gerendert. Die vorhandene Admin-Middleware bleibt
massgeblich; der Handler verlangt zusaetzlich eine angemeldete Sitzung und
einen sitzungsgebundenen CSRF-Token. Nur Komponenten des aktuellen aktiven
Katalogs sind erlaubt. Der Handler gibt JSON mit `html` oder einen sichtbaren
Fehler mit HTTP-Fehlerstatus zurueck und setzt `Cache-Control: private, no-store`.
Er nutzt den vorhandenen Smarty-Komponentenrenderer mit festen kompilierten
Templates, validierten Optionen und einem `data`-Teilbaum. Neue dynamische
Komponenten koennen deshalb zunaechst inhaltlich leer sein; ihre eingerahmte
Instanz bleibt sichtbar. Quellenkonfiguration folgt in einer spaeteren Etappe.
Die spaeter ergaenzte gleichnamige Komponenten-PHP darf ausschliesslich
Einstellungen und Renderdaten lesend vorbereiten, keine Aktionen ausfuehren.

Einfuegen, Verschieben und Entfernen veraendern zunaechst nur den aktuellen
iframe-DOM. Erst der obere Speichern-Button aus Etappe 4 schreibt DraftJson,
niemals PublishedJson. Abbrechen oder Neuladen verwirft ungespeicherte
Aenderungen, nicht den zuletzt gespeicherten Entwurf. Spaet eintreffende Komponenten-Antworten nach
Abbrechen oder Seitenwechsel werden nicht mehr eingefuegt. Die gruene
Vorschau bleibt von der unveraenderten oeffentlichen Darstellung getrennt.

Manuelle Pruefung:

1. Page Builder oeffnen: Startseite, kleine Toolbar rechts, kein Hinweistext.
2. Unterseite oeffnen und Edit aktivieren. Anbietergruppen und Container
   erscheinen links, leere Regionen erhalten gruene Einfuegeziele.
3. Einen zweispaltigen Container einfuegen, zwei Komponenten hineinlegen.
   Inhaltszellen erscheinen auf breiter Vorschau nebeneinander.
4. Einzelne Instanz in die zweite Region verschieben; Container und Instanz
   entfernen. Ziele bleiben auch nach Entfernen des letzten Kindes vorhanden.
5. Container nicht in sich selbst verschieben koennen.
6. Ohne Speichern abbrechen: keine Aenderungen auf der normalen Seite oder am Entwurf.

Automatisierte Pruefung: `BuilderAdminTest` testet Katalog, CSRF-/POST-/Sitzungs-
und Eingabepruefung sowie echtes Smarty-Rendering. `BuilderTest` prueft
zusaetzlich die nur in Vorschauen ausgegebenen Knoten-/Containerattribute.
`node --test tests/BuilderAdminUiTest.cjs` prueft Moduswechsel, Einfuegen,
Verschieben/Entfernen, eindeutige IDs, Zyklen, Renderfehler und spaete Antworten.
Eine isolierte Browser-Fixture mit kompiliertem Editor-Template prueft
Einfuege-/Drop-Ereignisse, zwei nebeneinanderliegende Inhaltszellen, Verschieben,
Entfernen, Zyklusschutz und Abbrechen. Sie ersetzt keinen angemeldeten
Admin-Test mit den tatsaechlich installierten Komponenten.

### Erweiterung: Verlauf und Element-Einstellungen

Alle bisherigen Builder-Etappen gehoeren zum noch unveroeffentlichten Core
`0.0.8` nach Tag `v0.0.7`, nicht zu separaten Versionsspruengen. Frontend bleibt
bis zu seinem naechsten Tag bei `0.0.2`, Focusbox bei `0.1.2`.

**Prev / Next** in der oberen Toolbar machen je eine erfolgreiche Aenderung
rueckgaengig bzw. wiederholen sie: Einfuegen, Verschieben, Entfernen und
Einstellungsuebernahme. Der Verlauf umfasst alle Regionen der aktuellen
Bearbeitungssitzung, einschliesslich Containerkindern und gerenderten
Komponenten. Nach Rueckgaengig verwirft eine neue Aenderung den Wiederholen-Zweig.
Waehrend ausstehender Renderanfragen oder geoeffnetem Einstellungsdialog sind
die Verlaufsbuttons deaktiviert. Abbrechen/Seitenwechsel setzt den Verlauf
zurueck. Es werden keine Datenbank-Revisionsstaende angelegt.

**Einstellungen** oeffnet ein modales Popup im Adminrahmen. Die Felder kommen
aus `component.json.settings`, mit Werten aus `defaults` plus aktuellen
Instanzoptionen. Container bieten die vorhandene Layoutauswahl an.
Ohne definierte Einstellungsfelder erscheint ein ausdruecklicher Hinweis;
es werden keine willkuerlichen Optionen oder Datenquellenfelder erfunden.
Diese temporaeren Formulare sind keine Pattern-Schreibformulare.

**Uebernehmen** rendert Komponenten mit den geaenderten, serverseitig validierten
Optionen erneut und ersetzt nur die betroffene Instanz. Das Fenster bleibt
offen; die Aenderung erscheint unmittelbar im iframe. **OK** uebernimmt
ebenfalls beide Tabs und schliesst erst nach erfolgreichem Rendering.
Wiederholte Uebernahmen bleiben einzeln rueckgaengig machbar.
Vorhandene aufgeloeste
Komponentendaten bleiben erhalten. Fehler bleiben im offenen Popup sichtbar
und veraendern die Instanz oder den Verlauf nicht. Abbrechen im Popup verwirft
die noch nicht uebernommenen Eingaben; bereits uebernommene Aenderungen bleiben
im Editor. Schliessen-X und Escape verhalten sich ebenso. Waehrend ausstehendem
Rendering sind OK, Uebernehmen, Abbrechen und X gesperrt.
Auch Uebernehmen und OK schreiben keine Datenbankdaten; Persistenz erfolgt
seit Etappe 4 ausschliesslich ueber den oberen Speichern-Button.

### Etappe 4: Seitenstand als Entwurf speichern

Der obere Button **Speichern** wird nach einer lokalen Aenderung aktiv, auch
in der sauberen Vorschau. Er erfasst alle vorhandenen oder angelegten Ebenen
der aktuellen Seite, einschliesslich ausdruecklich leerer Bereiche, geordneter Containerkinder,
Komponentenoptionen, eigener Navigationspunkte und allgemeiner Einstellungen.
Es wird strukturiertes JSON serialisiert, kein HTML, TPL, CSS-Stylesheet oder
Editor-Markup. Vorhandene `source`-Pfade bleiben Referenzen; die fuer die
Vorschau aufgeloesten Daten werden bei solchen Instanzen nicht dupliziert.
Die Auswahl neuer Datenquellen ist weiterhin nicht implementiert.

Jeder Vorschau-Bereich enthaelt eine serverseitig erzeugte Zielzuordnung:
Modul als Aufrufkontext, aufgeloeste Route, Region, Ebene, Branch und aktuelle
Editorsprache. Nur individuell zugeordnete Ebenen enthalten Entity; eine
vorhandene contextEntity bindet alle Ebenen an die gerade betrachtete Seite.
Sie ist mit einem separaten, nur serverseitigen Sitzungsschluessel signiert.
Der Editor uebertraegt Ziel-JSON und Signatur unveraendert. Die Speicherroute
`admin__builder`, POST, `ACTION=save` verlangt die vorhandene Admin-Middleware,
angemeldete Sitzung, CSRF-Token, gueltige Signaturen und ein noch aktives
Zielmodul. Bereiche unterschiedlicher Seiten/Sprachen werden nicht gemischt.
Bei englischem Fallback wird in der aktuellen Editorsprache gespeichert,
nicht versehentlich der englische Stand ueberschrieben.

Builder::saveDrafts prueft alle Bereiche und Knoten vor dem Schreiben:
Zuordnung, eindeutige IDs/Sprachstaende, Struktur, allgemeine Einstellungen,
aktive Komponentenanbieter, Manifest und Optionen. Danach erfolgt ein
gemeinsamer CData-Update in Core BUILDER/LANGUAGE/DraftJson; ein anschliessendes
Lesen prueft die gespeicherten Werte. PublishedJson und Active des Sprachstands
bleiben unveraendert. Keine automatische Publikation oder Reaktivierung.
Die vorhandene Einzelbereichs-API saveDraft verwendet denselben Speicherweg.

Die Antwort ist JSON mit `saved=true` und Bereichsanzahl oder ein HTTP-
Fehlerstatus mit `error`. Der Editor meldet Erfolg erst nach Bestaetigung.
Fehler lassen Inhalt und Verlauf unveraendert und Speichern erneut zu.
Waehrend eines Schreibrequests sind Bearbeitung und Abbrechen gesperrt,
damit ein laufender Schreibvorgang nicht als verworfen erscheint.
Nach Erfolg bleibt die Bearbeitung offen. Undo/Redo bleibt erhalten; ein
erneutes Speichern ist nur bei Abweichung vom bestaetigten Stand aktiv.
Popup-Uebernehmen und Vorschau-Schalter schreiben weiterhin nichts.

Nach Abbrechen und erneutem Edit oder Neuladen der Entwurfsvorschau erscheint
der gespeicherte Entwurf. Die normale Besucherseite bleibt beim veroeffentlichten
Stand. Revisionshistorie, parallele Bearbeitung/Konfliktaufloesung und die
Veroeffentlichungsoberflaeche sind separate Folgeschritte.

Pruefung: Einfuegen/verschieben/konfigurieren, Speichern, Editor verlassen,
erneut Edit und gleiche Inhalte kontrollieren. Auch leere Bereiche speichern,
damit geloeschte Inhalte nicht wieder erscheinen. Sprache/Unterseite wechseln
und deren Trennung pruefen. Manipulierte Zielzuordnung, falscher CSRF-Token,
Gast, GET und ungueltige zweite Region muessen ohne Entwurfsueberschreibung
abgewiesen werden. Normaler Frontend-Aufruf darf keinen Entwurf zeigen.

### Navigation aus der Menueverwaltung

Die fruehere instanzeigene Punkt-/Unterpunktbearbeitung ist entfernt.
Navigationen werden ausschliesslich unter Frontend `admin__menu.list` gepflegt.
Das Builder-Popup bietet `options.menu` als Dropdown mit Menue-Namen und ID.
Gespeichert wird nur die Auswahl, kein duplizierter Navigationsbaum. Aenderungen
an der zentralen Navigation erscheinen beim naechsten Rendern aller Instanzen.
Deaktivierte Navigationen liefern keine Links; fehlende IDs erzeugen einen
sichtbaren Fehler statt auf ein anderes Menue zurueckzufallen.

Die Datei `navigation.php` liegt neben Manifest und TPL im Komponentenordner
und verwendet Frontend::get_navigation fuer Auswahl und Daten. Core kennt
nur die allgemeine PHP-Datenanbieter-/choices-Schnittstelle, keine Menue-
Sonderlogik. Fuer diese Auswahl ist kein individuelles JavaScript erforderlich.
Etwaiger kuenftiger komponentenspezifischer Editorcode gehoert ebenfalls in
den jeweiligen Komponentenordner; ein allgemeiner Script-Ladevertrag wird
ohne konkreten Bedarf nicht vorweggenommen.

Ein leeres menu behaelt explizit uebergebene Layout-Daten (Header/Footer) bei.
Fruehere eigene `items` werden nicht automatisch migriert und melden einen
Fehler. Bei Bedarf Navigation zentral anlegen und die Instanz neu einsetzen.

### Etappe 5: Gemeinsames Layout und instanzbezogene Sprachdaten

Das vom Entwickler erweiterte BUILDER-Pattern enthaelt nun Active sowie
DraftJson/PublishedJson direkt auf BUILDER-Ebene. Diese Aenderung bleibt erhalten.
Die Speicherung verwendet diese Felder jetzt. BUILDER bezeichnet weiterhin
einen Bereich mit mehreren Komponenten, nicht eine einzelne Komponente.
Die Implementierung folgt dem abgestimmten Modell:

- Pro Bereich gibt es ein gemeinsames Layout fuer alle Sprachen: Container,
  Reihenfolge und sprachunabhaengige Komponenten werden nicht pro Sprache kopiert.
  Gemeinsamer Entwurf und gemeinsame Veroeffentlichung verwenden die
  DraftJson-/PublishedJson-Felder direkt am BUILDER-Datensatz.
- Navigation ist sprachunabhaengig. Ihre Menue-ID und Darstellungsoptionen
  gehoeren zum gemeinsamen Layout; Inhalte kommen weiterhin aus der zentralen
  Menueverwaltung. Keine zusaetzliche Builder-Sprachzuordnung fuer Navigation.
- Nur uebersetzbare Inhalte erhalten Sprachwerte. Die Zuordnung erfolgt ueber
  die stabile Komponenten-Instanz-ID, nicht ueber die Listenposition.
- Sprachaktivierung gilt fuer jede uebersetzbare Komponenteninstanz einzeln,
  nicht gemeinsam fuer alle Komponenten eines Bereichs.
- BUILDER.Active schaltet den gesamten Bereich sprachuebergreifend aus.
  general.Active schaltet dagegen nur die jeweilige Komponenten- oder
  Containerinstanz sprachuebergreifend aus.
- Deaktivierte Elemente bleiben im Editor sichtbar und bearbeitbar. Eine
  deaktivierte Sprache einer uebersetzbaren Komponente blendet nicht die
  sprachunabhaengigen Komponenten desselben Bereichs aus.
- Oeffentlich wirksam werden auch Aktivierungsaenderungen erst beim
  Veroeffentlichen. Speichern eines Entwurfs darf weder die veroeffentlichte
  Aktivierung noch die veroeffentlichten Inhalte veraendern. Deshalb darf ein
  Entwurfswechsel nicht sofort BUILDER.Active fuer die Besucherausgabe umstellen;
  der Entwurfsstatus muss im Entwurf mitgefuehrt werden.

Verbindliche Sprachaufloesung pro uebersetzbarer Komponenteninstanz:

| Zielsprache | Eigene gespeicherte Uebersetzung | Besucherausgabe |
|---|---|---|
| Nicht aktiviert | Beliebig | Keine Ausgabe, kein EN-Fallback |
| Aktiviert | Vorhanden und nicht leer | Eigene Uebersetzung |
| Aktiviert | Bewusst leer gespeichert | Leer, kein EN-Fallback |
| Aktiviert | Noch keine Uebersetzung gespeichert | Veroeffentlichtes EN, sofern dessen Sprachstand aktiviert und vorhanden ist |

Fehlende Sprachaktivierung gilt nicht als Aktivierung. Fehlt auch ein
verwendbarer EN-Stand, bleibt die Ausgabe leer. Ein Sprachfallback darf keine
Entwurfsinhalte oeffentlich anzeigen und keine sprachunabhaengigen Komponenten
ausblenden.

#### Aktueller Speichervertrag

```text
BUILDER.D.<placeId>
  ModuleId, PageId, EntityId, Region, Scope, Branch
  Active                        (veroeffentlichte Bereichsaktivierung)
  DraftJson / PublishedJson     {"version":2,"active":true,"nodes":[...]}
  LANGUAGE.D.<EN|DE|...>
    DraftJson / PublishedJson   {"version":2,"components":{
                                 "<instanceId>":{"active":true,
                                   "options":{"text":"Uebersetzung"}}
                               }}
```

`nodes` enthaelt die gemeinsame Knotenliste einschliesslich general.Active,
Containerstruktur, Datenquellen und nicht uebersetzbarer Optionen.
Sprachwerte werden ueber Instanz-IDs zugeordnet; nicht ueber Arraypositionen.
Sprachunabhaengige Komponenten erzeugen keine eigenen Spracheintraege.
Das bisherige LANGUAGE.Active bleibt nur fuer Altstaende in Verwendung;
im neuen Vertrag liegt Sprachaktivierung am jeweiligen components-Eintrag.

Ein Manifest markiert tatsaechlich uebersetzbare String-Einstellungen mit
`"translatable": true`. Nur diese Optionen wandern in die Sprach-Snapshots.
Fachliche `data`-Baeume und `source` bleiben gemeinsame Daten bzw. Referenzen;
ein Komponenten-Datenanbieter bleibt fuer deren eigene Sprachlogik zustaendig.
Es gibt keine pauschale Kopie aller Optionen oder fachlichen Daten pro Sprache.
Navigation deklariert keine uebersetzbaren Builder-Felder.

Fehlendes `options` in einem aktivierten Spracheintrag bedeutet: noch keine
eigene Uebersetzung gespeichert, EN-Fallback erlaubt. Vorhandenes `options`
muss alle uebersetzbaren Felder enthalten. Leere Stringwerte sind bewusst
gespeicherte Inhalte und loesen keinen Fallback aus. Fremde Felder,
ungueltige Sprachcodes, Typen und Spracheintraege fuer sprachunabhaengige
Komponenten werden vor dem Speichern abgelehnt.

Der Editor uebertraegt pro Komponente die vollstaendige `translations`-Map.
Der Server trennt diese beim Speichern vom Layout und fuehrt sie beim Laden
wieder zusammen. Entfernte Instanzen hinterlassen keine Sprachwerte im neuen
Entwurf. Andere Sprachwerte bleiben bei der Bearbeitung der Seitensprache
erhalten. Ohne mitgelieferte translations darf ein interner Aufrufer bei
neuen Instanzen markierte Optionen fuer die aktuelle Sprache initialisieren.

In der Bibliothek lassen sich einzelne Bereiche fuer alle Sprachen aktivieren
oder deaktivieren. Der Entwurfsstatus nimmt an Undo/Redo und Speichern teil.
Bei uebersetzbaren Komponenten bietet das Popup fuer die aktuelle Seitensprache
`Sprache XX aktiv` und `Eigene Uebersetzung (sonst EN-Fallback)` an. Die Sprache
folgt der vor Edit aufgerufenen Frontend-Seite. Sprachunabhaengige Komponenten
zeigen diese Felder nicht. Inaktive Inhalte bleiben im Editor bearbeitbar;
die saubere Vorschau beruecksichtigt Bereichs-, Instanz- und Sprachaktivierung.

Die interne Operation publish uebernimmt das gemeinsame Dokument samt
zugehoerigen Sprach-Snapshots und setzt erst dabei BUILDER.Active.
Ihr bisheriger Sprachparameter bleibt fuer Altstaende erhalten; beim
gemeinsamen Vertrag wird stets der gesamte Bereich veroeffentlicht.
Speichern allein aendert weder PublishedJson noch eine veroeffentlichte
Aktivierung. Die Veroeffentlichungsoberflaeche ergaenzt Etappe 6.

#### Bestehende Entwuerfe und Veroeffentlichungen

Alte sprachgebundene Knotenlisten bleiben lesbar, einschliesslich ihrer
bisherigen Fallback-/Aktivierungsregeln. Erst Speichern erzeugt den neuen
gemeinsamen Entwurf; eine alte Veroeffentlichung bleibt bis publish unveraendert.
Sind die alten Sprachlayouts je Stand identisch, koennen sprachunabhaengige
Inhalte ohne Auswahl eines beliebigen Sprachlayouts uebernommen werden.
Bei abweichenden alten Sprachlayouts wird der gesamte Speicherauftrag vor
dem ersten Datenbank-Update mit sichtbarer Fehlermeldung abgelehnt.
Dasselbe gilt fuer alte Instanzen, deren Felder nachtraeglich als uebersetzbar
markiert wurden: ihre Sprachwerte muessen vor der Umstellung ausdruecklich
zugeordnet werden. Eine automatische, potenziell verlustbehaftete
Konfliktaufloesung oder eine eigene Migrationsoberflaeche gibt es nicht.

### Etappe 6: Gespeicherten Seitenentwurf veroeffentlichen

Neben Speichern steht im Editormodus ein kleiner Button Veroeffentlichen.
Speichern schreibt weiterhin nur Entwuerfe. Veroeffentlichen uebernimmt
ausdruecklich die gespeicherten Bereiche der aktuell bearbeiteten Seite
einschliesslich aller gespeicherten Sprachstaende und Aktivierungen.
Der Button ist bei ungespeicherten Aenderungen, offenem Einstellungsfenster
oder laufenden Aktionen deaktiviert. Ohne gemeinsamen gespeicherten Entwurf
muss zuerst Speichern ausgefuehrt werden; dies ist auch ohne lokale Aenderung
moeglich, etwa zur sicheren Umstellung alter sprachunabhaengiger Entwuerfe.
Nach erfolgreicher Veroeffentlichung wird der Button bis zu einem anderen
gespeicherten Stand deaktiviert. Die Bearbeitung und Undo/Redo bleiben erhalten.
Beim erneuten Oeffnen kann derselbe gespeicherte Stand erneut veroeffentlicht
werden; dies aendert keine Inhalte.

Die POST-Aktion publish verwendet dieselben Admin-, Sitzungs-, CSRF- und
HMAC-Zielpruefungen wie save. Alle uebergebenen Bereiche muessen zur selben
Modul-/Seiten-/Datensatz-/Sprachzuordnung gehoeren. Der Request enthaelt nur
Ziel, Zielsignatur und Entwurfsrevision, keine zu veroeffentlichenden Client-
Knoten. Der Server liest die gespeicherten Dokumente selbst.

Die Revision ist ein SHA-256-Fingerabdruck aus gemeinsamem DraftJson und
saemtlichen zugehoerigen Sprach-DraftJson-Werten, sortiert nach Sprachcode.
Sie stammt beim Laden aus genau dem gelesenen Vorschaustand, beim Speichern
aus genau dem bestaetigten Schreibstand. Hat sich ein gespeicherter Entwurf
inzwischen geaendert, wird der gesamte Veroeffentlichungsauftrag vor dem
Update abgelehnt; der Benutzer muss neu laden und pruefen.

Builder::publishDrafts prueft alle Bereiche, Revisionen, Dokumentstrukturen,
Sprachdaten, aktiven Komponentenmodule und aktuellen Manifestoptionen vor
einem gemeinsamen CData-Update. Anschliessend werden PublishedJson und Active
einschliesslich aller Sprach-Snapshots zur Erfolgsbestaetigung zurueckgelesen.
Validierungsfehler schreiben keinen Bereich. CData bietet hierbei weiterhin
keine explizite Transaktion: eine Datenbank-I/O-Stoerung ist kein zugesichertes
atomisches Alles-oder-nichts, und der Revisionsvergleich ist keine atomare
Compare-and-swap-Sperre gegen einen gleichzeitigen Schreibvorgang nach der
Pruefung. Eine vollstaendige parallele Bearbeitungssperre bleibt offen.

Waehrend der Veroeffentlichung sind weitere Editoraktionen und Abbrechen
gesperrt. Fehler bleiben sichtbar und koennen erneut versucht werden;
eine fehlgeschlagene Antwort wird nicht als Erfolg behandelt. Leere Bereiche
und deaktivierte Bereiche koennen ebenso veroeffentlicht werden.
Eine Ruecknahme-/Revisionsoberflaeche und zeitgesteuerte Veroeffentlichung
sind nicht Teil dieser Etappe.

### Grid-Komponente statt fester Layout-Auswahl

Die Bibliothek zeigt nun eine allgemeine Komponente Grid unter
fremeo/core, keine getrennten Eintraege fuer Stapel/Eine Spalte/Zwei Spalten.
Manifest, PHP-Validierung, TPL und Editor-JavaScript liegen gemeinsam in
system/template/components/container/. Details und Editorcode-Vertrag stehen
in [Komponenten.md](Komponenten.md).

Individuelle Bootstrap-Spaltenbreiten werden als Folge angegeben, etwa 8+4
oder 3+6+3; jede Breite liegt bei 1 bis 12, maximal zwoelf Spalten pro Folge.
Summen ueber 12 umbrechen, etwa 12+12+12 fuer gestapelte mobile Inhalte.
Optionale XS-/SM-/MD-/LG-Folgen besitzen dieselbe Spaltenanzahl.
Nicht gesetzte Bildschirmwerte erben aufwaerts; ohne Overrides stehen Kinder
auf kleinen Bildschirmen untereinander und nutzen ab MD die allgemeine Folge.
Rasterfolgen koennen fuer weitere Spalten in neuen Zeilen wiederholt werden.
Mehrere Kinder mit demselben slot stehen in ihrer Listenreihenfolge in einer
Spalte. Oberhalb, zwischen und unterhalb erscheinen kompakte, textlose,
gelb-transparente Ablageflaechen; leere Spalten haben eine Flaeche.
Verschachtelte Grids sind weiterhin moeglich.

Neue Containerknoten enthalten Modul fremeo/core, Typ container, options und
children. Die alten Layoutwerte bleiben lesbar und werden im Editor ohne
Inhaltsverlust in 12 bzw. 6+6 ueberfuehrt. Speichern/Veroeffentlichen pruefen
auch verschachtelte Raster serverseitig. Der technische Typ bleibt container.
Das lokale Popup bietet Spaltenauswahl, gleichmaessig vorbelegte Textfelder
und Live-Vorschauen von LG nach XS mit Active-Haken je Ansicht.
Apply/OK ergaenzen passend viele leere Drop-Spalten und aktualisieren
Spaltenklassen, entfernen aber niemals vorhandene Kinder. Kind.slot erhaelt
leere vorhergehende Plaetze beim Speichern und Neuladen.
Deaktivierte Ansichten verbergen Grid samt Inhalt nur in sauberer/oeffentlicher
Vorschau, nicht im Editor. Undo/Redo, Entwuerfe,
Veroeffentlichung und saubere Vorschau verwenden dieselben Optionen.
Die Editorvorschau nutzt jetzt ebenfalls Bootstrap statt eines abweichenden
CSS-Grid-Rasters mit eigener Bildschirmgrenze.
