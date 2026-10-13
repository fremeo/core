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

	/**
	 * Registriert eine vorhandene Datei im Modul: wird in den Modulordner verschoben und in FILE eingetragen.
	 *
	 * @param string $source Quelldatei (wird verschoben)
	 * @param string $name   Originalname inkl. Endung; leer = Name der Quelldatei
	 * @return array|null ['Id' => string, 'Name' => string, 'Size' => int, 'Extension' => string] oder null (nicht erlaubt / nicht vorhanden)
	 */
	public function addFile(string $source, string $name = '', array $allowed = []): ?array {
		if (!is_file($source)) return null;

		$name = $name !== '' ? basename($name) : basename($source);
		$ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if (in_array($ext, self::BLOCKED, true) || ($allowed && !in_array($ext, $allowed, true))) return null;

		$id   = md5_file($source);
		$info = ['Name' => pathinfo($name, PATHINFO_FILENAME), 'Size' => filesize($source), 'Extension' => $ext];

		// copy statt rename: die Datei erbt die Rechte des Zielordners (rename behält unter Windows die Rechte des Temp-Ordners)
		$this->C['Filesystem']->copy($source, "{$this->dir}{$id}.{$ext}", true);
		$this->C['Filesystem']->remove($source);

		$D = ['FILE' => ['D' => [$id => $info]]];
		$this->C[$this->moduleId]['CData']->set_object($D);
		return ['Id' => $id] + $info;
	}

	/** Pfad einer gespeicherten Datei des eigenen Moduls. */
	public function path(string $id, string $ext): string {
		return "{$this->dir}{$id}.{$ext}";
	}

	/**
	 * Entpackt eine ZIP-Datei flach (ohne Unterordner) in einen Zielordner.
	 * Nur Dateien mit erlaubter Endung werden entpackt, alle anderen übersprungen.
	 * Unabhängig von add()/delete(): es wird nichts in die Datenbank geschrieben.
	 *
	 * @param string $zip       Pfad der ZIP-Datei
	 * @param string $targetDir Zielordner (wird angelegt)
	 * @param array  $allowed   erlaubte Endungen (klein, ohne Punkt). Leer = alle ausser ausführbarem Code.
	 * @return string[] Pfade der entpackten Dateien
	 */
	public function unzip(string $zip, string $targetDir, array $allowed = []): array {
		$za = new \ZipArchive();
		if (!is_file($zip) || $za->open($zip) !== true) return [];

		$targetDir = rtrim($targetDir, '/\\') . '/';
		$this->C['Filesystem']->mkdir($targetDir);

		$out = [];
		for ($i = 0; $i < $za->numFiles; $i++) {
			$entry = $za->getNameIndex($i);
			if ($entry === false || str_ends_with($entry, '/')) continue;

			$base = basename(str_replace('\\', '/', $entry)); // verhindert Pfade wie ../
			$ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));
			if ($base === '' || $base[0] === '.' || in_array($ext, self::BLOCKED, true) || ($allowed && !in_array($ext, $allowed, true))) continue;

			$stream = $za->getStream($entry);
			if (!$stream) continue;

			$target = $targetDir . $i . '_' . $base; // Index vermeidet gleiche Namen aus verschiedenen Ordnern
			$fh = fopen($target, 'wb');
			stream_copy_to_stream($stream, $fh);
			fclose($fh);
			fclose($stream);
			$out[] = $target;
		}
		$za->close();
		return $out;
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
