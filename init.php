<?php
#include('vendor/autoload.php');
include_once(__dir__.'/system/core/CFile.php'); #ToDo: Über autoloader laden

include_once(__dir__.'/system/core/Link.php'); #ToDo: Über autoloader laden

require_once('system/vendor/phploader/cdata/lib/CData.php'); #ToDo: Über autoloader laden



$C['CFile'] = new CFile();
if(!is_dir(PROJECT_ROOT.'data_c/papp~phpapp/')) {
	$C['CFile']->mkdir(PROJECT_ROOT.'data_c/papp~phpapp/');
}
$C['CCache'] = new \papp\CCache([ 'DB' => ['FILENAME' => PROJECT_ROOT.'data_c/papp~phpapp/cache.cache' ] ]);



// 1. Module scannen und Metadaten sammeln
foreach (glob(PROJECT_ROOT . '/system/vendor/' . '/*/*', GLOB_ONLYDIR) as $moduleDir) {
	$path = realpath($moduleDir); // Pfad zum Projektordner
	$parts = explode(DIRECTORY_SEPARATOR, $path);
	$vendor = $parts[count($parts)-2]; // xx
	$package = $parts[count($parts)-1]; // yy
	$Id = "{$vendor}/{$package}";
	
	$D['MODUL']['D'][ $Id ] = [
		'Id'			=> $Id,
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

#$C['CData'] = new \papp\CData( [ 'DB' => ['FILENAME' => __DIR__.'/../../../../data/data.db' ] ] );
$C['papp~phpapp']['CData'] = new \papp\CData( [ 'DB' => ['FILENAME' => PROJECT_ROOT.'data/papp~phpapp/data.db', 'FILENAME_C' => PROJECT_ROOT.'data_c/papp~phpapp/data.db' ] ] );

$C['papp~phpapp']['Link'] = new \papp\phpapp\Link( $C['papp~phpapp']['CData'] );

#DB-----------------

$Pattern = [];
 
$C['papp~phpapp']['CData']->registerPattern([ 
	'SETTING'	=> [
			'Active'		=> ['Type' => 'checkbox'],
			'ParentId'		=> ['Type' => 'id', 'ForeignKey' => 1],
			'Value'			=> ['Type' => 'text'],
		],
	'LINK'		=> [
			'Active'		=> ['Type' => 'checkbox'],
			'FromURL'		=> ['Type' => 'text'],
			'ToURL'			=> ['Type' => 'text'],
			'ModuleId'		=> ['Type' => 'id', 'ForeignKey' => 1],
		],
	'FILE' 		=> [
			'Name'			=> ['Type' => 'text'],
			'Size'			=> ['Type' => 'number'],
			'Extension'		=> ['Type' => 'text'],
		],
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


$C['papp~phpapp']['CData']->registerPattern($Pattern);

/*
$frame = new papp\framework($ModulId);
$frame->getLink($F);
$frame->setLink($active,$FromURL,$ToURL);
*/


// 2. Phase: alle init.php laden
foreach ($D['MODUL']['D'] as $moduleDir => $info) {
	if('papp/phpapp' != $moduleDir) {
		$D['MY'] = $info;
		
		$init = $info['ModulDir'] . '/init.php';
		if (is_file($init)) {
			require_once $init;
		}
	}
}