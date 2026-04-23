# Template- & PHP-Vererbungssystem (`getExtends()`)

| Abschnitt | Beschreibung |
|----------|--------------|
| **Zweck der Funktion** | Die Funktion `getExtends()` erzeugt automatisch eine vollständige Vererbungskette für Smarty‑Templates und die dazugehörigen (optionalen) PHP‑Dateien. Dadurch können Templates und PHP‑Logik modular erweitert werden, ohne sich gegenseitig zu überschreiben. |
| **Grundprinzip** | Das System basiert auf einer Namenskonvention: `base__sub.tpl`. Daraus wird eine hierarchische Vererbungskette erzeugt, die von einem Root‑Template bis zur konkreten Seite reicht. |
| **Namenskonvention** | Templates folgen dem Muster:<br>• `index.tpl`<br>• `index__frontend.tpl`<br>• `frontend__frontend.tpl`<br>• `frontend__user.register.tpl`<br>• `user.register__user.register.tpl` |
| **Modulübergreifende Suche** | Die Funktion durchsucht **alle Module** nach passenden Templates und PHP‑Dateien. Dadurch können Templates aus verschiedenen Modulen miteinander vererbt werden. |
| **TPL‑Vererbung** | Die Vererbungskette wird automatisch ermittelt und am Ende in der korrekten Reihenfolge ausgegeben:<br>1. `index.tpl`<br>2. `index__frontend.tpl`<br>3. `frontend__frontend.tpl`<br>4. `frontend__user.register.tpl`<br>5. `user.register__user.register.tpl` |
| **PHP‑Vererbung (optional)** | Zu jedem Template kann eine gleichnamige PHP‑Datei existieren. Die Funktion lädt diese Dateien **in derselben Reihenfolge wie die TPL‑Dateien**. Existiert eine PHP‑Datei nicht, wird sie einfach übersprungen. Dadurch können PHP‑Dateien optional sein. |
| **Sinn der PHP‑Vererbung** | Durch die parallele Verkettung können PHP‑Dateien **Logik ergänzen**, statt sie zu ersetzen. Jede Ebene kann zusätzliche Funktionen, Hooks oder Datenbereitstellung hinzufügen. Das ermöglicht modulare, erweiterbare Backend‑Logik. |
| **Sub‑Root‑Templates (`sub__sub.tpl`)** | Templates wie `user.register__user.register.tpl` repräsentieren die logische Wurzel eines Sub‑Bereichs. Sie werden bewusst so einsortiert, dass sie **immer am Ende der finalen Vererbungskette** stehen. |
| **Ablauf der Funktion** | 1. Prüfen, ob die Seite eine Vererbung besitzt<br>2. Zerlegen in `base` und `sub`<br>3. Konkrete Seite laden (`base__sub.tpl`)<br>4. Sub‑Root‑Template laden (`sub__sub.tpl`)<br>5. Base‑Root‑Template laden (`base__base.tpl`)<br>6. Rekursive Suche nach `*__base.tpl`<br>7. Root‑Template bestimmen (`base.tpl`)<br>8. Reihenfolge umdrehen → fertige Vererbungskette |
| **Smarty‑Ausgabe** | Die Funktion erzeugt eine Smarty‑`extends:`‑Kette:<br>`extends:index.tpl\|index__frontend.tpl\|frontend__frontend.tpl\|frontend__user.register.tpl\|user.register__user.register.tpl` |
| **Rückgabewert** | ```php<br>[<br>  'extends' => 'extends:…',<br>  'tpl'     => [...],<br>  'php'     => [...]<br>]``` |
| **Verwendung im Code** | ```php<br>$_tpl = getExtends($modules, $activeModuleId, $page);<br><br>foreach ($_tpl['php'] as $file) {<br>    include_once($file);<br>}<br><br>$Smarty->assign('D', $D);<br>$Smarty->display($_tpl['extends'] . "|include/input.tpl");``` |
| **Vorteile des Systems** | • Klare, modulare Template‑Struktur<br>• PHP‑Logik kann sich ergänzen statt überschreiben<br>• Wiederverwendbarkeit von Layouts und Logik<br>• Automatische, fehlerfreie Vererbung<br>• Erweiterbar für beliebige Module und Seiten |
| **Erweiterbarkeit** | Neue Templates, Module oder Sub‑Bereiche können ohne Anpassung der Funktion hinzugefügt werden. Die Vererbung wird automatisch erkannt. |

