<?php


##if(check_user_page_right(['guest','admin2l'],$D['_PAGE'], $C)) echo "OK"; else echo "nein";

if(!check_user_page_right(['guest',$D['SESSION']['UserId']??null],$D['_PAGE'], $C)) { #Prüfe ob die Guest Rechte und falls eingellogt auch User Rechte bereits ausreichen
	#Rechte Reichen nicht aus
	if( !isset($D['SESSION']['UserId']) ) { #Fals User nicht eingeloggt ist, dann zum Login
		header("Location: ?D[_PAGE]=index__login&R[ModuleId]=papp/phpapp&R[Return][Page]={$D['_PAGE']}&R[Return][ModuleId]={$R['ModuleId']}");
	}
	else { #User ist eingeloggt aber Rechte nicht ausreichend.
		header("Location: ?D[_PAGE]=error.403&R[ModuleId]=papp/phpapp");
	}
	
}





/*
if(!$D['SESSION']['ACTIVE_USER']['Id']) {
	#User ist nicht eingellogt, leite zur Login Seite.
	#ToDo: Übergib an die Login Seite die Zielurl um nach login wieder zurück zu kehren
	header("Location: {$D['BasePath']}login");
} else {
	#1. Prüfe ob berechtigung für Admin Bereich verfügbar ist
	#Wenn nicht, dann 404
}
*/
$F['SETTING'] = [];




function check_user_page_right($userId, $page, &$C)
{
	$f['USER']['W'][0]['ID'] = $userId;
	$f['USER']['W'][0]['Active'] = 1;
	$f['USER']['GROUP'] = [];
	$f['USER_GROUP']['PAGE'] = [];
	$f['USER_GROUP']['W'][0]['Active'] = 1;
	$C['CData']->get_object($d,$f);
	
	if(!isset($d['USER']['D'])) {
		return false;
	}
	
	$user_right = [];
	foreach((array)$d['USER']['D'] AS $kUSR => $USR) {
		foreach($USR['GROUP']['D'] AS $kUG => $UG ) {
			$user_right = array_merge_recursive((array)$user_right, (array)($d['USER_GROUP']['D'][ $kUG ]['PAGE']['D']??[]));
		}
	}

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


