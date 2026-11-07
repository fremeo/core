<?php
namespace fremeo\core;

class Setting {
    private Container $C;
    // CData setzt den referenzierten Datenbaum waehrend einer Abfrage kurz auf null.
    private ?array $D;

    public function __construct(Container $C, array &$D) {
        $this->C = $C;
        $this->D = &$D;
    }

    public function get(string $moduleId, string $settingId): mixed {
        $moduleId = trim((string) $moduleId);
        $settingId = trim((string) $settingId, ". ");

        if ($moduleId === '' || $settingId === '' || !isset($this->C[$moduleId]['CData'])) {
            return null;
        }

		$f['SETTING'] = [];
		$this->C[$moduleId]['CData']->get_object($d, $f);

		// Bestehende getrennte Theme-Auswahl bis zum naechsten Speichern uebernehmen.
		if ($settingId === 'theme' && ($d['SETTING']['D']['theme_variant']['Value'] ?? '') !== '') {
			return $d['SETTING']['D']['theme_variant']['Value'];
		}
		if (isset($d['SETTING']['D'][$settingId]) && array_key_exists('Value', $d['SETTING']['D'][$settingId])) {
			return $d['SETTING']['D'][$settingId]['Value'];
		}
        
        return $this->readDefaultSetting($moduleId, $settingId);
    }

    public function set(string $moduleId, string $settingId, mixed $value): bool {
        $moduleId = trim((string) $moduleId);
        $settingId = trim((string) $settingId, ". ");

        if ($moduleId === '' || $settingId === '') {
            return false;
        }

        if (!isset($this->C[$moduleId]['CData'])) {
            return false;
        }

        $d['SETTING']['D'][$settingId]['Value'] = $value;
		if ($settingId === 'theme') {
			$d['SETTING']['D']['theme_variant'] = '__DELETE__';
		}

        $this->C[$moduleId]['CData']->set_object($d);
        return true;
    }



    private function readDefaultSetting(string $moduleId, string $settingId): mixed {
        $module = $this->D['MODULE']['D'][$moduleId] ?? null;
        if (!is_array($module)) {
            return null;
        }

        $file = ($module['INFO']['ModulDir'] ?? '') . '/system/default/default.config.php';
        if (!is_file($file)) {
            return null;
        }

        $config = include $file;
        if (!is_array($config)) {
            return null;
        }

        return $config['SETTING'][$settingId]['Value'] ?? $this->findValueBySettingId($config, $settingId);
    }

    private function findValueBySettingId(array $node, string $settingId): mixed {
        $normalized = trim((string) $settingId, ". ");
        if ($normalized === '') {
            return null;
        }

        $keys = array_values(array_filter(explode('.', $normalized), static fn ($key) => trim((string) $key) !== ''));
        if ($keys === []) {
            return null;
        }

        $stack = [$node];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (!is_array($current)) {
                continue;
            }

            foreach ($current as $key => $value) {
                if (!is_array($value)) {
                    continue;
                }

                if (array_key_exists('Value', $value) && $key === end($keys)) {
                    return $value['Value'];
                }

                $stack[] = $value;
            }
        }

        return null;
    }
}
