<?php
switch($D['ACTION']??null)
{
	case 'login':#1. User Login
	
		$dumy_Pass = 'adminadmin';
		if($D['USER']['W']['NICKNAME'] && $D['PASSWORD']) {
			$D['USER']['D'] = null; #Sicherheit

			if($D['PASSWORD'] == $dumy_Pass){
				$_SESSION['D']['ADMIN']['USER']['Active'] = $D['SESSION']['ADMIN']['USER']['Active'] = 1;
				$_SESSION['D']['ADMIN']['USER']['PASSWORD'] = $D['SESSION']['ADMIN']['USER']['PASSWORD'] = $dumy_Pass;
				header("Location: admin");
			}

/*
			$USER = $MAIN->get_user($D);
			$User = array_keys((array)$D['USER']['D']);
			$USER_ID = $User[0];
			if($USER_ID) {
				if(password_verify($D['PASSWORD'], $D['USER']['D'][$USER_ID]['PASSWORD']) ) {
					$_SESSION['D']['SESSION']['USER'] = $D['USER']['D'][$USER_ID];
					$_SESSION['D']['SESSION']['USER']['ID'] = $USER_ID;
					$D['USER']['D'][$USER_ID]['LOGIN_FAIL'] = 0;
					#$MAIN->set_user($D);
					header("Location: admin");
				}
				else {
					$D['USER']['D'][$USER_ID]['LOGIN_FAIL'] ++;
					#$MAIN->set_user($D);
				}
			}*/
		}
		break;
	case 'logout':
		$_SESSION['D'] = null;
		break;
	case 'password_forgotten':
		#1. ToDo: user nick und sicherheitscode prüfen
		#2. email mit zurück setzen Link versenden
		break;
	case 'password_reset':
		#3. Passwort zurück setzen auf vor generierte Passwort.
		break;
}
#echo password_hash('admin',PASSWORD_DEFAULT);
#if( password_verify('test', '$2y$10$BPQIf5Rg5jrOLARUHbxmteu3yZlvxoEgCzJWYmzp7hafS5ESFnUrW') )echo 'OK';
$C['smarty']->assign('D',$D);
#$smarty->display('extends:login.tpl|include/input.tpl');

$base = 'login.tpl';
$content = file_get_contents($C['smarty']->getTemplateDir(0) . 'include/input.tpl');

$template = <<<TPL
{extends file="$base"}

$content
TPL;

$C['smarty']->display('string:' . $template);