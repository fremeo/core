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
	$definition = \fremeo\core\Component::load($module, $type, $D['MODULE']['D'][$module] ?? [], $D);
	$options = \fremeo\core\Component::options($definition['manifest'], $options);
	if ($definition['data'] !== null) {
		$data = ($definition['data'])($options, $data);
		if (!is_array($data)) throw new RuntimeException('Komponenten-Datenaufbereitung muss ein Array liefern.');
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
	$component = $template->smarty->createTemplate($definition['file'], null, null, $template);
	$component->assign([
		'data' => $data,
		'options' => $options,
		'component' => ['id' => $id, 'type' => $type, 'module' => $module],
	]);
	$html = $component->fetch();
	return '<div id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" class="component component-' . $type
		. '" data-component-module="' . htmlspecialchars($module, ENT_QUOTES, 'UTF-8') . '">' . $html . '</div>';
}
