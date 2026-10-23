<?php
if (($R['ACTION'] ?? null) == 'save') {
	foreach ($D['MODULE']['D'] as $kMOD => $MOD) {
		if (in_array($MOD['INFO']['Type'], ['module', 'template', 'core'])) {
			$C[$kMOD]['CData']->set_object($MOD);
		}
	}
}

# Defaults aus allen default.config.php laden, Werte aus der jeweiligen Modul-DB.
# Ergebnis: $R['SETTING_VIEW'][Section][] = ['Module','Key','Meta','Value']
# Eine Section darf Einstellungen mehrerer Module enthalten, eine Einstellung erscheint nur in den Sections, die ihr eigenes Modul nennt.
$F['SETTING'] = [];
$view = [];
foreach ($D['MODULE']['D'] as $kMOD => $MOD) {
	if (!in_array($MOD['INFO']['Type'], ['module', 'template', 'core'])) {
		continue;
	}

	$file = $MOD['INFO']['ModulDir'] . '/system/default/default.config.php';
	if (!is_file($file)) {
		continue;
	}

	# Isolierter Scope: $section/$setting der Datei dürfen nicht zwischen Modulen durchsickern
	$aConf = (static fn (string $f): array => (array)include $f)($file);
	$aSetting = (array)($aConf['SETTING'] ?? []);
	$aSection = (array)($aConf['SECTION'] ?? []);

	$C[$kMOD]['CData']->get_object($D['MODULE']['D'][$kMOD], $F);

	# Eintrag einer Einstellung: Wert aus der Modul-DB, sonst Standardwert aus der Datei
	$item = fn ($key) => [
		'Module' => $kMOD,
		'Key' => $key,
		'Meta' => $aSetting[$key],
		'Value' => $D['MODULE']['D'][$kMOD]['SETTING']['D'][$key]['Value'] ?? ($aSetting[$key]['Value'] ?? null),
	];

	$assigned = [];
	$walk = function (array $sections, &$node) use (&$walk, &$assigned, $aSetting, $item): void {
		foreach ($sections as $name => $section) {
			foreach ((array)($section['D'] ?? []) as $key => $sub) {
				if (is_array($sub)) { # Unter-Section
					$walk([$key => $sub], $node[$name]['Children']);
				} elseif (isset($aSetting[$key])) { # Einstellung
					$node[$name]['Items'][] = $item($key);
					$assigned[$key] = true;
				}
			}
		}
	};
	$walk($aSection, $view);

	# Einstellungen ohne Section dieses Moduls landen in 'default'
	foreach (array_diff_key($aSetting, $assigned) as $key => $meta) {
		$view['default']['Items'][] = $item($key);
	}
}

ksort($view);
$R['SETTING_VIEW'] = $view;

	


