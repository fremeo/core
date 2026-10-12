# Klasse `Link` (SEO-Links anlegen, umbenennen, löschen)

`\fremeo\core\Link` – Datei: `system/core/Link.php` (PSR-4 Autoload `fremeo\core\`, kein `include` nötig).

| Abschnitt | Beschreibung |
|----------|--------------|
| **Zweck** | SEO-Links (`LINK` in der CData) eines Moduls verwalten. Ist eine SEO-URL schon vergeben, wird automatisch `_1`, `_2`, … angehängt; Lücken werden aufgefüllt (`blog`, `blog_1`, `blog_3` → neu: `blog_2`). |
| **Instanz** | `$C['Link']`, angelegt in `start.php` für das **aktive Modul** (`$D['R']['ModuleId']`). Geschrieben wird nur in dessen DB. Im CLI-Modus wird `start.php` nicht geladen, dort muss `Link` selbst angelegt werden. |
| **Modulübergreifende Prüfung** | Konstruktor: `new Link($DB, $allDB = [])`. `$allDB` = `CData`-Objekte aller Module (in `start.php` automatisch aus `$C`). Vergebene URLs werden in **allen** diesen DBs geprüft, damit keine URL doppelt vergeben wird. Ohne `$allDB` nur die eigene DB. |
| **Link-ID** | `id($seo)` = `hash('crc32b', $seo)` – gleiche ID wie in Migrationen (`hash("crc32b", 'admin')`). |
| **Datensatz** | `LINK.D[{id}] = ['Active' => 1, 'FromURL' => seo, 'ToURL' => 'R[Page]=..&R[ModuleId]=..&param', 'ModuleId' => ..]` |

## Verwendung

```php
// Einen Link anlegen, Rückgabe = Link-ID
$id = $C['Link']->createOne('blog/mein-post', 'frontend__blog.view', 'fremeo/blog', ['id' => 5]);

// Mehrere anlegen (beliebige Keys), Rückgabe je Key: LinkId, Page, ModuleId, SeoUrl (final), Param
$r = $C['Link']->create([
    'a' => ['SeoURL' => 'blog/a', 'Page' => 'frontend__blog.view', 'ModuleId' => 'fremeo/blog'],
    'b' => ['SeoURL' => 'blog/b', 'Page' => 'frontend__blog.view', 'ModuleId' => 'fremeo/blog', 'Param' => 'id=2'],
]);
echo $r['a']['SeoUrl'];      // z.B. 'blog/a_1', falls vergeben

// Umbenennen (alter Eintrag wird gelöscht, neuer angelegt, ggf. mit _n)
$C['Link']->renameBySeo('blog/a', 'blog/neu');   // bool
$C['Link']->renameById($id, 'blog/neu');         // bool
$C['Link']->rename([['OldSeo' => 'x', 'NewSeo' => 'y'], ['OldSeoId' => 'a1b2c3d4', 'NewSeo' => 'z']]);
// je Key: OldSeo, NewSeo (final), OldId, NewId, Success

// Löschen / Lesen
$C['Link']->deleteById([$id1, $id2]);   $C['Link']->deleteBySeo('blog/neu');
$C['Link']->getById($id);               $C['Link']->getBySeo(['blog/a', 'blog/b']);
```

## Wichtig
* `Param` ist ein Array (wird mit `http_build_query` angehängt) oder ein fertiger String.
* Mehrfach gleiche `SeoURL` in **einem** `create()`/`rename()`-Aufruf ergeben `blog`, `blog_1`, …
* Eine leere SEO-URL (`''`, Startseite) wird nicht auf Konflikte geprüft.
* `getById()`/`getBySeo()` liefern bei keinem Treffer trotzdem ein Array (nur `LINK.COUNT`); auf `LINK.D` prüfen, nicht auf `null`.
* Das Feld `ModuleId` ist im globalen Pattern (`init.php`) auskommentiert und wird daher nicht gespeichert; `rename()` liest es mit `?? null`. Das Modul steckt auch in `ToURL` (`R[ModuleId]`).
* `$C['fremeo/core']['Link']` wird in `admin__link.list.php` und `admin__page.edit.php` verwendet, ist in `init.php` aber auskommentiert – dort `$C['Link']` verwenden.
* Tests: `tests/LinkTest.php`.
