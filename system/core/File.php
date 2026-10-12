<?php
namespace fremeo\core;

use Symfony\Component\Finder\Finder;

/**
 * Dateiverwaltung eines Moduls.
 *
 * Jedes Modul mit CData bekommt in init.php eine eigene, an das Modul gebundene Instanz.
 * Dateien liegen in data/{vendor}~{modul}/file/{md5}.{ext}
 * und werden im Pattern FILE der CData dieses Moduls eingetragen.
 *
 * Verwendung (ein Modul nutzt nur seine eigene Instanz):
 *   $files = $C['fremeo/blog']['File']->add($_FILES['images']);  // eine oder mehrere Dateien
 *   $C['fremeo/blog']['File']->delete($id);                      // oder Array von IDs
 */
class File {

	/** Endungen, die nie gespeichert werden (ausführbarer Code). */
	private const BLOCKED = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'htaccess'];

	private array $C;
	private string $dir;

	/** $C wird als Referenz gehalten, damit später registrierte Modul-CData verfügbar sind. */
	public function __construct(array &$C, private string $moduleId) {
		$this->C   = &$C;
		$this->dir = 'data/' . str_replace('/', '~', $moduleId) . '/file/';
	}

	/**
	 * Speichert hochgeladene Dateien im Ordner des eigenen Moduls.
	 *
	 * @param array $upload  $_FILES['feldname'] (einzeln oder mehrfach)
	 * @param array $allowed optional: erlaubte Endungen (klein, ohne Punkt), z.B. ['jpg', 'png']. Leer = alle ausser ausführbarem Code.
	 * @return array ID => ['Name' => string, 'Size' => int, 'Extension' => string]
	 */
	public function add(array $upload, array $allowed = []): array {
		$saved = [];
		$names = (array)$upload['name'];

		foreach ((array)$upload['tmp_name'] as $k => $tmp) {
			if (!$this->isUpload($tmp)) continue;

			$ext = strtolower(pathinfo($names[$k], PATHINFO_EXTENSION));
			if (in_array($ext, self::BLOCKED, true) || ($allowed && !in_array($ext, $allowed, true))) continue;

			$id = md5_file($tmp);
			$this->C['Filesystem']->copy($tmp, "{$this->dir}{$id}.{$ext}", true);

			$saved[$id] = [
				'Name'      => pathinfo($names[$k], PATHINFO_FILENAME),
				'Size'      => filesize($tmp),
				'Extension' => $ext,
			];
		}

		if ($saved) {
			$D = ['FILE' => ['D' => $saved]]; // set_object erwartet eine Variable (Referenz)
			$this->C[$this->moduleId]['CData']->set_object($D);
		}
		return $saved;
	}

	/** Nur echte HTTP-Uploads (in Tests überschreibbar). */
	protected function isUpload(string $tmp): bool {
		return is_uploaded_file($tmp);
	}

	/**
	 * Löscht Dateien des eigenen Moduls samt Datenbankeintrag und den erzeugten Bildvarianten im Cache.
	 *
	 * @param string|array $ids Datei-ID(s)
	 */
	public function delete(string|array $ids): void {
		$ids = (array)$ids;
		$DB  = $this->C[$this->moduleId]['CData'];

		$F['FILE']['W'][0]['ID'] = $ids;
		$DB->get_object($D, $F);

		foreach ($ids as $id) {
			if (empty($D['FILE']['D'][$id])) continue;

			$this->C['Filesystem']->remove("{$this->dir}{$id}.{$D['FILE']['D'][$id]['Extension']}");
			$this->removeCache($id);
			$U['FILE']['D'][$id] = '__DELETE__';
		}

		if (!empty($U)) {
			$DB->set_object($U);
		}
	}

	/** Entfernt alle skalierten Varianten ({id}_{x}x{y}.{ext}) aus dem zentralen Cache. */
	private function removeCache(string $id): void {
		$cache = 'data_c/fremeo~core/file/';
		if (!is_dir($cache)) return;

		$this->C['Filesystem']->remove(
			iterator_to_array((new Finder())->files()->in($cache)->name("{$id}_*"))
		);
	}
}
