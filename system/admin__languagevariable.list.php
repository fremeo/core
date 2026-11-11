<?php
# Sprachdateien liefern Standards; die Modul-DB haelt Overrides und automatisch erfasste fehlende Schluessel.
# Angezeigte Sprachen: Einstellung fremeo/core enable_languages, z.B. "DE|EN"
$isos = array_values(array_unique(array_filter(array_map(fn ($i) => strtoupper(trim($i)), explode('|', (string)$C['Setting']->get('fremeo/core', 'enable_languages')))))) ?: ['DE'];
$mods = array_filter($D['MODULE']['D'], fn ($M) => in_array($M['INFO']['Type'], ['module', 'template', 'core']) && (int)($M['Active'] ?? 1) === 1);

$defaults = [];
foreach ($mods as $kMOD => $MOD) {
	foreach ($isos as $iso) {
		$defaults[$kMOD][$iso] = $C['LanguageVariable']->getDefaults($kMOD, $iso);
	}
}

$f = ['LANGUAGE' => ['I18N' => []]];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($D['ACTION'] ?? null) === 'save') {
	foreach ($mods as $kMOD => $MOD) {
		$C['LanguageVariable']->save($kMOD, $MOD['LANGUAGE']['D'] ?? [], $isos);
	}
}

# Anzeige: Wert aus der Modul-DB, sonst Standard aus der Sprachdatei. $R['LanguageRows'][<modul>][<id>][<iso>]
$R['LanguageIsos'] = $isos;
$R['LanguageRows'] = [];
$R['LanguageMeta'] = [];
foreach ($mods as $kMOD => $MOD) {
	unset($D['MODULE']['D'][$kMOD]['LANGUAGE']);
	$C[$kMOD]['CData']->get_object($D['MODULE']['D'][$kMOD], $f);
	$R['LanguageRows'][$kMOD] = [];
	foreach ($isos as $iso) {
		$db = $D['MODULE']['D'][$kMOD]['LANGUAGE']['D'][$iso]['I18N']['D'] ?? [];
		foreach (array_keys(($defaults[$kMOD][$iso] ?? []) + $db) as $id) {
			$row = $db[$id] ?? null;
			$missing = (int)($row['Missing'] ?? 0) === 1;
			$R['LanguageRows'][$kMOD][$id][$iso] = $row !== null && !$missing
				? ($row['Value'] ?? '') : ($defaults[$kMOD][$iso][$id] ?? '');
			$R['LanguageMeta'][$kMOD][$id][$iso] = [
				'Missing' => $missing && !array_key_exists($id, $defaults[$kMOD][$iso]),
				'Override' => $row !== null && !$missing,
				'Default' => $defaults[$kMOD][$iso][$id] ?? '',
				'Source' => $row['Source'] ?? '',
			];
		}
	}
	# fehlende Sprachen einer Id leer auffüllen
	foreach ($R['LanguageRows'][$kMOD] as $id => $values) {
		$R['LanguageRows'][$kMOD][$id] = array_replace(array_fill_keys($isos, ''), $values);
	}
}
