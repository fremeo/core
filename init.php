<?php
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

define('SCRIPT_NAME',rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/');

require_once('system/vendor/autoload.php');

include_once(__dir__.'/system/core/Packagist.php'); #Todo: Über autoloader laden 

$D = [];
# Alle Module und Core Klassen werden in $C gespeichert, damit sie von allen Modulen genutzt werden können.
$C = new fremeo\core\Container(); # Klassen Instanz Array, für alle Module und Core Klassen
# Module Klassen Instanzen werden in $C['ModulId']['KlassenName'] gespeichert
$C->set('Security', fn () => new \fremeo\core\Security());
$C->set('Packagist', fn () => new Packagist() );
$C->set('Composer', fn () => new \fremeo\core\PackageManager(PROJECT_ROOT) );
$C->set('Filesystem', fn () => new Filesystem() );
$C->set('ImageManager', fn () => new ImageManager( Driver::class ) );
$C->set('Finder', fn () => new Finder() );
$C->set('Smarty', fn () => (new Smarty())->addPluginsDir(__DIR__ . '/system/core/smarty_plugin'));
$C->set('Template', function () use ($C, &$D) { return new \fremeo\core\Template($C, $D); });
$C->set('Setting', function () use ($C, &$D) { return new \fremeo\core\Setting($C, $D); });
$C->set('Builder', fn () => new \fremeo\core\Builder($C['fremeo/core']['CData']));

$C->set('CCache', fn () => new \phploader\CCache([ 'DB' => ['FILENAME' => PROJECT_ROOT.'data_c/fremeo~core/cache.cache' ] ]), 'fremeo/core');


#DB-----------------
# Core-Datenbank vor dem Modulscan fuer Security-Einstellungen bereitstellen.
$C->set('CData', new \phploader\CData( [ 'DB' => ['FILENAME' => PROJECT_ROOT.'data/fremeo~core/data.db', 'FILENAME_C' => PROJECT_ROOT.'data_c/fremeo~core/data.db' ] ] ), 'fremeo/core');
if(is_file(__dir__ . '/system/default/default.global.pattern.php') ) {
	$globalPattern = include __dir__ . '/system/default/default.global.pattern.php';
	$C['fremeo/core']['CData']->registerPattern($globalPattern);
}
if(is_file(__dir__ . '/system/default/default.pattern.php') ) {
	$Pattern = include __dir__ . '/system/default/default.pattern.php';
	$C['fremeo/core']['CData']->registerPattern($Pattern);
}

 #Todo: Problem: wenn unter einstellugnen diese Einstellung geändert wird, dann wird dies nach zweitem reload erst wirksam.
$C['Security']->SecurityActive = $C['Setting']->get('fremeo/core','security_enable_hash_input_names')?true:false;
if ($C['Security']->SecurityActive) {
	$SD = $_REQUEST['SD'] ?? []; // security Data array	
	$D = $C['Security']->resolveSecureData($SD);

} else {
	$D = $_REQUEST['D'] ?? []; //Data Array
}
$R = $_REQUEST['R'] ?? []; //Request Array

$D['R'] = &$R;
$D['C'] = &$C; //Klassen Instanz Array
if (session_status() === PHP_SESSION_NONE) {
    session_name('fremeo');
    session_start();
}
$D['SESSION'] = $_SESSION;



// 1. Module scannen und Metadaten sammeln # Todo: Cachen
$modules = [];
foreach (glob(PROJECT_ROOT . '/system/vendor/' . '/*/*', GLOB_ONLYDIR) as $moduleDir) {
	$path = realpath($moduleDir); // Pfad zum Projektordner
	$parts = explode(DIRECTORY_SEPARATOR, $path);
	$vendor = $parts[count($parts)-2]; // xx
	$package = $parts[count($parts)-1]; // yy
	$Id = "{$vendor}/{$package}";
	
	# Typ aus der composer.json des Moduls (core | template | module)
	$type = (string)(json_decode((string)@file_get_contents("{$moduleDir}/composer.json"), true)['type'] ?? 'library');
	
	$modules[$Id] = ['INFO' => [
		'Id'			=> $Id,
		'Type'			=> $type,
		'ModulDir'		=> $moduleDir,
		'VendorName'	=> $vendor,
		'PackageName'	=> $package,
		'CacheDir'		=> "data_c/{$vendor}~{$package}/",
		'DataDir'		=> "data/{$vendor}~{$package}/",
	]] + ($D['MODULE']['D'][$Id] ?? []);
}

# Formularfelder duerfen die Lade- und Anzeigereihenfolge nicht bestimmen.
$moduleTypes = ['core' => 0, 'template' => 1, 'module' => 2];
uasort($modules, static fn ($a, $b) => ($moduleTypes[$a['INFO']['Type']] ?? 3) <=> ($moduleTypes[$b['INFO']['Type']] ?? 3));
$D['MODULE']['D'] = $modules;
unset($modules, $moduleTypes);

$_activeFilter['MODULE'] = [];
$C['fremeo/core']['CData']->get_object($_activeModules, $_activeFilter);
foreach ($D['MODULE']['D'] as $_id => &$_module) {
	$_module['Active'] = $_module['INFO']['Type'] === 'core' ? 1 : (int)($_activeModules['MODULE']['D'][$_id]['Active'] ?? 1);
}
unset($_module, $_activeModules, $_activeFilter);
$_themeModules = $C['Template']->getModuleInheritance();
foreach ($D['MODULE']['D'] as $_id => &$_module) {
	if (in_array($_module['INFO']['Type'], ['template', 'module'], true) && !isset($_themeModules[$_id])) $_module['Active'] = 0;
}
unset($_module, $_themeModules);

# 2. Phase: alle init.php laden 
foreach ($D['MODULE']['D'] as $moduleDir => $info) {

	# Überspringe libery und core type
	if ($info['Active'] && in_array($info['INFO']['Type'], ['library', 'core']) === false) {
	
		$_modulePath = str_replace( '/', '~', $moduleDir);
		$C->set('CData', new \phploader\CData( [ 'DB' => ['FILENAME' => PROJECT_ROOT.'data/'.$_modulePath.'/data.db', 'FILENAME_C' => PROJECT_ROOT.'data_c/'.$_modulePath.'/data.db' ] ] ), $moduleDir);
		$C[$moduleDir]['CData']->registerPattern($globalPattern); # Globales Pattern für alle Module registrieren

		$init = $info['INFO']['ModulDir'] . '/init.php';
		require_once $init;

		$C->set('File', fn () => new \fremeo\core\File($C, $moduleDir), $moduleDir);
	}
}
$C['fremeo/core']['CData']->registerPattern($Pattern);

# 3. Phase: alle cli.php laden, wenn CLI Modus
if (PHP_SAPI === 'cli') {
	foreach ($D['MODULE']['D'] as $moduleDir => $info) {
		if (!$info['Active']) continue;
		$init = $info['INFO']['ModulDir'] . '/cli.php';
		if (is_file($init)) {
			require_once $init;
		}
	}
} else {
	# HTML Ausgabe, wenn nicht CLI Modus
	require_once "system/vendor/fremeo/core/start.php";
}
