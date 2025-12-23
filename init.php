<?php

session_start();
$D['SESSION'] = $_SESSION['D'];

$smarty = new Smarty();

$C['cdata'] = $CData = new papp\CData( [ 'DB' => ['FILENAME' => __DIR__.'/../../../data/data.db' ] , 'PATTERN' => $D['PATTERN'] ] );

#DB-----------------
/*
$C['cdata']->registerPattern([ 
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
 ]);
*/
$D['PATTERN']['SETTING'] = [
	'Active'		=> ['Type' => 'checkbox'],
	'ParentId'		=> ['Type' => 'id', 'ForeignKey' => 1],
	'Value'			=> ['Type' => 'text'],
];

$D['PATTERN']['LINK'] = [
	'Active'		=> ['Type' => 'checkbox'],
	'FromURL'		=> ['Type' => 'text'],
	'ToURL'			=> ['Type' => 'text'],
];