<?php 



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
	$C['Smarty']->addTemplateDir(__DIR__ . '/system/template/', 'framework');
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
		
		if(isset($D['LINK']['D'][ $hURL ]) && $D['LINK']['D'][ $hURL ]['Active'] && strpos($D['LINK']['D'][ $hURL ]['ToURL'], 'D[_PAGE]') !== false ) {
			
			parse_str($D['LINK']['D'][ $hURL ]['ToURL'], $d);
	
			$D = array_merge_recursive($D,(array)$d['D']);
			if(isset($d['R'])) {
				$D['R'] = array_merge_recursive((array)$d['R']);#ToDo: 
			}
		

			#$_path = "{$base}/system/{$D['R']['ModuleId']}/system/";
			#$_tpl .= (($_tpl)?'|':'')."{$_path}template/{$D['_PAGE']}.tpl";

			header( "HTTP/1.1 200 OK" );
		}
		else {#Link nicht vorhanden
			header( "HTTP/1.1 404 Not Found" );
			$D['_PAGE'] = 'error.404';
			$D['R']['Page'] = 'error.404';
			$D['R']['ModuleId'] = 'papp/framework';
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
		$D['R']['ModuleId'] = 'papp/framework';
	}
	
}

	#Template und php Verkettung
	$_tpl = getExtends($D['MODUL']['D'], $D['R']['ModuleId'], $D['_PAGE']);
	
	foreach($_tpl['php'] AS $kFile) {
		include_once ($kFile);
	}
	$C['CData']->get_object($D,$F); #Datenbank Abfrage
	#echo $_tpl['extends'];
	$C['Smarty']->assign('D',$D);
	$C['Smarty']->display($_tpl['extends']."|include/input.tpl");


#-------------
function getExtends(array $modules, string $activeModuleId, string $page): array
{
    $tplResult = [];
    $phpResult = [];

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
    // 1. Basis-Seite ohne "__"
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
    // 3. Rekursiv: *__base.tpl suchen
    // ---------------------------------------------------------
    $currentBase = $base;

    while (true) {
        $found = false;

        foreach ($modules as $moduleId => $moduleDir) {
            foreach (glob($moduleDir['ModulDir'] . "/system/template/*__{$currentBase}.tpl") as $file) {

                $name = basename($file);
                $tplPath = $moduleDir['ModulDir'] . '/system/template/' . $name;

                // TPL hinzufügen
                $tplResult[] = $tplPath;

                // PHP hinzufügen, aber nur wenn es existiert
                $phpPath = $toPhp($tplPath);
                if ($phpExists($phpPath)) {
                    $phpResult[] = $phpPath;
                }

                [$newBase] = explode('__', $name, 2);

                if ($newBase !== $currentBase) {
                    $currentBase = $newBase;
                    $found = true;
                    break 2;
                }
            }
        }

        if (!$found) break;
    }

    // ---------------------------------------------------------
    // 4. base__base.tpl suchen
    // ---------------------------------------------------------
    $baseBase = "{$base}__{$base}.tpl";
    foreach ($modules as $moduleId => $moduleDir) {
        if ($exists($moduleDir, $baseBase)) {

            $tplPath = $moduleDir['ModulDir'] . '/system/template/' . $baseBase;
            $tplResult[] = $tplPath;

            $phpPath = $toPhp($tplPath);
            if ($phpExists($phpPath)) {
                $phpResult[] = $phpPath;
            }
        }
    }

    // ---------------------------------------------------------
    // 5. sub__sub.tpl suchen
    // ---------------------------------------------------------
    $subSub = "{$sub}__{$sub}.tpl";
    foreach ($modules as $moduleId => $moduleDir) {
        if ($exists($moduleDir, $subSub)) {

            $tplPath = $moduleDir['ModulDir'] . '/system/template/' . $subSub;
            $tplResult[] = $tplPath;

            $phpPath = $toPhp($tplPath);
            if ($phpExists($phpPath)) {
                $phpResult[] = $phpPath;
            }
        }
    }

    // ---------------------------------------------------------
    // 6. konkrete Seite → nur aktives Modul
    // ---------------------------------------------------------
    $pageTpl = $page . '.tpl';
    if ($exists($modules[$activeModuleId], $pageTpl)) {

        $tplPath = $modules[$activeModuleId]['ModulDir'] . '/system/template/' . $pageTpl;
        $tplResult[] = $tplPath;

        $phpPath = $toPhp($tplPath);
        if ($phpExists($phpPath)) {
            $phpResult[] = $phpPath;
        }
    }

    // ---------------------------------------------------------
    // 7. root.tpl = currentBase.tpl
    // ---------------------------------------------------------
    $rootTpl = $currentBase . '.tpl';
    foreach ($modules as $moduleId => $moduleDir) {
        if ($exists($moduleDir, $rootTpl)) {

            $tplPath = $moduleDir['ModulDir'] . '/system/template/' . $rootTpl;

            array_unshift($tplResult, $tplPath);

            $phpPath = $toPhp($tplPath);
            if ($phpExists($phpPath)) {
                array_unshift($phpResult, $phpPath);
            }

            break;
        }
    }

    // Duplikate entfernen
    $tplResult = array_values(array_unique($tplResult));
    $phpResult = array_values(array_unique($phpResult));

    return [
        'extends' => 'extends:' . implode('|', $tplResult),
        'tpl'     => $tplResult,
        'php'     => $phpResult
    ];
}

