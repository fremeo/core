{block name="inner_body"}
	<style>
		#builder-dialog { width: min(620px, 94vw); max-height: 85vh; padding: 0; overflow: hidden; }
		#builder-options-form { display: flex; flex-direction: column; max-height: calc(85vh - 2px); }
		#builder-dialog header, #builder-dialog footer { flex-shrink: 0; padding: 8px 12px; background: var(--bs-tertiary-bg, #eee); }
		#builder-dialog header { border-bottom: 1px solid var(--bs-border-color, #ccc); cursor: move; touch-action: none; user-select: none; }
		#builder-dialog-close { color: #fff; background: #c42b1c; border: 0; border-radius: 3px; padding: 2px 9px; font-size: 1rem; line-height: 1.25; cursor: pointer; }
		#builder-dialog-close:hover { background: #a51f14; }
		#builder-dialog footer { border-top: 1px solid var(--bs-border-color, #ccc); }
		#builder-dialog-title { font-size: .9rem; margin: 0; }
		#builder-dialog-tabs { flex-shrink: 0; padding: 6px 12px 0; }
		#builder-dialog-content { overflow-y: auto; min-height: 0; padding: 12px; font-size: .85rem; }
	</style>
	<div class="d-flex justify-content-between align-items-center gap-2 mb-2">
		<h1 class="h4 mb-0">Page Builder</h1>
		<div class="d-flex gap-2" role="toolbar" aria-label="Page Builder">
			<button id="builder-edit" type="button" class="btn btn-sm btn-primary" disabled>Edit</button>
			<button id="builder-prev" type="button" class="btn btn-sm btn-outline-secondary" title="Rueckgaengig" disabled hidden>Prev</button>
			<button id="builder-next" type="button" class="btn btn-sm btn-outline-secondary" title="Wiederholen" disabled hidden>Next</button>
			<button id="builder-view" type="button" class="btn btn-sm btn-outline-secondary" aria-pressed="false" disabled hidden>Vorschau</button>
			<button id="builder-cancel" type="button" class="btn btn-sm btn-outline-secondary" hidden>Abbrechen</button>
			<button id="builder-save" type="button" class="btn btn-sm btn-success" disabled title="Als Entwurf speichern; die oeffentliche Seite bleibt unveraendert.">Speichern</button>
			<button id="builder-publish" type="button" class="btn btn-sm btn-outline-success" disabled hidden title="Gespeicherten Seitenentwurf mit allen Sprachstaenden oeffentlich schalten. Ungespeicherte Aenderungen zuerst speichern.">Veroeffentlichen</button>
		</div>
	</div>
	<p id="builder-preview-status" class="small text-body-secondary" role="status" hidden></p>
	{foreach $D.R.BuilderView.Errors as $error}<div class="alert alert-danger" role="alert">{$error|escape}</div>{/foreach}
	<div class="row g-3">
		<aside id="builder-library" class="col-lg-3" hidden>
			<div class="bg-body-tertiary border rounded p-3">
				<h2 class="h6">Bereiche</h2>
				<div id="builder-regions" class="mb-3"></div>
				<h2 class="h6">Komponenten</h2>
				{foreach $D.R.BuilderView.Components as $module => $components}
					<details class="mb-2" open>
						<summary>{$module|escape}</summary>
						<div class="d-grid gap-2 mt-2">
							{foreach $components as $type => $component}
								<button type="button" class="btn btn-sm btn-outline-primary" draggable="true" data-builder-module="{$module|escape}" data-builder-type="{$type|escape}">{$component.Name|escape}</button>
							{/foreach}
						</div>
					</details>
				{foreachelse}<p class="small">Keine Komponenten aktiver Module gefunden.</p>{/foreach}
				<p class="small text-body-secondary mb-0">Element auf eine gruene Flaeche ziehen. Erst Speichern sichert die Aenderungen als Entwurf; die oeffentliche Seite bleibt unveraendert.</p>
			</div>
			<dialog id="builder-dialog" class="border rounded" aria-labelledby="builder-dialog-title">
				<form id="builder-options-form" novalidate>
					<header id="builder-dialog-header" class="d-flex justify-content-between align-items-center gap-2">
						<h2 id="builder-dialog-title">Einstellungen</h2>
						<button id="builder-dialog-close" type="button" aria-label="Schliessen" title="Schliessen">&#215;</button>
					</header>
					<div id="builder-dialog-tabs" class="nav nav-tabs" role="tablist" aria-label="Einstellungen">
						<button id="builder-component-tab" type="button" class="nav-link active" role="tab" aria-selected="true" aria-controls="builder-options-fields">Komponente</button>
						<button id="builder-general-tab" type="button" class="nav-link" role="tab" aria-selected="false" aria-controls="builder-general-fields" tabindex="-1">Allgemein</button>
					</div>
					<div id="builder-dialog-content">
						<div id="builder-options-fields" role="tabpanel" aria-labelledby="builder-component-tab"></div>
						<div id="builder-general-fields" role="tabpanel" aria-labelledby="builder-general-tab" hidden></div>
						<p id="builder-options-error" class="text-danger small" role="alert" hidden></p>
					</div>
					<footer class="d-flex justify-content-end gap-2">
						<button id="builder-options-cancel" type="button" class="btn btn-sm btn-outline-secondary">Abbrechen</button>
						<button id="builder-options-apply" type="submit" class="btn btn-sm btn-outline-primary">Uebernehmen</button>
						<button id="builder-options-ok" type="submit" class="btn btn-sm btn-primary">OK</button>
					</footer>
				</form>
			</dialog>
		</aside>
		<div id="builder-viewport" class="col-12">
			<iframe id="builder-preview" title="Builder-Seitenvorschau" src="{$D.R.BuilderView.PreviewUrl|escape}" class="w-100 border rounded" style="height: 70vh;" sandbox="allow-same-origin allow-scripts allow-forms"></iframe>
		</div>
	</div>
	{include file="./builder/forms.tpl"}
{/block}
{block name="body_end" append}
	{foreach $D.R.BuilderView.EditorScripts|default:[] as $script}<script src="{$script|escape}"></script>{/foreach}
	<script id="builder-settings" type="application/json">{$D.R.BuilderView.SettingsJson nofilter}</script>
	<script>
	{literal}
	(() => {
		const frame = document.getElementById('builder-preview');
		if (!frame) return;
		const settings = JSON.parse(document.getElementById('builder-settings').textContent);
		const status = document.getElementById('builder-preview-status');
		const edit = document.getElementById('builder-edit');
		const cancel = document.getElementById('builder-cancel');
		const library = document.getElementById('builder-library');
		const regionFields = document.getElementById('builder-regions');
		const viewport = document.getElementById('builder-viewport');
		const prev = document.getElementById('builder-prev');
		const next = document.getElementById('builder-next');
		const save = document.getElementById('builder-save');
		const publish = document.getElementById('builder-publish');
		const view = document.getElementById('builder-view');
		const dialog = document.getElementById('builder-dialog');
		const form = document.getElementById('builder-options-form');
		const fields = document.getElementById('builder-options-fields');
		const generalFields = document.getElementById('builder-general-fields');
		const componentTab = document.getElementById('builder-component-tab');
		const generalTab = document.getElementById('builder-general-tab');
		const optionError = document.getElementById('builder-options-error');
		const apply = document.getElementById('builder-options-apply');
		const ok = document.getElementById('builder-options-ok');
		const close = document.getElementById('builder-dialog-close');
		const optionCancel = document.getElementById('builder-options-cancel');
		const header = document.getElementById('builder-dialog-header');
		const cloneForm = (id) => {
			const template = document.getElementById(id);
			if (!template) throw new Error('Einstellungs-TPL fehlt: ' + id);
			const element = document.createElement('div');
			element.innerHTML = template.innerHTML.replaceAll('builder-input', 'builder-input-' + crypto.randomUUID());
			element.querySelectorAll('script').forEach((script) => script.remove());
			return element.children[0];
		};
		const bindControl = (element, field, value) => {
			const choices = field.choices ? [...field.choices] : field.enum?.map((value) => ({ value, label: String(value) }));
			const frame = element.querySelector('iframe');
			element.querySelectorAll('input, select, textarea').forEach((input) => {
				for (const attribute of [...input.attributes]) {
					if (attribute.name.startsWith('on') || attribute.name === 'ischanged' || (attribute.name === 'id' && !frame)) input.removeAttribute(attribute.name);
				}
			});
			element.querySelectorAll('input[type="hidden"]').forEach((input) => input.remove());
			const input = element.querySelector('input, select, textarea');
			if (!input) throw new Error('Eingabefeld fehlt in Einstellungs-TPL: ' + field.label);
			input.classList.add(field.type === 'boolean' ? 'ms-2' : 'form-control-sm');
			let read = () => choices ? choices[Number(input.value)].value
				: field.type === 'boolean' ? input.checked : ['number', 'integer'].includes(field.type) ? Number(input.value) : input.value;
			if (choices) {
				if (!choices.some((choice) => choice.value === value)) {
					const option = cloneForm('builder-unavailable-choice');
					option.value = String(choices.length);
					option.textContent = 'Nicht verfuegbar: ' + String(value);
					input.append(option);
					choices.push({ value, label: option.textContent });
				}
				input.value = String(choices.findIndex((choice) => choice.value === value));
			} else if (field.type === 'boolean') input.checked = value;
			else {
				input.value = value;
				if (field.type === 'integer' || field.type === 'number') {
					input.step = field.type === 'integer' ? '1' : 'any';
					if (field.min !== undefined) input.min = field.min;
					if (field.max !== undefined) input.max = field.max;
					input.required = true;
				}
				if (input.tagName === 'TEXTAREA') input.rows = 5;
			}
			if (frame) {
				let ready = false;
				frame.removeAttribute('onload');
				frame.setAttribute('sandbox', 'allow-same-origin');
				frame.addEventListener('load', () => {
					if (!frame.contentDocument?.body) return;
					const body = frame.contentDocument.body;
					const colors = window.getComputedStyle(frame);
					Object.assign(body.style, {
						backgroundColor: colors.backgroundColor, color: colors.color,
						colorScheme: colors.colorScheme, fontFamily: colors.fontFamily, fontSize: '14px',
					});
					body.innerHTML = input.value;
					body.contentEditable = input.disabled ? 'false' : 'true';
					body.addEventListener('input', () => { input.value = body.innerHTML; });
					ready = true;
				});
				frame.srcdoc = '<!doctype html><html><body contenteditable></body></html>';
				input.addEventListener('input', () => { if (frame.contentDocument?.body) frame.contentDocument.body.innerHTML = input.value; });
				element.querySelectorAll('button').forEach((button) => {
					if (!(button.getAttribute('onclick') || '').includes('.toggle()')) return;
					button.removeAttribute('onclick');
					button.addEventListener('click', () => {
						const source = input.style.display === 'none';
						if (source && frame.contentDocument?.body) input.value = frame.contentDocument.body.innerHTML;
						input.style.display = source ? 'block' : 'none';
						frame.style.display = source ? 'none' : '';
						element.querySelectorAll('.Only4Editor').forEach((item) => { item.hidden = source; });
					});
				});
				const resizable = element.querySelector('.resizable');
				if (resizable) { resizable.style.resize = 'vertical'; resizable.style.overflow = 'auto'; }
				read = () => ready && frame.style.display !== 'none' ? frame.contentDocument.body.innerHTML : input.value;
			}
			return {
				element, read,
				disable: (disabled) => {
					element.querySelectorAll('input, select, textarea, button').forEach((item) => { item.disabled = disabled; });
					if (frame?.contentDocument?.body) frame.contentDocument.body.contentEditable = disabled ? 'false' : 'true';
				},
			};
		};
		let drag = null;
		header.addEventListener('pointerdown', (event) => {
			if (event.button !== 0 || event.target.closest('button')) return;
			const rect = dialog.getBoundingClientRect();
			drag = { x: event.clientX - rect.left, y: event.clientY - rect.top };
			header.setPointerCapture(event.pointerId);
			event.preventDefault();
		});
		header.addEventListener('pointermove', (event) => {
			if (!drag) return;
			const rect = dialog.getBoundingClientRect();
			dialog.style.position = 'fixed';
			dialog.style.margin = '0';
			dialog.style.left = Math.max(0, Math.min(event.clientX - drag.x, window.innerWidth - rect.width)) + 'px';
			dialog.style.top = Math.max(0, Math.min(event.clientY - drag.y, window.innerHeight - rect.height)) + 'px';
		});
		const endDrag = () => { drag = null; };
		header.addEventListener('pointerup', endDrag);
		header.addEventListener('pointercancel', endDrag);
		header.addEventListener('lostpointercapture', endDrag);
		let applyOptions = null;
		let history = [];
		let position = -1;
		let restore = null;
		let collect = null;
		let savedState = null;
		let revisions = [];
		let regionElements = [];
		let publishedState = null;
		let saving = false;
		let browsingUrl = null;
		let language = null;
		let editing = false;
		let viewing = false;
		let previewMode = null;
		let selected = null;
		let moving = null;
		let pending = false;
		let generation = 0;
		const message = (text) => {
			status.textContent = text;
			status.hidden = !text;
		};
		const historyButtons = () => {
			const relevant = revisions.filter((value, index) => regionElements[index]?.dataset.builderExists !== '0' || regionElements[index]?.dataset.builderDeleted === '1');
			prev.disabled = viewing || pending || dialog.open || position <= 0;
			next.disabled = viewing || pending || dialog.open || position >= history.length - 1;
			view.disabled = !editing || !previewMode || pending || dialog.open;
			save.disabled = !editing || !collect || pending || dialog.open || !relevant.length || (JSON.stringify(history[position]) === savedState && relevant.every((value) => value));
			publish.disabled = !editing || !collect || pending || dialog.open || !relevant.length || relevant.some((value) => !value)
				|| JSON.stringify(history[position]) !== savedState || savedState === publishedState;
			cancel.disabled = saving;
			apply.disabled = ok.disabled = close.disabled = optionCancel.disabled = pending;
		};
		const selectTab = (general) => {
			fields.hidden = general;
			generalFields.hidden = !general;
			[componentTab, generalTab].forEach((tab, index) => {
				const active = general === (index === 1);
				tab.className = 'nav-link' + (active ? ' active' : '');
				tab.setAttribute('aria-selected', String(active));
				tab.tabIndex = active ? 0 : -1;
			});
		};
		[componentTab, generalTab].forEach((tab, index) => {
			tab.addEventListener('click', () => selectTab(index === 1));
			tab.addEventListener('keydown', (event) => {
				if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
				event.preventDefault();
				const general = event.key === 'End' || event.key !== 'Home' && index === 0;
				selectTab(general);
				(general ? generalTab : componentTab).focus();
			});
		});
		const generalDefaults = { CustomCssClass: '', CustomCss: '', Active: true, Viewports: ['XS', 'SM', 'MD', 'LG'] };
		const appearance = (node) => {
			const general = { ...generalDefaults, ...JSON.parse(node.dataset.builderGeneral || '{}') };
			const element = [...node.children].find((child) => child.classList.contains('builder-element'));
			const visibility = ['XS', 'SM', 'MD', 'LG'].map((size, index) => 'd' + (index ? '-' + size.toLowerCase() : '')
				+ (general.Viewports.includes(size) ? '-block' : '-none')).join(' ');
			element.className = 'builder-element ' + general.CustomCssClass + ' ' + visibility;
			element.setAttribute('style', general.CustomCss);
			element.hidden = !general.Active || node.dataset.builderLanguageVisible === '0';
		};
		const editorFor = (node) => window.BuilderComponentEditors?.[node.dataset.builderModule + ':' + node.dataset.builderType];
		const layoutContainers = (doc) => doc.querySelectorAll('[data-builder-node]').forEach((node) => {
			if (node.dataset.builderKind === 'container') {
				const editor = editorFor(node);
				if (!editor) throw new Error('Grid-Editorcode fehlt. Page Builder neu laden.');
				editor.layout(node, JSON.parse(node.dataset.builderConfig).options);
			}
		});
		view.addEventListener('click', () => {
			if (view.disabled) return;
			viewing = !viewing;
			selected = moving = null;
			previewMode(viewing);
			view.textContent = viewing ? 'Bearbeiten' : 'Vorschau';
			view.setAttribute('aria-pressed', String(viewing));
			library.hidden = viewing;
			viewport.className = viewing ? 'col-12' : 'col-lg-9';
			historyButtons();
		});
		prev.addEventListener('click', () => {
			if (prev.disabled) return;
			restore(history[--position]);
			historyButtons();
		});
		next.addEventListener('click', () => {
			if (next.disabled) return;
			restore(history[++position]);
			historyButtons();
		});
		save.addEventListener('click', async () => {
			if (save.disabled) return;
			const current = generation;
			const state = JSON.stringify(history[position]);
			pending = saving = true;
			historyButtons();
			message('Entwurf wird gespeichert ...');
			try {
				const entries = collect();
				const body = new URLSearchParams({
					'R[ACTION]': 'save', 'R[BuilderToken]': settings.Token,
					'R[BuilderRegionsJson]': JSON.stringify(entries),
				});
				const response = await fetch(settings.RenderUrl, { method: 'POST', credentials: 'same-origin', body });
				if (response.redirected) throw new Error('Anmeldung abgelaufen. Page Builder neu laden.');
				const result = await response.json();
				if (!response.ok || result.saved !== true) throw new Error(result.error || 'Entwurf konnte nicht gespeichert werden.');
				if (current !== generation) return;
				savedState = state;
				if (!Array.isArray(result.revisions) || result.revisions.length !== entries.length
					|| result.revisions.some((value) => typeof value !== 'string' || !/^[a-f0-9]{64}$/.test(value))) {
					revisions = revisions.map(() => null);
					throw new Error('Entwurf gespeichert, aber Veroeffentlichungsstand fehlt. Vorschau neu laden.');
				}
				let index = 0;
				revisions = regionElements.map((region) => region.dataset.builderExists === '0' && region.dataset.builderDeleted !== '1' ? null : result.revisions[index++]);
				message('Entwurf gespeichert. Die oeffentliche Seite bleibt unveraendert.');
			} catch (error) {
				if (current !== generation) return;
				message('Speichern fehlgeschlagen: ' + error.message);
				console.error('Builder-Speichern:', error);
			} finally {
				if (current === generation) {
					pending = saving = false;
					historyButtons();
				}
			}
		});
		publish.addEventListener('click', async () => {
			if (publish.disabled) return;
			const current = generation;
			pending = saving = true;
			historyButtons();
			message('Gespeicherter Seitenentwurf wird fuer alle Sprachen veroeffentlicht ...');
			try {
				const body = new URLSearchParams({
					'R[ACTION]': 'publish', 'R[BuilderToken]': settings.Token,
					'R[BuilderRegionsJson]': JSON.stringify(collect().map((region, index) => ({
						target: region.target, token: region.token, revision: region.revision,
					}))),
				});
				const response = await fetch(settings.RenderUrl, { method: 'POST', credentials: 'same-origin', body });
				if (response.redirected) throw new Error('Anmeldung abgelaufen. Page Builder neu laden.');
				const result = await response.json();
				if (!response.ok || result.published !== true) throw new Error(result.error || 'Seite konnte nicht veroeffentlicht werden.');
				if (current !== generation) return;
				regionElements.forEach((region, index) => {
					if (region.dataset.builderDeleted !== '1') return;
					region.dataset.builderDeleted = '0';
					revisions[index] = null;
					history[position][index].deleted = false;
				});
				savedState = JSON.stringify(history[position]);
				publishedState = savedState;
				message('Seite veroeffentlicht. Gemeinsames Layout, Aktivierungen und gespeicherte Sprachstaende sind jetzt oeffentlich.');
			} catch (error) {
				if (current !== generation) return;
				message('Veroeffentlichen fehlgeschlagen: ' + error.message);
				console.error('Builder-Veroeffentlichen:', error);
			} finally {
				if (current === generation) {
					pending = saving = false;
					historyButtons();
				}
			}
		});
		optionCancel.addEventListener('click', () => { if (!pending) dialog.close(); });
		close.addEventListener('click', () => { if (!pending) dialog.close(); });
		dialog.addEventListener('close', () => { applyOptions = null; historyButtons(); });
		dialog.addEventListener('cancel', (event) => {
			if (pending) event.preventDefault();
		});
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			if (applyOptions && !pending) void applyOptions(event.submitter === ok);
		});
		const showEditor = (active) => {
			editing = active;
			viewing = false;
			previewMode = null;
			view.textContent = 'Vorschau';
			view.setAttribute('aria-pressed', 'false');
			view.hidden = !active;
			edit.hidden = active;
			cancel.hidden = !active;
			library.hidden = !active;
			prev.hidden = next.hidden = !active;
			history = [];
			position = -1;
			restore = null;
			collect = null;
			savedState = null;
			revisions = [];
			publishedState = null;
			publish.hidden = !active;
			historyButtons();
			viewport.className = active ? 'col-lg-9' : 'col-12';
			selected = null;
			moving = null;
		};
		const choose = (button) => {
			selected = { module: button.dataset.builderModule, type: button.dataset.builderType };
			moving = null;
		};
		library.querySelectorAll('button[draggable]').forEach((button) => {
			button.addEventListener('dragstart', (event) => {
				choose(button);
				event.dataTransfer.setData('text/plain', 'builder');
				event.dataTransfer.effectAllowed = 'copy';
			});
			button.addEventListener('dragend', () => { selected = null; moving = null; });
		});
		const inspect = () => {
			generation++;
			pending = false;
			saving = false;
			collect = null;
			savedState = null;
			revisions = [];
			publishedState = null;
			previewMode = null;
			viewing = false;
			view.textContent = 'Vorschau';
			view.setAttribute('aria-pressed', 'false');
			library.hidden = !editing;
			viewport.className = editing ? 'col-lg-9' : 'col-12';
			historyButtons();
			if (dialog.open) dialog.close();
			edit.disabled = true;
			try {
				const doc = frame.contentDocument;
				const url = new URL(frame.contentWindow.location.href);
				if (url.origin !== window.location.origin || !doc || doc.body.dataset.layout !== 'frontend') {
					throw new Error('Keine lesbare Frontend-Seite.');
				}
				language = doc.documentElement.dataset.builderLanguage;
				if (!/^[A-Z]{2}$/.test(language || '')) throw new Error('Seitensprache fehlt.');
				const preview = url.searchParams.has('R[BuilderPreview]');
				if (preview && !editing) throw new Error('Bitte die Seite ohne Vorschauparameter aufrufen.');
				if (!preview) {
					showEditor(false);
					browsingUrl = url.href;
					edit.disabled = false;
					message('');
					doc.querySelectorAll('a[target="_blank"]').forEach((link) => { link.target = '_self'; });
					return;
				}
				const regions = [...doc.querySelectorAll('[data-builder-region]')];
				regionElements = regions;
				const editable = (target) => !regions.some((region) => region.dataset.builderLocked === '1' && region.contains(target));
				revisions = regions.map((region) => region.dataset.builderDraftRevision || null);
				const style = doc.createElement('style');
				style.textContent = `
					[data-builder-node] { position: relative; min-width: 0; min-height: 36px; border: 1px solid #198754; padding: 8px; margin-block: 3px; }
					[data-builder-node] > .builder-element { display: block !important; }
					[data-builder-children] { display: flex !important; min-height: 60px; margin: 0; }
					[data-builder-children] > .col { display: block !important; }
					[data-builder-children] > .builder-insert { flex: 0 0 100%; }
					.builder-insert { display: block; width: 100%; min-height: 18px; margin-block: 3px; border: 1px dashed rgba(180, 155, 55, .65); background: rgba(255, 225, 125, .18); }
					.builder-insert:focus, .builder-insert.builder-over { outline: 3px solid #198754; }
					.builder-node-tools { position: absolute; bottom: 100%; left: -1px; z-index: 20; display: flex; gap: 2px; align-items: center; padding: 3px 6px; background: #287ff0; color: #fff; font: 12px sans-serif; opacity: 0; visibility: hidden; }
					[data-builder-node]:hover > .builder-node-tools, [data-builder-node]:focus-within > .builder-node-tools { opacity: 1; visibility: visible; }
					.builder-node-tools button { display: inline-flex; align-items: center; border: 0; background: none; color: inherit; padding: 3px; cursor: pointer; }
					.builder-node-tools svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; }
					.builder-node-tools [draggable] { cursor: move; }
					[data-builder-locked="1"] .builder-node-tools, [data-builder-locked="1"] .builder-insert { display: none; }
					[data-builder-locked="1"]::before { content: 'Geerbter Inhalt - zum Bearbeiten die Ebene links oeffnen.'; display: block; font: 12px sans-serif; color: #495057; background: #e9ecef; padding: 4px; }
					[data-builder-editing]::before { content: attr(data-builder-editing); display: block; font: 12px sans-serif; color: #084298; background: #cfe2ff; padding: 4px; }
				`;
				doc.head.append(style);
				const cleanStyle = doc.createElement('style');
				cleanStyle.textContent = `
					[data-builder-region] { min-height: 0 !important; outline: none !important; padding: 0 !important; margin-block: 0 !important; }
					[data-builder-region][data-builder-active="0"] { display: none !important; }
					[data-builder-node] { display: contents; }
					.builder-insert, .builder-node-tools { display: none !important; }
					[data-builder-node] > .builder-element[hidden], [data-builder-children] > .col[hidden] { display: none !important; }
				`;
				doc.head.append(cleanStyle);
				cleanStyle.disabled = true;
				previewMode = (active) => {
					style.disabled = active;
					cleanStyle.disabled = !active;
					doc.querySelectorAll('[data-builder-node]').forEach((node) => { node.tabIndex = active ? -1 : 0; });
					updateScopeVisibility(active);
				};
				historyButtons();
				const snapshot = () => regions.map((region) => {
					const copy = region.cloneNode(true);
					copy.querySelectorAll('.builder-insert, .builder-node-tools').forEach((item) => item.remove());
					return { html: copy.innerHTML, active: region.dataset.builderActive !== '0',
						...(region.dataset.builderScope ? { exists: region.dataset.builderExists === '1', deleted: region.dataset.builderDeleted === '1' } : {}) };
				});
				const record = () => {
					regions.forEach((region) => {
						if (region.dataset.builderExists === '0' && region.querySelector('[data-builder-node]')) {
							region.dataset.builderExists = '1';
							region.dataset.builderActive = '1';
							region.dataset.builderDeleted = '0';
						}
					});
					regionControls();
					const state = snapshot();
					if (JSON.stringify(state) === JSON.stringify(history[position])) return;
					history.splice(position + 1);
					history.push(state);
					position = history.length - 1;
					historyButtons();
				};
				const renderComponent = async (node, config, general = JSON.parse(node.dataset.builderGeneral || '{}')) => {
					const body = new URLSearchParams({
						'R[ACTION]': 'render', 'R[BuilderToken]': settings.Token,
						'R[BuilderComponent][Module]': node.dataset.builderModule,
						'R[BuilderComponent][Type]': node.dataset.builderType,
						'R[BuilderComponent][Id]': node.dataset.builderRenderId || node.dataset.builderNode,
						'R[BuilderComponent][OptionsJson]': JSON.stringify(config.options),
						'R[BuilderComponent][DataJson]': JSON.stringify(config.data),
						'R[BuilderComponent][GeneralJson]': JSON.stringify(general),
						'R[BuilderComponent][TranslationsJson]': JSON.stringify(config.translations || {}),
						'R[BuilderComponent][Language]': language,
					});
					const response = await fetch(settings.RenderUrl, { method: 'POST', credentials: 'same-origin', body });
					if (response.redirected) throw new Error('Anmeldung abgelaufen. Page Builder neu laden.');
					const result = await response.json();
					if (!response.ok || typeof result.html !== 'string') throw new Error(result.error || 'Komponenten-Vorschau fehlgeschlagen.');
					return result;
				};
				const insertions = () => {
					doc.querySelectorAll('.builder-insert').forEach((button) => button.remove());
					const cells = [...doc.querySelectorAll('[data-builder-children]')].flatMap((row) =>
						[...row.children].filter((cell) => cell.classList.contains('col')));
					for (const target of [...regions, ...cells]) {
						const children = [...target.children].filter((child) => child.hasAttribute('data-builder-node'));
						for (const child of [...children, null]) {
							const button = doc.createElement('div');
							button.className = 'builder-insert';
							button.setAttribute('aria-label', 'Element hier einfuegen');
							button.addEventListener('dragover', (event) => {
								if (!selected && !moving) return;
								event.preventDefault();
								event.dataTransfer.dropEffect = moving ? 'move' : 'copy';
								button.classList.add('builder-over');
							});
							button.addEventListener('dragleave', () => button.classList.remove('builder-over'));
							button.addEventListener('drop', (event) => {
								event.preventDefault();
								event.stopPropagation();
								button.classList.remove('builder-over');
								if (event.dataTransfer.getData('text/plain') !== 'builder') return;
								void insert(target, child);
							});
							target.insertBefore(button, child);
						}
					}
				};
				const openOptions = (node) => {
					if (viewing || pending || !editable(node)) return;
					fields.replaceChildren();
					generalFields.replaceChildren();
					selectTab(false);
					optionError.hidden = true;
					const container = node.dataset.builderKind === 'container';
					const definition = settings.Components?.[node.dataset.builderModule]?.[node.dataset.builderType];
					const componentLanguage = definition?.EditorLanguage || language;
					const config = JSON.parse(node.dataset.builderConfig);
					const schema = definition?.Settings || {};
					const values = { ...definition?.Defaults, ...config.options };
					const translatedKeys = Object.keys(schema).filter((key) => schema[key].translatable);
					const translations = config?.translations || {};
					const translation = translations[componentLanguage] || { active: false };
					const translatedValues = { ...values, ...(translation.options || (translations.EN?.active ? translations.EN.options : null) || {}) };
					const controls = [];
					const name = definition?.Name || node.dataset.builderType;
					document.getElementById('builder-dialog-title').textContent = name;
					componentTab.textContent = name;
					const prefix = 'builder-field-' + node.dataset.builderModule + ':' + node.dataset.builderType + '-';
					const customFormId = 'builder-component-form-' + node.dataset.builderModule + ':' + node.dataset.builderType;
					const componentForm = document.getElementById(customFormId) ? cloneForm(customFormId) : null;
					const control = (field, value, templateId, existing = null) => {
						if (field.type === 'tree') {
							const section = cloneForm('builder-tree');
							section.querySelector('legend').textContent = field.label;
							let changed = false;
							const list = (parent, items) => {
								const entries = [];
								const rows = cloneForm('builder-tree-list');
								parent.append(rows);
								const draw = () => {
									rows.replaceChildren(...entries.map((entry) => entry.row));
									entries.forEach((entry, index) => {
										entry.up.disabled = index === 0;
										entry.down.disabled = index === entries.length - 1;
									});
								};
								const add = (item) => {
									const row = cloneForm('builder-tree-row');
									const inputs = Object.entries(field.fields).map(([name, definition]) => {
										const input = control(definition, item[name], templateId + '-' + name);
										row.append(input.element);
										input.element.addEventListener('input', () => { changed = true; });
										input.element.addEventListener('change', () => { changed = true; });
										return { name, input };
									});
									const tools = cloneForm('builder-tree-tools');
									const [up, down, remove] = tools.querySelectorAll('button');
									row.append(tools);
									const children = cloneForm('builder-tree-children');
									row.append(children);
									const readChildren = list(children, item[field.children]);
									const entry = { row, up, down, read: () => ({
										...Object.fromEntries(inputs.map(({ name, input }) => [name, input.read()])),
										[field.children]: readChildren(),
									}) };
									up.addEventListener('click', () => {
										const index = entries.indexOf(entry);
										if (index <= 0) return;
										entries.splice(index, 1);
										entries.splice(index - 1, 0, entry);
										changed = true;
										draw();
									});
									down.addEventListener('click', () => {
										const index = entries.indexOf(entry);
										if (index >= entries.length - 1) return;
										entries.splice(index, 1);
										entries.splice(index + 1, 0, entry);
										changed = true;
										draw();
									});
									remove.addEventListener('click', () => {
										entries.splice(entries.indexOf(entry), 1);
										changed = true;
										draw();
									});
									entries.push(entry);
									draw();
								};
								(items || []).forEach(add);
								const addButton = cloneForm('builder-tree-add');
								addButton.textContent = parent === section ? 'Punkt hinzufuegen' : 'Unterpunkt hinzufuegen';
								addButton.addEventListener('click', () => {
									add({
										...Object.fromEntries(Object.entries(field.fields).map(([name, definition]) => [name, definition.default])),
										[field.children]: [],
									});
									changed = true;
								});
								parent.append(addButton);
								return () => entries.map((entry) => entry.read());
							};
							const read = list(section, value);
							return { element: section, read: () => value === null && !changed ? null : read() };
						}
						return bindControl(existing || cloneForm(templateId), field, value);
					};
					let languageActive = null;
					let ownContent = null;
					if (translatedKeys.length) {
						const activeControl = control({ type: 'boolean', label: 'Sprache ' + componentLanguage + ' aktiv' }, translation.active, 'builder-field-general-language');
						activeControl.element.querySelector('span').textContent = 'Sprache ' + componentLanguage + ' aktiv';
						const ownControl = control({ type: 'boolean', label: 'Eigene Uebersetzung (sonst EN-Fallback)' }, Object.hasOwn(translation, 'options'), 'builder-field-general-own');
						fields.append(activeControl.element, ownControl.element);
						languageActive = activeControl.element.querySelector('input');
						ownContent = ownControl.element.querySelector('input');
					}
					if (componentForm) fields.append(componentForm);
					const customSettings = editorFor(node)?.settings?.(componentForm, values, bindControl);
					if (customSettings && !componentForm) throw new Error('Komponenten-Einstellungs-TPL fehlt.');
					for (const [key, field] of Object.entries(customSettings ? {} : schema)) {
						const existing = componentForm?.querySelector('[data-builder-setting="' + key + '"]');
						const input = control(field, field.translatable ? translatedValues[key] : values[key], prefix + key, existing);
						if (!existing) fields.append(input.element);
						controls.push({ key, input });
					}
					const translationControls = () => {
						if (!languageActive) return;
						ownContent.disabled = !languageActive.checked;
						for (const { key, input } of controls) {
							if (schema[key].translatable) input.disable(!languageActive.checked || !ownContent.checked);
						}
					};
					languageActive?.addEventListener('change', translationControls);
					ownContent?.addEventListener('change', translationControls);
					translationControls();
					const general = { ...generalDefaults, ...JSON.parse(node.dataset.builderGeneral || '{}') };
					const generalControls = Object.entries({
						CustomCssClass: { type: 'string', label: 'CustomCssClass' },
						CustomCss: { type: 'textarea', label: 'CustomCss (CSS-Deklarationen)' },
						Active: { type: 'boolean', label: 'Active (alle Sprachen)' },
					}).map(([key, field]) => {
						const input = control(field, general[key], 'builder-field-general-' + key);
						generalFields.append(input.element);
						return { key, input };
					});
					const sizes = cloneForm('builder-viewports');
					const sizeControls = ['Alle', 'XS', 'SM', 'MD', 'LG'].map((size) => {
						const input = control({ type: 'boolean', label: size }, size === 'Alle'
							? general.Viewports.length === 4 : general.Viewports.includes(size), 'builder-field-general-viewport');
						input.element.querySelector('span').textContent = size;
						sizes.append(input.element);
						return input.element.querySelector('input');
					});
					sizeControls.forEach((input, index) => input.addEventListener('change', () => {
						if (index === 0) sizeControls.slice(1).forEach((child) => { child.checked = input.checked; });
						sizeControls[0].checked = sizeControls.slice(1).every((child) => child.checked);
					}));
					generalFields.append(sizes);
					if (!controls.length && !customSettings) {
						fields.append(cloneForm('builder-empty-settings'));
					}
					apply.disabled = false;
					applyOptions = async (closeAfter) => {
						optionError.hidden = true;
						if (!form.checkValidity()) {
							selectTab(!fields.querySelector(':invalid'));
							form.reportValidity();
							return;
						}
						const options = { ...values, ...customSettings?.read() };
						const translatedOptions = {};
						for (const { key, input } of controls) {
							if (schema[key].translatable) {
								translatedOptions[key] = input.read();
								delete options[key];
							} else options[key] = input.read();
						}
						const updatedGeneral = Object.fromEntries(generalControls.map(({ key, input }) => [key, input.read()]));
						updatedGeneral.Viewports = ['XS', 'SM', 'MD', 'LG'].filter((size, index) => sizeControls[index + 1].checked);
						if (!/^(?:[A-Za-z_][A-Za-z0-9_-]*(?: +[A-Za-z_][A-Za-z0-9_-]*)*)?$/.test(updatedGeneral.CustomCssClass)
							|| /[{}<>\\\x00-\x08\x0b\x0c\x0e-\x1f]|@|expression\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i.test(updatedGeneral.CustomCss)) {
							selectTab(true);
							optionError.textContent = 'Ungueltige CSS-Klassen oder CSS-Deklarationen (keine Selektoren oder Regelbloecke).';
							optionError.hidden = false;
							return;
						}
						pending = true;
						apply.disabled = true;
						historyButtons();
						const current = generation;
						try {
							if (container) {
								const editor = editorFor(node);
								if (!editor) throw new Error('Grid-Editorcode fehlt. Page Builder neu laden.');
								editor.validate(options);
								await renderComponent(node, { ...config, options }, updatedGeneral);
								if (current !== generation || !editing || !dialog.open) return;
								node.dataset.builderConfig = JSON.stringify({ ...config, options });
								editor.layout(node, options);
							} else {
								const updated = { ...config, options };
								if (languageActive) updated.translations = {
									...translations,
									[componentLanguage]: { active: languageActive.checked, ...(ownContent.checked ? { options: translatedOptions } : {}) },
								};
								const result = await renderComponent(node, updated, updatedGeneral);
								if (current !== generation || !editing || !dialog.open) return;
								node.querySelector('.builder-element').innerHTML = result.html;
								node.dataset.builderLanguageVisible = result.visible === false ? '0' : '1';
								node.dataset.builderConfig = JSON.stringify(updated);
							}
							node.dataset.builderGeneral = JSON.stringify(updatedGeneral);
							appearance(node);
							layoutContainers(doc);
							insertions();
							record();
							if (closeAfter) dialog.close();
							message('Einstellungen uebernommen. Noch nicht in der Datenbank gespeichert.');
						} catch (error) {
							if (current !== generation) return;
							optionError.textContent = error.message;
							optionError.hidden = false;
							console.error('Builder-Einstellungen:', error);
						} finally {
							if (current === generation) {
								pending = false;
								apply.disabled = false;
								historyButtons();
							}
						}
					};
					dialog.showModal();
					historyButtons();
				};
				const decorate = (node) => {
					if (node.dataset.builderKind === 'container' && !node.dataset.builderConfig) {
						node.dataset.builderModule = 'fremeo/core';
						node.dataset.builderType = 'container';
						node.dataset.builderConfig = JSON.stringify({ options: {
							...settings.Components?.['fremeo/core']?.container?.Defaults,
							columns: node.dataset.builderLayout === 'two-columns' ? '6+6' : '12',
						}, data: [] });
					}
					appearance(node);
					node.tabIndex = 0;
					const tools = doc.createElement('div');
					tools.className = 'builder-node-tools';
					const handle = doc.createElement('button');
					handle.type = 'button';
					handle.draggable = true;
					handle.title = 'Verschieben';
					handle.setAttribute('aria-label', 'Verschieben');
					handle.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v20M2 12h20M8 6l4-4 4 4M8 18l4 4 4-4M6 8l-4 4 4 4M18 8l4 4-4 4"/></svg>';
					handle.addEventListener('dragstart', (event) => {
						if (!editable(node) || viewing || pending) { event.preventDefault(); return; }
						selected = null;
						moving = node;
						event.dataTransfer.setData('text/plain', 'builder');
						event.dataTransfer.effectAllowed = 'move';
					});
					handle.addEventListener('dragend', () => { moving = null; selected = null; });
					const label = doc.createElement('span');
					label.textContent = settings.Components?.[node.dataset.builderModule]?.[node.dataset.builderType]?.Name || node.dataset.builderType;
					const configure = doc.createElement('button');
					configure.type = 'button';
					configure.title = 'Einstellungen';
					configure.setAttribute('aria-label', 'Einstellungen');
					configure.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20l5-1L21 7l-4-4L5 15zM14 6l4 4"/></svg>';
					configure.addEventListener('click', () => {
						try { openOptions(node); }
						catch (error) {
							message('Einstellungen konnten nicht geladen werden: ' + error.message);
							console.error('Builder-Einstellungsformular:', error);
						}
					});
					const remove = doc.createElement('button');
					remove.type = 'button';
					remove.title = 'Entfernen';
					remove.setAttribute('aria-label', 'Entfernen');
					remove.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/></svg>';
					remove.addEventListener('click', () => {
						if (viewing || pending || !editable(node)) return;
						if (moving && node.contains(moving)) moving = null;
						node.remove();
						layoutContainers(doc);
						insertions();
						record();
						message('Element entfernt. Nur Vorschau, noch nicht gespeichert.');
					});
					tools.append(label, handle, configure, remove);
					node.prepend(tools);
				};
				const insert = async (target, before) => {
					if (!editing || viewing || pending || !editable(target) || (!selected && !moving)) return;
					if (moving && (moving === before || moving.contains(target))) {
						message('Ein Grid kann nicht in sich selbst verschoben werden.');
						return;
					}
					pending = true;
					historyButtons();
					const current = generation;
					try {
						let node = moving;
						if (!node) {
							node = doc.createElement('div');
							node.dataset.builderNode = 'node-' + crypto.randomUUID();
							const definition = settings.Components?.[selected.module]?.[selected.type];
							node.dataset.builderKind = definition?.Builder === 'container' ? 'container' : 'component';
							const element = doc.createElement('div');
							element.className = 'builder-element';
							node.append(element);
							node.dataset.builderModule = selected.module;
							node.dataset.builderType = selected.type;
							const config = { options: { ...definition?.Defaults }, data: [] };
							const keys = Object.keys(definition?.Settings || {}).filter((key) => definition.Settings[key].translatable);
							if (keys.length) {
								config.translations = { [definition?.EditorLanguage || language]: { active: true, options: Object.fromEntries(keys.map((key) => [key, config.options[key]])) } };
								keys.forEach((key) => { delete config.options[key]; });
							}
							if (node.dataset.builderKind === 'container') {
								const editor = editorFor(node);
								if (!editor) throw new Error('Grid-Editorcode fehlt. Page Builder neu laden.');
								element.append(editor.create(doc, config.options, node.dataset.builderRenderId || node.dataset.builderNode));
							} else {
								const result = await renderComponent(node, config);
								if (current !== generation || !editing) return;
								node.dataset.builderLanguageVisible = result.visible === false ? '0' : '1';
								element.innerHTML = result.html;
							}
							node.dataset.builderConfig = JSON.stringify(config);
							decorate(node);
						}
						target.insertBefore(node, before);
						appearance(node);
						moving = null;
						selected = null;
						layoutContainers(doc);
						insertions();
						record();
						message('Vorschau geaendert. Noch nicht gespeichert; Abbrechen verwirft die Aenderungen.');
					} catch (error) {
						if (current !== generation) return;
						message('Einfuegen fehlgeschlagen: ' + error.message);
						console.error('Builder:', error);
					} finally {
						if (current === generation) {
							pending = false;
							selected = null;
							moving = null;
							historyButtons();
						}
					}
				};
				doc.querySelectorAll('[data-builder-node]').forEach(decorate);
				layoutContainers(doc);
				const scopeLabels = { individual: 'Diese Seite', page: 'Seitentyp', global: 'Allgemein' };
				const scopeGroups = new Map();
				const scopeEditing = new Map();
				const scopeOpen = new Set();
				for (const region of regions.filter((region) => region.dataset.builderScope)) {
					const name = region.dataset.builderRegion;
					if (!scopeGroups.has(name)) scopeGroups.set(name, []);
					scopeGroups.get(name).push(region);
				}
				const effectiveScope = (group) => group.find((region) => region.dataset.builderExists === '1' && region.dataset.builderActive === '1');
				const updateScopeVisibility = (clean = viewing) => {
					for (const [name, group] of scopeGroups) {
						const effective = effectiveScope(group);
						const selected = !clean && scopeEditing.get(name);
						const visible = selected || effective || (!clean && group[0].dataset.builderExists !== '1' ? group[0] : null);
						group.forEach((region) => {
							region.hidden = region !== visible;
							region.dataset.builderLocked = region.dataset.builderExists === '1' && region.dataset.builderScope !== 'individual' && selected !== region ? '1' : '0';
							if (selected === region) region.setAttribute('data-builder-editing', 'Bearbeitung: ' + scopeLabels[region.dataset.builderScope]);
							else region.removeAttribute('data-builder-editing');
						});
					}
				};
				const regionControls = () => {
					regionFields.replaceChildren();
					for (const [name, group] of scopeGroups) {
						const section = cloneForm('builder-scope-group');
						section.open = scopeOpen.has(name);
						section.addEventListener('toggle', () => {
							if (section.open) scopeOpen.add(name);
							else scopeOpen.delete(name);
						});
						section.querySelector('[data-scope-name]').textContent = name;
						const effective = effectiveScope(group);
						const status = section.querySelector('[data-scope-status]');
						status.textContent = effective ? (effective.dataset.builderScope === 'individual' ? 'Individuell' : 'Geerbt: ' + scopeLabels[effective.dataset.builderScope]) : 'Kein aktiver Inhalt';
						status.className = 'badge ' + (effective?.dataset.builderScope === 'individual' ? 'text-bg-primary' : 'text-bg-secondary');
						const selected = scopeEditing.get(name);
						const hint = section.querySelector('[data-scope-editing]');
						hint.hidden = !selected;
						if (selected) hint.textContent = 'Bearbeitung: ' + scopeLabels[selected.dataset.builderScope]
							+ (selected.dataset.builderScope === 'individual' ? '' : '. Aenderungen betreffen alle erbenden Seiten.');
						const back = section.querySelector('[data-scope-return]');
						back.hidden = !selected;
						back.addEventListener('click', () => {
							if (pending || dialog.open || viewing) return;
							scopeEditing.delete(name);
							regionControls();
						});
						for (const region of group) {
							const row = cloneForm('builder-scope-level');
							const target = JSON.parse(region.dataset.builderTarget);
							row.querySelector('span').textContent = scopeLabels[region.dataset.builderScope]
								+ (region.dataset.builderScope === 'individual' ? ': ' + target.entity
									: region.dataset.builderScope === 'page' ? ': ' + target.page : '');
							const exists = region.dataset.builderExists === '1';
							const input = row.querySelector('input');
							input.hidden = !exists;
							input.checked = region.dataset.builderActive === '1';
							input.title = region.dataset.builderScope === 'individual' ? 'Individuelle Ebene aktiv' : 'Aktivierung gilt fuer alle Seiten, die diese Ebene erben.';
							input.addEventListener('change', () => {
								if (pending || dialog.open || viewing) { input.checked = region.dataset.builderActive === '1'; return; }
								region.dataset.builderActive = input.checked ? '1' : '0';
								scopeEditing.delete(name);
								record();
							});
							const remove = row.querySelector('[data-scope-delete]');
							remove.disabled = !exists;
							remove.title = region.dataset.builderDeleted === '1' ? 'Loeschung vorgemerkt - noch nicht veroeffentlicht'
								: 'Ebene komplett loeschen' + (region.dataset.builderScope === 'individual' ? '' : ' (betrifft alle erbenden Seiten)');
							remove.addEventListener('click', () => {
								if (!exists || pending || dialog.open || viewing) return;
								if (!window.confirm('Diese Ebene komplett loeschen?'
									+ (region.dataset.builderScope === 'individual' ? '' : ' Dies betrifft alle Seiten, die diese Ebene erben.')
									+ ' Die Loeschung wird erst nach Speichern und Veroeffentlichen wirksam.')) return;
								region.replaceChildren();
								region.dataset.builderExists = '0';
								region.dataset.builderActive = '0';
								region.dataset.builderDeleted = '1';
								scopeEditing.delete(name);
								insertions();
								record();
								message('Loeschung vorgemerkt. Erst Speichern und Veroeffentlichen entfernt die Ebene endgueltig.');
							});
							const button = row.querySelector('[data-scope-edit]');
							button.addEventListener('click', () => {
								if (pending || dialog.open || viewing) return;
								if (!exists) {
									const source = effectiveScope(group);
									region.dataset.builderExists = '1';
									region.dataset.builderActive = '1';
									region.dataset.builderDeleted = '0';
									if (source && source !== region) {
										const copy = source.cloneNode(true);
										copy.querySelectorAll('.builder-insert, .builder-node-tools').forEach((item) => item.remove());
										const ids = new Map();
										copy.querySelectorAll('[id]').forEach((element) => {
											const id = 'builder-copy-' + crypto.randomUUID();
											ids.set(element.id, id);
											element.id = id;
										});
										copy.querySelectorAll('[data-builder-node]').forEach((node) => {
											node.dataset.builderNode = 'node-' + crypto.randomUUID();
											node.dataset.builderRenderId = ids.get(node.dataset.builderRenderId)
												|| node.querySelector('[data-component-module]')?.id || node.dataset.builderNode;
										});
										copy.querySelectorAll('[href], [for], [aria-labelledby], [aria-controls], [data-bs-target]').forEach((element) => {
											for (const attribute of ['href', 'for', 'aria-labelledby', 'aria-controls', 'data-bs-target']) {
												const value = element.getAttribute(attribute);
												if (!value) continue;
												element.setAttribute(attribute, value.split(' ').map((part) => {
													const prefix = part.startsWith('#') ? '#' : '';
													return ids.has(part.slice(prefix.length)) ? prefix + ids.get(part.slice(prefix.length)) : part;
												}).join(' '));
											}
										});
										region.innerHTML = copy.innerHTML;
										region.querySelectorAll('[data-builder-node]').forEach(decorate);
										layoutContainers(region);
										insertions();
									}
									record();
								}
								scopeEditing.set(name, region);
								scopeOpen.add(name);
								regionControls();
							});
							section.querySelector('[data-scope-levels]').append(row);
						}
						regionFields.append(section);
					}
					regions.filter((region) => !region.dataset.builderScope).forEach((region) => {
						const label = document.createElement('label');
						label.className = 'form-label d-block small';
						const input = document.createElement('input');
						input.type = 'checkbox';
						input.className = 'form-check-input me-2';
						input.checked = region.dataset.builderActive !== '0';
						label.append(input, document.createTextNode(region.dataset.builderRegion));
						input.addEventListener('change', () => {
							if (pending || viewing || dialog.open) { input.checked = region.dataset.builderActive !== '0'; return; }
							region.dataset.builderActive = input.checked ? '1' : '0';
							record();
						});
						regionFields.append(label);
					});
					updateScopeVisibility();
				};
				regionControls();
				insertions();
				record();
				savedState = JSON.stringify(history[position]);
				if (regions.length && regions.every((region) => region.dataset.builderTarget && region.dataset.builderToken)) {
					collect = () => {
						const nodes = (target) => [...target.children].flatMap((entry) => {
							if (entry.classList.contains('col')) return nodes(entry);
							const node = entry.hasAttribute('data-builder-node') ? entry : null;
							if (!node) return [];
							const value = {
								id: node.dataset.builderNode, kind: node.dataset.builderKind,
								general: { ...generalDefaults, ...JSON.parse(node.dataset.builderGeneral || '{}') },
							};
							if (target.classList.contains('col')) value.slot = [...target.parentElement.children].filter((cell) => cell.classList.contains('col')).indexOf(target);
							if (value.kind === 'container') {
								const config = JSON.parse(node.dataset.builderConfig);
								value.module = node.dataset.builderModule;
								value.type = node.dataset.builderType;
								value.options = config.options;
								value.children = nodes(node.querySelector('[data-builder-children]'));
							} else {
								const config = JSON.parse(node.dataset.builderConfig);
								value.module = node.dataset.builderModule;
								value.type = node.dataset.builderType;
								value.options = config.options;
								if (config.translations) value.translations = config.translations;
								if (config.source !== null && config.source !== undefined) value.source = config.source;
								else value.data = config.data;
							}
							return [value];
						});
						return regions.flatMap((region, index) => region.dataset.builderExists === '0' && region.dataset.builderDeleted !== '1' ? [] : [{
							target: region.dataset.builderTarget, token: region.dataset.builderToken, active: region.dataset.builderActive !== '0', nodes: nodes(region),
							deleted: region.dataset.builderDeleted === '1',
							revision: revisions[index],
						}]);
					};
				}
				historyButtons();
				restore = (state) => {
					moving = selected = null;
					regions.forEach((region, index) => {
						region.innerHTML = state[index].html;
						region.dataset.builderActive = state[index].active ? '1' : '0';
						if (region.dataset.builderScope) {
							region.dataset.builderExists = state[index].exists ? '1' : '0';
							region.dataset.builderDeleted = state[index].deleted ? '1' : '0';
						}
					});
					scopeEditing.clear();
					regionControls();
					doc.querySelectorAll('[data-builder-node]').forEach(decorate);
					layoutContainers(doc);
					insertions();
					message('Vorschau geaendert. Noch nicht in der Datenbank gespeichert.');
				};
				message(!regions.length ? 'Keine Builder-Stellen erkannt. Abbrechen fuehrt zur normalen Seite zurueck.'
					: !collect ? 'Speichern nicht verfuegbar: autorisierte Seitenzuordnung fehlt. Vorschau neu laden.' : '');
				doc.addEventListener('click', (event) => {
					if (!viewing && event.target.closest('.builder-insert, .builder-node-tools')) return;
					if (event.target.closest('a, button, input, select, textarea')) {
						event.preventDefault();
						event.stopImmediatePropagation();
					}
				}, true);
				doc.addEventListener('submit', (event) => {
					event.preventDefault();
					event.stopImmediatePropagation();
				}, true);
			} catch (error) {
				message('Seite nicht bearbeitbar. Bitte eine Frontend-Seite derselben Anwendung oeffnen.');
				console.error('Builder-Vorschau:', error);
			}
		};
		edit.addEventListener('click', () => {
			if (edit.disabled || !browsingUrl) return;
			const url = new URL(browsingUrl);
			url.searchParams.set('R[BuilderPreview]', '1');
			url.searchParams.set('R[BuilderLanguage]', language);
			showEditor(true);
			edit.disabled = true;
			message('');
			frame.src = url.href;
		});
		cancel.addEventListener('click', () => {
			if (saving) return;
			generation++;
			pending = false;
			if (dialog.open) dialog.close();
			showEditor(false);
			edit.disabled = true;
			message('');
			frame.src = browsingUrl;
		});
		frame.addEventListener('load', inspect);
		if (frame.contentDocument && frame.contentDocument.readyState === 'complete'
			&& frame.contentWindow.location.href !== 'about:blank') inspect();
	})();
	{/literal}
	</script>
{/block}
