<?php
if(($R['Action']??null) == 'save') {

	if(isset($R['Mail']) && isset($R['Password']) && $R['Password'] == $R['Password2']) {
		
		$f['USER']['W'][0]['Mail'] = $R['Mail'];
		$C['CData']->get_object($d,$f); #Prüfe ob email bereits vorhanden ist
		if(count(($d['USER']['D']??[])) == 0) { #Es gibt kein Ergebnis, keine Benutzer mit dieser E-Mail, dann kann Benutzer und Account erstellt werden
			
			$user_id = hash("crc32b", $R['Mail'] . microtime(true));
			$d['USER']['D'][ $user_id ] = [
				'Active'	=> 1,
				'Mail'		=> $R['Mail'],
				'Password'	=> password_hash($R['Password'],PASSWORD_DEFAULT),
				
			];
			
			$d['USER']['D'][$user_id]['GROUP']['D']['guest'] = [
				'Active' => 1,
			];
			
			$d['ACCOUNT']['D'][$user_id] = [
				'Active'	=> 1,
				'Name'		=> 'Mein Account',
			];
			$d['USER']['D'][$user_id]['ACCOUNT']['D']['guest'] = [
				'Active' => 1,
			];
			
			$C['CData']->set_object($d);
			
			
			#ToDo: Sende E-Mail zum freischalten des Accounts
		}
	}


}


