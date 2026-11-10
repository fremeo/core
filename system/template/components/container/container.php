<?php

return static function (array $provider, array $context): array {
	$widths = static function (array $options): array {
		$parsed = [];
		foreach (['columns', 'xs', 'sm', 'md', 'lg'] as $key) {
			$value = $options[$key];
			if ($value === '' && $key !== 'columns') continue;
			if (!preg_match('/^(?:[1-9]|1[0-2])(?:\s*\+\s*(?:[1-9]|1[0-2]))*$/D', $value)) {
				throw new RuntimeException('Ungueltige Grid-Spalten (' . $key . '). Beispiel: 8+4; Breiten von 1 bis 12.');
			}
			$parsed[$key] = array_map('intval', preg_split('/\s*\+\s*/', $value));
			if (count($parsed[$key]) > 12) throw new RuntimeException('Ein Grid darf maximal 12 Spalten besitzen (' . $key . ').');
		}
		$count = count($parsed['columns']);
		foreach ($parsed as $key => $values) {
			if (count($values) !== $count) throw new RuntimeException('Alle Grid-Aufteilungen muessen dieselbe Spaltenanzahl besitzen (' . $key . ').');
		}
		return [
			'xs' => $parsed['xs'] ?? array_fill(0, $count, 12),
			'sm' => $parsed['sm'] ?? null,
			'md' => $parsed['md'] ?? (isset($parsed['xs']) || isset($parsed['sm']) ? null : $parsed['columns']),
			'lg' => $parsed['lg'] ?? null,
		];
	};
	return [
		'validate' => static function (array $options) use ($widths): void { $widths($options); },
		'data' => static function (array $options, array $data) use ($widths): array {
			$grid = $widths($options);
			$classes = array_fill(0, count($grid['xs']), []);
			foreach ($grid as $size => $values) {
				if ($values === null) continue;
				foreach ($values as $index => $width) $classes[$index][] = 'col-' . ($size === 'xs' ? '' : $size . '-') . $width;
			}
			$data['columnClasses'] = array_map(static fn ($values) => implode(' ', $values), $classes);
			$data['gridVisibility'] = implode(' ', array_map(
				static fn ($size) => 'd-' . ($size === 'xs' ? '' : $size . '-') . ($options['active' . ucfirst($size)] ? 'flex' : 'none'),
				['xs', 'sm', 'md', 'lg']
			));
			$slots = array_fill(0, count($classes), []);
			foreach ($data['nodes'] ?? [] as $index => $node) {
				$slot = $node['slot'] ?? $index;
				if (!is_int($slot) || $slot < 0 || $slot > count($data['nodes']) + 11) {
					throw new RuntimeException('Ungueltiger Grid-Ablageplatz.');
				}
				for ($i = count($slots); $i <= $slot; $i++) $slots[$i] = [];
				$slots[$slot][] = $node;
			}
			$data['slots'] = $slots;
			$data['slotVisibility'] = [];
			$data['slotHidden'] = [];
			foreach ($slots as $index => $nodes) {
				$visible = [];
				foreach ($nodes as $node) {
					if (($node['general']['Active'] ?? true) && ($node['languageVisible'] ?? true)) {
						$visible = array_merge($visible, $node['general']['Viewports'] ?? ['XS', 'SM', 'MD', 'LG']);
					}
				}
				$data['slotHidden'][$index] = $nodes && !$visible;
				$data['slotVisibility'][$index] = implode(' ', array_map(
					static fn ($size) => 'd-' . ($size === 'XS' ? '' : strtolower($size) . '-') . (!$nodes || in_array($size, $visible, true) ? 'block' : 'none'),
					['XS', 'SM', 'MD', 'LG']
				));
			}
			return $data;
		},
	];
};
