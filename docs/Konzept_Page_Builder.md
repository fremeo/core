# Konzept: Page Builder im Core

## Status und Pflege

Dieses Dokument beschreibt den abgestimmten Entwurf und noch offene
Entscheidungen. Der Page Builder ist noch nicht implementiert. Die vorhandene
Komponenten-Grundlage ist in [Komponenten.md](Komponenten.md) beschrieben.
Der aktuelle Auftrag umfasst ausschliesslich Dokumentation, keine Umsetzung.
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
  <type>.php       (optional, geplanter Vertrag)
```

Komponenten werden weder automatisch vererbt noch durch gleichnamige anderer
Module ueberschrieben. Nur registrierte Komponenten aktiver Anbieter sind
verwendbar. Interne Smarty-Funktionen brauchen anbieterspezifische Namen.
Jede Instanz besitzt eine stabile Builder-ID; die Ausgabe erhaelt eine
eindeutige HTML-ID, `component component-<type>` und die Modulzuordnung.

Eine gleichnamige PHP-Datei darf zur Komponente gehoeren, etwa fuer
Datenaufbereitung oder einen Formularversand. Ihre Aufrufschnittstelle und
Aktionsweiterleitung sind noch festzulegen. Der bisherige Komponentenrenderer
fuehrt solche Dateien noch nicht aus.

Darstellung und schreibende Aktionen muessen getrennt sein: Seitenaufruf,
Smarty-Rendering und Editor-Vorschau duerfen keinen E-Mail-Versand oder andere
Schreibaktionen ausloesen. Aktionen benoetigen explizite Requests, serverseitige
Validierung, Berechtigungspruefung und CSRF-Schutz; Formulare gegebenenfalls
Spam-Schutz. Fehlgeschlagene Aktionen werden sichtbar gemeldet, nicht als
erfolgreiche Ausgabe kaschiert. PHP wird nur aus registrierten lokalen
Komponenten geladen, niemals aus gespeicherten Benutzereingaben.

Das Manifest soll kuenftig Optionen mit Datentypen, Standardwerten,
Beschriftungen und erlaubten Werten beschreiben. Daraus entstehen Editorfelder
und serverseitige Validierung. Aktuell existieren lediglich `type`, `name`,
`defaults` und `variants`; ein vollstaendiges Schema ist noch nicht implementiert.

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

Eine Zielseite wird stabil ueber Modul-ID, Route und gegebenenfalls Datensatz-ID
identifiziert, nicht ueber die sichtbare SEO-URL. Dadurch koennen eine
Galerieuebersicht und ein konkretes Album unterschiedliche Dokumente erhalten.
Festgelegt ist die Zuordnung pro Modul und Route sowie optional konkretem
Datensatz, ohne Override-Kaskade.

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

## 5. Editor und Vorschau

Links erscheint die Komponentenbibliothek nach Modul gruppiert, beispielsweise
"Galerie / Galerie-Vorschau". Rechts befindet sich die echte Seitenvorschau
mit markierten erlaubten Ablagezielen. Instanzen lassen sich hinzufuegen,
konfigurieren, verschieben und entfernen. Der Editor erzeugt weitere
Einfuegeziele, sobald Inhalte vorhanden sind.

Ein iframe fuer die Vorschau ist empfohlen, noch nicht verbindlich festgelegt.
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
Die genaue Ebene des Active-Feldes (Dokument, Sprachstand oder einzelne Instanz)
ist vor Implementierung noch festzulegen.

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

Ein moeglicher, noch nicht implementierter Layout-Aufruf:

```smarty
{builder_region name="before_content"}
{block name="inner_body"}{/block}
{builder_region name="after_content"}
```

PHP laedt das veroeffentlichte Dokument und seine Datenquellen. Ein gemeinsamer
Renderer gibt den Baum ueber die festen Templates und den Komponentenrenderer
aus. Der manuelle `{component ...}`-Aufruf bleibt moeglich.

Kompilierung ist kein Ausgabe-Cache: Layoutverarbeitung und dynamische Abfragen
finden weiter statt. HTML-/Abfrage-Caching ist eine getrennte spaetere Aufgabe.
Dabei muessen Revision, Sprache, Theme, Datenaenderungen und Zugriffsrechte
beruecksichtigt werden. Geschuetzte Inhalte, Formulare und Entwurfsvorschauen
duerfen nicht ungeprueft oeffentlich gecacht werden.

## 8. Vor der Implementierung klären

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
