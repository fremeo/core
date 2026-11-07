<?php
namespace fremeo\core;

use RuntimeException;
use Composer\InstalledVersions;
use MatthiasMullie\Minify;
use MatthiasMullie\PathConverter\Converter;
use Wikimedia\CSS\Objects\Token;
use Wikimedia\CSS\Objects\AtRule;
use Wikimedia\CSS\Parser\Parser;
use Wikimedia\CSS\Util;

class Template {

	// CData setzt den referenzierten Datenbaum waehrend einer Abfrage kurz auf null.
	private ?array $D;
	private array $themeFallbacks = [];

	public function __construct(private Container $C, array &$D) {
		$this->D = &$D;
	}

	public function getModuleInheritance(?array $modules = null, bool $activeOnly = true): array {
		$providers = [];
		foreach ($modules ?? $this->D['MODULE']['D'] as $id => $module) {
			$type = $module['INFO']['Type'] ?? $module['Type'] ?? 'library';
			if (in_array($type, ['core', 'template', 'module'], true)) {
				$providers[$id] = ['Directory' => str_replace('\\', '/', $module['INFO']['ModulDir']), 'Type' => $type, 'Parent' => null];
			}
		}
		foreach ($providers as $id => &$provider) {
			$file = $provider['Directory'] . '/composer.json';
			$meta = is_file($file) ? json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : [];
			$parent = $meta['extra']['module-parent'] ?? null;
			if ($parent === null) continue;
			if (!is_string($parent) || !isset($providers[$parent]) || !isset($meta['require'][$parent])
				|| ($provider['Type'] === 'template' && !in_array($providers[$parent]['Type'], ['core', 'template'], true))) {
				throw new RuntimeException("Module-Elternmodul fehlt, ist ungueltig oder nicht unter require angegeben: $id");
			}
			$provider['Parent'] = $parent;
		}
		unset($provider);

		foreach ($providers as $id => &$provider) {
			$chain = [];
			for ($current = $id; $current !== null; $current = $providers[$current]['Parent']) {
				if (in_array($current, $chain, true)) {
					throw new RuntimeException("Zyklische Module-Vererbung: $id");
				}
				$chain[] = $current;
			}
			$provider['Chain'] = array_reverse($chain);
			$provider['Depth'] = count($chain) - 1;
		}
		unset($provider);
		uasort($providers, fn ($a, $b) => $a['Depth'] <=> $b['Depth']);
		$modules ??= $this->D['MODULE']['D'];
		return $activeOnly ? array_filter($providers, static fn ($provider) => !array_filter($provider['Chain'], static fn ($id) => $providers[$id]['Type'] !== 'core' && (int)($modules[$id]['Active'] ?? 1) !== 1)) : $providers;
	}

	public function getThemeModules(?array $modules = null): array {
		return array_filter($this->getModuleInheritance($modules), static fn ($module) => in_array($module['Type'], ['core', 'template'], true));
	}

	public function getThemes(string $provider, ?array $contributors = null, ?array $moduleRows = null): array {
		$modules = $this->getThemeModules($moduleRows);
		if (!isset($modules[$provider])) {
			throw new RuntimeException("Unbekannter Theme-Anbieter: $provider");
		}
		$contributors ??= array_keys(array_filter($modules, fn ($module) => in_array($provider, $module['Chain'], true)));
		$themes = [];
		foreach ($contributors as $id) {
			if (!isset($modules[$id]) || !in_array($provider, $modules[$id]['Chain'], true)) {
				throw new RuntimeException("Unpassender Theme-Beitrag: $id");
			}
			foreach (glob($modules[$id]['Directory'] . '/system/template/theme/*', GLOB_ONLYDIR) as $dir) {
				$name = basename($dir);
				if (is_link($dir) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/D', $name)) {
					throw new RuntimeException("Ungueltiger Theme-Ordner: $dir");
				}
				$parts = explode('__', $name);
				if (count($parts) > 2 || in_array('', $parts, true)) {
					throw new RuntimeException("Ungueltige Theme-Ableitung: $name");
				}
				$themes[$name]['Directories'][] = $dir;
				$themes[$name]['ParentName'] = count($parts) === 2 ? $parts[0] : null;
			}
		}
		foreach ($themes as $name => &$theme) {
			$parents = [];
			if ($theme['ParentName'] !== null) {
				foreach (array_keys($themes) as $candidate) {
					$parts = explode('__', $candidate);
					if (end($parts) === $theme['ParentName']) {
						$parents[] = $candidate;
					}
				}
				if (count($parents) !== 1) {
					throw new RuntimeException("Theme-Elternteil fehlt oder ist mehrdeutig: $name");
				}
			}
			$theme['Parent'] = $parents[0] ?? null;
		}
		unset($theme);
		foreach ($themes as $name => &$theme) {
			$chain = [];
			for ($current = $name; $current !== null; $current = $themes[$current]['Parent']) {
				if (in_array($current, $chain, true)) {
					throw new RuntimeException("Zyklische Theme-Vererbung: $name");
				}
				$chain[] = $current;
			}
			$theme['Chain'] = array_reverse($chain);
			$theme['Root'] = $theme['Chain'][0];
		}
		unset($theme);
		return $themes;
	}

	public function getThemeChoices(string $provider, ?array $moduleRows = null): array {
		$modules = $this->getThemeModules($moduleRows);
		if (!isset($modules[$provider])) {
			throw new RuntimeException("Unbekannter Theme-Anbieter: $provider");
		}
		$choices = [];
		foreach ($modules as $id => $module) {
			if (!in_array($provider, $module['Chain'], true)) continue;
			$themes = $this->getThemes($module['Chain'][0], $module['Chain'], $moduleRows);
			foreach ($themes as $name => $theme) {
				$directory = $module['Directory'] . '/system/template/theme/' . $name;
				if (!in_array($directory, $theme['Directories'], true)) continue;
				$choices["$id:$name"] = [
					'Template' => $id, 'Name' => $name, 'Templates' => $module['Chain'],
					'Theme' => $theme, 'Themes' => $themes,
				];
			}
		}
		return $choices;
	}

	public function getThemeOptions(string $provider): array {
		$options = [];
		foreach ($this->getThemeChoices($provider) as $key => $choice) {
			$options[] = $key . '|' . $choice['Template'] . ' : ' . $choice['Name'];
		}
		return $options;
	}

	public function getThemeSelection(string $provider, ?array $moduleRows = null): array {
		$choices = $this->getThemeChoices($provider, $moduleRows);
		$selected = (string)($this->C['Setting']->get($provider, 'theme') ?? 'basic');
		if (isset($choices[$selected])) return ['Key' => $selected] + $choices[$selected];
		// Alte Theme-Namen bevorzugen das Elternlayout; eindeutige Child-Themes bleiben kompatibel.
		if (!str_contains($selected, ':')) {
			$key = "$provider:$selected";
			if (isset($choices[$key])) return ['Key' => $key] + $choices[$key];
			$matches = array_filter($choices, static fn ($choice) => $choice['Name'] === $selected);
			if (count($matches) > 1) {
				throw new RuntimeException("Mehrdeutige Theme-Auswahl fuer $provider: $selected");
			}
			if ($matches) {
				$key = array_key_first($matches);
				return ['Key' => $key] + $matches[$key];
			}
		}
		// Nicht mehr verfuegbare Auswahlen fallen zurueck, nicht fehlerhafte Theme-Manifeste.
		$name = str_contains($selected, ':') ? explode(':', $selected, 2)[1] : $selected;
		$main = explode('__', $name, 2)[0];
		$fallbackProvider = $provider;
		if (str_contains($selected, ':')) {
			$origin = explode(':', $selected, 2)[0];
			$inheritance = $this->getModuleInheritance($moduleRows, false);
			foreach (array_reverse($inheritance[$origin]['Chain'] ?? []) as $id) {
				if ($id !== $origin && isset($choices["$id:$main"]) && in_array($provider, $inheritance[$id]['Chain'], true)) {
					$fallbackProvider = $id;
					break;
				}
			}
		}
		$key = "$fallbackProvider:$main";
		if (!isset($choices[$key]) || $choices[$key]['Theme']['Parent'] !== null) {
			$key = "$fallbackProvider:basic";
		}
		if (!isset($choices[$key])) {
			$mainThemes = array_filter($choices, static fn ($choice) => $choice['Template'] === $fallbackProvider && $choice['Theme']['Parent'] === null);
			if (!$mainThemes) {
				$mainThemes = array_filter($choices, static fn ($choice) => $choice['Template'] === $provider && $choice['Theme']['Parent'] === null);
			}
			$key = array_key_first($mainThemes);
		}
		if ($key === null) {
			throw new RuntimeException("Kein gueltiges Basis-Theme fuer $fallbackProvider");
		}
		if (!isset($this->themeFallbacks["$provider:$selected"])) {
			error_log("Theme-Auswahl $provider / $selected nicht verfuegbar; verwende $key.");
			$this->themeFallbacks["$provider:$selected"] = true;
		}
		return ['Key' => $key] + $choices[$key];
	}

	public function resolveTheme(array $tpl): array {
		if (!$tpl) {
			return [];
		}
		$modules = $this->getThemeModules();
		$root = pathinfo($tpl[0], PATHINFO_FILENAME);
		$branch = null;
		$owners = [];
		foreach ($tpl as $file) {
			$file = str_replace('\\', '/', $file);
			foreach ($modules as $id => $module) {
				if (!str_starts_with($file, $module['Directory'] . '/system/template/')) {
					continue;
				}
				$parts = explode('__', pathinfo($file, PATHINFO_FILENAME));
				if (count($parts) === 2 && $parts[0] === $root && $parts[1] !== $root) {
					if ($branch !== null && $branch !== $parts[1]) {
						throw new RuntimeException('Die Template-Kette enthaelt mehrere Layout-Zweige.');
					}
					$branch = $parts[1];
					$owners[$id] = $module;
				}
			}
		}
		if ($branch === null) {
			return [];
		}
		$candidates = array_filter($owners, fn ($module) => $module['Type'] === 'template') ?: $owners;
		$roots = array_unique(array_map(fn ($module) => $module['Chain'][0], $candidates));
		if (count($roots) !== 1) {
			throw new RuntimeException("Mehrdeutiger Theme-Anbieter fuer den Zweig $branch");
		}
		$provider = reset($roots);
		$selection = $this->getThemeSelection($provider);
		$themes = $selection['Themes'];
		$selected = $selection['Name'];
		$main = $selection['Theme']['Root'];
		$variant = $selected === $main ? '' : $selected;
		$layers = [];
		foreach ($themes[$selected]['Chain'] as $name) {
			array_push($layers, ...$themes[$name]['Directories']);
		}
		return [
			'Provider' => $provider, 'Template' => $selection['Template'], 'Selection' => $selection['Key'],
			'Branch' => $branch, 'Main' => $main, 'Variant' => $variant,
			'Chain' => $themes[$selected]['Chain'], 'Layers' => $layers,
			'CustomCss' => (string)($this->C['Setting']->get($provider, "theme_css_$main") ?? ''),
		];
	}

	public function publishTheme(array $theme, string $target, string $baseUrl): array {
		if (!$theme) {
			return [];
		}
		$files = [];
		$assets = ['css' => [], 'head_js' => [], 'body_js' => []];
		$scheme = 'light';
		foreach ($theme['Layers'] as $dir) {
			$manifest = "$dir/theme.json";
			if (is_link($manifest)) {
				throw new RuntimeException("Verlinktes Theme-Manifest ist nicht erlaubt: $manifest");
			}
			if (is_file($manifest)) {
				$config = json_decode(file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
				if (!is_array($config) || array_diff(array_keys($config), ['css', 'head_js', 'body_js', 'color_scheme'])) {
					throw new RuntimeException("Ungueltiges Theme-Manifest: $manifest");
				}
				$scheme = $config['color_scheme'] ?? $scheme;
				foreach ($assets as $group => &$entries) {
					if (isset($config[$group]) && (!is_array($config[$group]) || !array_is_list($config[$group]))) {
						throw new RuntimeException("Asset-Liste erwartet: $manifest");
					}
					foreach ($config[$group] ?? [] as $entry) {
						$entry = is_string($entry) ? ['path' => $entry] : $entry;
						if (!is_array($entry) || !is_string($entry['path'] ?? null) || array_diff(array_keys($entry), ['path', 'media', 'branches'])) {
							throw new RuntimeException("Ungueltiger Asset-Eintrag: $manifest");
						}
						if ((isset($entry['branches']) && (!is_array($entry['branches']) || !array_is_list($entry['branches'])))
							|| (isset($entry['media']) && !is_string($entry['media']))) {
							throw new RuntimeException("Ungueltige Asset-Attribute: $manifest");
						}
						if (isset($entry['branches']) && !in_array($theme['Branch'], $entry['branches'], true)) {
							continue;
						}
						$extension = $group === 'css' ? 'css' : 'js';
						if (!str_ends_with($entry['path'], ".$extension")) {
							throw new RuntimeException("Unpassender Asset-Typ: {$entry['path']}");
						}
						$entries[$entry['path']] = $entry;
					}
				}
				unset($entries);
			}
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file) {
				if ($file->isLink()) {
					throw new RuntimeException("Verlinkte Theme-Datei ist nicht erlaubt: {$file->getPathname()}");
				}
				$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
				if (!preg_match('~^(?:[A-Za-z0-9_-]+(?:[.-][A-Za-z0-9_-]+)*/)*[A-Za-z0-9_-]+(?:[.-][A-Za-z0-9_-]+)*\.(?:css|js|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|otf|eot)$~D', $relative)
					|| preg_match('~(?:^|[./])(?:php[0-9]*|phps|pht|phtml|phar|cgi|fcgi|pl|py|rb|asp|aspx|shtml|htaccess)(?:[./]|$)~i', $relative)) {
					continue;
				}
				$files[$relative] = ['Source' => $file->getPathname(), 'Hash' => hash_file('sha256', $file->getPathname())];
			}
		}
		if (!in_array($scheme, ['light', 'dark'], true)) {
			throw new RuntimeException('Ungueltiges Bootstrap-Farbschema im Theme.');
		}
		foreach ($assets as $entries) {
			foreach ($entries as $entry) {
				if (!isset($files[$entry['path']])) {
					throw new RuntimeException("Theme-Asset fehlt oder ist nicht erlaubt: {$entry['path']}");
				}
			}
		}
		ksort($files);
		if (isset($files['_custom.css'])) {
			throw new RuntimeException('Der Dateiname _custom.css ist fuer Custom CSS reserviert.');
		}
		foreach (array_keys($files) as $path) {
			if (str_starts_with($path, '_build/')) {
				throw new RuntimeException('Der Theme-Ordner _build ist fuer erzeugte Assets reserviert.');
			}
		}
		$options = [];
		foreach (['minify_css', 'minify_js', 'combine_css'] as $option) {
			$options[$option] = (bool)$this->C['Setting']->get($theme['Provider'], "theme_$option");
		}
		$versions = [];
		foreach (['matthiasmullie/minify', 'matthiasmullie/path-converter', 'wikimedia/css-sanitizer'] as $package) {
			$versions[$package] = InstalledVersions::getReference($package);
		}
		$hash = substr(hash('sha256', serialize([
			4, $theme['Provider'], $theme['Branch'], $theme['Chain'], $assets, $scheme, $options, $versions,
			array_map(fn ($file) => $file['Hash'], $files), $theme['CustomCss'],
		])), 0, 24);
		$namespace = substr(hash('sha256', $theme['Provider'] . ':' . $theme['Branch']), 0, 12);
		$relative = "$namespace/" . end($theme['Chain']);
		$destination = rtrim($target, '/\\') . "/$relative";
		$fs = $this->C['Filesystem'];
		$fs->mkdir($target);
		$lock = fopen(rtrim($target, '/\\') . "/.$namespace.lock", 'c');
		if ($lock === false) {
			throw new RuntimeException('Theme-Veroeffentlichung konnte nicht gesperrt werden.');
		}
		$temporary = null;
		try {
			if (!flock($lock, LOCK_EX)) {
				throw new RuntimeException('Theme-Veroeffentlichung konnte nicht gesperrt werden.');
			}
			$state = is_file("$destination/_assets.json")
				? json_decode(file_get_contents("$destination/_assets.json"), true, 512, JSON_THROW_ON_ERROR) : null;
			if (($state['Hash'] ?? null) !== $hash) {
				$temporary = rtrim($target, '/\\') . '/.build-' . bin2hex(random_bytes(8));
				$fs->mkdir($temporary);
				foreach ($files as $path => $file) {
					$fs->copy($file['Source'], "$temporary/$path", true);
					if (hash_file('sha256', "$temporary/$path") !== $file['Hash']) {
						throw new RuntimeException('Theme-Quelle wurde waehrend der Veroeffentlichung geaendert.');
					}
				}
				if ($theme['CustomCss'] !== '') {
					$fs->dumpFile("$temporary/_custom.css", $theme['CustomCss']);
				}
				$prepared = $assets;
				if ($theme['CustomCss'] !== '') {
					$prepared['css']['_custom.css'] = ['path' => '_custom.css'];
				}
				$prepared = $this->optimizeAssets($prepared, $temporary, $options);
				foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($temporary, \FilesystemIterator::SKIP_DOTS)) as $file) {
					if ($file->getExtension() === 'css') {
						$this->versionCss($file->getPathname(), $hash);
					}
				}
				$state = ['Hash' => $hash, 'Assets' => $prepared];
				$fs->dumpFile("$temporary/_assets.json", json_encode($state, JSON_THROW_ON_ERROR));
				$fs->mkdir(dirname($destination));
				$backup = $temporary . '-previous';
				if (is_dir($destination)) {
					$fs->rename($destination, $backup);
				}
				try {
					$fs->rename($temporary, $destination);
				} catch (\Throwable $exception) {
					if (is_dir($backup)) {
						$fs->rename($backup, $destination);
					}
					throw $exception;
				}
				$temporary = null;
				if (is_dir($backup)) {
					$fs->remove($backup);
				}
			}
		} finally {
			if ($temporary !== null && is_dir($temporary)) {
				$fs->remove($temporary);
			}
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		$url = rtrim($baseUrl, '/') . "/file/theme/$relative/";
		$assets = $state['Assets'];
		foreach ($assets as &$entries) {
			$entries = array_values(array_map(fn ($entry) => ['Url' => $url . $entry['path'] . "?v=$hash", 'Media' => $entry['media'] ?? 'all'], $entries));
		}
		unset($entries);
		return ['Provider' => $theme['Provider'], 'Branch' => $theme['Branch'], 'Main' => $theme['Main'],
			'Variant' => $theme['Variant'], 'ColorScheme' => $scheme, 'Hash' => $hash, 'Assets' => $assets];
	}

	public function versionCss(string $file, string $version): void {
		$content = file_get_contents($file);
		if (!mb_check_encoding($content, 'UTF-8') || (stripos($content, 'url') === false && stripos($content, '@import') === false && stripos($content, 'image-set') === false)) {
			return;
		}
		$parser = Parser::newFromString($content);
		$document = $parser->parseStylesheet();
		if ($parser->getParseErrors()) {
			throw new RuntimeException("Ungueltiges CSS in $file: " . json_encode($parser->getParseErrors(), JSON_THROW_ON_ERROR));
		}
		foreach ($document->getRuleList() as $rule) {
			if ($rule instanceof AtRule && strtolower($rule->getName()) === 'charset') {
				$charset = Util::findFirstNonWhitespace($rule->getPrelude());
				if (!$charset instanceof Token || strtolower($charset->value()) !== 'utf-8') {
					return;
				}
			}
		}
		$tokens = $document->toTokenArray();
		$functions = [];
		$import = false;
		$changed = false;
		foreach ($tokens as $i => $token) {
			$type = $token->type();
			if ($type === Token::T_AT_KEYWORD) {
				$import = strtolower($token->value()) === 'import';
			} elseif ($type === Token::T_SEMICOLON) {
				$import = false;
			} elseif ($type === Token::T_FUNCTION || $type === Token::T_LEFT_PAREN) {
				$functions[] = $type === Token::T_FUNCTION ? strtolower($token->value()) : '';
			} elseif ($type === Token::T_RIGHT_PAREN) {
				array_pop($functions);
			} elseif ($type === Token::T_URL || ($type === Token::T_STRING && ($import || in_array(end($functions), ['url', 'image-set', '-webkit-image-set'], true)))) {
				$value = $token->value();
				if ($value !== '' && !str_starts_with($value, '/') && !str_starts_with($value, '#') && parse_url($value, PHP_URL_SCHEME) === null) {
					[$path, $fragment] = array_pad(explode('#', $value, 2), 2, null);
					[$path, $query] = array_pad(explode('?', $path, 2), 2, '');
					$params = array_values(array_filter(explode('&', $query), static fn ($param) => $param !== '' && urldecode(explode('=', $param, 2)[0]) !== 'v'));
					$params[] = "v=$version";
					$tokens[$i] = new Token($type, $path . '?' . implode('&', $params) . ($fragment !== null ? "#$fragment" : ''));
					$changed = true;
				}
				if (!$changed) {
					return;
				}
				$import = false;
			}
		}
		preg_match_all('~/\*[\s\S]*?\*/~', $content, $comments);
		$licenses = array_filter($comments[0], static fn ($comment) => str_starts_with($comment, '/*!') || stripos($comment, 'copyright') !== false || stripos($comment, '@license') !== false);
		$css = Util::stringify($tokens);
		if (str_starts_with($css, '@charset ')) {
			$end = strpos($css, ';') + 1;
			$css = substr($css, 0, $end) . "\n" . implode("\n", $licenses) . substr($css, $end);
		} else {
			$css = implode("\n", $licenses) . "\n" . $css;
		}
		$this->C['Filesystem']->dumpFile($file, $css);
	}

	public function optimizeAssets(array $assets, string $directory, array $options): array {
		$fs = $this->C['Filesystem'];
		$css = [];
		$bundle = null;
		foreach ($assets['css'] as $entry) {
			$path = $entry['path'];
			if (!$options['minify_css'] && !$options['combine_css']) {
				$css[] = $entry;
				continue;
			}
			$content = file_get_contents("$directory/$path");
			if (!mb_check_encoding($content, 'UTF-8') || (!$options['combine_css'] && str_ends_with($path, '.min.css'))) {
				$css[] = $entry;
				$bundle = null;
				continue;
			}
			$parser = Parser::newFromString($content);
			$document = $parser->parseStylesheet();
			if ($parser->getParseErrors()) {
				throw new RuntimeException("Ungueltiges CSS in $path: " . json_encode($parser->getParseErrors(), JSON_THROW_ON_ERROR));
			}
			$rules = $document->getRuleList();
			foreach ($rules as $i => $rule) {
				if ($rule instanceof AtRule && strtolower($rule->getName()) === 'charset') {
					$charset = Util::findFirstNonWhitespace($rule->getPrelude());
					if ($charset instanceof Token && $charset->type() === Token::T_STRING && strtolower($charset->value()) === 'utf-8') {
						$rules->remove($i);
					}
				}
			}
			$tokens = $document->toTokenArray();
			// Import-/Namespace-Geltung und Zeichenkodierung duerfen nicht auf andere Dateien uebergreifen.
			$separate = false;
			foreach ($tokens as $token) {
				if ($token->type() === Token::T_AT_KEYWORD && in_array(strtolower($token->value()), ['import', 'namespace', 'charset'], true)) {
					$separate = true;
				}
			}
			if ($separate) {
				$css[] = $entry;
				$bundle = null;
				continue;
			}
			$media = $entry['media'] ?? 'all';
			if (!$options['combine_css'] || $bundle === null || ($css[$bundle]['media'] ?? 'all') !== $media) {
				$bundle = count($css);
				$css[] = ['path' => '_build/css-' . $bundle . ($options['minify_css'] ? '.min.css' : '.css'), 'media' => $media];
			}
			$output = $css[$bundle]['path'];
			$converter = new Converter("/theme/$path", "/theme/$output");
			$functions = [];
			foreach ($tokens as $i => $token) {
				$type = $token->type();
				if ($type === Token::T_FUNCTION || $type === Token::T_LEFT_PAREN) {
					$functions[] = $type === Token::T_FUNCTION ? strtolower($token->value()) : '';
				} elseif ($type === Token::T_RIGHT_PAREN) {
					array_pop($functions);
				} elseif ($type === Token::T_URL || ($type === Token::T_STRING && in_array(end($functions), ['url', 'image-set', '-webkit-image-set'], true))) {
					$value = $token->value();
					if ($value !== '' && !str_starts_with($value, '/') && !str_starts_with($value, '#') && parse_url($value, PHP_URL_SCHEME) === null) {
						$tokens[$i] = new Token($type, $converter->convert($value));
					}
				}
			}
			$converted = Util::stringify($tokens);
			// Lizenzhinweise bleiben auch nach Tokenisierung und Minimierung erhalten.
			preg_match_all('~/\*[\s\S]*?\*/~', $content, $comments);
			$licenses = array_filter($comments[0], static fn ($comment) => str_starts_with($comment, '/*!') || stripos($comment, 'copyright') !== false || stripos($comment, '@license') !== false);
			$converted = implode("\n", $licenses) . "\n" . ($options['minify_css'] ? (new Minify\CSS($converted))->minify() : $converted);
			$built = "$directory/$output";
			$fs->dumpFile($built, (is_file($built) ? file_get_contents($built) : "@charset \"UTF-8\";\n") . $converted . "\n");
		}
		$assets['css'] = $css;
		if ($options['minify_js']) {
			foreach (['head_js', 'body_js'] as $group) {
				foreach ($assets[$group] as &$entry) {
					if (str_ends_with($entry['path'], '.min.js')) {
						continue;
					}
					$output = '_build/' . $group . '-' . substr(hash('sha256', $entry['path']), 0, 12) . '.min.js';
					$fs->dumpFile("$directory/$output", (new Minify\JS("$directory/{$entry['path']}"))->minify());
					$entry['path'] = $output;
				}
				unset($entry);
			}
		}
		return $assets;
	}

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
		$providers = $this->getThemeModules($modules);
		$inheritance = $this->getModuleInheritance($modules);
		$selectedTemplates = [];
		foreach ($providers as $id => $provider) {
			if ($provider['Parent'] !== null) continue;
			// Layouts ohne Themes behalten die bestehende strukturelle Vererbung.
			if (!$this->getThemeChoices($id, $modules)) continue;
			$selectedTemplates[$id] = $this->getThemeSelection($id, $modules)['Templates'];
		}
		$modules = array_filter($modules, static fn ($m, $id) => ($m['INFO']['Type'] ?? $m['Type'] ?? 'module') !== 'library'
			&& isset($inheritance[$id])
			&& (($m['INFO']['Type'] ?? $m['Type'] ?? '') === 'core' || (int)($m['Active'] ?? 1) === 1)
			&& (($m['INFO']['Type'] ?? $m['Type'] ?? '') !== 'template' || (isset($providers[$id])
				&& (!isset($selectedTemplates[$providers[$id]['Chain'][0]]) || in_array($id, $selectedTemplates[$providers[$id]['Chain'][0]], true)))), ARRAY_FILTER_USE_BOTH);

		// Modul muss existieren
		if (!isset($modules[$activeModuleId])) {
			return ['extends' => '', 'tpl' => [], 'php' => []];
		}

		$tplChain = [];
		$phpChain = [];

		// Rangfolge der Modul-Typen für die Template-Kette (Typ aus der composer.json). Unbekannte Typen zählen als 'module'.
		// Die Kette wird am Ende umgedreht, dadurch ergibt sich core -> template -> module.
		$rank = ['core' => 0, 'template' => 1, 'module' => 2];
		$typeOf = fn(array $m): string => $m['INFO']['Type'] ?? $m['Type'] ?? 'module';
		$depth = [];
		foreach ($inheritance as $provider) {
			$depth[$provider['Directory']] = $provider['Depth'];
		}
		$depthOf = fn (array $m): int => $depth[str_replace('\\', '/', $m['INFO']['ModulDir'])] ?? 0;

		// Alle Module: Module vor Template vor Core. Gleicher Rang behält die bisherige Reihenfolge (uasort ist ab PHP 8 stabil).
		uasort($modules, fn($a, $b) => (($rank[$typeOf($b)] ?? 2) <=> ($rank[$typeOf($a)] ?? 2)) ?: ($depthOf($b) <=> $depthOf($a)));

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
