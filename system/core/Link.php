<?php
namespace fremeo\core;

/**
 * SEO-Links eines Moduls anlegen, umbenennen, löschen und lesen.
 * Ist eine SEO-URL schon vergeben, wird automatisch _1, _2, ... angehängt (Lücken werden aufgefüllt).
 * Die Prüfung auf vergebene URLs läuft über alle übergebenen Modul-Datenbanken, geschrieben wird nur in die eigene.
 *
 * Verwendung: $C['Link']->create([...]) bzw. $C['Link']->createOne('blog/post', 'blog__post', 'fremeo/blog');
 */
class Link {

	/** @var \phploader\CData[] Alle Datenbanken, in denen vergebene SEO-URLs geprüft werden (inkl. der eigenen). */
	private array $allDB;

	/**
	 * @param \phploader\CData $DB    Datenbank des Moduls, in die geschrieben wird
	 * @param array            $allDB optional: weitere CData-Objekte (z.B. aller Module) für die Prüfung auf vergebene URLs
	 */
	public function __construct(private \phploader\CData $DB, array $allDB = []) {
		foreach ([$DB, ...$allDB] as $db) {
			$this->allDB[spl_object_id($db)] = $db;
		}
	}

	/** Link-ID einer SEO-URL (CRC32b-Hash). */
	public function id(string $SeoURL): string {
		return hash('crc32b', $SeoURL);
	}

	/** Umlaute und Akzente => einfache Buchstaben (der Text wird vorher in Kleinbuchstaben gewandelt). */
	private const TRANSLIT = [
		'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
		'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'æ' => 'ae',
		'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
		'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
		'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'œ' => 'oe',
		'ú' => 'u', 'ù' => 'u', 'û' => 'u',
		'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
	];

	/**
	 * Macht aus beliebigem Text eine saubere SEO-URL: Kleinbuchstaben, Umlaute/Akzente als einfache Buchstaben (ä => a, ß => ss),
	 * Leer- und Sonderzeichen => _, erlaubt sind a-z 0-9 . _ - und / als Trenner. Z.B. "Gallery/Schöner Urlaub 2026!" => "gallery/schoner_urlaub_2026".
	 * Wird nicht automatisch in create()/rename() angewendet (bestehende URLs wie "admin/user.edit" bleiben unverändert).
	 */
	public function normalize(string $text): string {
		$seo = strtr(mb_strtolower($text), self::TRANSLIT);
		$seo = preg_replace('~[^a-z0-9/._-]+~', '_', $seo);
		$seo = preg_replace(['~_+~', '~_*/+_*~'], ['_', '/'], $seo);
		return trim($seo, '/_.-');
	}

	/** Alle vorhandenen SEO-URLs (in allen Datenbanken), die mit einer der Basis-URLs beginnen, z.B. ["blog", "blog_1", "blog_3"]. */
	private function fetchExisting(array $baseSeo): array {
		foreach (array_filter($baseSeo, 'strlen') as $seo) {
			$F['LINK']['W'][]['FromURL']['LIKE%'] = $seo;
		}
		if (empty($F)) return [];

		$urls = [];
		foreach ($this->allDB as $DB) {
			$D = [];
			$DB->get_object($D, $F);
			$urls = [...$urls, ...array_column($D['LINK']['D'] ?? [], 'FromURL')];
		}
		return $urls;
	}

	/** Nächste freie SEO-URL: "blog" -> "blog_1" -> "blog_2" ... und merkt sie sich in $existing. */
	private function freeSeo(string $seo, array &$existing): string {
		$free = $seo;
		for ($i = 1; in_array($free, $existing, true); $i++) {
			$free = "{$seo}_{$i}";
		}
		$existing[] = $free;
		return $free;
	}

	/** Legt einen Link an und gibt die Link-ID zurück. */
	public function createOne(string $SeoURL, string $Page, string $ModuleId, array|string|null $Param = null): string {
		return $this->create(['SeoURL' => $SeoURL, 'Page' => $Page, 'ModuleId' => $ModuleId, 'Param' => $Param])[0]['LinkId'];
	}

	/**
	 * Legt einen oder mehrere SEO-Links an.
	 *
	 * Eingabe: ['SeoURL' => string, 'Page' => string, 'ModuleId' => string, 'Param' => array|string (optional)]
	 *          oder eine Liste davon (beliebige Keys).
	 * Rückgabe: je Key ['LinkId', 'Page', 'ModuleId', 'SeoUrl' (final, inkl. _n), 'Param']
	 */
	public function create(array $d): array {
		if (isset($d['SeoURL'])) $d = [$d];

		$existing = $this->fetchExisting(array_column($d, 'SeoURL'));
		$D = $result = [];

		foreach ($d as $k => $e) {
			$Param = $e['Param'] ?? [];
			$param = is_string($Param) ? $Param : http_build_query($Param);
			$seo   = $this->freeSeo($e['SeoURL'], $existing);
			$id    = $this->id($seo);

			$D['LINK']['D'][$id] = [
				'Active'   => 1,
				'FromURL'  => $seo,
				'ModuleId' => $e['ModuleId'],
				'ToURL'    => "R[Page]={$e['Page']}&R[ModuleId]={$e['ModuleId']}" . ($param !== '' ? "&$param" : ''),
			];
			$result[$k] = ['LinkId' => $id, 'Page' => $e['Page'], 'ModuleId' => $e['ModuleId'], 'SeoUrl' => $seo, 'Param' => $Param];
		}

		if ($D) $this->DB->set_object($D);
		return $result;
	}

	/**
	 * Benennt einen oder mehrere SEO-Links um (alter Eintrag wird gelöscht, neuer angelegt, ggf. mit _n).
	 *
	 * Eingabe: ['OldSeo' => string | 'OldSeoId' => string, 'NewSeo' => string] oder eine Liste davon.
	 * Rückgabe: je Key ['OldSeo', 'NewSeo' (final), 'OldId', 'NewId', 'Success']
	 */
	public function rename(array $d): array {
		if (isset($d['NewSeo'])) $d = [$d];

		foreach ($d as $k => $e) {
			$d[$k]['OldId'] = $e['OldSeoId'] ?? (isset($e['OldSeo']) ? $this->id($e['OldSeo']) : null);
		}
		$ids = array_filter(array_column($d, 'OldId'));
		$old = $ids ? ($this->getById($ids)['LINK']['D'] ?? []) : [];
		$existing = $this->fetchExisting(array_column($d, 'NewSeo'));
		$U = $result = [];

		foreach ($d as $k => $e) {
			$row = $old[$e['OldId']] ?? null;
			$result[$k] = ['OldSeo' => $row['FromURL'] ?? $e['OldSeo'] ?? null, 'NewSeo' => $e['NewSeo'], 'OldId' => $e['OldId'], 'NewId' => null, 'Success' => false];
			if (!$row) continue;

			$seo   = $this->freeSeo($e['NewSeo'], $existing);
			$newId = $this->id($seo);

			$U['LINK']['D'][$newId] = ['Active' => 1, 'FromURL' => $seo, 'ModuleId' => $row['ModuleId'] ?? null, 'ToURL' => $row['ToURL']];
			$U['LINK']['D'][$e['OldId']] = '__DELETE__';
			$result[$k] = array_replace($result[$k], ['NewSeo' => $seo, 'NewId' => $newId, 'Success' => true]);
		}

		if ($U) $this->DB->set_object($U);
		return $result;
	}

	public function renameById(string $oldSeoId, string $newSeo): bool {
		return $this->rename(['OldSeoId' => $oldSeoId, 'NewSeo' => $newSeo])[0]['Success'];
	}

	public function renameBySeo(string $oldSeo, string $newSeo): bool {
		return $this->renameById($this->id($oldSeo), $newSeo);
	}

	/** Löscht SEO-Links anhand ihrer ID(s). */
	public function deleteById(string|array $ids): void {
		$D = [];
		foreach ((array)$ids as $id) {
			$D['LINK']['D'][$id] = '__DELETE__';
		}
		if ($D) $this->DB->set_object($D);
	}

	/** Löscht SEO-Links anhand ihrer SEO-URL(s). */
	public function deleteBySeo(string|array $seo): void {
		$this->deleteById(array_map([$this, 'id'], (array)$seo));
	}

	/** Holt SEO-Links anhand ihrer ID(s). */
	public function getById(string|array $ids): array|null {
		$F['LINK']['W'][0]['ID'] = (array)$ids;
		$this->DB->get_object($D, $F);
		return $D ?: null;
	}

	/** Holt SEO-Links anhand ihrer SEO-URL(s). */
	public function getBySeo(string|array $seo): array|null {
		return $this->getById(array_map([$this, 'id'], (array)$seo));
	}
}
