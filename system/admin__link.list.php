<?php

if(($D['ACTION']??null) == 'save') {

	// Zu löschende Links je Modul: R[delete][<ModulId>][] = Link-ID (die Tabelle zeigt die Links je Modul, jedes Modul hat seine eigene Datenbank)
	foreach((array)($R['delete'] ?? []) AS $kMOD => $ids) {
		if(!isset($C[$kMOD]['CData'])) continue;

		$ids = array_filter((array)$ids, fn($id) => is_string($id) && preg_match('/^[A-Za-z0-9_-]+$/', $id));
		if($ids) (new \fremeo\core\Link($C[$kMOD]['CData']))->deleteById($ids);
	}

	unset($D['LINK']);
}

#$F['PLATFORM']['PAGE']['W'][0]['ID'] = [$D['ID']];
###$F['LINK'] = [];

/* # Test Eintrag
$d1['LINK']['D']['4f166f26'] = [
	'Active'	=> 1,
	'FromURL'	=> 'widerrufsrecht',
	'ToURL'		=> 'R[Page]=frontend__page&R[Id]=dd7e77fc&R[LanguageId]=DE',
];
$C['fremeo/page']['CData']->set_object($d1);
*/

#Todo: muss in die Link Klasse verschoben werden.
#Durchlaufe alle Module und hole die Link Einträge
$f['LINK'] = [];
if(!empty($D['MODULE']['D'])) {
	foreach((array)$D['MODULE']['D'] AS $kMOD => $MOD) {


		if(isset($C[$kMOD]['CData'])) {
			$C[$kMOD]['CData']->get_object($D['MODULE']['D'][$kMOD],$f);
		}
	}
}
#print_r($D['MODULE']['D']['fremeo/page']);
#$C['fremeo/page']['CData']->get_object($D,$F);
/*
$Dd['LINK']['D']['35ccf52f'] = [
	'ToURL'		=> 'R[Page]=index__login&D[ACTION]=logout',
];
$C['fremeo/core']['CData']->set_object($Dd);
*/

