<?php
# Defaults aus allen default.config.php laden, Werte aus der jeweiligen Modul-DB.
# Ergebnis: $R['SETTING_VIEW'][Section][] = ['Module','Key','Meta','Value']
# Eine Section darf Einstellungen mehrerer Module enthalten, eine Einstellung erscheint nur in den Sections, die ihr eigenes Modul nennt.
$F['SETTING'] = [];
$view = [];
$R['SettingError'] = [];
foreach ($D['MODULE']['D'] as $kMOD => $MOD) {
	if (!in_array($MOD['INFO']['Type'], ['module', 'template', 'core']) || (int)($MOD['Active'] ?? 1) !== 1) {
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

	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($R['ACTION'] ?? null) === 'save') {
		$posted = (array)($MOD['SETTING']['D'] ?? []);
		$update = [];
		foreach ($aSetting as $key => $meta) {
			if (array_key_exists('Value', (array)($posted[$key] ?? []))) {
				$value = $posted[$key]['Value'];
				if (!is_scalar($value)) {
					throw new RuntimeException("Ungueltiger Einstellungswert: $kMOD/$key");
				}
				$update['SETTING']['D'][$key] = (string)$value === (string)($meta['Value'] ?? '')
					? '__DELETE__' : ['Value' => $value];
			}
		}
		$valid = true;
		if (isset($aSetting['theme'], $posted['theme'])) {
			$themes = $C['Template']->getThemeChoices($kMOD);
			$themeKey = (string)$posted['theme']['Value'];
			if (!str_contains($themeKey, ':')) $themeKey = "$kMOD:$themeKey";
			$valid = isset($themes[$themeKey]);
			if (!$valid) {
				$R['SettingError'][] = "Theme-Auswahl fuer $kMOD wurde nicht gespeichert: Unbekanntes Theme.";
			} else {
				$defaultTheme = (string)($aSetting['theme']['Value'] ?? 'basic');
				if (!str_contains($defaultTheme, ':')) $defaultTheme = "$kMOD:$defaultTheme";
				$update['SETTING']['D']['theme'] = $themeKey === $defaultTheme ? '__DELETE__' : ['Value' => $themeKey];
				$update['SETTING']['D']['theme_variant'] = '__DELETE__';
			}
		}
		if ($valid && $update) {
			$C[$kMOD]['CData']->set_object($update);
			if (isset($posted['theme'])) $C['LanguageVariable']->clearCache();
		}
	}
	unset($D['MODULE']['D'][$kMOD]['SETTING']);
	$C[$kMOD]['CData']->get_object($D['MODULE']['D'][$kMOD], $F);

	# Eintrag einer Einstellung: Wert aus der Modul-DB, sonst Standardwert aus der Datei
	$item = fn ($key) => [
		'Module' => $kMOD,
		'Key' => $key,
		'Meta' => array_map(fn ($v) => $v instanceof Closure ? $v($C) : $v, $aSetting[$key]), # Closure in der Config (z.B. 'Options' => fn ($C) => [...]) = dynamischer Wert
		'Value' => $key === 'theme' ? $C['Template']->getThemeSelection($kMOD)['Key'] : ($D['MODULE']['D'][$kMOD]['SETTING']['D'][$key]['Value'] ?? ($aSetting[$key]['Value'] ?? null)),
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

	
