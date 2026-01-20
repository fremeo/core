<?php 
if($D['ACTION']??null) {
	
	if( $R['USER']['D'][$R['Id']]['Password'] ) {
		echo "a";
		$D['USER']['D'][$R['Id']]['Password'] = password_hash( $R['USER']['D'][ $R['Id'] ]['Password'] ,PASSWORD_DEFAULT);
	}
	
	$C['CData']->set_object($D);
}

$F['USER']['W'][0]['ID'] = ($R['Id']??null);
$F['USER']['GROUP'] = [];
$F['USER_GROUP']['W'][0]['Active'] = 1;
