<?php

if(($D['ACTION']??null) == 'save') {

// 1. IDs sammeln, die gelöscht werden sollen
    $deleteIds = [];
    if (!empty($R['delete'])) {
        $deleteIds = array_values($R['delete']);
    }

	#erstelle neues SEO Link weiterleitung und lösche alte und weise die Link-ID der Seite neu zu.
	foreach((array)$D['LINK']['D'] AS $kLNK => $LNK) {
		if(is_array($LNK) && isset($LNK['ToURL']) && !in_array($kLNK,($R['delete']??[])  ) ) {
			$hURL = hash("crc32b", ($LNK['FromURL']??''));
			
			$D['LINK']['D'][$hURL] = [
				'Active'	=> $LNK['Active'],
				'FromURL'	=> $LNK['FromURL'],
				'ToURL'		=> $LNK['ToURL'],
			];
			if($kLNK != $hURL) { #Überprüfe ob sich die FromURL geändert hat, denn dann alte löschen.
				$D['LINK']['D'][ $kLNK ]['Active'] = -2;#Alte URL löschen
			}
		} else {
			unset($D['LINK']['D'][$kLNK] );
		}
	}

	// 3. Löschen 
	if (!empty($deleteIds)) { 
		$C['fremeo/core']['Link']->deleteById($deleteIds); 
	}

	$C['fremeo/core']['CData']->set_object($D);
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

