<?php
// classes/Packagist.php

declare(strict_types=1);

class Packagist
{
    private function fetchJson(string $url): array
    {
        $ctx = stream_context_create(['http' => ['timeout' => 20, 'header' => "User-Agent: PHP-Setup\r\n"]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data === false) {
            return [];
        }
        $json = json_decode($data, true);
        return is_array($json) ? $json : [];
    }

    public function getProjectList(string $tags, string $search, string $type = ''): array
    {
        $query = [];
        if ($search !== '') $query[] = 'q=' . urlencode($search);
        if ($tags !== '') $query[] = 'tags=' . urlencode($tags);
		if ($type !== '')   $query[] = 'type=' . urlencode($type);
        $url = 'https://packagist.org/search.json' . (empty($query) ? '' : ('?' . implode('&', $query)));
        $res = $this->fetchJson($url);

        # Weitere Ergebnisseiten laden (Packagist liefert 15 Treffer je Seite), nur von packagist.org
        $results = $res['results'] ?? [];
        for ($page = 1; !empty($res['next']) && str_starts_with((string)$res['next'], 'https://packagist.org/') && $page < 5; $page++) {
            $res = $this->fetchJson($res['next']);
            $results = array_merge($results, $res['results'] ?? []);
        }

        $items = [];

        foreach ($results as $r) {
            $items[ $r['name'] ] = [
                'name' => $r['name'] ?? '',
                'description' => $r['description'] ?? '',
                'url' => $r['url'] ?? '',
                'repository' => $r['repository'] ?? '',
                'downloads' => $r['downloads'] ?? 0,
                'favers' => $r['favers'] ?? 0,
                'author' => $r['vendor'] ?? '', // vendor maps roughly to author/org
				'type' => '',
                'keywords' => [],
                'installed' => false,
                'installed_version' => '',
                'update_available' => false,
            ];

			# Mehr Details zu den Versionen abrufen
			$pkgUrl = 'https://repo.packagist.org/p2/' . $r['name'] . '.json';
			$data = $this->fetchJson($pkgUrl);

			
			if (isset($data['packages'][$r['name']]) && is_array($data['packages'][$r['name']])) {
				# Typ und Keywords der neuesten Version (erster Eintrag ist vollständig, die übrigen sind nur Unterschiede dazu)
				$latest = reset($data['packages'][$r['name']]);
				$items[ $r['name'] ]['type'] = (string)($latest['type'] ?? '');
				$items[ $r['name'] ]['keywords'] = (array)($latest['keywords'] ?? []);
				foreach ($data['packages'][$r['name']] as $ver) {
					$items[ $r['name'] ]['versions'][$ver['version']] = [
						'version' => $ver['version'] ?? '',
						'require' => $ver['require'] ?? [],
						'time' => $ver['time'] ?? null
					];
				}
			}

			$items[ $r['name'] ]['version_latest'] = array_key_first($items[ $r['name'] ]['versions']?:[]);

        }
        return $items;
    }

    public function getProject(string $package): array
    {
        // Primary package info: includes versions via /packages/<vendor>/<name>.json
        $pkgUrl = 'https://repo.packagist.org/p2/' . $package . '.json';
        $data = $this->fetchJson($pkgUrl);

        $versions = [];
        if (isset($data['packages'][$package]) && is_array($data['packages'][$package])) {
            foreach ($data['packages'][$package] as $ver) {
                $versions[$ver['version']] = [
                    'version' => $ver['version'] ?? '',
                    'require' => $ver['require'] ?? [],
                    'time' => $ver['time'] ?? null
                ];
            }
        }

        // Attempt to infer metadata for UI continuity
        $metaUrl = 'https://packagist.org/packages/' . $package . '.json';
        $meta = $this->fetchJson($metaUrl);
        $desc = $meta['package']['description'] ?? '';
        $url = $meta['package']['repository'] ?? ('https://packagist.org/packages/' . $package);
        $authors = $meta['package']['authors'][0]['name'] ?? '';
		$type = $meta['package']['type'] ?? '';
        $wiki = null; // Not standardized; could be from extra, if present
        if (isset($meta['package']['extra']['wiki'])) {
            $wiki = $meta['package']['extra']['wiki'];
        }

        // Security advisories endpoint example (for existence)
        // https://packagist.org/api/security-advisories/?packages[]=vendor/name
        // Not parsed here, but could be used to flag advisories.

        return [
            'versions' => $versions,
			'version_latest' => array_key_first($versions),
            'description' => $desc,
            'url' => $url,
            'author' => $authors,
            'wiki' => $wiki,
			'type' => $meta['package']['type'] ?? ''
        ];
    }
	
	private function getPackageType(string $package): string
{
    // Detail-Endpoint liefert den Typ
    $metaUrl = 'https://packagist.org/packages/' . $package . '.json';
    $meta = $this->fetchJson($metaUrl);

    if (isset($meta['package']['type'])) {
        return $meta['package']['type'];
    }
    return '';
}
}
