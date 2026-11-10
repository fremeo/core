<?php
namespace fremeo\core;

use RuntimeException;
use phploader\CData;

class Builder {
	private ?string $previewLanguage = null;

	public function __construct(private CData $db) {}

	public function enablePreview(string $language): void {
		if (!preg_match('/^[A-Z]{2}$/D', $language)) throw new RuntimeException('Ungueltige Builder-Vorschausprache.');
		$this->previewLanguage = $language;
	}

	public function getPreviewLanguage(): ?string {
		return $this->previewLanguage;
	}

	public static function appearance(mixed $general = []): array {
		if (!is_array($general) || array_diff(array_keys($general), ['CustomCssClass', 'CustomCss', 'Active', 'Viewports'])) {
			throw new RuntimeException('Ungueltige allgemeine Builder-Einstellungen.');
		}
		$general = array_replace(['CustomCssClass' => '', 'CustomCss' => '', 'Active' => true, 'Viewports' => ['XS', 'SM', 'MD', 'LG']], $general);
		if (!is_string($general['CustomCssClass']) || !preg_match('/^(?:[A-Za-z_][A-Za-z0-9_-]*(?: +[A-Za-z_][A-Za-z0-9_-]*)*)?$/D', $general['CustomCssClass'])
			|| !is_string($general['CustomCss']) || preg_match('/[{}<>\\\\\x00-\x08\x0b\x0c\x0e-\x1f]|@|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i', $general['CustomCss'])
			|| !is_bool($general['Active']) || !is_array($general['Viewports']) || !array_is_list($general['Viewports'])
			|| count(array_filter($general['Viewports'], 'is_string')) !== count($general['Viewports'])
			|| array_diff($general['Viewports'], ['XS', 'SM', 'MD', 'LG'])
			|| count(array_unique($general['Viewports'])) !== count($general['Viewports'])) {
			throw new RuntimeException('Ungueltige CSS-, Aktivierungs- oder Bildschirmgroessen-Einstellung.');
		}
		$classes = [];
		foreach (['XS' => '', 'SM' => '-sm', 'MD' => '-md', 'LG' => '-lg'] as $size => $suffix) {
			$classes[] = 'd' . $suffix . (in_array($size, $general['Viewports'], true) ? '-block' : '-none');
		}
		$visibility = implode(' ', $classes);
		return ['values' => $general, 'classes' => trim('builder-element ' . $general['CustomCssClass'] . ' ' . $visibility), 'visibility' => $visibility];
	}

	public function placeId(string $module, string $page, string $region, string $entity = '', ?string $scope = null, string $branch = 'frontend'): string {
		if (!preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#D', $module)
			|| !preg_match('/^[A-Za-z0-9_.-]+$/D', $page)
			|| !preg_match('/^[a-z][a-z0-9_-]*$/D', $region)) {
			throw new RuntimeException('Ungueltige Builder-Seitenzuordnung.');
		}
		$scope ??= $entity === '' ? 'page' : 'individual';
		if (!in_array($scope, ['individual', 'page', 'global'], true) || !in_array($branch, ['frontend', 'admin'], true)
			|| ($scope === 'individual' && $entity === '')) throw new RuntimeException('Ungueltige Builder-Ebene.');
		$key = ['branch' => $branch, 'region' => $region, 'scope' => $scope];
		if ($scope !== 'global') $key['page'] = $page;
		if ($scope === 'individual') $key['entity'] = $entity;
		return hash('sha256', json_encode($key, JSON_THROW_ON_ERROR));
	}

	public function validate(array $nodes): void {
		$ids = [];
		$walk = function (array $items) use (&$walk, &$ids): void {
			if (!array_is_list($items)) throw new RuntimeException('Builder-Knoten muessen eine geordnete Liste sein.');
			foreach ($items as $index => $node) {
				if (!is_array($node) || !is_string($node['id'] ?? null)
					|| !preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $node['id']) || isset($ids[$node['id']])) {
					throw new RuntimeException('Builder-Instanz-ID fehlt, ist ungueltig oder doppelt.');
				}
				$ids[$node['id']] = true;
				$slot = $node['slot'] ?? $index;
				if ((array_key_exists('slot', $node) && !is_int($node['slot'])) || $slot < 0 || $slot > count($items) + 11) {
					throw new RuntimeException('Ungueltiger Grid-Ablageplatz.');
				}
				self::appearance($node['general'] ?? []);
				if (($node['kind'] ?? '') === 'container') {
					if ((!isset($node['options']) && !in_array($node['layout'] ?? '', ['stack', 'one-column', 'two-columns'], true))
						|| (isset($node['options']) && (!is_array($node['options']) || ($node['module'] ?? '') !== 'fremeo/core' || ($node['type'] ?? '') !== 'container'))
						|| !is_array($node['children'] ?? null) || array_key_exists('translations', $node)) {
						throw new RuntimeException('Ungueltiger Builder-Container.');
					}
					if (isset($node['options'])) {
						$definition = Component::load('fremeo/core', 'container', ['Active' => 1, 'INFO' => ['Type' => 'core', 'ModulDir' => dirname(__DIR__, 2)]]);
						Component::options($definition['manifest'], $node['options']);
					}
					$walk($node['children']);
				} elseif (($node['kind'] ?? '') === 'component') {
					if (!is_string($node['module'] ?? null)
						|| !preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#D', $node['module'])
						|| !is_string($node['type'] ?? null) || !preg_match('/^[a-z][a-z0-9_-]*$/D', $node['type'])
						|| !is_array($node['options'] ?? []) || !is_array($node['data'] ?? [])
						|| (isset($node['source']) && (!is_array($node['source']) || !array_is_list($node['source'])))) {
						throw new RuntimeException('Ungueltige Builder-Komponente.');
					}
					foreach ($node['source'] ?? [] as $part) {
						if (!is_string($part) || $part === '') throw new RuntimeException('Ungueltiger Builder-Datenpfad.');
					}
					if (array_key_exists('translations', $node) && !is_array($node['translations'])) throw new RuntimeException('Ungueltige Builder-Sprachdaten.');
					foreach ($node['translations'] ?? [] as $code => $translation) {
						if (!is_string($code) || !preg_match('/^[A-Z]{2}$/D', $code) || !is_array($translation)
							|| !is_bool($translation['active'] ?? null) || array_diff(array_keys($translation), ['active', 'options'])
							|| (array_key_exists('options', $translation) && !is_array($translation['options']))) {
							throw new RuntimeException('Ungueltige Builder-Sprachdaten.');
						}
					}
				} else {
					throw new RuntimeException('Unbekannter Builder-Knotentyp.');
				}
			}
		};
		$walk($nodes);
	}

	public function saveDraft(string $module, string $page, string $region, string $language, array $nodes, string $entity = ''): void {
		$this->saveDrafts([['target' => compact('module', 'page', 'region', 'entity', 'language'), 'nodes' => $nodes]]);
	}

	public function saveDrafts(array $regions, ?array $providers = null, array $context = []): array {
		if (!array_is_list($regions) || !$regions) throw new RuntimeException('Keine Builder-Bereiche zum Speichern.');
		$update = [];
		$ids = [];
		foreach ($regions as $entry) {
			$target = $entry['target'] ?? null;
			if (!is_array($target) || array_diff(['module', 'page', 'region', 'language'], array_keys($target))
				|| count(array_filter($target, 'is_string')) !== count($target) || !is_array($entry['nodes'] ?? null)) {
				throw new RuntimeException('Ungueltige Builder-Speicherzuordnung.');
			}
			$id = $this->placeId($target['module'], $target['page'], $target['region'], $target['entity'] ?? '', $target['scope'] ?? null, $target['branch'] ?? 'frontend');
			$language = strtoupper($target['language']);
			if (!preg_match('/^[A-Z]{2}$/D', $language) || isset($ids[$id])) {
				throw new RuntimeException('Ungueltige oder doppelte Builder-Sprachzuordnung.');
			}
			$ids[$id] = true;
			if (array_key_exists('active', $entry) && !is_bool($entry['active'])) throw new RuntimeException('Ungueltige Builder-Bereichsaktivierung.');
			if (array_key_exists('deleted', $entry) && !is_bool($entry['deleted'])) throw new RuntimeException('Ungueltige Builder-Loeschvormerkung.');
			if (($entry['deleted'] ?? false) && ($entry['nodes'] || ($entry['active'] ?? true))) throw new RuntimeException('Geloeschte Builder-Ebene muss leer und inaktiv sein.');
			$this->validate($entry['nodes']);
			$filter = ['BUILDER' => ['W' => [0 => ['ID' => $id]], 'LANGUAGE' => []]];
			$this->db->get_object($existing, $filter);
			$old = $existing['BUILDER']['D'][$id] ?? [];
			$legacyIds = [];
			if (empty($old['DraftJson'])) {
				$index = function (array $nodes) use (&$index, &$legacyIds): void {
					foreach ($nodes as $node) {
						if ($node['kind'] === 'container') $index($node['children']);
						else $legacyIds[$node['id']] = true;
					}
				};
				foreach (['DraftJson', 'PublishedJson'] as $field) {
					$layouts = [];
					foreach ($old['LANGUAGE']['D'] ?? [] as $row) {
						if (($row[$field] ?? '') === '') continue;
						$layout = json_decode($row[$field], true, 512, JSON_THROW_ON_ERROR);
						if (!is_array($layout) || !array_is_list($layout)) throw new RuntimeException('Unbekannter alter Builder-Datenstand.');
						$this->validate($layout);
						$index($layout);
						$layouts[] = $layout;
					}
					foreach ($layouts as $layout) {
						if ($layout !== $layouts[0]) throw new RuntimeException('Abweichende alte Sprachlayouts: gemeinsame Umstellung muss zuerst ausdruecklich geklaert werden. Bestehende Daten bleiben unveraendert.');
					}
				}
			}
			$languageRows = [];
			foreach ($old['LANGUAGE']['D'] ?? [] as $code => $row) $languageRows[$code] = ['version' => 2, 'components' => []];
			$nodes = $entry['nodes'];
			$split = function (array &$nodes) use (&$split, &$languageRows, $providers, $context, $language, $legacyIds): void {
				foreach ($nodes as &$node) {
					if ($node['kind'] === 'container') {
						$split($node['children']);
					} else {
						if ($providers !== null) {
							$manifest = Component::load($node['module'], $node['type'], $providers[$node['module']] ?? [], $context)['manifest'];
							Component::options($manifest, $node['options'] ?? []);
							$keys = array_keys(array_filter($manifest['settings'] ?? [], static fn ($field) => $field['translatable'] ?? false));
							if ($keys && isset($legacyIds[$node['id']])) throw new RuntimeException('Alte Komponente mit uebersetzbaren Feldern: Sprachwerte vor der gemeinsamen Umstellung ausdruecklich zuordnen. Bestehende Daten bleiben unveraendert.');
							if ($keys && !array_key_exists('translations', $node)) {
								$options = Component::options($manifest, $node['options'] ?? []);
								$node['translations'] = [$language => ['active' => true, 'options' => array_intersect_key($options, array_flip($keys))]];
							}
							Component::localizedOptions($manifest, $node['options'] ?? [], $node['translations'] ?? [], $language);
							$node['options'] = array_diff_key($node['options'] ?? [], array_flip($keys));
						}
						foreach ($node['translations'] ?? [] as $code => $translation) {
							$languageRows[$code] ??= ['version' => 2, 'components' => []];
							$languageRows[$code]['components'][$node['id']] = $translation;
						}
						unset($node['translations']);
					}
				}
			};
			$split($nodes);
			$update['BUILDER']['D'][$id]['ModuleId'] = $target['module'];
			$update['BUILDER']['D'][$id]['PageId'] = $target['page'];
			$update['BUILDER']['D'][$id]['Region'] = $target['region'];
			$update['BUILDER']['D'][$id]['Scope'] = $target['scope'] ?? (($target['entity'] ?? '') === '' ? 'page' : 'individual');
			$update['BUILDER']['D'][$id]['Branch'] = $target['branch'] ?? 'frontend';
			if (isset($target['entity'])) $update['BUILDER']['D'][$id]['EntityId'] = $target['entity'];
			$update['BUILDER']['D'][$id]['DraftJson'] = json_encode(['version' => 2, 'active' => $entry['active'] ?? true, 'nodes' => $nodes,
				...(($entry['deleted'] ?? false) ? ['deleted' => true] : [])], JSON_THROW_ON_ERROR);
			foreach ($languageRows as $code => $row) {
				$update['BUILDER']['D'][$id]['LANGUAGE']['D'][$code]['DraftJson'] = json_encode($row, JSON_THROW_ON_ERROR);
			}
		}
		$this->db->set_object($update);
		foreach ($update['BUILDER']['D'] as $id => $expected) {
			$filter = ['BUILDER' => ['W' => [0 => ['ID' => $id]], 'LANGUAGE' => []]];
			$this->db->get_object($stored, $filter);
			if (($stored['BUILDER']['D'][$id]['DraftJson'] ?? null) !== $expected['DraftJson']) throw new RuntimeException('Builder-Entwurf wurde nicht vollstaendig gespeichert.');
			foreach ($expected['LANGUAGE']['D'] ?? [] as $language => $row) {
				if (($stored['BUILDER']['D'][$id]['LANGUAGE']['D'][$language]['DraftJson'] ?? null) !== $row['DraftJson']) {
					throw new RuntimeException('Builder-Entwurf wurde nicht vollstaendig gespeichert.');
				}
			}
		}
		return array_values(array_map(fn ($place) => $this->revision($place), $update['BUILDER']['D']));
	}

	public function publish(string $id, string $language): void {
		$f['BUILDER']['W'][0]['ID'] = $id;
		$f['BUILDER']['LANGUAGE'] = [];
		$this->db->get_object($data, $f);
		$place = $data['BUILDER']['D'][$id] ?? [];
		if (($place['DraftJson'] ?? '') !== '') {
			$this->publishDrafts([['id' => $id, 'revision' => $this->revision($place)]]);
			return;
		}
		$row = $data['BUILDER']['D'][$id]['LANGUAGE']['D'][$language] ?? [];
		if (!isset($row['DraftJson'])) throw new RuntimeException('Kein Builder-Entwurf zum Veroeffentlichen vorhanden.');
		$nodes = json_decode($row['DraftJson'], true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($nodes)) throw new RuntimeException('Ungueltiger Builder-Entwurf.');
		$this->validate($nodes);
		$update['BUILDER']['D'][$id]['LANGUAGE']['D'][$language] = [
			'PublishedJson' => $row['DraftJson'], 'Active' => 1,
		];
		$this->db->set_object($update);
	}

	public function draftRevision(string $id): ?string {
		$filter = ['BUILDER' => ['W' => [0 => ['ID' => $id]], 'LANGUAGE' => []]];
		$this->db->get_object($data, $filter);
		$place = $data['BUILDER']['D'][$id] ?? [];
		return ($place['DraftJson'] ?? '') === '' ? null : $this->revision($place);
	}

	private function revision(array $place): string {
		$values = ['layout' => $place['DraftJson'], 'languages' => []];
		foreach ($place['LANGUAGE']['D'] ?? [] as $code => $row) $values['languages'][$code] = $row['DraftJson'] ?? null;
		ksort($values['languages']);
		return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
	}

	public function publishDrafts(array $entries, ?array $providers = null, array $context = []): void {
		if (!array_is_list($entries) || !$entries) throw new RuntimeException('Keine Builder-Entwuerfe zum Veroeffentlichen.');
		$update = [];
		foreach ($entries as $entry) {
			if (!is_array($entry) || !is_string($entry['id'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $entry['id'])
				|| !is_string($entry['revision'] ?? null) || array_key_exists($entry['id'], $update['BUILDER']['D'] ?? [])) {
				throw new RuntimeException('Ungueltige oder doppelte Builder-Veroeffentlichung.');
			}
			$id = $entry['id'];
			$filter = ['BUILDER' => ['W' => [0 => ['ID' => $id]], 'LANGUAGE' => []]];
			$this->db->get_object($data, $filter);
			$place = $data['BUILDER']['D'][$id] ?? [];
			if (($place['DraftJson'] ?? '') === '') throw new RuntimeException('Zuerst alle Seitenbereiche als gemeinsamen Entwurf speichern.');
			if (!hash_equals($this->revision($place), $entry['revision'])) throw new RuntimeException('Entwurf wurde zwischenzeitlich geaendert. Vorschau neu laden und pruefen.');
			$document = $this->readDocument($place, 'DraftJson');
			if ($document['deleted'] ?? false) {
				$update['BUILDER']['D'][$id] = null;
				continue;
			}
			if ($providers !== null) {
				$check = function (array $nodes) use (&$check, $providers, $context): void {
					foreach ($nodes as $node) {
						if ($node['kind'] === 'container') $check($node['children']);
						else {
							$manifest = Component::load($node['module'], $node['type'], $providers[$node['module']] ?? [], $context)['manifest'];
							Component::localizedOptions($manifest, $node['options'] ?? [], $node['translations'] ?? [], 'EN');
						}
					}
				};
				$check($document['nodes']);
			}
			$update['BUILDER']['D'][$id] = ['PublishedJson' => $place['DraftJson'], 'Active' => (int)$document['active']];
			foreach ($place['LANGUAGE']['D'] ?? [] as $code => $row) {
				if (($row['DraftJson'] ?? '') === '') throw new RuntimeException('Gemeinsamer Sprachentwurf fehlt.');
				$update['BUILDER']['D'][$id]['LANGUAGE']['D'][$code]['PublishedJson'] = $row['DraftJson'];
			}
		}
		$this->db->set_object($update);
		foreach ($update['BUILDER']['D'] as $id => $expected) {
			$filter = ['BUILDER' => ['W' => [0 => ['ID' => $id]], 'LANGUAGE' => []]];
			$data = [];
			$this->db->get_object($data, $filter);
			$stored = $data['BUILDER']['D'][$id] ?? [];
			if ($expected === null) {
				if ($stored) throw new RuntimeException('Builder-Ebene wurde nicht vollstaendig geloescht.');
				continue;
			}
			if (($stored['PublishedJson'] ?? null) !== $expected['PublishedJson'] || (int)($stored['Active'] ?? -1) !== $expected['Active']) {
				throw new RuntimeException('Builder-Veroeffentlichung wurde nicht vollstaendig gespeichert.');
			}
			foreach ($expected['LANGUAGE']['D'] ?? [] as $code => $row) {
				if (($stored['LANGUAGE']['D'][$code]['PublishedJson'] ?? null) !== $row['PublishedJson']) throw new RuntimeException('Builder-Sprachstand wurde nicht vollstaendig veroeffentlicht.');
			}
		}
	}

	public function deactivate(string $id, string $language, string $reason): void {
		$f = ['BUILDER' => ['W' => [0 => ['ID' => $id]]]];
		$this->db->get_object($data, $f);
		if (($data['BUILDER']['D'][$id]['PublishedJson'] ?? '') !== '') $update['BUILDER']['D'][$id]['Active'] = 0;
		else $update['BUILDER']['D'][$id]['LANGUAGE']['D'][$language]['Active'] = 0;
		$this->db->set_object($update);
		error_log("Builder $id/$language deaktiviert: $reason");
	}

	public function getRegion(string $module, string $page, string $region, string $language, string $entity = '', ?string $scope = null, string $branch = 'frontend'): array {
		if (!preg_match('/^[A-Z]{2}$/D', strtoupper($language))) throw new RuntimeException('Ungueltige Builder-Sprache.');
		$id = $this->placeId($module, $page, $region, $entity, $scope, $branch);
		$f['BUILDER']['W'][0]['ID'] = $id;
		$f['BUILDER']['LANGUAGE'] = [];
		$this->db->get_object($data, $f);
		$place = $data['BUILDER']['D'][$id] ?? [];
		$field = $this->previewLanguage !== null && ($place['DraftJson'] ?? '') !== '' ? 'DraftJson' : 'PublishedJson';
		if (($place[$field] ?? '') !== '') {
			if ($this->previewLanguage === null && (int)($place['Active'] ?? 0) !== 1) return [];
			try {
				$document = $this->readDocument($place, $field);
				if ($document['deleted'] ?? false) return $this->previewLanguage === null ? [] : [
					'id' => $id, 'language' => strtoupper($language), 'active' => false, 'nodes' => [],
					'deleted' => true, 'revision' => $this->revision($place),
				];
				if ($this->previewLanguage === null && !$document['active']) return [];
				return ['id' => $id, 'language' => strtoupper($language), 'active' => $document['active'], 'nodes' => $document['nodes'],
					'revision' => $field === 'DraftJson' ? $this->revision($place) : null];
			} catch (\JsonException | RuntimeException $error) {
				if ($this->previewLanguage !== null) throw $error;
				$this->deactivate($id, strtoupper($language), $error->getMessage());
				return [];
			}
		}
		$languages = $data['BUILDER']['D'][$id]['LANGUAGE']['D'] ?? [];
		foreach (array_unique([strtoupper($language), 'EN']) as $candidate) {
			$row = $languages[$candidate] ?? [];
			if ($this->previewLanguage !== null && isset($row['DraftJson']) && $row['DraftJson'] !== '') {
				$nodes = json_decode($row['DraftJson'], true, 512, JSON_THROW_ON_ERROR);
				if (!is_array($nodes)) throw new RuntimeException('Ungueltiger Builder-Entwurf.');
				$this->validate($nodes);
				return ['id' => $id, 'language' => $candidate, 'legacy' => true, 'nodes' => $nodes];
			}
			if (!isset($row['PublishedJson']) || $row['PublishedJson'] === '') continue;
			// Ein deaktivierter Sprachstand darf nicht durch Fallback wieder erscheinen.
			if ((int)($row['Active'] ?? 0) !== 1) return [];
			try {
				$nodes = json_decode($row['PublishedJson'], true, 512, JSON_THROW_ON_ERROR);
				if (!is_array($nodes)) throw new RuntimeException('Ungueltige veroeffentlichte Builder-Struktur.');
				$this->validate($nodes);
			} catch (\JsonException | RuntimeException $error) {
				if ($this->previewLanguage !== null) throw $error;
				$this->deactivate($id, $candidate, $error->getMessage());
				return [];
			}
			return ['id' => $id, 'language' => $candidate, 'legacy' => true, 'nodes' => $nodes];
		}
		return [];
	}

	private function readDocument(array $place, string $field): array {
		$document = json_decode($place[$field], true, 512, JSON_THROW_ON_ERROR);
		if (!is_array($document) || ($document['version'] ?? null) !== 2 || !is_bool($document['active'] ?? null)
			|| !is_array($document['nodes'] ?? null)) throw new RuntimeException('Ungueltiges gemeinsames Builder-Dokument.');
		if (array_key_exists('deleted', $document) && (!is_bool($document['deleted'])
			|| ($document['deleted'] && ($document['active'] || $document['nodes'])))) throw new RuntimeException('Ungueltiger Builder-Loeschentwurf.');
		$this->validate($document['nodes']);
		$translations = [];
		foreach ($place['LANGUAGE']['D'] ?? [] as $code => $row) {
			if (($row[$field] ?? '') === '') continue;
			$value = json_decode($row[$field], true, 512, JSON_THROW_ON_ERROR);
			if (!is_string($code) || !preg_match('/^[A-Z]{2}$/D', $code) || !is_array($value)
				|| ($value['version'] ?? null) !== 2 || !is_array($value['components'] ?? null)) {
				throw new RuntimeException('Ungueltige gemeinsame Builder-Sprachdaten.');
			}
			$translations[$code] = $value['components'];
		}
		$ids = [];
		$hydrate = function (array &$nodes) use (&$hydrate, $translations, &$ids): void {
			foreach ($nodes as &$node) {
				if ($node['kind'] === 'container') $hydrate($node['children']);
				else {
					$ids[$node['id']] = true;
					foreach ($translations as $code => $components) {
						if (array_key_exists($node['id'], $components)) $node['translations'][$code] = $components[$node['id']];
					}
				}
			}
		};
		$hydrate($document['nodes']);
		foreach ($translations as $components) {
			if (array_diff_key($components, $ids)) throw new RuntimeException('Builder-Sprachdaten ohne zugehoerige Komponenteninstanz.');
		}
		$this->validate($document['nodes']);
		return $document;
	}
}
