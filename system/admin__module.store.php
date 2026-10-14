<?php
// Modul-Store: Suche in den fremeo-Modulen auf Packagist (Name, Beschreibung, Keywords) und Installation per Composer. Template: admin__module.store.tpl

$_action  = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($D['R']['Action'] ?? null) : null; # Installation ändert das System: nur per POST
$_id      = (string)($R['Module']['Id'] ?? '');
$_version = trim((string)($R['Module']['Version'] ?? ''));
# Paketname und Version landen in einer Composer-Kommandozeile: nur gültige Werte zulassen
$_ok      = preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $_id) === 1 && ($_version === '' || preg_match('#^[A-Za-z0-9._~^*-]+$#', $_version) === 1);

if ($_action == 'install' && $_ok) {
    # installieren eines Moduls über Composer
    $R['ComposerOutput'] = $C['Composer']->run('require ' . $_id . ($_version !== '' ? ':' . $_version : '')); # Migrationen laufen danach automatisch (Composer-Skripte)
    $C['CCache']->flush(['Tag' => 'Package']); # Modulübersicht neu ermitteln
}

if (isset($R['Search']) && in_array($_action ?? ($D['R']['Action'] ?? null), ['search', 'install'], true)) { # nach der Installation bleibt die Trefferliste sichtbar
    # Alle fremeo-Pakete (Tag "fremeo") einmal laden und für eine Stunde zwischenspeichern, die Suche filtert danach lokal.
    $_key = 'Packagist_fremeo';
    $_c   = $C['CCache']->get_cache($_key);
    $_all = isset($_c[$_key]['Data']) ? unserialize($_c[$_key]['Data'], ['allowed_classes' => false]) : null;
    if (!is_array($_all)) {
        $_all = $C['Packagist']->getProjectList('fremeo', '');
        if ($_all) { # kein leeres Ergebnis (z.B. Packagist nicht erreichbar) zwischenspeichern
            $C['CCache']->set_cache([ $_key => [ 'Tag' => 'Packagist', 'Data' => serialize($_all), 'Ttl' => 3600 ] ]);
        }
    }

    # Nur installierbare fremeo-Pakete (Module, Templates, auch ältere Typen wie fremeo-module / fremeo-frontend), nicht Core, Loader und fremde Pakete
    $_words  = preg_split('/\s+/', mb_strtolower(trim((string)$R['Search'])), -1, PREG_SPLIT_NO_EMPTY);
    $_result = [];
    foreach ($_all as $_name => $_p) {
        $_keywords = array_map('strtolower', (array)($_p['keywords'] ?? []));
        $_type     = strtolower((string)($_p['type'] ?? ''));
        $_isSystem = in_array($_type, ['core', 'fremeo-core', 'project'], true) || in_array('fremeo-core', $_keywords, true);
        if (!str_starts_with($_name, 'fremeo/') || $_isSystem) {
            continue;
        }

        # Jedes Suchwort muss in Name, Beschreibung, Typ oder einem Keyword vorkommen (z.B. "template" findet auch Pakete mit dem Keyword fremeo-template)
        $_haystack = mb_strtolower($_name . ' ' . ($_p['description'] ?? '') . ' ' . $_type . ' ' . implode(' ', $_keywords));
        foreach ($_words as $_w) {
            if (!str_contains($_haystack, $_w)) {
                continue 2;
            }
        }
        $_result[$_name] = $_p;
    }
    $D['R']['Module']['D'] = $_result;
}

# Bereits installierte Module markieren (Version aus vendor/composer/installed.json)
$_installed = $C['Composer']->getInstalledPackages();
foreach ((array)($D['R']['Module']['D'] ?? []) as $_name => $_m) {
    if (isset($_installed[$_name])) {
        $D['R']['Module']['D'][$_name]['installed'] = true;
        $D['R']['Module']['D'][$_name]['installed_version'] = $_installed[$_name]['version'];
    }
}
