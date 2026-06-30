<?php

#ToDo: Gast und User Gruppe soll zur Standard Gruppe werden, so dass es Gast und User User nicht merh benötigt wird!
##if(check_user_page_right(['guest','admin2l'],$D['_PAGE'], $C)) echo "OK"; else echo "nein";

if(!check_user_page_right(['guest',(isset($D['SESSION']['UserId']))?'user':null,($D['SESSION']['UserId'])??null],$R['Page'], $C)) { #Prüfe ob die Guest Rechte und falls eingellogt auch User Rechte bereits ausreichen
	#Rechte Reichen nicht aus
	if( !isset($D['SESSION']['UserId']) ) { #Fals User nicht eingeloggt ist, dann zum Login
		header("Location: ?R[Page]=index__login&R[ModuleId]=fremeo/core&R[Return][Page]={$D['_PAGE']}&R[Return][ModuleId]={$R['ModuleId']}");
	}
	else { #User ist eingeloggt aber Rechte nicht ausreichend.
		header("Location: ?R[Page]=error.403&R[ModuleId]=fremeo/core");
	}
	
}

$F['SETTING'] = [];




function check_user_page_right($userId, $page, &$C)
{
	$f['USER']['W'][0]['ID'] = $userId;
	$f['USER']['W'][0]['Active'] = 1;
	$f['USER']['GROUP']['W'][0]['Active'] = 1; #deaktivierte Gruppen, nicht listen
	$f['USER_GROUP']['PAGE'] = [];
	$f['USER_GROUP']['W'][0]['Active'] = 1;
	$C['fremeo/core']['CData']->get_object($d,$f);
	
	if(!isset($d['USER']['D'])) {
		return false;
	}
	##print_r($d['USER']['D']);
	$user_right = [];
	foreach((array)$d['USER']['D'] AS $kUSR => $USR) {
		foreach($USR['GROUP']['D']??[] AS $kUG => $UG ) {
			$user_right = array_replace_recursive((array)$user_right, (array)($d['USER_GROUP']['D'][ $kUG ]['PAGE']['D']??[]));
		}
	}
##print_R($user_right);exit;
    // Basisname extrahieren:
    // "admin__user.list" → "admin"
    // "frontend__page"   → "frontend"
    // "admin"            → "admin"
    $base = explode("__", $page)[0];

    // --- 1. Prüfen: Wildcard‑Rechte wie "admin__" ---
    foreach ($user_right as $right => $data) {

        if ($data["Active"] != 1) {
            continue;
        }

        // Wildcard‑Recht endet auf "__"
        if (substr($right, -2) === "__") {

            $prefix = substr($right, 0, -2); // "admin__" → "admin"

            // Wildcard gilt für:
            // - Basis-Seite ("admin")
            // - alle Unterseiten ("admin__...")
            if ($prefix === $base) {
                return true;
            }
        }
    }

    // --- 2. Prüfen: Exakte Seitenrechte ---
    if (isset($user_right[$page]) && $user_right[$page]["Active"] == 1) {
        return true;
    }

    // --- 3. Basis-Seite erlauben, wenn Unterseiten-Rechte existieren ---
    // WICHTIG: Nur ausführen, wenn wirklich NUR die Basis-Seite geprüft wird
    // Beispiel:
    // page = "admin"  → darf auf "admin__user.list" basieren
    // page = "admin__user_group.list" → darf NICHT hier reinfallen
    if ($page === $base) {
        foreach ($user_right as $right => $data) {

            if ($data["Active"] != 1) {
                continue;
            }

            // Prüfen, ob ein Recht mit "admin__" beginnt
            if (strpos($right, $base . "__") === 0) {
                return true;
            }
        }
    }

    return false;
}


