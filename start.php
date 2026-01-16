<?php 
session_name('phpapp');
session_start();
$D['SESSION'] = $_SESSION;


// 3. Phase: alle start.php laden
foreach ($D['MODUL']['D'] as $moduleDir => $info) {
    $D['MY'] = $info;
	
	$start = $info['ModulDir'] . '/start.php';
    if (is_file($start)) {
        require_once $start;
    }
}

	#Admin

	// Smarty-Instanz erzeugen
	#$C['Smarty'] = new Smarty();

	// Konfiguration (optional)
	#$C['Smarty']->setTemplateDir(__DIR__ . '/system/template/');
	$C['Smarty']->addTemplateDir(__DIR__ . '/system/template/', 'phpapp');
	$C['Smarty']->setCompileDir("{$D['MY']['CacheDir']}template_c/");
	$C['Smarty']->error_reporting = E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED;


	$my_security_policy = new Smarty_Security($C['Smarty']);

	#ToDo: file_get_contents ist keien Sichere Funktion da es Zugrif auf lokale Dateien erlaubt. Es muss eine abgespeckte Version der CFILE Klasse zur verfügung gestelt wertden.
	#file_get_contents nur auf URLs erlauben nicht auf URIs
	$my_security_policy->php_functions = ['mail','base64_encode','mb_convert_encoding','hash','header','json_decode','json_encode','serialize','COUNT','file_get_contents','substr','json_decode','array_key_exists','array_merge_recursive','array_diff_key','str_pad','strtotime','date','ceil','array_keys','current','count','strlen','strtolower','number_format','md5','in_array','is_array','time','nl2br','print_r','array_key_first','strpos','strrpos','str_replace','isset','empty','sizeof','trim','explode','implode'];
	$my_security_policy->php_modifiers = ['in_array','strlen','strstr','array_keys','count','COUNT','number_format'];#Smarty PHP Erweiteurngen
	$my_security_policy->streams = null;
	$my_security_policy->secure_dir = ['system/'];
	
	$C['Smarty']->enableSecurity($my_security_policy);
$_tpl = null;
if($D['SEO_URL'] == 'admin') {
	$_tpl = 'index.tpl';
	$D['R']['ModuleId'] = 'papp/admin';
	##include(__DIR__."/system/index.php");
}

if( isset($D['SEO_URL'] ) ) {

	if(!isset($D['_PAGE'])) { #Mit SEO Link darf kein Page übergeben werden
		$hURL = hash("crc32b", rtrim($D['SEO_URL'],'/'));
		
		#$F['PLATFORM']['W'][0]['ID'] = [ $D['PLATFORM_ID'] ];
		$f['LINK']['W'][0]['ID'] = $hURL;
		$C['CData']->get_object($D,$f);
		
		if(isset($D['LINK']['D'][ $hURL ]) && ($D['LINK']['D'][ $hURL ]['Active']??false) && strpos($D['LINK']['D'][ $hURL ]['ToURL'], 'D[_PAGE]') !== false ) {
			
			parse_str($D['LINK']['D'][ $hURL ]['ToURL'], $d);
	
			$D = array_merge_recursive($D,(array)$d['D']);
			if(isset($d['R'])) {
				$R = $D['R'] = array_merge_recursive((array)$d['R']);#ToDo: 
			}
		

			#$_path = "{$base}/system/{$D['R']['ModuleId']}/system/";
			#$_tpl .= (($_tpl)?'|':'')."{$_path}template/{$D['_PAGE']}.tpl";

			header( "HTTP/1.1 200 OK" );
		}
		else {#Link nicht vorhanden
			header( "HTTP/1.1 404 Not Found" );
			$D['_PAGE'] = 'error.404';
			$D['R']['Page'] = 'error.404';
			$D['R']['ModuleId'] = 'papp/phpapp';
		}
	}
	#print_r($D['PLATFORM']['D'][ $D['PLATFORM_ID'] ]['SETTING']);
	##include("view/shop/".(($D['PAGE'])?$D['PAGE']:'index').".php");
	##include(__DIR__."/system/index.php");

/*

	$_path = "{PROJECT_ROOT}/system/{$D['R']['ModuleId']}/system/";

	if( is_file("{$_path}{$D['_PAGE']}.php") ) {
		
		include("{$_path}{$D['_PAGE']}.php");  #Fügt ggf. Seiten Spezifische Ausgaben $F hinzu
	}

	$C['CData']->get_object($D,$F); 
*/

/*
$D['MODUL']['D'][ $Id ] = [
		'Id'			=> $Id,
		'ModulDir'		=> $moduleDir,
		'VendorName'	=> $vendor,
		'PackageName'	=> $package,
		'CacheDir'		=> "data_c/{$vendor}_{$package}/",
		'DataDir'		=> "data/{$vendor}_{$package}/",
	];
*/

	if(!isset($D['R']['ModuleId'])) {
		header( "HTTP/1.1 404 Not Found" );
		$D['_PAGE'] = 'error.404';
		$D['R']['Page'] = 'error.404';
		$D['R']['ModuleId'] = 'papp/phpapp';
	}
	
}

	#Template und php Verkettung
	
	$_tpl = getExtends($D['MODUL']['D'], $D['R']['ModuleId'], $D['_PAGE']);
	
	foreach($_tpl['php'] AS $kFile) {
		include_once ($kFile);
	}
	$C['CData']->get_object($D,$F); #Datenbank Abfrage
	#echo $_tpl['extends'];
	/*
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
    // Modul muss existieren
    if (!isset($modules[$activeModuleId])) {
        return [
            'extends' => '',
            'tpl'     => [],
            'php'     => []
        ];
    }

    $tplChain = [];
    $phpChain = [];

    // Hilfsfunktion: TPL existiert?
    $exists = function($moduleDir, $tpl) {
        return file_exists($moduleDir['ModulDir'] . '/system/template/' . $tpl);
    };

    // Hilfsfunktion: TPL → PHP Pfad
    $toPhp = function(string $tplPath): string {
        return str_replace(
            ['/system/template/', '.tpl'],
            ['/system/', '.php'],
            $tplPath
        );
    };

    // Hilfsfunktion: PHP existiert?
    $phpExists = function(string $phpPath): bool {
        return file_exists($phpPath);
    };

    // ---------------------------------------------------------
    // 1. Basis-Seite ohne "__" → nur root.tpl
    // ---------------------------------------------------------
    if (!str_contains($page, '__')) {

        $tpl = $modules[$activeModuleId]['ModulDir'] . '/system/template/' . $page . '.tpl';
        $php = $toPhp($tpl);

        return [
            'extends' => 'extends:' . $tpl,
            'tpl'     => [$tpl],
            'php'     => $phpExists($php) ? [$php] : []
        ];
    }

    // ---------------------------------------------------------
    // 2. base__sub zerlegen
    // ---------------------------------------------------------
    [$base, $sub] = explode('__', $page);

    // ---------------------------------------------------------
    // 3. konkrete Seite (nur aktives Modul) → unterstes Element
    // ---------------------------------------------------------
    $pageTpl = $page . '.tpl';
    if ($exists($modules[$activeModuleId], $pageTpl)) {
        $tplPath = $modules[$activeModuleId]['ModulDir'] . '/system/template/' . $pageTpl;
        $tplChain[] = $tplPath;

        $phpPath = $toPhp($tplPath);
        if ($phpExists($phpPath)) {
            $phpChain[] = $phpPath;
        }
    }

// 4. base__base.tpl (admin__admin.tpl) – ALLE Module sammeln
$baseBase = "{$base}__{$base}.tpl";

foreach ($modules as $moduleId => $moduleDir) {

    // hypothetischer TPL-Pfad
    $tplPath = $moduleDir['ModulDir'] . '/system/template/' . $baseBase;

    // korrekter PHP-Pfad über toPhp() – auch wenn TPL fehlt
    $phpPath = $toPhp($tplPath); // ergibt .../system/admin__admin.php

    // 1. Wenn TPL existiert → TPL + PHP (falls vorhanden)
    if ($exists($moduleDir, $baseBase)) {

        $tplChain[] = $tplPath;

        if ($phpExists($phpPath)) {
            $phpChain[] = $phpPath;
        }

        continue;
    }

    // 2. Wenn TPL fehlt, aber PHP existiert → nur PHP aufnehmen
    if ($phpExists($phpPath)) {
        $phpChain[] = $phpPath;
    }
}



    // ---------------------------------------------------------
    // 5. Rekursiv: *__base.tpl nach oben suchen
    //    – base__base.tpl dabei überspringen
    //    – z.B. index__admin.tpl
    // ---------------------------------------------------------
    $currentBase = $base;

    while (true) {
        $candidates = [];

        foreach ($modules as $moduleId => $moduleDir) {
            foreach (glob($moduleDir['ModulDir'] . "/system/template/*__{$currentBase}.tpl") as $file) {

                $name = basename($file);

                // base__base.tpl überspringen (haben wir schon)
                if ($name === $baseBase) {
                    continue;
                }

                [$newBase] = explode('__', $name, 2);

                $candidates[] = [
                    'tpl'     => $moduleDir['ModulDir'] . '/system/template/' . $name,
                    'newBase' => $newBase
                ];
            }
        }

        if (empty($candidates)) {
            break;
        }

        // bevorzugt eines, bei dem newBase != currentBase (z.B. index__admin)
        $chosen = null;
        foreach ($candidates as $cand) {
            if ($cand['newBase'] !== $currentBase) {
                $chosen = $cand;
                break;
            }
        }
        // falls keins gefunden → erstes nehmen
        if ($chosen === null) {
            $chosen = $candidates[0];
        }

        $tplChain[] = $chosen['tpl'];

        $phpPath = $toPhp($chosen['tpl']);
        if ($phpExists($phpPath)) {
            $phpChain[] = $phpPath;
        }

        // wenn wir oben angekommen sind (z.B. index__index), abbrechen
        if ($chosen['newBase'] === $currentBase) {
            break;
        }

        $currentBase = $chosen['newBase'];
    }

    // ---------------------------------------------------------
    // 6. root.tpl = currentBase.tpl (oberstes Template, z.B. index.tpl)
    // ---------------------------------------------------------
    $rootTplName = $currentBase . '.tpl';
    foreach ($modules as $moduleId => $moduleDir) {
        if ($exists($moduleDir, $rootTplName)) {

            $tplPath = $moduleDir['ModulDir'] . '/system/template/' . $rootTplName;
            $tplChain[] = $tplPath;

            $phpPath = $toPhp($tplPath);
            if ($phpExists($phpPath)) {
                $phpChain[] = $phpPath;
            }

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


