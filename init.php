<?php
include('system/autoload.php');
include('system/papp/framework/core/CFile.php');
include('system/papp/framework/core/Packagist.php');
include('system/papp/framework/core/ComposerManager.php');

require_once('system/papp/cdata/lib/CData.php');

$C['CFile'] = new CFile();
$C['CCache'] = new papp\CCache([ 'DB' => ['FILENAME' => PROJECT_ROOT.'data_c/papp_framework/cache.cache' ] ]);

$C['Packagist'] = new Packagist();
$C['ComposerManager'] = new ComposerManager(__DIR__.'/core/composer.phar', 'data_c/papp_framework/log.txt');

// 1. Module scannen und Metadaten sammeln
foreach (glob(PROJECT_ROOT . '/system' . '/*/*', GLOB_ONLYDIR) as $moduleDir) {
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
		'CacheDir'		=> "data_c/{$vendor}_{$package}/",
		'DataDir'		=> "data/{$vendor}_{$package}/",
	];
}


session_start();
$D['SESSION'] = $_SESSION['D'];

$C['Smarty'] = new Smarty();
$C['CData'] = new papp\CData( [ 'DB' => ['FILENAME' => __DIR__.'/../../../data/data.db' ] ] );
#DB-----------------

$C['CData']->registerPattern([ 
	'SETTING'	=> [
			'Active'		=> ['Type' => 'checkbox'],
			'ParentId'		=> ['Type' => 'id', 'ForeignKey' => 1],
			'Value'			=> ['Type' => 'text'],
		],
	'LINK'		=> [
			'Active'		=> ['Type' => 'checkbox'],
			'FromURL'		=> ['Type' => 'text'],
			'ToURL'			=> ['Type' => 'text'],
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
 

/*
$frame = new papp\framework($ModulId);
$frame->getLink($F);
$frame->setLink($active,$FromURL,$ToURL);
*/


// 2. Phase: alle init.php laden
foreach ($D['MODUL']['D'] as $moduleDir => $info) {
	if('papp/framework' != $moduleDir) {
		$D['MY'] = $info;
		
		$init = $info['ModulDir'] . '/init.php';
		if (is_file($init)) {
			require_once $init;
		}
	}
}