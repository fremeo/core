<?php
switch($D['ACTION']??null)
{
	case 'login':#1. User Login

		$dumy_Pass = 'adminadmin';
		if(isset($R['UserName']) && isset($R['Password'])) {
			$D['USER']['D'] = null; #Sicherheit

			if($R['Password'] == $dumy_Pass){
				$_SESSION['UserId'] = 'admin2';
				
				if($R['Return']['Page'] && $R['Return']['ModuleId']) {
					header("Location: ?D[_PAGE]={$R['Return']['Page']}&R[ModuleId]={$R['Return']['ModuleId']}");
				}
				else {
					header("Location: ./");
				}
			}
		}
		break;
	case 'logout':
		#$_SESSION = null;
		
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