<?php
namespace fremeo\core;

use RuntimeException;
use Smarty;
use Smarty_Internal_TemplateCompilerBase;

class LanguageVariable {
	private ?array $D;
	private array $providers = [];
	private array $defaults = [];
	private array $rows = [];
	private array $theme = [];
	private string $language = 'DE';

	public function __construct(private Container $C, array &$D) {
		$this->D = &$D;
	}

	public function configure(Smarty $smarty, array $theme = [], string $language = 'DE'): void {
		$this->language = strtoupper($language);
		if (!preg_match('/^[A-Z]{2}$/D', $this->language)) {
			throw new RuntimeException('Ungueltige Sprache: ' . $language);
		}
		$this->providers = $this->C['Template']->getModuleInheritance();
		$files = [];
		foreach ($this->providers as &$provider) {
			$path = realpath($provider['Directory']);
			if ($path === false) throw new RuntimeException('Modulverzeichnis fehlt: ' . $provider['Directory']);
			$provider['Directory'] = str_replace('\\', '/', $path);
			array_push($files, ...glob($provider['Directory'] . '/system/template/language/*.php'));
		}
		unset($provider);
		sort($files);
		$directory = $smarty->getCompileDir();
		$this->C['Filesystem']->mkdir($directory);
		$manifest = $directory . '/language-files.json';
		$fileList = json_encode($files, JSON_THROW_ON_ERROR);
		if (!is_file($manifest) || file_get_contents($manifest) !== $fileList) {
			if (is_file($manifest)) $this->clearCache();
			$this->C['Filesystem']->dumpFile($manifest, $fileList);
		}
		$this->theme = $theme;
		$smarty->compile_id = 'language_' . $this->language . '_' . hash('sha256', json_encode([
			$theme['Branch'] ?? '', $theme['Template'] ?? '', $this->providers,
		], JSON_THROW_ON_ERROR));
		require_once __DIR__ . '/smarty_plugin/compiler.i18n.php';
		$plugin = new \Smarty_I18n(strtolower($this->language), [$this, 'resolveTemplate']);
		$smarty->unregisterPlugin(Smarty::PLUGIN_COMPILER, 'i18n');
		$smarty->registerPlugin(Smarty::PLUGIN_COMPILER, 'i18n', [$plugin, 'compile']);
	}

	public function getDefaults(string $module, string $language): array {
		$language = strtoupper($language);
		if (!preg_match('/^[A-Z]{2}$/D', $language) || !isset($this->D['MODULE']['D'][$module])) {
			throw new RuntimeException("Unbekanntes Sprachpaket: $module/$language");
		}
		if (!isset($this->defaults[$module][$language])) {
			$file = $this->D['MODULE']['D'][$module]['INFO']['ModulDir'] . '/system/template/language/' . strtolower($language) . '.php';
			$values = [];
			if (is_file($file)) {
				$groups = (static fn (string $path) => include $path)($file);
				if (!is_array($groups)) throw new RuntimeException("Sprachpaket muss ein Array liefern: $file");
				foreach ($groups as $group) {
					if (!is_array($group)) throw new RuntimeException("Ungueltige Sprachgruppe: $file");
					foreach ($group as $key => $text) {
						if (!is_string($key) || !is_string($text) || array_key_exists($key, $values)) {
							throw new RuntimeException("Ungueltiger oder doppelter Sprachschluessel: $file/$key");
						}
						$values[$key] = $text;
					}
				}
			}
			$this->defaults[$module][$language] = $values;
		}
		return $this->defaults[$module][$language];
	}

	public function resolve(string $module, string $key, string $source = ''): string {
		if (!isset($this->providers[$module]) || !preg_match('/^[A-Za-z0-9_.-]+$/D', $key)) {
			throw new RuntimeException("Ungueltige Sprachvariable: $module:$key");
		}
		$chain = $this->providers[$module]['Chain'];
		$selected = $this->theme['Template'] ?? $module;
		if (isset($this->providers[$selected]) && in_array($module, $this->providers[$selected]['Chain'], true)) {
			$chain = $this->providers[$selected]['Chain'];
		}
		$chain = array_reverse($chain);
		foreach (array_unique([$this->language, 'DE']) as $language) {
			foreach ($chain as $provider) {
				if (!isset($this->rows[$provider])) {
					$filter = ['LANGUAGE' => ['I18N' => []]];
					$data = null;
					$this->C[$provider]['CData']->get_object($data, $filter);
					$this->rows[$provider] = $data['LANGUAGE']['D'] ?? [];
				}
				$id = in_array($provider, $this->providers[$module]['Chain'], true) ? $key : "$module:$key";
				$row = $this->rows[$provider][$language]['I18N']['D'][$id] ?? null;
				if (is_array($row) && (int)($row['Missing'] ?? 0) !== 1) {
					return (string)($row['Value'] ?? '');
				}
			}
			foreach ($chain as $provider) {
				$id = in_array($provider, $this->providers[$module]['Chain'], true) ? $key : "$module:$key";
				$values = $this->getDefaults($provider, $language);
				if (array_key_exists($id, $values)) return $values[$id];
			}
		}

		// Auch parallele Compiler duerfen einen inzwischen gepflegten Eintrag nicht ersetzen.
		$directory = $this->D['MODULE']['D'][$module]['INFO']['DataDir'];
		$this->C['Filesystem']->mkdir($directory);
		$lock = fopen($directory . '/language.lock', 'c');
		if ($lock === false) throw new RuntimeException("Sprachvariablen-Sperre nicht verfuegbar: $module");
		try {
			if (!flock($lock, LOCK_EX)) throw new RuntimeException("Sprachvariablen-Sperre fehlgeschlagen: $module");
			$filter = ['LANGUAGE' => ['I18N' => []]];
			$this->C[$module]['CData']->get_object($current, $filter);
			$row = $current['LANGUAGE']['D'][$this->language]['I18N']['D'][$key] ?? null;
			if ($row === null) {
				$update = ['LANGUAGE' => ['D' => [$this->language => ['I18N' => ['D' => [
					$key => ['Value' => '', 'Missing' => 1, 'Source' => $source],
				]]]]]];
				$this->C[$module]['CData']->set_object($update);
				unset($this->rows[$module]);
			} elseif ((int)($row['Missing'] ?? 0) !== 1) {
				return (string)($row['Value'] ?? '');
			}
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		return htmlspecialchars($key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	public function save(string $module, array $posted, array $languages): void {
		$directory = $this->D['MODULE']['D'][$module]['INFO']['DataDir'];
		$this->C['Filesystem']->mkdir($directory);
		$lock = fopen($directory . '/language.lock', 'c');
		if ($lock === false) throw new RuntimeException("Sprachvariablen-Sperre nicht verfuegbar: $module");
		try {
			if (!flock($lock, LOCK_EX)) throw new RuntimeException("Sprachvariablen-Sperre fehlgeschlagen: $module");
			$filter = ['LANGUAGE' => ['I18N' => []]];
			$this->C[$module]['CData']->get_object($current, $filter);
			$update = [];
			foreach ($languages as $language) {
				$defaults = $this->getDefaults($module, $language);
				foreach ($posted[$language]['I18N']['D'] ?? [] as $key => $row) {
					if ($row === '__DELETE__') {
						foreach (array_unique([...$languages, ...array_keys($current['LANGUAGE']['D'] ?? [])]) as $iso) {
							$update['LANGUAGE']['D'][$iso]['I18N']['D'][$key] = '__DELETE__';
						}
					} elseif (is_array($row) && !empty($row['Reset'])) {
						$update['LANGUAGE']['D'][$language]['I18N']['D'][$key] = '__DELETE__';
					} elseif (is_array($row) && !empty($row['Empty'])) {
						$update['LANGUAGE']['D'][$language]['I18N']['D'][$key] = ['Value' => '', 'Missing' => 0];
					} elseif (is_array($row) && array_key_exists('Value', $row)) {
						if (!is_scalar($row['Value'])) throw new RuntimeException("Ungueltiger Sprachvariablenwert: $module/$language/$key");
						$value = (string)$row['Value'];
						$update['LANGUAGE']['D'][$language]['I18N']['D'][$key] = array_key_exists($key, $defaults) && $value === $defaults[$key]
							? '__DELETE__' : ['Value' => $value, 'Missing' => 0];
					}
				}
			}
			if ($update) {
				$this->C[$module]['CData']->set_object($update);
				$this->clearCache();
			}
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	public function resolveTemplate(string $key, ?string $module, Smarty_Internal_TemplateCompilerBase $compiler): string {
		$source = str_replace('\\', '/', $compiler->template->source->filepath);
		$explicitModule = $module;
		if ($module === null) {
			foreach ($this->providers as $id => $provider) {
				if (str_starts_with($source, $provider['Directory'] . '/system/template/')) $module = $id;
			}
		}
		if (str_contains($key, ':')) {
			[$owner, $key] = explode(':', $key, 2);
			if ($explicitModule !== null && $owner !== $module) throw new RuntimeException('Widerspruechliche i18n-Modulangabe.');
			$module = $owner;
		}
		if ($module === null) throw new RuntimeException("Ursprungsmodul der Sprachvariable fehlt: $source");
		$text = $this->resolve($module, $key, $source);
		// Sprachdateien als echte Smarty-Abhaengigkeiten pruefen, ohne erneut Texte zu laden.
		foreach ($this->defaults as $provider => $languages) {
			foreach ($languages as $language => $values) {
				$file = $this->D['MODULE']['D'][$provider]['INFO']['ModulDir'] . '/system/template/language/' . strtolower($language) . '.php';
				if (is_file($file)) {
					$compiler->parent_compiler->template->compiled->file_dependency[sha1($file)] = [$file, filemtime($file), 'file'];
				}
			}
		}
		return $text;
	}

	public function clearCache(): void {
		$this->defaults = $this->rows = [];
		$this->C['Smarty']->clearCompiledTemplate();
		$this->C['Smarty']->clearAllCache();
	}
}
