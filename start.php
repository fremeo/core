<?php 
/*
if (session_status() === PHP_SESSION_NONE) {
    session_name('fremeo');
    session_start();
}
$D['SESSION'] = $_SESSION;
*/
	$C['Smarty']->addTemplateDir(__DIR__ . '/system/template/', 'core');
    $C['Smarty']->setCompileDir("{$D['MODULE']['D']['fremeo/core']['INFO']['CacheDir']}template_c/");
	$C['Smarty']->error_reporting = E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED;
    
  
    #$C['Smarty']->setConfigDir("{$D['MODULE']['D']['fremeo/core']['INFO']['DataDir']}smarty-config/"); #ToDo aus data/fremeo~core/smarty-config auslesen

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
		$f['LINK']['W'][0]['Active'] = 1; #ToDo: wird in cData nicht erkannt, wieso?

		foreach((array)($D['MODULE']['D'] ?? []) AS $kMOD => $MOD) {

            # keine libery
            if($MOD['INFO']['Type'] === 'library' || !$MOD['Active']) {
                continue;
            }
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


if (isset($R['ModuleId']) && (!isset($D['MODULE']['D'][$R['ModuleId']]) || !$D['MODULE']['D'][$R['ModuleId']]['Active'])) {
	http_response_code(404);
	$R['Page'] = 'error.404';
	$R['ModuleId'] = 'fremeo/core';
}

if($D['R']['ModuleId']) {
	
    #Todo: Prüfen ob es so richtig ist, dass link für fremeo/core gesetzt wird, oder ob es dynamisch für das aktive Modul gesetzt werden sollte.
    $_moduleId = $D['R']['ModuleId'];
    #$C->set('Link', fn () => new \fremeo\core\Link($C[$_moduleId]['CData'], array_values($C->getAll('CData'))), 'fremeo/core');
    $C->set('Link', fn () => new \fremeo\core\Link($C[$_moduleId]['CData'], array_values($C->getAll('CData'))), $_moduleId);
}

	#Template und php Verkettung
	$_tpl = $C['Template']->getExtends($D['MODULE']['D'], $D['R']['ModuleId'], $D['R']['Page']);
	$_theme = $C['Template']->resolveTheme($_tpl['tpl']);
	if (($_theme['Branch'] ?? '') === 'frontend' && !array_filter($D['MODULE']['D'], static fn ($module) => $module['INFO']['Type'] === 'template' && $module['Active'])) {
		http_response_code(503);
		echo 'Das Frontend ist deaktiviert. Die Administration bleibt erreichbar.';
		return;
	}


	foreach($_tpl['php'] AS $kFile) {
		include_once ($kFile);
	}
	$C['fremeo/core']['CData']->get_object($D,$F); #Datenbank Abfrage
	$R['Theme'] = $C['Template']->publishTheme(
		$C['Template']->resolveTheme($_tpl['tpl']),
		PROJECT_ROOT . 'data_c/public/theme',
		SCRIPT_NAME
	);
	
	/* # Diesen Code nicht löschen, dient zur Debugging-Ausgabe der Template-Kette
	echo "<pre>";
	print_R($_tpl['tpl']);
	print_r($_tpl['php']);
	echo "</pre>";
	*/
	$C['Smarty']->assign('D',$D);
	$C['Smarty']->display($_tpl['extends']."|include/input.tpl");
