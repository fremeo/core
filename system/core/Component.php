<?php
namespace fremeo\core;

use RuntimeException;

class Component {
	public static function load(string $module, string $type, array $provider, array $context = []): array {
		if ((int)($provider['Active'] ?? 0) !== 1
			|| !in_array($provider['INFO']['Type'] ?? '', ['core', 'template', 'module'], true)) {
			throw new RuntimeException("Komponenten-Modul nicht verfuegbar: $module");
		}
		if (!preg_match('/^[a-z][a-z0-9_-]*$/D', $type)) throw new RuntimeException('Ungueltiger Komponenten-Typ.');
		$root = realpath($provider['INFO']['ModulDir']);
		$directory = $root === false ? false : realpath($root . '/system/template/components/' . $type);
		if ($directory === false || !str_starts_with($directory . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) {
			throw new RuntimeException("Komponente nicht gefunden: $module:$type");
		}
		$manifestFile = realpath($directory . '/component.json');
		$file = realpath($directory . '/' . $type . '.tpl');
		foreach ([$manifestFile, $file] as $path) {
			if ($path === false || !is_file($path) || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) {
				throw new RuntimeException("Komponenten-Dateien fehlen oder liegen ausserhalb der Komponente: $module:$type");
			}
		}
		$manifest = json_decode(file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($manifest) || ($manifest['type'] ?? null) !== $type || !is_array($manifest['defaults'] ?? [])
			|| !is_array($manifest['settings'] ?? []) || (isset($manifest['name']) && !is_string($manifest['name']))
			|| (array_key_exists('editorLanguage', $manifest) && (!is_string($manifest['editorLanguage']) || !preg_match('/^[A-Z]{2}$/D', $manifest['editorLanguage'])))
			|| (isset($manifest['builder']) && $manifest['builder'] !== 'container')) {
			throw new RuntimeException("Ungueltiges Komponenten-Manifest: $module:$type");
		}
		$extension = [];
		$phpFile = $directory . DIRECTORY_SEPARATOR . $type . '.php';
		if (file_exists($phpFile)) {
			$phpFile = realpath($phpFile);
			if ($phpFile === false || !is_file($phpFile) || !str_starts_with($phpFile, $directory . DIRECTORY_SEPARATOR)) {
				throw new RuntimeException('Komponenten-PHP liegt ausserhalb der Komponente.');
			}
			$factory = require $phpFile;
			if (!$factory instanceof \Closure) throw new RuntimeException('Komponenten-PHP muss eine Closure liefern.');
			$extension = $factory($provider, $context);
			if (!is_array($extension) || array_diff(array_keys($extension), ['settings', 'data', 'validate'])
				|| !is_array($extension['settings'] ?? []) || (isset($extension['data']) && !$extension['data'] instanceof \Closure)
				|| (isset($extension['validate']) && !$extension['validate'] instanceof \Closure)) {
				throw new RuntimeException('Ungueltige Komponenten-PHP-Schnittstelle.');
			}
			if (isset($extension['validate'])) $manifest['validate'] = $extension['validate'];
			foreach ($extension['settings'] ?? [] as $key => $field) {
				if (!isset($manifest['settings'][$key]) || !is_array($field)) throw new RuntimeException('Unbekanntes Komponenten-PHP-Einstellungsfeld.');
				$manifest['settings'][$key] = array_replace($manifest['settings'][$key], $field);
			}
		}
		$validateDefinition = function (string $key, mixed $field, bool $child = false) use (&$validateDefinition): void {
			if (!is_string($key) || !preg_match('/^[a-z][A-Za-z0-9_]*$/D', $key) || !is_array($field)
				|| !is_string($field['label'] ?? null)
				|| !in_array($field['type'] ?? '', ['string', 'boolean', 'integer', 'number', 'tree'], true)
				|| (isset($field['enum']) && (!is_array($field['enum']) || !array_is_list($field['enum']) || !$field['enum']))
				|| (isset($field['choices']) && (!is_array($field['choices']) || !array_is_list($field['choices']) || !$field['choices']))
				|| (isset($field['min']) && !is_int($field['min']) && !is_float($field['min']))
				|| (isset($field['max']) && !is_int($field['max']) && !is_float($field['max']))
				|| ((isset($field['min']) || isset($field['max'])) && !in_array($field['type'], ['integer', 'number'], true))
				|| (isset($field['nullable']) && !is_bool($field['nullable']))
				|| (isset($field['translatable']) && (!is_bool($field['translatable']) || ($field['translatable'] && ($field['type'] !== 'string' || $child))))
				|| (isset($field['format']) && ($field['format'] !== 'link' || $field['type'] !== 'string'))
				|| (array_key_exists('input', $field) && (!in_array($field['input'], ['textarea', 'wysiwyg'], true) || $field['type'] !== 'string' || isset($field['enum']) || isset($field['choices'])))
				|| (isset($field['min'], $field['max']) && $field['min'] > $field['max'])) {
				throw new RuntimeException("Ungueltiges Komponenten-Einstellungsfeld: $key");
			}
			foreach ($field['choices'] ?? [] as $choice) {
				if (!is_array($choice) || !array_key_exists('value', $choice) || !is_string($choice['label'] ?? null)
					|| !is_scalar($choice['value'])) throw new RuntimeException("Ungueltige Komponenten-Auswahl: $key");
			}
			if ($field['type'] === 'tree') {
				if ($child || !is_string($field['children'] ?? null) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $field['children'])
					|| !is_array($field['fields'] ?? null) || !$field['fields'] || isset($field['fields'][$field['children']])
					|| isset($field['enum'])) throw new RuntimeException("Ungueltige Komponenten-Baumdefinition: $key");
				foreach ($field['fields'] as $name => $definition) {
					if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $name) || !is_array($definition)
						|| !array_key_exists('default', $definition)) throw new RuntimeException("Ungueltiges Komponenten-Baumfeld: $key");
					$validateDefinition(lcfirst($name), $definition, true);
				}
			}
		};
		foreach ($manifest['settings'] ?? [] as $key => $field) {
			if (!is_string($key) || !array_key_exists($key, $manifest['defaults'] ?? [])) {
				throw new RuntimeException('Komponenten-Einstellung benoetigt einen Standardwert.');
			}
			$validateDefinition($key, $field);
		}
		self::options($manifest, []);
		foreach ($manifest['settings'] ?? [] as $key => $field) {
			foreach ($field['enum'] ?? [] as $value) self::options($manifest, [$key => $value]);
			foreach ($field['choices'] ?? [] as $choice) self::options($manifest, [$key => $choice['value']]);
			if ($field['type'] === 'tree') {
				$node = array_map(static fn ($definition) => $definition['default'], $field['fields']);
				$node[$field['children']] = [];
				self::options($manifest, [$key => [$node]]);
				foreach ($field['fields'] as $name => $definition) {
					foreach ($definition['enum'] ?? [] as $value) {
						self::options($manifest, [$key => [array_replace($node, [$name => $value])]]);
					}
				}
			}
		}
		$editor = null;
		if (file_exists($directory . DIRECTORY_SEPARATOR . $type . '.js')) {
			$editor = realpath($directory . DIRECTORY_SEPARATOR . $type . '.js');
			if ($editor === false || !is_file($editor) || !str_starts_with($editor, $directory . DIRECTORY_SEPARATOR)) throw new RuntimeException('Komponenten-JavaScript liegt ausserhalb der Komponente.');
		}
		$settings = null;
		if (file_exists($directory . DIRECTORY_SEPARATOR . $type . '.settings.tpl')) {
			$settings = realpath($directory . DIRECTORY_SEPARATOR . $type . '.settings.tpl');
			if ($settings === false || !is_file($settings) || !str_starts_with($settings, $directory . DIRECTORY_SEPARATOR)) throw new RuntimeException('Komponenten-Einstellungs-TPL liegt ausserhalb der Komponente.');
		}
		return ['manifest' => $manifest, 'file' => $file, 'data' => $extension['data'] ?? null, 'editor' => $editor, 'settings' => $settings];
	}

	public static function localizedOptions(array $manifest, array $options, array $translations, string $language): array {
		if (!preg_match('/^[A-Z]{2}$/D', $language)) throw new RuntimeException('Ungueltige Komponenten-Sprache.');
		$keys = array_keys(array_filter($manifest['settings'] ?? [], static fn ($field) => $field['translatable'] ?? false));
		foreach ($translations as $code => $translation) {
			if (!is_string($code) || !preg_match('/^[A-Z]{2}$/D', $code) || !is_array($translation)
				|| !is_bool($translation['active'] ?? null) || array_diff(array_keys($translation), ['active', 'options'])
				|| (array_key_exists('options', $translation) && (!is_array($translation['options'])
					|| array_diff(array_keys($translation['options']), $keys) || array_diff($keys, array_keys($translation['options']))))) {
				throw new RuntimeException('Ungueltige Komponenten-Uebersetzung.');
			}
			if (!$keys) throw new RuntimeException('Diese Komponente hat keine uebersetzbaren Einstellungen.');
			if (array_key_exists('options', $translation)) self::options($manifest, array_replace($options, $translation['options']));
		}
		$resolved = self::options($manifest, $options);
		if (!$keys) return ['options' => $resolved, 'visible' => true];
		$current = $translations[$language] ?? [];
		$visible = ($current['active'] ?? false) === true;
		$content = array_key_exists('options', $current) ? $current
			: (($translations['EN']['active'] ?? false) ? $translations['EN'] : []);
		if (!$visible || !array_key_exists('options', $content)) {
			return ['options' => $resolved, 'visible' => false];
		}
		return ['options' => array_replace($resolved, $content['options']), 'visible' => true];
	}

	public static function options(array $manifest, array $options): array {
		$options = array_replace($manifest['defaults'] ?? [], $options);
		if (isset($manifest['variants']) && (!is_array($manifest['variants'])
			|| !in_array($options['variant'] ?? null, $manifest['variants'], true))) {
			throw new RuntimeException('Ungueltige Komponenten-Variante.');
		}
		$validate = function (array $field, mixed $value, string $key) use (&$validate): void {
			if ($value === null && ($field['nullable'] ?? false)) return;
			$valid = match ($field['type']) {
				'string' => is_string($value),
				'boolean' => is_bool($value),
				'integer' => is_int($value),
				'number' => (is_int($value) || is_float($value)) && is_finite((float)$value),
				'tree' => is_array($value) && array_is_list($value),
			};
			if (!$valid || (isset($field['enum']) && !in_array($value, $field['enum'], true))
				|| (isset($field['choices']) && !in_array($value, array_column($field['choices'], 'value'), true))
				|| (is_numeric($value) && isset($field['min']) && $value < $field['min'])
				|| (is_numeric($value) && isset($field['max']) && $value > $field['max'])) {
				throw new RuntimeException("Ungueltige Komponenten-Einstellung: $key");
			}
			if (($field['format'] ?? '') === 'link' && (preg_match('/[\x00-\x20\x7f\\\\]/', $value)
				|| (preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $scheme)
					&& !in_array(strtolower($scheme[1]), ['http', 'https', 'mailto', 'tel'], true)))) {
				throw new RuntimeException("Ungueltiger Komponenten-Link: $key");
			}
			if ($field['type'] === 'tree') {
				foreach ($value as $index => $node) {
					if (!is_array($node) || array_diff(array_keys($node), [...array_keys($field['fields']), $field['children']])) {
						throw new RuntimeException("Ungueltiger Komponenten-Baumpunkt: $key");
					}
					foreach ($field['fields'] as $name => $definition) {
						if (!array_key_exists($name, $node)) throw new RuntimeException("Komponenten-Baumfeld fehlt: $key.$name");
						$validate($definition, $node[$name], "$key.$index.$name");
					}
					if (!array_key_exists($field['children'], $node) || !is_array($node[$field['children']])) {
						throw new RuntimeException("Komponenten-Unterpunkte fehlen: $key");
					}
					$validate(array_replace($field, ['nullable' => false]), $node[$field['children']], "$key.$index." . $field['children']);
				}
			}
		};
		foreach ($manifest['settings'] ?? [] as $key => $field) {
			$validate($field, $options[$key] ?? null, $key);
		}
		if (isset($manifest['validate'])) ($manifest['validate'])($options);
		return $options;
	}
}
