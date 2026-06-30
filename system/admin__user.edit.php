<?php 
if($D['ACTION']??null) {
	
	if( $R['USER']['D'][$R['Id']]['Password']??false ) {

		$D['USER']['D'][$R['Id']]['Password'] = password_hash( $R['USER']['D'][ $R['Id'] ]['Password'] ,PASSWORD_DEFAULT);
	}
	
	
	$C['fremeo/core']['CData']->set_object($D);
}

$F['USER']['W'][0]['ID'] = ($R['Id']??null);
$F['USER']['GROUP'] = [];
$F['USER_GROUP']['W'][0]['Active'] = 1;
