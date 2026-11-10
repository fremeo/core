<?php
require __DIR__ . '/../../../autoload.php';

$components = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$tmp = sys_get_temp_dir() . '/builder_forms_' . bin2hex(random_bytes(6));
mkdir($tmp);
try {
	$smarty = new Smarty();
	$smarty->setCompileDir($tmp);
	$templates = ['fremeo/core' => ['container' => __DIR__ . '/../system/template/components/container/container.settings.tpl']];
	foreach ($components as $module => $types) {
		foreach ($types as $type => $component) {
			if (($component['Settings']['text']['input'] ?? '') === 'wysiwyg') {
				$templates[$module][$type] = __DIR__ . '/../system/template/components/text/text.settings.tpl';
			}
		}
	}
	$smarty->assign('D', [
		'C' => ['Security' => new class {
			public function registerSecurityD(string $name): string { return $name; }
		}],
		'R' => ['BuilderView' => ['Components' => $components, 'FormTemplates' => $templates]],
	]);
	$html = $smarty->fetch(__DIR__ . '/../system/template/builder/forms.tpl');
	$dom = new DOMDocument();
	libxml_use_internal_errors(true);
	$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
	$encode = static function (DOMElement $node) use (&$encode): array {
		$attributes = [];
		foreach ($node->attributes as $attribute) $attributes[$attribute->name] = $attribute->value;
		$children = [];
		foreach ($node->childNodes as $child) if ($child instanceof DOMElement) $children[] = $encode($child);
		return ['tag' => $node->tagName, 'attributes' => $attributes, 'textContent' => $node->textContent, 'children' => $children];
	};
	$result = [];
	foreach ($dom->getElementsByTagName('template') as $template) $result[$template->getAttribute('id')] = $encode($template);
	echo json_encode($result, JSON_THROW_ON_ERROR);
} finally {
	(new \Symfony\Component\Filesystem\Filesystem())->remove($tmp);
}
