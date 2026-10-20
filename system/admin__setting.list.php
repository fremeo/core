<?php

if(($R['ACTION']??null) == 'save') {
	foreach($D['MODULE']['D'] AS $kMOD => $MOD) {
		if(in_array($MOD['INFO']['Type'], ['module', 'template', 'core'])) {
			$C[$kMOD]['CData']->set_object($MOD); 
		}
	}
}
#$F['PLATFORM']['PAGE']['W'][0]['ID'] = [$D['ID']];
$F['SETTING'] = [];

foreach($D['MODULE']['D'] AS $kMOD => $MOD) {
	#stelle sicher dass $MOD['INFO']['Type'] ein module, Template oder core ist bzw. ungleich libery
	if(in_array($MOD['INFO']['Type'], ['module', 'template', 'core'])) {
		$C[$kMOD]['CData']->get_object($D['MODULE']['D'][$kMOD],$F);
	}
}