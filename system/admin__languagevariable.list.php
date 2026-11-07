<?php
# Sprachvariablen je Modul. Standard: <Modul>/system/template/language/<iso2>.php, in der Modul-DB (LANGUAGE.D[<ISO2>].I18N.D[<id>].Value) stehen nur geänderte Werte.
# Id einer Variable = Schlüssel aus der Sprachdatei (die Gruppe, z.B. index__login, gehört nicht zur Id).
# Angezeigte Sprachen: Einstellung fremeo/core enable_languages, z.B. "DE|EN"
$isos = array_values(array_unique(array_filter(array_map(fn ($i) => strtoupper(trim($i)), explode('|', (string)$C['Setting']->get('fremeo/core', 'enable_languages')))))) ?: ['DE'];
$mods = array_filter($D['MODULE']['D'], fn ($M) => in_array($M['INFO']['Type'], ['module', 'template', 'core']) && (int)($M['Active'] ?? 1) === 1);

$defaults = [];
foreach ($mods as $kMOD => $MOD) {
	foreach ($isos as $iso) {
		$file = $MOD['INFO']['ModulDir'] . '/system/template/language/' . strtolower($iso) . '.php';
		foreach (is_file($file) ? (array)include $file : [] as $vars) {
			foreach ((array)$vars as $key => $text) {
				$defaults[$kMOD][$iso][$key] = $text;
			}
		}
	}
}

$f = ['LANGUAGE' => ['I18N' => []]];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($D['ACTION'] ?? null) === 'save') {
	foreach ($mods as $kMOD => $MOD) {
		$update = [];
		# Wert gleich dem Standard: nicht speichern, eine bereits gespeicherte Abweichung wird entfernt
		$del = [];
		foreach ($isos as $iso) {
			foreach ((array)($MOD['LANGUAGE']['D'][$iso]['I18N']['D'] ?? []) as $id => $row) {
				if ($row === '__DELETE__') {
					$del[] = $id;
				} elseif (is_array($row) && array_key_exists('Value', $row)) {
					if (!is_scalar($row['Value'])) {
						throw new RuntimeException("Ungueltiger Sprachvariablenwert: $kMOD/$iso/$id");
					}
					$update['LANGUAGE']['D'][$iso]['I18N']['D'][$id] = (string)$row['Value'] === (string)($defaults[$kMOD][$iso][$id] ?? '')
						? '__DELETE__' : ['Value' => (string)$row['Value']];
				}
			}
		}
		# Gelöschte Variablen: in allen Sprachen der Modul-DB entfernen
		if ($del) {
			$all = [];
			$C[$kMOD]['CData']->get_object($all, $f);
			foreach (array_unique([...$isos, ...array_keys($all['LANGUAGE']['D'] ?? [])]) as $lang) {
				foreach ($del as $id) {
					$update['LANGUAGE']['D'][$lang]['I18N']['D'][$id] = '__DELETE__';
				}
			}
		}
		if ($update) $C[$kMOD]['CData']->set_object($update);
	}
}

# Anzeige: Wert aus der Modul-DB, sonst Standard aus der Sprachdatei. $R['LanguageRows'][<modul>][<id>][<iso>]
$R['LanguageIsos'] = $isos;
$R['LanguageRows'] = [];
foreach ($mods as $kMOD => $MOD) {
	unset($D['MODULE']['D'][$kMOD]['LANGUAGE']);
	$C[$kMOD]['CData']->get_object($D['MODULE']['D'][$kMOD], $f);
	$R['LanguageRows'][$kMOD] = [];
	foreach ($isos as $iso) {
		$db = $D['MODULE']['D'][$kMOD]['LANGUAGE']['D'][$iso]['I18N']['D'] ?? [];
		foreach (array_keys(($defaults[$kMOD][$iso] ?? []) + $db) as $id) {
			$R['LanguageRows'][$kMOD][$id][$iso] = $db[$id]['Value'] ?? $defaults[$kMOD][$iso][$id] ?? '';
		}
	}
	# fehlende Sprachen einer Id leer auffüllen
	foreach ($R['LanguageRows'][$kMOD] as $id => $values) {
		$R['LanguageRows'][$kMOD][$id] = array_replace(array_fill_keys($isos, ''), $values);
	}
}
