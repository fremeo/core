<?php
namespace fremeo\core;

class Template {

	/**
	 * Liefert die Template-Kette für ein gegebenes Modul und eine Seite.
	 * Berücksichtigt die Reihenfolge: core → template → module.
	 * 
	 * @param array $modules Alle verfügbaren Module mit ihren Informationen.
	 * @param string $activeModuleId Die ID des aktiven Moduls.
	 * @param string $page Die angeforderte Seite (z.B. "admin__admin").
	 * @return array Array mit 'extends', 'tpl' und 'php' Ketten.
	 */
	public function getExtends(array $modules, string $activeModuleId, string $page): array
	{
		// Bibliotheken (type "library", z.B. phploader/cdata, symfony/*) enthalten keine Templates und werden nicht durchsucht
		$modules = array_filter($modules, fn($m) => ($m['Type'] ?? 'module') !== 'library');

		// Modul muss existieren
		if (!isset($modules[$activeModuleId])) {
			return ['extends' => '', 'tpl' => [], 'php' => []];
		}

		$tplChain = [];
		$phpChain = [];

		// Rangfolge der Modul-Typen für die Template-Kette (Typ aus der composer.json). Unbekannte Typen zählen als 'module'.
		// Die Kette wird am Ende umgedreht, dadurch ergibt sich core -> template -> module.
		$rank = ['core' => 0, 'template' => 1, 'module' => 2];
		$typeOf = fn(array $m): string => $m['Type'] ?? 'module';

		// Alle Module: Module vor Template vor Core. Gleicher Rang behält die bisherige Reihenfolge (uasort ist ab PHP 8 stabil).
		uasort($modules, fn($a, $b) => ($rank[$typeOf($b)] ?? 2) <=> ($rank[$typeOf($a)] ?? 2));

		// Dateien, bei denen nur EIN Modul verwendet wird (z.B. index.tpl): Template vor Core vor Modulen
		$firstOrder = ['template' => 0, 'core' => 1, 'module' => 2];
		$first = $modules;
		uasort($first, fn($a, $b) => ($firstOrder[$typeOf($a)] ?? 2) <=> ($firstOrder[$typeOf($b)] ?? 2));

		// Hilfsfunktion: TPL existiert?
		$exists = fn(array $module, string $tpl): bool => file_exists($module['INFO']['ModulDir'] . '/system/template/' . $tpl);

		// Hilfsfunktion: TPL → PHP Pfad
		$toPhp = fn(string $tplPath): string => str_replace(['/system/template/', '.tpl'], ['/system/', '.php'], $tplPath);

		// Hilfsfunktion: TPL in die Kette aufnehmen, dazu die passende PHP-Datei (falls vorhanden)
		$add = function(string $tplPath) use (&$tplChain, &$phpChain, $toPhp) {
			$tplChain[] = $tplPath;
			if (file_exists($php = $toPhp($tplPath))) {
				$phpChain[] = $php;
			}
		};

		// ---------------------------------------------------------
		// 1. Basis-Seite ohne "__" → nur root.tpl
		// ---------------------------------------------------------
		if (!str_contains($page, '__')) {

			$tpl = $modules[$activeModuleId]['INFO']['ModulDir'] . '/system/template/' . $page . '.tpl';
			$php = $toPhp($tpl);

			return [
				'extends' => 'extends:' . $tpl,
				'tpl'     => [$tpl],
				'php'     => file_exists($php) ? [$php] : []
			];
		}

		// ---------------------------------------------------------
		// 2. base__sub zerlegen
		// ---------------------------------------------------------
		[$base, $sub] = explode('__', $page);

		// ---------------------------------------------------------
		// 2b. sub__sub.tpl (z.B. user.register__user.register.tpl) – erstes Modul, kommt als ERSTES in die Chain
		// ---------------------------------------------------------
		$subBase = "{$sub}__{$sub}.tpl";

		foreach ($first as $moduleDir) {
			if ($exists($moduleDir, $subBase)) {
				$add($moduleDir['INFO']['ModulDir'] . '/system/template/' . $subBase);
				break; // nur das erste gefundene Modul verwenden
			}
		}

		// ---------------------------------------------------------
		// 3. konkrete Seite (nur aktives Modul) → unterstes Element
		// ---------------------------------------------------------
		if ($exists($modules[$activeModuleId], $page . '.tpl')) {
			$add($modules[$activeModuleId]['INFO']['ModulDir'] . '/system/template/' . $page . '.tpl');
		}

		// ---------------------------------------------------------
		// 4. base__base.tpl (admin__admin.tpl) – ALLE Module sammeln
		// ---------------------------------------------------------
		$baseBase = "{$base}__{$base}.tpl";

		foreach ($modules as $moduleDir) {
			$tplPath = $moduleDir['INFO']['ModulDir'] . '/system/template/' . $baseBase;

			if ($exists($moduleDir, $baseBase)) {
				$add($tplPath);                                   // TPL + PHP (falls vorhanden)
			} elseif (file_exists($php = $toPhp($tplPath))) {
				$phpChain[] = $php;                               // TPL fehlt, aber PHP existiert → nur PHP
			}
		}

		// ---------------------------------------------------------
		// 5. Rekursiv: *__base.tpl nach oben suchen
		//    – base__base.tpl dabei überspringen
		// ---------------------------------------------------------
		$currentBase  = $base;
		$visitedBases = [];

		while (true) {
			$candidates = [];

			foreach ($modules as $moduleDir) {
				foreach (glob($moduleDir['INFO']['ModulDir'] . "/system/template/*__{$currentBase}.tpl") as $file) {

					// base__base.tpl überspringen (haben wir in Schritt 4 schon)
					if (basename($file) === $baseBase) {
						continue;
					}

					$candidates[] = [
						'tpl'     => $file,
						'newBase' => explode('__', basename($file), 2)[0],
					];
				}
			}

			if (empty($candidates)) {
				break;
			}

			// Sortierung:
			//  - zuerst Templates, deren newBase == currentBase (z.B. frontend__frontend)
			//  - danach Templates, deren newBase != currentBase (z.B. index__frontend)
			//  (weil wir am Ende die Kette umdrehen!)
			usort($candidates, fn($a, $b) => ($b['newBase'] === $currentBase) <=> ($a['newBase'] === $currentBase));

			// Alle Kandidaten in Chain aufnehmen (ohne Duplikate)
			foreach ($candidates as $cand) {
				if (!in_array($cand['tpl'], $tplChain, true)) {
					$add($cand['tpl']);
				}
			}

			// Nächste Base: erste, deren newBase != currentBase (also "eine Ebene höher")
			$nextBase = null;
			foreach ($candidates as $cand) {
				if ($cand['newBase'] !== $currentBase) {
					$nextBase = $cand['newBase'];
					break;
				}
			}

			if ($nextBase === null || isset($visitedBases[$nextBase])) {
				break;
			}

			$visitedBases[$nextBase] = true;
			$currentBase             = $nextBase;
		}

		// 6. root.tpl = currentBase.tpl (oberstes Template, z.B. index.tpl)
		$rootTplName = $currentBase . '.tpl';

		// 6a. root__root.php zuerst einfügen (z.B. index__index.php)
		$rootBaseBasePhp = $currentBase . '__' . $currentBase . '.php';

		foreach ($first as $moduleDir) {
			if (file_exists($phpPath = $moduleDir['INFO']['ModulDir'] . '/system/' . $rootBaseBasePhp)) {
				$phpChain[] = $phpPath;   // index__index.php
				break;
			}
		}

		// 6b. root.tpl + root.php danach einfügen (z.B. index.tpl, index.php)
		foreach ($first as $moduleDir) {
			if ($exists($moduleDir, $rootTplName)) {
				$add($moduleDir['INFO']['ModulDir'] . '/system/template/' . $rootTplName);
				break;
			}
		}

		// ---------------------------------------------------------
		// 7. Kette umdrehen: von root → Seite
		// ---------------------------------------------------------
		$tplChain = array_reverse(array_values(array_unique($tplChain)));
		$phpChain = array_reverse(array_values(array_unique($phpChain)));

		return [
			'extends' => 'extends:' . implode('|', $tplChain),
			'tpl'     => $tplChain,
			'php'     => $phpChain
		];
	}
}
