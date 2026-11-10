<?php

function smarty_function_builder(array $params, Smarty_Internal_Template $template): string {
	$D = $template->getTemplateVars('D');
	$entity = $params['entity'] ?? $D['R']['Id'] ?? null;
	if ($entity !== null && !is_string($entity) && !is_int($entity)) throw new RuntimeException('Ungueltige Builder-Inhalts-ID.');
	$entity = $entity === null || (string)$entity === '' ? null : (string)$entity;
	$scopes = $entity === null ? ['page', 'global'] : ['individual', 'page', 'global'];
	$preview = $D['C']['Builder']->getPreviewLanguage() !== null;
	$html = '';
	$effective = false;
	foreach ($scopes as $scope) {
		$html .= smarty_builder_scope(array_replace($params, ['scope' => $scope]), $template, $entity, $effective);
		if (!$preview && $effective) break;
	}
	return $html;
}

function smarty_builder_scope(array $params, Smarty_Internal_Template $template, ?string $contextEntity, bool &$effective): string {
	$name = $params['name'] ?? null;
	$scope = $params['scope'];
	$entity = $scope === 'individual' ? $contextEntity : '';
	if (!is_string($name) || !is_string($entity)) throw new RuntimeException('builder erwartet name und optional entity als Text.');
	$D = $template->getTemplateVars('D');
	if (!is_string($D['R']['ModuleId'] ?? null) || !is_string($D['R']['Page'] ?? null)) {
		throw new RuntimeException('builder benoetigt den aufgeloesten Modul-/Seitenkontext.');
	}
	$builder = $D['C']['Builder'];
	$branch = $D['R']['Theme']['Branch'] ?? 'frontend';
	$preview = $builder->getPreviewLanguage() !== null;
	try {
		$region = $builder->getRegion(
			$D['R']['ModuleId'], $D['R']['Page'], $name,
			$builder->getPreviewLanguage() ?? $D['R']['Frontend']['language'] ?? 'EN', $entity, $scope, $branch
		);
	} catch (JsonException | RuntimeException $error) {
		if (!$preview) throw $error;
		error_log('Builder-Vorschau: ' . $error->getMessage());
		return '<div class="alert alert-danger" role="alert">' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
	}
	if (!$preview && !$region) return '';
	$visible = !$effective && ($region ? ($region['active'] ?? true) : false);
	if ($visible) $effective = true;
	if (!$preview && !$region['nodes']) return '';
	$id = 'builder-' . $builder->placeId($D['R']['ModuleId'], $D['R']['Page'], $name, $entity, $scope, $branch);
	$nodes = $region['nodes'] ?? [];
	try {
		$prepare = function (array &$items) use (&$prepare, $D, $id, $preview, $region): void {
			foreach ($items as &$node) {
				$node['htmlId'] = $id . '-' . $node['id'];
				$appearance = \fremeo\core\Builder::appearance($node['general'] ?? []);
				$node['general'] = $appearance['values'];
				$node['appearanceClass'] = $appearance['classes'];
				$node['visibilityClass'] = $appearance['visibility'];
				$node['generalJson'] = json_encode($node['general'], JSON_THROW_ON_ERROR);
				$node['languageVisible'] = true;
				if (!$preview && !$node['general']['Active']) continue;
				if ($node['kind'] === 'container') {
					$node['module'] = 'fremeo/core';
					$node['type'] = 'container';
					$node['options'] ??= ['columns' => ($node['layout'] ?? '') === 'two-columns' ? '6+6' : '12'];
					$definition = \fremeo\core\Component::load('fremeo/core', 'container', $D['MODULE']['D']['fremeo/core'] ?? [], $D);
					$node['options'] = \fremeo\core\Component::options($definition['manifest'], $node['options']);
					$node['editorJson'] = json_encode(['options' => $node['options'], 'data' => []], JSON_THROW_ON_ERROR);
					$prepare($node['children']);
					$node['containerData'] = ['nodes' => $node['children'], 'preview' => $preview];
					continue;
				}
				$provider = $D['MODULE']['D'][$node['module']] ?? [];
				if ((int)($provider['Active'] ?? 0) !== 1) throw new RuntimeException('Builder-Komponentenmodul nicht aktiv: ' . $node['module']);
				$definition = \fremeo\core\Component::load($node['module'], $node['type'], $provider, $D);
				$localized = ($region['legacy'] ?? false)
					? ['options' => \fremeo\core\Component::options($definition['manifest'], $node['options'] ?? []), 'visible' => true]
					: \fremeo\core\Component::localizedOptions($definition['manifest'], $node['options'] ?? [], $node['translations'] ?? [], $region['language']);
				$node['languageVisible'] = $localized['visible'];
				if (!$preview && !$node['languageVisible']) continue;
				if (isset($node['source'])) {
					$value = $provider;
					foreach ($node['source'] as $key) {
						if (!is_array($value) || !array_key_exists($key, $value)) throw new RuntimeException('Builder-Datenquelle nicht geladen.');
						$value = $value[$key];
					}
					if (!is_array($value)) throw new RuntimeException('Builder-Datenquelle ist kein Pattern-Teilbaum.');
					$node['data'] = $value;
				}
				$node['editorJson'] = json_encode([
					'options' => $node['options'] ?? [], 'data' => $node['data'] ?? [], 'source' => $node['source'] ?? null,
					'translations' => $node['translations'] ?? [],
				], JSON_THROW_ON_ERROR);
				$node['options'] = $localized['options'];
			}
		};
		$prepare($nodes);
		$targetJson = '';
		$targetToken = '';
		if ($preview) {
			$_SESSION['BuilderContextKey'] ??= bin2hex(random_bytes(32));
			$targetJson = json_encode([
				'module' => $D['R']['ModuleId'], 'page' => $D['R']['Page'], 'region' => $name,
				'language' => $builder->getPreviewLanguage(), 'scope' => $scope, 'branch' => $branch,
				...($scope === 'individual' ? ['entity' => $entity] : []),
				...($contextEntity !== null ? ['contextEntity' => $contextEntity] : []),
			], JSON_THROW_ON_ERROR);
			$targetToken = hash_hmac('sha256', $targetJson, $_SESSION['BuilderContextKey']);
		}
		$render = $template->smarty->createTemplate(__DIR__ . '/../../template/builder/region.tpl', null, null, $template);
		$render->assign([
			'builderId' => $id, 'builderNodes' => $nodes, 'builderPreview' => $preview, 'builderRegion' => $name,
			'builderTargetJson' => $targetJson, 'builderTargetToken' => $targetToken,
			'builderActive' => $region['active'] ?? false,
			'builderScope' => $scope, 'builderExists' => (bool)$region && !($region['deleted'] ?? false),
			'builderDeleted' => $region['deleted'] ?? false, 'builderVisible' => $visible,
			'builderDraftRevision' => $preview ? ($region['revision'] ?? null) : null,
		]);
		return $render->fetch();
	} catch (JsonException | RuntimeException $error) {
		if ($preview) {
			error_log('Builder-Vorschau: ' . $error->getMessage());
			return '<div class="alert alert-danger" role="alert">' . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
		}
		$builder->deactivate($region['id'], $region['language'], $error->getMessage());
		$effective = false;
		return '';
	}
}
