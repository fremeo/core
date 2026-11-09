<?php

function smarty_function_component(array $params, Smarty_Internal_Template $template): string {
	$module = $params['module'] ?? null;
	$type = $params['type'] ?? null;
	$data = $params['data'] ?? null;
	$options = $params['options'] ?? [];
	if (!is_string($module) || !is_string($type) || !preg_match('/^[a-z][a-z0-9_-]*$/D', $type)
		|| !is_array($data) || !is_array($options)) {
		throw new RuntimeException('component erwartet module, type, einen Daten-Teilbaum als data und optionale options.');
	}
	$D = $template->getTemplateVars('D');
	$provider = $D['MODULE']['D'][$module] ?? null;
	if (!$provider || (int)($provider['Active'] ?? 0) !== 1
		|| !in_array($provider['INFO']['Type'] ?? '', ['core', 'template', 'module'], true)) {
		throw new RuntimeException("Komponenten-Modul nicht verfuegbar: $module");
	}
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
	if (!is_array($manifest) || ($manifest['type'] ?? null) !== $type || !is_array($manifest['defaults'] ?? [])) {
		throw new RuntimeException("Ungueltiges Komponenten-Manifest: $module:$type");
	}
	$options = array_replace($manifest['defaults'] ?? [], $options);
	if (isset($manifest['variants']) && (!is_array($manifest['variants'])
		|| !in_array($options['variant'] ?? null, $manifest['variants'], true))) {
		throw new RuntimeException("Ungueltige Komponenten-Variante: $module:$type");
	}

	// Ein Renderer kann mehrfach und verschachtelt Komponenten ausgeben.
	static $instances;
	$instances ??= new WeakMap();
	$state = $instances[$template->smarty] ?? ['next' => 0, 'ids' => []];
	$id = $params['id'] ?? null;
	if ($id === null) {
		do {
			$id = 'component-' . $type . '-' . ++$state['next'];
		} while (isset($state['ids'][$id]));
	}
	if (!is_string($id) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $id) || isset($state['ids'][$id])) {
		throw new RuntimeException('Komponenten-ID ist ungueltig oder bereits vergeben.');
	}
	$state['ids'][$id] = true;
	$instances[$template->smarty] = $state;
	$component = $template->smarty->createTemplate($file, null, null, $template);
	$component->assign([
		'data' => $data,
		'options' => $options,
		'component' => ['id' => $id, 'type' => $type, 'module' => $module],
	]);
	$html = $component->fetch();
	return '<div id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" class="component component-' . $type
		. '" data-component-module="' . htmlspecialchars($module, ENT_QUOTES, 'UTF-8') . '">' . $html . '</div>';
}
