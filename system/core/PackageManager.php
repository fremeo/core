<?php
namespace fremeo\core;

class PackageManager {
	public const UNAVAILABLE = 'Paketverwaltung nicht verfuegbar: setup/Composer.php oder setup/composer.phar fehlt oder ist nicht lesbar.';

	public function __construct(private string $projectRoot, private ?\Closure $invalidateTemplates = null) {}

	public function isAvailable(): bool {
		return is_file($this->projectRoot . '/setup/Composer.php')
			&& is_readable($this->projectRoot . '/setup/Composer.php')
			&& is_file($this->projectRoot . '/setup/composer.phar')
			&& is_readable($this->projectRoot . '/setup/composer.phar');
	}

	public function run(string $command): array {
		if (!$this->isAvailable()) {
			return ['success' => false, 'output' => self::UNAVAILABLE];
		}
		require_once $this->projectRoot . '/setup/Composer.php';
		try {
			return (new \Composer($this->projectRoot . '/setup/composer.phar', $this->projectRoot))->run($command);
		} finally {
			if ($this->invalidateTemplates !== null && preg_match('/^(require|install|update|reinstall|remove)\b/', $command)) {
				($this->invalidateTemplates)();
			}
		}
	}

	public function getInstalledPackages(): array {
		$root = rtrim($this->projectRoot, '/\\');
		$json = json_decode(file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
		$vendor = $root . '/' . trim((string)($json['config']['vendor-dir'] ?? 'vendor'), '/\\');
		$path = $vendor . '/composer/installed.json';
		if (!is_file($path)) {
			throw new \RuntimeException('Installierte Paketinformationen fehlen: ' . $path);
		}
		$installed = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
		$packages = [];
		foreach ($installed['packages'] ?? $installed as $package) {
			if (empty($package['name'])) continue;
			$packages[$package['name']] = [
				'version' => $package['version'] ?? '',
				'type' => $package['type'] ?? '',
				'description' => $package['description'] ?? '',
				'author' => $package['authors'][0]['name'] ?? '',
			];
		}
		ksort($packages);
		return $packages;
	}
}
