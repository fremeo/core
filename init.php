<?php
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

define('SCRIPT_NAME',rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/');


require_once('system/vendor/autoload.php');

require_once(__DIR__ . '/system/core/Security.php'); # ToDo: Über Autoload muss geladen werden
$D = $_REQUEST['D'] ?? []; //Data Array
$SD = $_REQUEST['SD'] ?? null; // security Data array
$R = $_REQUEST['R'] ?? []; //Request Array
if (!is_array($R)) {
	$R = [];
}

$C = null;
$C['Security'] = new \fremeo\core\Security();
if (is_array($SD) && !empty($SD)) {
	$D = $C['Security']->resolveSecureData($SD, $D);
	$R = array_replace_recursive($R, $D['R'] ?? []);
}

$D['R'] = &$R;
$D['C'] = &$C; //Klassen Instanz Array
$D['SESSION'] = null; 


include_once(__dir__.'/system/core/Packagist.php'); #ToDo: Über autoloader laden
include_once(__dir__.'/system/core/Composer.php'); # identisch mit setup/Composer.php (Setup kann den Core bei der Installation noch nicht laden), beide synchron halten

$C['Packagist'] = new Packagist();

$C['Composer'] = new Composer(__DIR__.'/system/core/composer.phar', PROJECT_ROOT); # PHP-CLI wird automatisch gesucht, ohne proc_open läuft Composer im selben Prozess

$C['Filesystem'] = new Filesystem();
$C['ImageManager'] = new ImageManager( Driver::class );
$C['Finder'] = new Finder();





#require_once('system/vendor/phploader/cdata/lib/CData.php'); #ToDo: Über autoloader laden
#require_once('system/vendor/phploader/cdata/lib/CCache.php'); #ToDo: Über autoloader laden



$C['Filesystem']->mkdir(PROJECT_ROOT.'data_c/fremeo~core/'); # ToDo: ins CCAche verschieben
$C['CCache'] = new \phploader\CCache([ 'DB' => ['FILENAME' => PROJECT_ROOT.'data_c/fremeo~core/cache.cache' ] ]); # Gemeinsamer Cache für alle Module, die CCache nutzen.



// 1. Module scannen und Metadaten sammeln # Todo: Cachen
foreach (glob(PROJECT_ROOT . '/system/vendor/' . '/*/*', GLOB_ONLYDIR) as $moduleDir) {
	$path = realpath($moduleDir); // Pfad zum Projektordner
	$parts = explode(DIRECTORY_SEPARATOR, $path);
	$vendor = $parts[count($parts)-2]; // xx
	$package = $parts[count($parts)-1]; // yy
	$Id = "{$vendor}/{$package}";
	
	# Typ aus der composer.json des Moduls (core | template | module)
	$type = (string)(json_decode((string)@file_get_contents("{$moduleDir}/composer.json"), true)['type'] ?? 'module');
	
	$D['MODULE']['D'][ $Id ] = [
		'Id'			=> $Id,
		'Type'			=> $type,
		'ModulDir'		=> $moduleDir,
		'VendorName'	=> $vendor,
		'PackageName'	=> $package,
		'CacheDir'		=> "data_c/{$vendor}~{$package}/",
		'DataDir'		=> "data/{$vendor}~{$package}/",
	];
}


##session_start();
#$D['SESSION'] = $_SESSION['D'];

$C['Smarty'] = new Smarty();

#$C['CData'] = new \phploader\CData( [ 'DB' => ['FILENAME' => __DIR__.'/../../../../data/data.db' ] ] );
$C['fremeo/core']['CData'] = new \phploader\CData( [ 'DB' => ['FILENAME' => PROJECT_ROOT.'data/fremeo~core/data.db', 'FILENAME_C' => PROJECT_ROOT.'data_c/fremeo~core/data.db' ] ] );

#Globales Link Objekt für die gesamte Anwendung

#$C['fremeo/core']['Link'] = new \fremeo\core\Link( $C['fremeo/core']['CData'] );

#DB-----------------

$Pattern = [];

$globalPattern = [
	'LINK'		=> [
		'Active'		=> ['Type' => 'checkbox'],
		'FromURL'		=> ['Type' => 'text'],
		'ToURL'			=> ['Type' => 'text'],
		#'ModuleId'		=> ['Type' => 'id', 'ForeignKey' => 1],
	],
	'FILE' 		=> [
		'Name'			=> ['Type' => 'text'],
		'Size'			=> ['Type' => 'number'],
		'Extension'		=> ['Type' => 'text'],
	],
	'MIGRATION'	=> [
		'File'			=> ['Type' => 'text'],
		'Timestamp'		=> ['Type' => 'number'],
	],
];
 
$C['fremeo/core']['CData']->registerPattern([ 
	'SETTING'	=> [
			'Active'		=> ['Type' => 'checkbox'],
			'ParentId'		=> ['Type' => 'id', 'ForeignKey' => 1],
			'Value'			=> ['Type' => 'text'],
		],
	/*'LINK'		=> [
			'Active'		=> ['Type' => 'checkbox'],
			'FromURL'		=> ['Type' => 'text'],
			'ToURL'			=> ['Type' => 'text'],
			'ModuleId'		=> ['Type' => 'id', 'ForeignKey' => 1],
		],*/
	/*'FILE' 		=> [
			'Name'			=> ['Type' => 'text'],
			'Size'			=> ['Type' => 'number'],
			'Extension'		=> ['Type' => 'text'],
		],*/
	'USER'		=> [
			'Active'		=> ['Type' => 'checkbox'],
			'Name'			=> ['Type' => 'text'],
			'Mail'			=> ['Type' => 'text'],
			'Password'		=> ['Type' => 'text'],
		],
	
 ]);
 
$Pattern['USER']['D']['GROUP'] = [
			'Active'		=> ['Type' => 'checkbox'],
];
 
$Pattern['USER_GROUP'] = [ #Rechteverwaltung
			'Active'		=> ['Type' => 'checkbox'],
			'Name'			=> ['Type' => 'text'],
];
		
$Pattern['USER_GROUP']['D']['PAGE'] = [#Rechteverwaltung je Seite
			'Active'		=> ['Type' => 'checkbox'],
];
 
 #ToDo: Prüfen ob Accounts wirklich in phpapp angelegt werden müssen. 
$Pattern['USER']['D']['ACCOUNT'] = [
			'Active'		=> ['Type' => 'checkbox'],
];
$Pattern['ACCOUNT'] = [ #Kunden Accounts
			'Active'		=> ['Type' => 'checkbox'],
			'Name'			=> ['Type' => 'text'], #??
];


$C['fremeo/core']['CData']->registerPattern($Pattern);

/*
$frame = new fremeo\framework($ModulId);
$frame->getLink($F);
$frame->setLink($active,$FromURL,$ToURL);
*/

#Todo: inaktive Module nicht laden
# 2. Phase: alle init.php laden 
foreach ($D['MODULE']['D'] as $moduleDir => $info) {

	if('fremeo/core' != $moduleDir) { 
		$init = $info['ModulDir'] . '/init.php';
		if (is_file($init)) {
			require_once $init;
		}
	}
	# Datenbank für je Modul registrieren, falls das Modul CData nutzt.
	if(isset($C[$moduleDir]['CData']) ) {
		$C[$moduleDir]['CData']->registerPattern($globalPattern);
		$C[$moduleDir]['File'] = new \fremeo\core\File($C, $moduleDir); #Datei Upload/Löschen, gebunden an das eigene Modul: $C['fremeo/blog']['File']->add($_FILES['x']);
	}
}

# 3. Phase: alle cli.php laden, wenn CLI Modus
if (PHP_SAPI === 'cli') {
	foreach ($D['MODULE']['D'] as $moduleDir => $info) {
		$init = $info['ModulDir'] . '/cli.php';
		if (is_file($init)) {
			require_once $init;
		}
	}
} else {
	# HTML Ausgabe, wenn nicht CLI Modus
	require_once "system/vendor/fremeo/core/start.php";
}
