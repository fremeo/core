<?php

final class Smarty_I18n {
	private Closure $resolver;
	private static array $messages = [];

	public function __construct(private string $locale, callable $resolver) {
		$this->resolver = Closure::fromCallable($resolver);
	}

	public function compile(array $args, Smarty_Internal_TemplateCompilerBase $compiler): string {
		if (array_diff(array_keys($args), ['key', 'module', 'values'])) {
			throw new RuntimeException('i18n erwartet key, optional module und ein values-Array.');
		}
		$literal = [];
		foreach (['key', 'module'] as $name) {
			if (isset($args[$name])) {
				if (!preg_match('/^([\'"])([A-Za-z0-9_.:\/-]+)\1$/D', $args[$name], $match)) {
					throw new RuntimeException("i18n $name muss eine feste Zeichenkette sein.");
				}
				$literal[$name] = $match[2];
			}
		}
		if (!isset($literal['key'])) throw new RuntimeException('i18n benoetigt key.');
		$key = $literal['key'];
		$pattern = ($this->resolver)($key, $literal['module'] ?? null, $compiler);
		if (!is_string($pattern)) throw new RuntimeException("i18n $key: Resolver muss eine Zeichenkette liefern.");
		$message = self::message($this->locale, $pattern, $key);
		if (!$message['arguments'] && !isset($args['values'])) {
			$text = self::format($this->locale, $pattern, [], $key);
			// PHP-Tags bleiben Text und duerfen Smartys PHP-Blockzusammenfuehrung nicht beeinflussen.
			return str_contains($text, '<?') || str_contains($text, '?>')
				? '<?php echo ' . var_export($text, true) . '; ?>' : $text;
		}
		if ($message['arguments'] && !isset($args['values'])) {
			throw new RuntimeException("i18n $key ({$this->locale}): Platzhalter benoetigen values.");
		}
		return '<?php require_once ' . var_export(__FILE__, true) . '; echo Smarty_I18n::format('
			. var_export($this->locale, true) . ', ' . var_export($pattern, true) . ', '
			. ($args['values'] ?? '[]') . ', ' . var_export($key, true) . '); ?>';
	}

	public static function format(string $locale, string $pattern, array $values, string $key = ''): string {
		$message = self::message($locale, $pattern, $key);
		foreach ($message['arguments'] as $name => $types) {
			if (!array_key_exists($name, $values)) {
				throw new RuntimeException("i18n $key ($locale): Platzhalter $name fehlt.");
			}
			$value = $values[$name];
			if (!is_string($value) && !is_int($value) && !is_float($value)) {
				throw new RuntimeException("i18n $key ($locale): Platzhalter $name muss Text oder eine Zahl sein.");
			}
			if (array_intersect($types, ['plural', 'selectordinal', 'number', 'date', 'time', 'spellout', 'ordinal', 'duration', 'choice'])
				&& !is_int($value) && !is_float($value)) {
				throw new RuntimeException("i18n $key ($locale): Platzhalter $name muss numerisch sein.");
			}
			if (is_string($value)) {
				$values[$name] = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			}
		}
		if ($pattern === '') return '';
		try {
			$result = $message['formatter']->format($values);
		} catch (IntlException $error) {
			throw new RuntimeException("i18n $key ($locale): " . $error->getMessage(), 0, $error);
		}
		if ($result === false) {
			throw new RuntimeException("i18n $key ($locale): " . $message['formatter']->getErrorMessage());
		}
		return $result;
	}

	private static function message(string $locale, string $pattern, string $key): array {
		$id = $locale . "\0" . $pattern;
		if (isset(self::$messages[$id])) return self::$messages[$id];
		if (!class_exists(MessageFormatter::class)) throw new RuntimeException('i18n benoetigt die PHP-Erweiterung ext-intl.');
		if ($pattern === '') return ['formatter' => null, 'arguments' => []];
		try {
			$formatter = new MessageFormatter($locale, $pattern);
		} catch (IntlException $error) {
			throw new RuntimeException("i18n $key ($locale): " . $error->getMessage(), 0, $error);
		}
		$arguments = [];
		$stack = ['message'];
		$quoted = false;
		for ($i = 0, $length = strlen($pattern); $i < $length; $i++) {
			$char = $pattern[$i];
			if ($char === "'") {
				$next = $pattern[$i + 1] ?? '';
				if ($next === "'") { $i++; continue; }
				if ($quoted) { $quoted = false; continue; }
				if (in_array($next, ['{', '}'], true)
					|| ($next === '#' && (in_array('plural', $stack, true) || in_array('selectordinal', $stack, true)))
					|| ($next === '|' && in_array('choice', $stack, true))) {
					$quoted = true;
				}
				continue;
			}
			if ($quoted) continue;
			if ($char === '}') {
				array_pop($stack);
			} elseif ($char === '{') {
				if (in_array(end($stack), ['plural', 'select', 'selectordinal'], true)) {
					$stack[] = 'message';
					continue;
				}
				if (!preg_match('/\G\{\s*([^\s{},]+)\s*(?:\}|,\s*([A-Za-z]+)\s*([,}]))/', $pattern, $match, 0, $i)) {
					throw new RuntimeException("i18n $key ($locale): Nicht unterstuetzter ICU-Platzhalter.");
				}
				$type = strtolower($match[2] ?? '');
				$name = $match[1];
				$arguments[$name][] = $type;
				$i += strlen($match[0]) - 1;
				if ($pattern[$i] !== '}') $stack[] = $type;
			}
		}
		return self::$messages[$id] = ['formatter' => $formatter, 'arguments' => $arguments];
	}
}
