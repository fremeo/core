<?php

// Nur Module mit eigener Datenbank und File-Dienst (keine Bibliotheken, type "library"). Das Zielmodul kommt nie als Pfad in den Dateinamen,
// sondern über seinen File-Dienst, der nur in den eigenen Ordner schreibt und ausführbare Endungen ablehnt.
$modId = $R['activeModuleId'] ?? null;
$valid = $modId && isset($C[$modId]['CData'], $C[$modId]['File']);

if($valid && ($D['ACTION']??null) == 'save') {
	$C[$modId]['CData']->set_object($D);
}

// Zu löschende Dateien je Modul: R[delete][<ModulId>][] = Datei-ID. Die Datei und ihr Datenbankeintrag werden über den File-Dienst des Moduls entfernt.
if(($D['ACTION']??null) == 'save') {
	foreach((array)($R['delete'] ?? []) AS $kMOD => $ids) {
		if(!isset($C[$kMOD]['File'])) continue;

		$ids = array_filter((array)$ids, fn($id) => is_string($id) && preg_match('/^[A-Za-z0-9_-]+$/', $id));
		if($ids) $C[$kMOD]['File']->delete(array_values($ids));
	}
}

if($valid && ($D['ACTION']??null) == 'upload' && isset($_FILES['file'])) {
	$C[$modId]['File']->add($_FILES['file']);
}

// $D enthält nur die Datenbankstruktur (je Modul MODULE.D.<Id>.FILE), die Liste der anzuzeigenden Module geht über $R an das Template ($D.R.FileModules).
$f['FILE'] = [];
$R['FileModules'] = [];
foreach((array)($D['MODULE']['D'] ?? []) AS $kMOD => $MOD) {
	if(($MOD['Type'] ?? 'module') === 'library' || !isset($C[$kMOD]['CData'], $C[$kMOD]['File'])) continue;

	$C[$kMOD]['CData']->get_object($D['MODULE']['D'][$kMOD], $f);
	$R['FileModules'][] = $kMOD;
}