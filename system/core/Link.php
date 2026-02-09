<?php
namespace papp\phpapp;

class Link {

    private \papp\CData $DB;

    /**
     * Konstruktor
     *
     * @param \papp\CData $DB  Datenbank‑Objekt für set_object() / get_object()
     */
    public function __construct(\papp\CData $DB) {
        $this->DB = $DB;
    }

    /**
     * Erzeugt eine eindeutige Link‑ID aus einer SEO‑URL.
     * Die ID wird über einen CRC32b‑Hash generiert.
     *
     * @param string $SeoURL
     * @return string Hash‑ID
     */
    public function id(string $SeoURL): string {
        return hash("crc32b", $SeoURL);
    }

    /**
     * Holt alle vorhandenen SEO‑URLs, die mit einer bestimmten Basis‑URL beginnen.
     * Beispiel: Basis "blog" → liefert ["blog", "blog_1", "blog_3"]
     *
     * @param string $baseSeo
     * @return array Liste aller vorhandenen FromURL‑Werte
     */
    private function fetchExisting(string|array $baseSeo): array {
		if(is_array($baseSeo)) {
			foreach($baseSeo AS $k => $v) {
				if($v != '') {
				$F['LINK']['W'][]['FromURL']['LIKE%'] = $v;
				} else { $F['LINK']['W'][]['ID'] = '00000000';}
			}
		} else{
			if($baseSeo != '') {
				$F['LINK']['W'][0]['FromURL']['LIKE%'] = $baseSeo;
			} else { $F['LINK']['W'][0]['ID'] = '00000000';}
		}
        $this->DB->get_object($D, $F);

        $urls = [];
        if (!empty($D['LINK']['D'])) {
            foreach ($D['LINK']['D'] as $row) {
                $urls[] = $row['FromURL'];
            }
        }
        return $urls;
    }

    /**
     * Findet die nächste freie SEO‑URL.
     *
     * Regeln:
     * - Wenn Basis‑URL frei ist → Basis verwenden
     * - Wenn Basis existiert → "_1" testen
     * - Wenn "_1" existiert → "_2" testen
     * - Lücken werden berücksichtigt:
     *   Beispiel: existiert ["blog", "blog_1", "blog_3"] → Ergebnis = "blog_2"
     *
     * @param string $baseSeo
     * @param array $existing Liste vorhandener URLs
     * @return string freie SEO‑URL
     */
    private function findFreeSeo(string $baseSeo, array $existing): string {
        if (!in_array($baseSeo, $existing, true)) {
            return $baseSeo;
        }

        $counter = 1;
        while (in_array($baseSeo . '_' . $counter, $existing, true)) {
            $counter++;
        }

        return $baseSeo . '_' . $counter;
    }

	function createOne(string $SeoURL, string $Page, string $ModuelId, array|string $Param=null): string {
		$_newLink[] = [
						'Page' => $Page,
						'ModuleId' => $ModuelId,
						'Param' => $Param,
						'SeoURL' => $SeoURL,
					];
		$ret = $this->create($_newLink);
		return $ret['LinkId'];
	}



    /**
	 * Legt neue SEO‑Links an.
	 *
	 * Unterstützte Eingabeformate:
	 *
	 * 1) Einzelner Link:
	 *    $d = [
	 *        'Page'     => int|string,
	 *        'ModuleId' => int|string,
	 *        'SeoURL'   => string,
	 *        'Param'    => array|string (optional)
	 *    ];
	 *
	 * 2) Mehrere Links:
	 *    $d = [
	 *        'key1' => [
	 *            'Page'     => int|string,
	 *            'ModuleId' => int|string,
	 *            'SeoURL'   => string,
	 *            'Param'    => array|string (optional)
	 *        ],
	 *        'key2' => [...],
	 *        ...
	 *    ];
	 *
	 * Rückgabeformat:
	 * [
	 *     'keyX' => [
	 *         'Page'     => int|string,
	 *         'ModuleId' => int|string,
	 *         'SeoUrl'   => string (final, inkl. _n),
	 *         'LinkId'   => string (Hash),
	 *         'Param'    => array|string,
	 *     ],
	 *     ...
	 * ]
	 *
	 * Funktionen:
	 * - prüft, ob die gewünschte SEO‑URL bereits existiert
	 * - findet automatisch die nächste freie Variante (inkl. Lückenerkennung)
	 * - erzeugt die Link‑ID über Hash
	 * - speichert alle neuen Links in einem einzigen set_object()‑Aufruf
	 *
	 * @param array $d
	 * @return array
	 */
    public function create(array $d): array {
		// Einzelnen Link in Mehrfach‑Array umwandeln 
		if (isset($d['SeoURL']) && !isset($d[0])) { 
			$d = [0 => $d]; 
		}
        $D = ['LINK' => ['D' => []]];
		$result = [];

		#Erzeugt vorher eine Liste von allen exsistierenden SEO Links
		foreach ($d as $k => $vEntry) {
			$SeoUrl[]   = $vEntry['SeoURL'];
		}
		$existing = $this->fetchExisting($SeoUrl);// vorhandene URLs holen

        foreach ($d as $k => $vEntry) {
            $Page     = $vEntry['Page'];
            $ModuleId = $vEntry['ModuleId'];
            $SeoUrl   = $vEntry['SeoURL'];
            $Param    = $vEntry['Param'] ?? [];

            ##$existing = $this->fetchExisting($SeoUrl);// vorhandene URLs holen
            $finalSeo = $this->findFreeSeo($SeoUrl, $existing);// freie URL finden (inkl. Lückenerkennung)

            $ID       = $this->id($finalSeo);// ID erzeugen

			$_toURL_Param = is_string($Param) ? $Param : http_build_query($Param);
            $D['LINK']['D'][$ID] = [
                'Active'   => 1,
                'FromURL'  => $finalSeo,
                'ModuleId' => $ModuleId,
                'ToURL'    => "R[Page]=$Page&R[ModuleId]=$ModuleId" . ($_toURL_Param !== '' ? '&'.$_toURL_Param : '' ),
            ];

			$result[$k] = [ 'LinkId' => $ID, 'Page' => $Page, 'ModuleId' => $ModuleId, 'SeoUrl' => $finalSeo, 'LinkId' => $ID, 'Param' => $Param ];
        }

        $this->DB->set_object($D);// speichern

		return $result;
    }

    /**
     * Bennent eine bestehende SEO‑URL um.
     *
     * Verhalten:
     * - alte SEO‑URL wird gelöscht
     * - neue SEO‑URL wird angelegt
     * - falls neue URL bereits existiert → automatische Vergabe der nächsten freien Variante
     *
     * @param string $oldSeo bisherige SEO‑URL
     * @param string $newSeo gewünschte neue SEO‑URL
     * @return bool
     */
    public function renameById(string $oldSeoId, string $newSeo): bool {
        $existing = $this->fetchExisting($newSeo);
        $finalSeo = $this->findFreeSeo($newSeo, $existing);

        $newId = $this->id($finalSeo);

        $F['LINK']['W'][0]['ID'] = [$oldSeoId];
        $this->DB->get_object($D, $F);

        if (empty($D['LINK']['D'][$oldSeoId])) {
            return false;
        }

        $row = $D['LINK']['D'][$oldSeoId];

        $U['LINK']['D'][$newId] = [
            'Active'   => 1,
            'FromURL'  => $finalSeo,
            'ModuleId' => $row['ModuleId'],
            'ToURL'    => $row['ToURL'],
        ];

        $U['LINK']['D'][$oldSeoId] = '__DELETE__';

        $this->DB->set_object($U);
		return true;
    }

	public function renameBySeo(string $oldSeo, string $newSeo): bool {
		$oldId = $this->id($oldSeo);
		return $this->renameById($oldId, $newSeo);
    }

	/**
	 * Bennent einen oder mehrere SEO‑Links um.
	 *
	 * Unterstützte Eingabeformate:
	 *
	 * 1) Einzelner Eintrag:
	 *    $d = [
	 *        'OldSeo'   => string,   // oder
	 *        'OldSeoId' => string,
	 *        'NewSeo'   => string
	 *    ];
	 *
	 * 2) Mehrere Einträge:
	 *    $d = [
	 *        ['OldSeo' => 'blog', 'NewSeo' => 'artikel'],
	 *        ['OldSeoId' => 'a1b2c3d4', 'NewSeo' => 'demo'],
	 *        ...
	 *    ];
	 *
	 * Rückgabeformat:
	 * [
	 *     [
	 *         'OldSeo'  => string|null,
	 *         'NewSeo'  => string (final, inkl. _n),
	 *         'OldId'   => string,
	 *         'NewId'   => string,
	 *         'Success' => bool
	 *     ],
	 *     ...
	 * ]
	 */
	public function rename(array $d): array
	{
		// Einzelnen Eintrag in Array umwandeln
		if ((isset($d['OldSeo']) || isset($d['OldSeoId'])) && isset($d['NewSeo'])) {
			$d = [0 => $d];
		}

		$result = [];
		$U = ['LINK' => ['D' => []]];
		$F = ['LINK' => ['W' => []]];

		// 1. Alle benötigten IDs sammeln
		foreach ($d as $k => $entry) {

			if (isset($entry['OldSeoId'])) {
				$oldId = $entry['OldSeoId'];
			} elseif (isset($entry['OldSeo'])) {
				$oldId = $this->id($entry['OldSeo']);
			} else {
				$result[$k] = [
					'OldSeo'  => $entry['OldSeo'] ?? null,
					'NewSeo'  => $entry['NewSeo'] ?? null,
					'OldId'   => null,
					'NewId'   => null,
					'Success' => false
				];
				continue;
			}

			$d[$k]['_OldId'] = $oldId;
			$F['LINK']['W'][]['ID'] = [$oldId];
		}

		// 2. Alle alten Datensätze in einem Rutsch holen
		$this->DB->get_object($existing, $F);

		// 3. Verarbeitung aller Einträge
		foreach ($d as $k => $entry) {

			$oldId = $entry['_OldId'];
			$newSeo = $entry['NewSeo'];

			if (empty($existing['LINK']['D'][$oldId])) {
				$result[$k] = [
					'OldSeo'  => $entry['OldSeo'] ?? null,
					'NewSeo'  => $newSeo,
					'OldId'   => $oldId,
					'NewId'   => null,
					'Success' => false
				];
				continue;
			}

			$row = $existing['LINK']['D'][$oldId];
			$oldSeo = $row['FromURL'];

			// freie neue SEO finden
			$existingNew = $this->fetchExisting($newSeo);
			$finalSeo = $this->findFreeSeo($newSeo, $existingNew);
			$newId = $this->id($finalSeo);

			// neuen Datensatz anlegen
			$U['LINK']['D'][$newId] = [
				'Active'   => 1,
				'FromURL'  => $finalSeo,
				'ModuleId' => $row['ModuleId'],
				'ToURL'    => $row['ToURL'],
			];

			// alten löschen
			$U['LINK']['D'][$oldId] = '__DELETE__';

			// Rückgabe
			$result[$k] = [
				'OldSeo'  => $oldSeo,
				'NewSeo'  => $finalSeo,
				'OldId'   => $oldId,
				'NewId'   => $newId,
				'Success' => true
			];
		}

		// 4. Alles in einem Rutsch speichern
		$this->DB->set_object($U);

		return $result;
	}



    /**
     * Löscht SEO‑Links anhand ihrer IDs.
     *
     * @param string|array $ids
     * @return void
     */
    public function deleteById(string|array $ids): void {
        $ids = is_array($ids) ? $ids : [$ids];

        $D = ['LINK' => ['D' => []]];
        foreach ($ids as $id) {
			$D['LINK']['D'][$id] = '__DELETE__';
        }

        $this->DB->set_object($D);

    }

    /**
     * Löscht SEO‑Links anhand ihrer SEO‑URLs.
     *
     * @param string|array $seo
     * @return void
     */
    public function deleteBySeo(string|array $seo): void {
        $seo = is_array($seo) ? $seo : [$seo];

        $ids = [];
        foreach ($seo as $s) {
            $ids[] = $this->id($s);
        }

        $this->deleteById($ids);
    }

    /**
     * Holt SEO‑Links anhand ihrer IDs.
     *
     * @param string|array $ids
     * @return array|null
     */
    public function getById(string|array $ids): array|null {
        $ids = is_array($ids) ? $ids : [$ids];

        $F['LINK']['W'][0]['ID'] = $ids;
        $this->DB->get_object($D, $F);

        return $D ?: null;
    }

    /**
     * Holt SEO‑Links anhand ihrer SEO‑URLs.
     *
     * @param string|array $seo
     * @return array|null
     */
    public function getBySeo(string|array $seo): array|null {
        $seo = is_array($seo) ? $seo : [$seo];

        $ids = [];
        foreach ($seo as $s) {
            $ids[] = $this->id($s);
        }

        return $this->getById($ids);
    }
}
