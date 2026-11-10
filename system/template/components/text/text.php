<?php

return static function (array $provider, array $context): array {
	return ['validate' => static function (array $options): void {
		$html = $options['text'];
		if ($html === '') return;
		if (preg_match('/<\s*[!?]|<\/?(?:html|head|body)\b|[\x00-\x08\x0b\x0c\x0e-\x1f]/i', $html)) {
			throw new RuntimeException('Text enthaelt unzulaessigen HTML-Code.');
		}
		$document = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		try {
			if (!$document->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET)) {
				throw new RuntimeException('Text-HTML konnte nicht gelesen werden.');
			}
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors($previous);
		}
		$allowed = [
			'p', 'div', 'span', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del',
			'sub', 'sup', 'small', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'code',
			'ul', 'ol', 'li', 'a', 'img', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
		];
		$walk = static function (DOMNode $parent) use (&$walk, $allowed): void {
			foreach ($parent->childNodes as $node) {
				if ($node instanceof DOMText) continue;
				if (!$node instanceof DOMElement || !in_array($node->tagName, $allowed, true)) {
					throw new RuntimeException('Text: HTML-Element nicht erlaubt: ' . $node->nodeName);
				}
				foreach ($node->attributes as $attribute) {
					$name = $attribute->name;
					$value = $attribute->value;
					$specific = match ($node->tagName) {
						'a' => ['href', 'target', 'rel'],
						'img' => ['src', 'alt', 'width', 'height'],
						'th', 'td' => ['colspan', 'rowspan', 'scope'],
						'ol' => ['start', 'type'],
						default => [],
					};
					if (!in_array($name, ['class', 'title', 'lang', 'dir', 'style', ...$specific], true)) {
						throw new RuntimeException('Text: HTML-Attribut nicht erlaubt: ' . $name);
					}
					if (in_array($name, ['href', 'src'], true) && (preg_match('/[\x00-\x20\x7f\\\\]/', $value)
						|| (preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $scheme)
							&& !in_array(strtolower($scheme[1]), $name === 'src' ? ['http', 'https'] : ['http', 'https', 'mailto', 'tel'], true)))) {
						throw new RuntimeException('Text: unsichere HTML-Adresse.');
					}
					if ($name === 'target' && !in_array($value, ['_blank', '_self'], true)) throw new RuntimeException('Text: ungueltiges Linkziel.');
					if ($name === 'style') {
						foreach (explode(';', $value) as $declaration) {
							if (trim($declaration) === '') continue;
							$parts = explode(':', $declaration, 2);
							$property = strtolower(trim($parts[0]));
							$css = trim($parts[1] ?? '');
							$valid = match ($property) {
								'text-align' => preg_match('/^(left|right|center|justify|start|end)$/iD', $css),
								'font-weight' => preg_match('/^(normal|bold|bolder|lighter|[1-9]00)$/iD', $css),
								'font-style' => preg_match('/^(normal|italic|oblique)$/iD', $css),
								'text-decoration', 'text-decoration-line' => preg_match('/^(none|underline|line-through|overline)( (underline|line-through|overline))*$/iD', $css),
								'color', 'background-color' => preg_match('/^(#[a-f0-9]{3,8}|[a-z]+|rgba?\([\d.,% ]+\))$/iD', $css),
								'margin-left', 'padding-left', 'font-size', 'line-height' => preg_match('/^(\d+(\.\d+)?(px|em|rem|%|pt)?|normal)$/iD', $css),
								'margin', 'padding' => preg_match('/^\d+(\.\d+)?(px|em|rem|%|pt)?( \d+(\.\d+)?(px|em|rem|%|pt)?){0,3}$/iD', $css),
								'border' => preg_match('/^(none|0(px)?)$/iD', $css),
								default => false,
							};
							if (!$valid) throw new RuntimeException('Text: CSS-Deklaration nicht erlaubt: ' . $property);
						}
					}
				}
				$walk($node);
			}
		};
		foreach ($document->documentElement->childNodes as $part) {
			if ($part instanceof DOMElement) $walk($part);
		}
	}];
};
