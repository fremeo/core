<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_name('fremeo');
    session_start();
}
$D['SESSION'] = $_SESSION;

	$C['Smarty']->addTemplateDir(__DIR__ . '/system/template/', 'core');
    $C['Smarty']->setCompileDir("{$D['MODULE']['D']['fremeo/core']['INFO']['CacheDir']}template_c/");
	$C['Smarty']->error_reporting = E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED;


	$my_security_policy = new Smarty_Security($C['Smarty']);

	#ToDo: file_get_contents ist keien Sichere Funktion da es Zugrif auf lokale Dateien erlaubt. Es muss eine abgespeckte Version der CFILE Klasse zur verfügung gestelt wertden.
	#file_get_contents nur auf URLs erlauben nicht auf URIs
	$my_security_policy->php_functions = ['max','round','hrtime','rtrim','dirname','mail','base64_encode','mb_convert_encoding','hash','header','json_decode','json_encode','serialize','COUNT','file_get_contents','substr','json_decode','array_key_exists','array_merge_recursive','array_diff_key','str_pad','strtotime','date','ceil','array_keys','current','count','strlen','strtolower','number_format','md5','in_array','is_array','time','nl2br','print_r','array_key_first','strpos','strrpos','str_replace','isset','empty','sizeof','trim','explode','implode'];
	$my_security_policy->php_modifiers = ['in_array','strlen','strstr','array_keys','count','COUNT','number_format'];#Smarty PHP Erweiteurngen
	$my_security_policy->streams = null;
	$my_security_policy->secure_dir = ['system/'];
	
	$C['Smarty']->enableSecurity($my_security_policy);

if( isset($R['SEO_URL']) ) {

	if(!isset($R['Page'])) { #Mit SEO Link darf kein Page übergeben werden
	
		$hURL = hash("crc32b", rtrim($R['SEO_URL'],'/'));
		
		$f['LINK']['W'][0]['ID'] = $hURL;
		$f['LINK']['W'][0]['Active'] = 1;

		foreach((array)($D['MODULE']['D'] ?? []) AS $kMOD => $MOD) {

			if(!isset($C[$kMOD]['CData'])) continue;
			$C[$kMOD]['CData']->get_object($D['MODULE']['D'][$kMOD],$f);

			$link = $D['MODULE']['D'][$kMOD]['LINK']['D'][ $hURL ] ?? null;
			if($link && ($link['Active']??false) && strpos($link['ToURL'], 'R[Page]') !== false ) {

				parse_str($link['ToURL'], $d);

				$D = array_merge_recursive((array)($D??[]),(array)($d['D']??[]));
				if(isset($d['R'])) {
					$R = $D['R'] = array_replace_recursive((array)($d['R']),($R??[]) );#ToDo: 
				}

				header( "HTTP/1.1 200 OK" );
				$D['R']['ModuleId'] = $kMOD;
				break;
			}
		}
	}
	
	if(!isset($D['R']['ModuleId'])) {
		header( "HTTP/1.1 404 Not Found" );
		$D['_PAGE'] = 'error.404';
		$D['R']['Page'] = 'error.404';
		$D['R']['ModuleId'] = 'fremeo/core';
	}
	
}


if($D['R']['ModuleId']) {
	#Globales Link Objekt für die gesamte Anwendung. Geschrieben wird in das aktive Modul, vergebene SEO-URLs werden in allen Modulen geprüft.
	$C['Link'] = new \fremeo\core\Link(
		$C[ $D['R']['ModuleId'] ]['CData'],
		array_column(array_filter($C, 'is_array'), 'CData')
	);
}

	#Template und php Verkettung

	$_tpl = getExtends($D['MODULE']['D'], $D['R']['ModuleId'], $D['R']['Page']);


	foreach($_tpl['php'] AS $kFile) {
		include_once ($kFile);
	}
	$C['fremeo/core']['CData']->get_object($D,$F); #Datenbank Abfrage
	
	/* # Diesen Code nicht löschen, dient zur Debugging-Ausgabe der Template-Kette
	echo "<pre>";
	print_R($_tpl['tpl']);
	print_r($_tpl['php']);
	echo "</pre>";
	*/
	$C['Smarty']->assign('D',$D);
	$C['Smarty']->display($_tpl['extends']."|include/input.tpl");


#-------------
function getExtends(array $modules, string $activeModuleId, string $page): array
{
    // Bibliotheken (type "library", z.B. phploader/cdata, symfony/*) enthalten keine Templates und werden nicht durchsucht
    $modules = array_filter($modules, fn($m) => ($m['Type'] ?? 'module') !== 'library');

    // Modul muss existieren
    if (!isset($modules[$activeModuleId])) {
        return ['extends' => '', 'tpl' => [], 'php' => []];
    }

    $tplChain = [];
    $phpChain = [];

    // Rangfolge der Modul-Typen für die Template-Kette (Typ aus der composer.json). Unbekannte Typen zählen als 'module'.
    // Die Kette wird am Ende umgedreht, dadurch ergibt sich core -> template -> module.
    $rank = ['core' => 0, 'template' => 1, 'module' => 2];
    $typeOf = fn(array $m): string => $m['Type'] ?? 'module';

    // Alle Module: Module vor Template vor Core. Gleicher Rang behält die bisherige Reihenfolge (uasort ist ab PHP 8 stabil).
    uasort($modules, fn($a, $b) => ($rank[$typeOf($b)] ?? 2) <=> ($rank[$typeOf($a)] ?? 2));

    // Dateien, bei denen nur EIN Modul verwendet wird (z.B. index.tpl): Template vor Core vor Modulen
    $firstOrder = ['template' => 0, 'core' => 1, 'module' => 2];
    $first = $modules;
    uasort($first, fn($a, $b) => ($firstOrder[$typeOf($a)] ?? 2) <=> ($firstOrder[$typeOf($b)] ?? 2));

    // Hilfsfunktion: TPL existiert?
    $exists = fn(array $module, string $tpl): bool => file_exists($module['INFO']['ModulDir'] . '/system/template/' . $tpl);

    // Hilfsfunktion: TPL → PHP Pfad
    $toPhp = fn(string $tplPath): string => str_replace(['/system/template/', '.tpl'], ['/system/', '.php'], $tplPath);

    // Hilfsfunktion: TPL in die Kette aufnehmen, dazu die passende PHP-Datei (falls vorhanden)
    $add = function(string $tplPath) use (&$tplChain, &$phpChain, $toPhp) {
        $tplChain[] = $tplPath;
        if (file_exists($php = $toPhp($tplPath))) {
            $phpChain[] = $php;
        }
    };

    // ---------------------------------------------------------
    // 1. Basis-Seite ohne "__" → nur root.tpl
    // ---------------------------------------------------------
    if (!str_contains($page, '__')) {

        $tpl = $modules[$activeModuleId]['INFO']['ModulDir'] . '/system/template/' . $page . '.tpl';
        $php = $toPhp($tpl);

        return [
            'extends' => 'extends:' . $tpl,
            'tpl'     => [$tpl],
            'php'     => file_exists($php) ? [$php] : []
        ];
    }

    // ---------------------------------------------------------
    // 2. base__sub zerlegen
    // ---------------------------------------------------------
    [$base, $sub] = explode('__', $page);

    // ---------------------------------------------------------
    // 2b. sub__sub.tpl (z.B. user.register__user.register.tpl) – erstes Modul, kommt als ERSTES in die Chain
    // ---------------------------------------------------------
    $subBase = "{$sub}__{$sub}.tpl";

    foreach ($first as $moduleDir) {
        if ($exists($moduleDir, $subBase)) {
            $add($moduleDir['INFO']['ModulDir'] . '/system/template/' . $subBase);
            break; // nur das erste gefundene Modul verwenden
        }
    }

    // ---------------------------------------------------------
    // 3. konkrete Seite (nur aktives Modul) → unterstes Element
    // ---------------------------------------------------------
    if ($exists($modules[$activeModuleId], $page . '.tpl')) {
        $add($modules[$activeModuleId]['INFO']['ModulDir'] . '/system/template/' . $page . '.tpl');
    }

    // ---------------------------------------------------------
    // 4. base__base.tpl (admin__admin.tpl) – ALLE Module sammeln
    // ---------------------------------------------------------
    $baseBase = "{$base}__{$base}.tpl";

    foreach ($modules as $moduleDir) {
        $tplPath = $moduleDir['INFO']['ModulDir'] . '/system/template/' . $baseBase;

        if ($exists($moduleDir, $baseBase)) {
            $add($tplPath);                                   // TPL + PHP (falls vorhanden)
        } elseif (file_exists($php = $toPhp($tplPath))) {
            $phpChain[] = $php;                               // TPL fehlt, aber PHP existiert → nur PHP
        }
    }



    // ---------------------------------------------------------
    // 5. Rekursiv: *__base.tpl nach oben suchen
    //    – base__base.tpl dabei überspringen
    // ---------------------------------------------------------
    $currentBase  = $base;
    $visitedBases = [];

    while (true) {
        $candidates = [];

        foreach ($modules as $moduleDir) {
            foreach (glob($moduleDir['INFO']['ModulDir'] . "/system/template/*__{$currentBase}.tpl") as $file) {

                // base__base.tpl überspringen (haben wir in Schritt 4 schon)
                if (basename($file) === $baseBase) {
                    continue;
                }

                $candidates[] = [
                    'tpl'     => $file,
                    'newBase' => explode('__', basename($file), 2)[0],
                ];
            }
        }

        if (empty($candidates)) {
            break;
        }

        // Sortierung:
        //  - zuerst Templates, deren newBase == currentBase (z.B. frontend__frontend)
        //  - danach Templates, deren newBase != currentBase (z.B. index__frontend)
        //  (weil wir am Ende die Kette umdrehen!)
        usort($candidates, fn($a, $b) => ($b['newBase'] === $currentBase) <=> ($a['newBase'] === $currentBase));

        // Alle Kandidaten in Chain aufnehmen (ohne Duplikate)
        foreach ($candidates as $cand) {
            if (!in_array($cand['tpl'], $tplChain, true)) {
                $add($cand['tpl']);
            }
        }

        // Nächste Base: erste, deren newBase != currentBase (also "eine Ebene höher")
        $nextBase = null;
        foreach ($candidates as $cand) {
            if ($cand['newBase'] !== $currentBase) {
                $nextBase = $cand['newBase'];
                break;
            }
        }

        if ($nextBase === null || isset($visitedBases[$nextBase])) {
            break;
        }

        $visitedBases[$nextBase] = true;
        $currentBase             = $nextBase;
    }


    // 6. root.tpl = currentBase.tpl (oberstes Template, z.B. index.tpl)
	$rootTplName = $currentBase . '.tpl';

	// 6a. root__root.php zuerst einfügen (z.B. index__index.php)
	$rootBaseBasePhp = $currentBase . '__' . $currentBase . '.php';

	foreach ($first as $moduleDir) {
		if (file_exists($phpPath = $moduleDir['INFO']['ModulDir'] . '/system/' . $rootBaseBasePhp)) {
			$phpChain[] = $phpPath;   // index__index.php
			break;
		}
	}

	// 6b. root.tpl + root.php danach einfügen (z.B. index.tpl, index.php)
	foreach ($first as $moduleDir) {
		if ($exists($moduleDir, $rootTplName)) {
			$add($moduleDir['INFO']['ModulDir'] . '/system/template/' . $rootTplName);
			break;
		}
	}

    // ---------------------------------------------------------
    // 7. Kette umdrehen: von root → Seite
    // ---------------------------------------------------------
    $tplChain = array_reverse(array_values(array_unique($tplChain)));
    $phpChain = array_reverse(array_values(array_unique($phpChain)));

    return [
        'extends' => 'extends:' . implode('|', $tplChain),
        'tpl'     => $tplChain,
        'php'     => $phpChain
    ];
}


