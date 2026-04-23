<?php

if ( !($_SESSION['UserId']??null) ) {
	header("Location: ./");
} 
elseif (1 || !($_SESSION['AccountId']??null) ) {
	
	$f['USER']['W'][0]['ID'] = $_SESSION['UserId'];
	$f['USER']['ACCOUNT'] = null;
	$C['papp~phpapp']['CData']->get_object($d,$f);

	if( $d['USER']['D'][ $_SESSION['UserId'] ]['ACCOUNT']['COUNT'] > 0 ) {
		$_AccId = array_key_first($d['USER']['D'][ $_SESSION['UserId'] ]['ACCOUNT']['D']);
		if( $d['USER']['D'][ $_SESSION['UserId'] ]['ACCOUNT']['D'][ $_AccId ]['Active'] ) {
			$_SESSION['AccountId'] = $_AccId;
		} else { #Account ist zwar da aber dieser ist zugeordnet, daher wird der User auf die Startseite umgeleitet. Kein Zugriff auf den Account
			header("Location: ./");
		}
	}
	else { # Wenn User keinem Account zugehörig ist, dann erstell einen und ordne an den User zu.
		$_SESSION['AccountId'] = hash("crc32b",$_SESSION['UserId'].hrtime(true));
		$d['USER']['D'][ $_SESSION['UserId'] ]['ACCOUNT']['D'][ $_SESSION['AccountId'] ]['Active'] = 1;
		$d['ACCOUNT']['D'][ $_SESSION['AccountId'] ]['Active'] = 1;
		$C['papp~phpapp']['CData']->set_object($d);
	}
} 
else {
	$F['ACCOUNT']['W'][0]['Active'] = 1;
	$F['ACCOUNT']['W'][0]['ID'] = $_SESSION['AccountId'];
}