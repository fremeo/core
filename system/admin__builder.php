<?php

$R['BuilderView'] = [
	'PreviewUrl' => SCRIPT_NAME, 'Components' => [], 'Errors' => [], 'EditorScripts' => [], 'FormTemplates' => [],
	'RenderUrl' => SCRIPT_NAME . '?' . http_build_query(['R' => ['ModuleId' => 'fremeo/core', 'Page' => 'admin__builder']]),
];
$_SESSION['BuilderToken'] ??= bin2hex(random_bytes(32));
$R['BuilderView']['Token'] = $_SESSION['BuilderToken'];
foreach ($D['MODULE']['D'] ?? [] as $_id => $_module) {
	if ((int)($_module['Active'] ?? 0) !== 1
		|| !in_array($_module['INFO']['Type'] ?? '', ['core', 'template', 'module'], true)) continue;
	$_root = realpath($_module['INFO']['ModulDir']);
	if ($_root === false) {
		$R['BuilderView']['Errors'][] = 'Komponentenverzeichnis nicht lesbar: ' . $_id;
		continue;
	}
	foreach (glob($_root . '/system/template/components/*/component.json') as $_file) {
		$_type = basename(dirname($_file));
		try {
			$_definition = \fremeo\core\Component::load($_id, $_type, $_module, $D + ['C' => $C ?? []]);
			$_manifest = $_definition['manifest'];
			$R['BuilderView']['Components'][$_id][$_type] = [
				'Name' => $_manifest['name'] ?? $_type, 'Defaults' => $_manifest['defaults'] ?? [],
				'Settings' => $_manifest['settings'] ?? [],
			];
			if (isset($_manifest['builder'])) $R['BuilderView']['Components'][$_id][$_type]['Builder'] = $_manifest['builder'];
			if (isset($_manifest['editorLanguage'])) $R['BuilderView']['Components'][$_id][$_type]['EditorLanguage'] = $_manifest['editorLanguage'];
			if ($_definition['settings'] !== null) $R['BuilderView']['FormTemplates'][$_id][$_type] = $_definition['settings'];
			if ($_definition['editor'] !== null) $R['BuilderView']['EditorScripts'][] = $R['BuilderView']['RenderUrl'] . '&' . http_build_query([
				'R' => ['ACTION' => 'editor', 'BuilderEditorModule' => $_id, 'BuilderEditorType' => $_type],
			]);
		} catch (JsonException | RuntimeException $error) {
			$_message = 'Builder-Komponente ' . $_id . ':' . $_type . ': ' . $error->getMessage();
			$R['BuilderView']['Errors'][] = $_message;
			error_log($_message);
		}
	}
}
ksort($R['BuilderView']['Components']);
$R['BuilderView']['SettingsJson'] = json_encode([
	'Token' => $_SESSION['BuilderToken'], 'RenderUrl' => $R['BuilderView']['RenderUrl'],
	'Components' => $R['BuilderView']['Components'],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

if (($R['ACTION'] ?? null) === 'editor') {
	header('Cache-Control: private, no-store');
	header('X-Content-Type-Options: nosniff');
	try {
		if (!isset($D['SESSION']['UserId']) || !is_string($R['BuilderEditorModule'] ?? null) || !is_string($R['BuilderEditorType'] ?? null)) throw new RuntimeException('Keine Berechtigung fuer Komponenten-Editorcode.');
		$_definition = \fremeo\core\Component::load($R['BuilderEditorModule'], $R['BuilderEditorType'], $D['MODULE']['D'][$R['BuilderEditorModule']] ?? [], $D + ['C' => $C]);
		if ($_definition['editor'] === null) throw new RuntimeException('Komponenten-Editorcode fehlt.');
		header('Content-Type: application/javascript; charset=utf-8');
		readfile($_definition['editor']);
	} catch (JsonException | RuntimeException $error) {
		http_response_code(403);
		header('Content-Type: text/plain; charset=utf-8');
		error_log('Builder-Editorcode: ' . $error->getMessage());
		echo $error->getMessage();
	}
	exit;
}

if (in_array($R['ACTION'] ?? null, ['render', 'save', 'publish'], true)) {
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: private, no-store');
	try {
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($D['SESSION']['UserId'])
			|| !is_string($R['BuilderToken'] ?? null) || !hash_equals($_SESSION['BuilderToken'], $R['BuilderToken'])) {
			http_response_code(403);
			throw new RuntimeException('Keine Berechtigung fuer die Builder-Aktion. Seite neu laden.');
		}
		if (in_array($R['ACTION'], ['save', 'publish'], true)) {
			if (!is_string($R['BuilderRegionsJson'] ?? null)) throw new RuntimeException('Builder-Bereiche muessen JSON-Text sein.');
			$_regions = json_decode($R['BuilderRegionsJson'], true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($_regions) || !array_is_list($_regions) || !$_regions
				|| !is_string($_SESSION['BuilderContextKey'] ?? null)) throw new RuntimeException('Keine autorisierten Builder-Bereiche.');
			$_entries = [];
			$_page = null;
			foreach ($_regions as $_region) {
				if (!is_array($_region) || !is_string($_region['target'] ?? null) || !is_string($_region['token'] ?? null)
					|| !hash_equals(hash_hmac('sha256', $_region['target'], $_SESSION['BuilderContextKey']), $_region['token'])
					|| ($R['ACTION'] === 'save' && !is_array($_region['nodes'] ?? null))) {
					throw new RuntimeException('Builder-Seitenzuordnung ungueltig. Vorschau neu laden.');
				}
				$_target = json_decode($_region['target'], true, 512, JSON_THROW_ON_ERROR);
				if (!is_array($_target) || !is_string($_target['module'] ?? null) || !is_string($_target['page'] ?? null)
					|| !is_string($_target['language'] ?? null) || (int)($D['MODULE']['D'][$_target['module']]['Active'] ?? 0) !== 1) {
					throw new RuntimeException('Builder-Zielmodul nicht mehr verfuegbar.');
				}
				if (isset($_target['scope']) && (!in_array($_target['scope'], ['individual', 'page', 'global'], true)
					|| ($_target['branch'] ?? null) !== 'frontend'
					|| ($_target['scope'] === 'individual' && (!is_string($_target['entity'] ?? null) || $_target['entity'] === '' || $_target['entity'] !== ($_target['contextEntity'] ?? null)))
					|| ($_target['scope'] !== 'individual' && array_key_exists('entity', $_target)))) throw new RuntimeException('Ungueltige Builder-Ebenenzuordnung.');
				$_identity = [$_target['module'], $_target['page'], $_target['language'], $_target['contextEntity'] ?? $_target['entity'] ?? ''];
				if ($_page !== null && $_page !== $_identity) throw new RuntimeException('Nur Bereiche derselben Seite und Sprache gemeinsam speichern.');
				$_page = $_identity;
				if ($R['ACTION'] === 'publish') {
					if (!is_string($_region['revision'] ?? null)) throw new RuntimeException('Gespeicherter Entwurfsstand fehlt. Zuerst speichern.');
					if (!is_string($_target['region'] ?? null)) throw new RuntimeException('Ungueltige Builder-Veroeffentlichungszuordnung.');
					$_entries[] = [
						'id' => $C['Builder']->placeId($_target['module'], $_target['page'], $_target['region'] ?? '', $_target['entity'] ?? '', $_target['scope'] ?? null, $_target['branch'] ?? 'frontend'),
						'revision' => $_region['revision'],
					];
					continue;
				}
				if (array_key_exists('active', $_region) && !is_bool($_region['active'])) throw new RuntimeException('Ungueltige Builder-Bereichsaktivierung.');
				if (array_key_exists('deleted', $_region) && !is_bool($_region['deleted'])) throw new RuntimeException('Ungueltige Builder-Loeschvormerkung.');
				$_entries[] = ['target' => $_target, 'nodes' => $_region['nodes'], 'active' => $_region['active'] ?? true, 'deleted' => $_region['deleted'] ?? false];
			}
			if ($R['ACTION'] === 'publish') {
				$C['Builder']->publishDrafts($_entries, $D['MODULE']['D'] ?? [], $D + ['C' => $C]);
				echo json_encode(['published' => true, 'regions' => count($_entries)], JSON_THROW_ON_ERROR);
			} else {
				$_revisions = $C['Builder']->saveDrafts($_entries, $D['MODULE']['D'] ?? [], $D + ['C' => $C]);
				echo json_encode(['saved' => true, 'regions' => count($_entries), 'revisions' => $_revisions], JSON_THROW_ON_ERROR);
			}
			exit;
		}
		$_component = $R['BuilderComponent'] ?? null;
		if (!is_array($_component) || !is_string($_component['Module'] ?? null) || !is_string($_component['Type'] ?? null)
			|| !isset($R['BuilderView']['Components'][$_component['Module']][$_component['Type']])
			|| !is_string($_component['Id'] ?? null) || !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $_component['Id'])) {
			http_response_code(400);
			throw new RuntimeException('Ungueltige Komponenten-Vorschau.');
		}
		if (!is_string($_component['OptionsJson'] ?? '{}') || !is_string($_component['DataJson'] ?? '{}')
			|| !is_string($_component['GeneralJson'] ?? '{}')) {
			throw new RuntimeException('Komponenten-Einstellungen und Daten muessen JSON-Text sein.');
		}
		$_options = json_decode($_component['OptionsJson'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
		$_data = json_decode($_component['DataJson'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($_options) || !is_array($_data)) throw new RuntimeException('Ungueltige Komponenten-Einstellungen oder Daten.');
		\fremeo\core\Builder::appearance(json_decode($_component['GeneralJson'] ?? '{}', true, 512, JSON_THROW_ON_ERROR));
		if (!is_string($_component['TranslationsJson'] ?? '{}') || !is_string($_component['Language'] ?? 'EN')
			|| !preg_match('/^[A-Z]{2}$/D', $_component['Language'] ?? 'EN')) throw new RuntimeException('Ungueltige Komponenten-Sprachvorschau.');
		$_translations = json_decode($_component['TranslationsJson'] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($_translations)) throw new RuntimeException('Ungueltige Komponenten-Uebersetzungen.');
		$_definition = \fremeo\core\Component::load($_component['Module'], $_component['Type'], $D['MODULE']['D'][$_component['Module']], $D + ['C' => $C]);
		$_localized = \fremeo\core\Component::localizedOptions($_definition['manifest'], $_options, $_translations, $_component['Language'] ?? 'EN');
		$_render = $C['Smarty']->createTemplate('string:{component module=$builderModule type=$builderType id=$builderInstance data=$builderData options=$builderOptions}');
		$_render->assign([
			'D' => $D, 'builderModule' => $_component['Module'], 'builderType' => $_component['Type'], 'builderInstance' => $_component['Id'],
			'builderOptions' => $_localized['options'], 'builderData' => $_data,
		]);
		echo json_encode(['html' => $_render->fetch(), 'visible' => $_localized['visible']], JSON_THROW_ON_ERROR);
	} catch (JsonException | RuntimeException | SmartyException $error) {
		if (http_response_code() < 400) http_response_code(422);
		error_log('Builder-' . $R['ACTION'] . ': ' . $error->getMessage());
		echo json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR);
	}
	exit;
}
