<?php
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

define('SCRIPT_NAME',rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/');

require_once('system/vendor/autoload.php');

include_once(__dir__.'/system/core/Composer.php'); #ToDo: Über autoloader laden # identisch mit setup/Composer.php (Setup kann den Core bei der Installation noch nicht laden), beide synchron halten
include_once(__dir__.'/system/core/Packagist.php'); #Todo: Über autoloader laden 

$D = [];
# Alle Module und Core Klassen werden in $C gespeichert, damit sie von allen Modulen genutzt werden können.
$C = new fremeo\core\Container(); # Klassen Instanz Array, für alle Module und Core Klassen
# Module Klassen Instanzen werden in $C['ModulId']['KlassenName'] gespeichert
$C->set('Security', fn () => new \fremeo\core\Security());
$C->set('Packagist', fn () => new Packagist() );
$C->set('Composer', fn () => new Composer(__DIR__.'/system/core/composer.phar', PROJECT_ROOT) );
$C->set('Filesystem', fn () => new Filesystem() );
$C->set('ImageManager', fn () => new ImageManager( Driver::class ) );
$C->set('Finder', fn () => new Finder() );
$C->set('Smarty', fn () => new Smarty() );
$C->set('Setting', fn () => new \fremeo\core\Setting($C, $D) );

$C->set('CCache', fn () => new \phploader\CCache([ 'DB' => ['FILENAME' => PROJECT_ROOT.'data_c/fremeo~core/cache.cache' ] ]), 'fremeo/core');


#DB-----------------
#Todo: weil die untere Schleife core nicht priorisiert lädt, muss core hier zuerst initialisiert werden.
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
foreach (glob(PROJECT_ROOT . '/system/vendor/' . '/*/*', GLOB_ONLYDIR) as $moduleDir) {
	$path = realpath($moduleDir); // Pfad zum Projektordner
	$parts = explode(DIRECTORY_SEPARATOR, $path);
	$vendor = $parts[count($parts)-2]; // xx
	$package = $parts[count($parts)-1]; // yy
	$Id = "{$vendor}/{$package}";
	
	# Typ aus der composer.json des Moduls (core | template | module)
	$type = (string)(json_decode((string)@file_get_contents("{$moduleDir}/composer.json"), true)['type'] ?? 'library');
	
	$D['MODULE']['D'][ $Id ]['INFO'] = [ #Todo: in ['INFO'] ändern, damit Module Array für anhänge anderer Module daten möglich ist.
		'Id'			=> $Id,
		'Type'			=> $type,
		'ModulDir'		=> $moduleDir,
		'VendorName'	=> $vendor,
		'PackageName'	=> $package,
		'CacheDir'		=> "data_c/{$vendor}~{$package}/",
		'DataDir'		=> "data/{$vendor}~{$package}/",
	];
}


#Todo: inaktive Module nicht laden
# 2. Phase: alle init.php laden 
foreach ($D['MODULE']['D'] as $moduleDir => $info) {

	# Überspringe libery und core type
	if ( in_array($info['INFO']['Type'], ['library', 'core']) === false) {
	
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
		$init = $info['INFO']['ModulDir'] . '/cli.php';
		if (is_file($init)) {
			require_once $init;
		}
	}
} else {
	# HTML Ausgabe, wenn nicht CLI Modus
	require_once "system/vendor/fremeo/core/start.php";
}
