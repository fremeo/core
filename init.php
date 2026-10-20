<?php
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

define('SCRIPT_NAME',rtrim(dirname($_SERVER['SCRIPT_NAME']), '/').'/');

require_once('system/vendor/autoload.php');

include_once(__dir__.'/system/core/Composer.php'); #ToDo: Über autoloader laden # identisch mit setup/Composer.php (Setup kann den Core bei der Installation noch nicht laden), beide synchron halten
include_once(__dir__.'/system/core/Packagist.php'); #Todo: Über autoloader laden 


$C = new fremeo\core\Container(); # Klassen Instanz Array, für alle Module und Core Klassen
# Module Klassen Instanzen werden in $C['ModulId']['KlassenName'] gespeichert
$C->set('Security', fn () => new \fremeo\core\Security());
$C->set('Packagist', fn () => new Packagist() );
$C->set('Composer', fn () => new Composer(__DIR__.'/system/core/composer.phar', PROJECT_ROOT) );
$C->set('Filesystem', fn () => new Filesystem() );
$C->set('ImageManager', fn () => new ImageManager( Driver::class ) );
$C->set('Finder', fn () => new Finder() );
$C->set('Smarty', fn () => new Smarty() );

$C->set('CCache', fn () => new \phploader\CCache([ 'DB' => ['FILENAME' => PROJECT_ROOT.'data_c/fremeo~core/cache.cache' ] ]), 'fremeo/core');



$C['Security']->SecurityActive = false; #ToDo: aktivierung von Security über DB einstellung
if ($C['Security']->SecurityActive) {
	$SD = $_REQUEST['SD'] ?? []; // security Data array	
	$D = $C['Security']->resolveSecureData($SD);
} else {
	$D = $_REQUEST['D'] ?? []; //Data Array
}
$R = $_REQUEST['R'] ?? []; //Request Array

$D['R'] = &$R;
$D['C'] = &$C; //Klassen Instanz Array
$D['SESSION'] = null; 



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

#DB-----------------


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
	'SETTING_KATEGORIE'	=> [
		#'Active'		=> ['Type' => 'checkbox'],
		#'Name'			=> ['Type' => 'text'],
		'Path'			=> ['Type' => 'text'], # Pfad, z.B. "Frontend/Navigation/Menu1" (sind Ids von Sprach Variablen) (für die Anzeige in der Admin-Oberfläche, kann auch leer sein)
	],
	'SETTING'	=> [ #ID = Frontend_Navigation_Menu1
			#'Active'		=> ['Type' => 'checkbox'],
			'SettingKategorieId'	=> ['Type' => 'id', 'ForeignKey' => 1],
			'Value'			=> ['Type' => 'text'],
			'Required'		=> ['Type' => 'checkbox'], #Pflichtfeld
			'StandardValue'	=> ['Type' => 'text'],
			'Option'		=> ['Type' => 'text'], #Optionen für Select, Radio, Checkbox (JSON-Array)
			#'RequiredSetting'=> ['Type' => 'text'], #array Ids, von Einstellungen diese benötigt werden, damit diese Einstellung angezeigt wird. (JSON-Array)
			'InputType'		=> ['Type' => 'text'], # z.B. "text", "number", "checkbox", "select", "radio", "textarea", "file", "json", "image"
			#'Path'			=> ['Type' => 'text'], # Pfad, z.B. "Frontend/Navigation/Menu1" (sind Ids von Sprach Variablen) (für die Anzeige in der Admin-Oberfläche, kann auch leer sein)
	],	#Todo: abhängige Einstellungen; Option auswahl
];
 
#?? $LANGUAGE[D][DE][CONTEXT][D][id][Value]
/*
$globalPattern['LANGUAGE']['D']['CONTEXT'] = [
	'Active'		=> ['Type' => 'checkbox'],
	'Value'			=> ['Type' => 'text'],
];
*/
/*
$C['fremeo/core']['CData']->registerPattern([ 

	'USER'		=> [
			'Active'		=> ['Type' => 'checkbox'],
			'Name'			=> ['Type' => 'text'],
			'Mail'			=> ['Type' => 'text'],
			'Password'		=> ['Type' => 'text'],
		],
	
 ]);
 */
$Pattern['USER'] = [
			'Active'		=> ['Type' => 'checkbox'],
			'Name'			=> ['Type' => 'text'],
			'Mail'			=> ['Type' => 'text'],
			'Password'		=> ['Type' => 'text'],
		];

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


#Todo: weil die untere Schleife core nicht priorisiert lädt, muss core hier zuerst initialisiert werden.
$C->set('CData', new \phploader\CData( [ 'DB' => ['FILENAME' => PROJECT_ROOT.'data/fremeo~core/data.db', 'FILENAME_C' => PROJECT_ROOT.'data_c/fremeo~core/data.db' ] ] ), 'fremeo/core');
$C['fremeo/core']['CData']->registerPattern($globalPattern);
$C['fremeo/core']['CData']->registerPattern($Pattern);
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
