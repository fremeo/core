<?php
switch($D['ACTION']??null)
{
	case 'login':#1. User Login

		if(isset($R['UserName']) && isset($R['Password'])) {

			$f['USER']['W'][0]['Active'] = 1;
			$f['USER']['W'][0]['Name'] = $R['UserName'];
			$f['USER']['W'][1]['Mail'] = $R['UserName'];
			$C['CData']->get_object($d,$f);
			if( isset($d['USER']['D']) && password_verify($R['Password'], $d['USER']['D'][ array_key_first((array)($d['USER']['D']??[])) ]['Password']) ){
				$_SESSION['UserId'] = 'admin2';
				
				if($R['Return']['Page'] && $R['Return']['ModuleId']) {
					header("Location: ?R[Page]={$R['Return']['Page']}&R[ModuleId]={$R['Return']['ModuleId']}");
				}
				else {
					header("Location: ./");
				}
			}
		}
		break;
	case 'logout':
		session_destroy(); #Alle Session löschen beim ausloggen.
		header("Location: ./");
		break;
	case 'password_forgotten':
		#1. ToDo: user nick und sicherheitscode prüfen
		#2. email mit zurück setzen Link versenden
		break;
	case 'password_reset':
		#3. Passwort zurück setzen auf vor generierte Passwort.
		break;
}