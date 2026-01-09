<?php

if(($D['ACTION']??null) == 'save') {

	#erstelle neues SEO Link weiterleitung und lösche alte und weise die Link-ID der Seite neu zu.
	foreach((array)$D['LINK']['D'] AS $kLNK => $LNK) {
		$hURL = hash("crc32b", $LNK['FromURL']);
		
		$D['LINK']['D'][$hURL] = [
			'Active'	=> $LNK['Active'],
			'FromURL'	=> $LNK['FromURL'],
			'ToURL'		=> $LNK['ToURL'],
		];
		if($kLNK != $hURL) { #Überprüfe ob sich die FromURL geändert hat, denn dann alte löschen.
			$D['LINK']['D'][ $kLNK ]['Active'] = -2;#Alte URL löschen
		}
	}


	$C['CData']->set_object($D);
	unset($D['LINK']); 
}

#$F['PLATFORM']['PAGE']['W'][0]['ID'] = [$D['ID']];
$F['LINK'] = [];