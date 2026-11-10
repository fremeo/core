const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const crypto = require('node:crypto');
const { spawnSync } = require('node:child_process');
const formCache = new Map();
const tick = () => new Promise((resolve) => setImmediate(resolve));
const treeManifest = {
	name: 'Example tree', defaults: { items: null },
	settings: { items: { type: 'tree', label: 'Items', nullable: true, children: 'Children', fields: {
		Title: { type: 'string', label: 'Title', default: '' },
		Url: { type: 'string', label: 'Link', default: '' },
		Target: { type: 'string', label: 'Target', enum: ['_self', '_blank'], default: '_self' },
	} } },
};

const source = fs.readFileSync(path.join(__dirname, '../system/template/admin__builder.tpl'), 'utf8')
	.split('{literal}')[1].split('{/literal}')[0];

// Minimal element doubles exercise the editor's actual insertion handlers.
function element(tag = 'div', dataset = {}) {
	const node = {
		tag, dataset, children: [], listeners: {}, className: '', style: {}, attrs: {},
		get tagName() { return this.tag.toUpperCase(); },
		get attributes() { return Object.entries(this.attrs).map(([name, value]) => ({ name, value })); },
		ownerDocument: { createElement: element },
		getBoundingClientRect() { return { left: 100, top: 100, width: 400, height: 300 }; },
		setPointerCapture(id) { this.pointerId = id; },
		addEventListener(type, handler) { this.listeners[type] = handler; },
		setAttribute(name, value) { this.attrs[name] = value; this[name] = value; },
		getAttribute(name) { return this.attrs[name] ?? null; },
		removeAttribute(name) { delete this.attrs[name]; delete this[name]; },
		hasAttribute(name) {
			const key = name.replace(/^data-/, '').replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
			return Object.hasOwn(this.dataset, key);
		},
		querySelectorAll(selector) {
			const matches = (item) => {
				if (selector.includes(', ')) return selector.split(', ').some((part) => item.matches(part));
				if (selector === '[aria-pressed]') return Object.hasOwn(item, 'aria-pressed');
				return item.matches(selector);
			};
			return this.children.flatMap((child) => [...(matches(child) ? [child] : []), ...child.querySelectorAll(selector)]);
		},
		matches(selector) {
			if (selector.startsWith('.')) return this.classList.contains(selector.slice(1));
			const match = selector.match(/^(?:([a-z]+))?\[([a-z-]+)(?:="([^"]*)")?\]$/);
			if (match) {
				if (match[1] && this.tag !== match[1]) return false;
				if (match[2].startsWith('data-')) {
					const key = match[2].slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
					return Object.hasOwn(this.dataset, key) && (match[3] === undefined || this.dataset[key] === match[3]);
				}
				return match[3] === undefined ? Boolean(this[match[2]]) : this[match[2]] === match[3];
			}
			return this.tag === selector;
		},
		querySelector(selector) { return this.querySelectorAll(selector)[0] || null; },
		append(...items) { items.forEach((item) => this.insertBefore(item, null)); },
		prepend(item) { this.insertBefore(item, this.children[0] || null); },
		insertBefore(item, before) {
			item.remove();
			const index = before === null ? this.children.length : this.children.indexOf(before);
			assert.ok(index >= 0, 'Insertion anchor must belong to target');
			this.children.splice(index, 0, item);
			item.parentElement = this;
		},
		remove() {
			if (this.parentElement) {
				this.parentElement.children = this.parentElement.children.filter((child) => child !== this);
				this.parentElement = null;
			}
		},
		contains(item) { return this === item || this.children.some((child) => child.contains(item)); },
		replaceChildren(...items) { [...this.children].forEach((child) => child.remove()); this.append(...items); },
		cloneNode() {
			const copy = element(this.tag, { ...this.dataset });
			copy.className = this.className;
			copy.type = this.type;
			copy.attrs = { ...this.attrs };
			copy.style = { ...this.style };
			copy.value = this.value;
			copy.textContent = this.textContent;
			copy.append(...this.children.map((child) => child.cloneNode()));
			return copy;
		},
		get innerHTML() {
			const serialize = (item) => ({ tag: item.tag, dataset: item.dataset, className: item.className, attrs: item.attrs, style: item.style, value: item.value, textContent: item.textContent, children: item.children.map(serialize) });
			return JSON.stringify(this.children.map(serialize));
		},
		set innerHTML(html) {
			this.replaceChildren();
			if (html.startsWith('[')) {
				const restore = (value) => {
					const child = element(value.tag, value.dataset);
					child.className = value.className;
					child.attrs = value.attrs || {};
					child.style = value.style || {};
					child.value = value.value;
					child.textContent = value.textContent;
					for (const [name, attribute] of Object.entries(child.attrs)) if (name !== 'style') child[name] = attribute;
					child.append(...value.children.map(restore));
					return child;
				};
				this.append(...JSON.parse(html).map(restore));
			} else if (html.includes('data-component-module')) {
				this.append(element('div', { componentModule: 'test/provider' }));
			} else assert.match(html, /<svg/);
		},
		showModal() { this.open = true; },
		close() { this.open = false; this.listeners.close?.(); },
		reportValidity() { return true; },
		checkValidity() { return true; },
		focus() {},
	};
	node.classList = {
		contains: (name) => node.className.split(' ').includes(name),
		add: (name) => { if (!node.classList.contains(name)) node.className += ' ' + name; },
		remove: (name) => { node.className = node.className.split(' ').filter((item) => item !== name).join(' '); },
	};
	if (tag === 'iframe') node.contentDocument = { body: { innerHTML: '', style: {}, contentEditable: 'true', addEventListener() {} } };
	return node;
}

function formTemplates(components) {
	const key = JSON.stringify(components);
	if (!formCache.has(key)) {
		const result = spawnSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'BuilderFormsFixture.php')], { input: key, encoding: 'utf8' });
		assert.equal(result.status, 0, result.stderr || result.error?.message);
		formCache.set(key, JSON.parse(result.stdout));
	}
	const decode = (value) => {
		const node = element(value.tag);
		node.textContent = value.textContent;
		for (const [name, attribute] of Object.entries(value.attributes)) {
			if (name === 'style') node.attrs[name] = attribute;
			else node.setAttribute(name, attribute);
			if (name === 'class') node.className = attribute;
			if (name.startsWith('data-')) node.dataset[name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] = attribute;
			if (name === 'style') for (const declaration of attribute.split(';')) {
				const [property, text] = declaration.split(':');
				if (property && text) node.style[property.trim().replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] = text.trim();
			}
		}
		node.append(...value.children.map(decode));
		return node;
	};
	return Object.fromEntries(Object.entries(formCache.get(key)).map(([id, value]) => [id, decode(value)]));
}

function editor(render = async () => ({ ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>' }) }),
	definition = { Name: 'Example', Defaults: { text: 'Default' }, Settings: { text: { type: 'string', label: 'Text' } } }) {
	const elements = {};
	for (const id of ['preview', 'preview-status', 'edit', 'cancel', 'library', 'regions', 'viewport', 'save', 'publish', 'prev', 'next', 'view', 'dialog',
		'options-form', 'options-fields', 'general-fields', 'component-tab', 'general-tab', 'options-error', 'options-apply', 'options-ok', 'options-cancel', 'dialog-title', 'dialog-close', 'dialog-header']) {
		elements['builder-' + id] = element();
		elements['builder-' + id].disabled = id === 'edit' || id === 'save';
	}
	const containerButton = element('button', { builderModule: 'fremeo/core', builderType: 'container' });
	const componentButton = element('button', { builderModule: 'test/provider', builderType: 'example' });
	containerButton.draggable = componentButton.draggable = true;
	elements['builder-library'].append(containerButton, componentButton);
	elements['builder-settings'] = { textContent: JSON.stringify({
		Token: 'test', RenderUrl: 'https://example.test/render',
		Components: { 'test/provider': { example: definition }, 'fremeo/core': { container: (() => {
			const manifest = JSON.parse(fs.readFileSync(path.join(__dirname, '../system/template/components/container/component.json'), 'utf8'));
			return { Name: manifest.name, Defaults: { ...manifest.defaults, columns: '6+6' }, Settings: manifest.settings, Builder: manifest.builder };
		})() } },
	}) };
	Object.assign(elements, formTemplates(JSON.parse(elements['builder-settings'].textContent).Components));
	const frame = elements['builder-preview'];
	const errors = [];
	const context = {
		document: { getElementById: (id) => elements[id], createElement: element, createTextNode: () => element('text') },
		window: {
			confirm: () => true,
			location: { origin: 'https://example.test' }, innerWidth: 1000, innerHeight: 800,
			getComputedStyle: () => ({ backgroundColor: 'rgb(33, 37, 41)', color: 'rgb(222, 226, 230)', colorScheme: 'dark', fontFamily: 'sans-serif' }),
		},
		URL,
		URLSearchParams,
		crypto,
		fetch: render,
		console: { error: (...args) => errors.push(args) },
	};
	vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../system/template/components/container/container.js'), 'utf8'), context);
	vm.runInNewContext(source, context);
	return {
		elements, frame, errors, containerButton, componentButton,
		load(url, branch = 'frontend', language = 'DE', count = 2, authorized = false, setup = null) {
			const links = [{ target: '_blank' }];
			const handlers = {};
			const body = element();
			const regions = Array.from({ length: count }, (_, index) => element('div', {
				builderRegion: 'region-' + index,
				...(authorized ? {
					builderTarget: JSON.stringify({ module: 'test/provider', page: 'frontend__page', region: 'region-' + index, entity: '', language }),
					builderToken: 'signed-' + index,
				} : {}),
			}));
			body.append(...regions);
			if (setup) setup(regions);
			frame.contentWindow = { location: { href: url } };
			frame.contentDocument = {
				body: { dataset: { layout: branch } },
				documentElement: { dataset: { builderLanguage: language } },
				head: element('head'),
				createElement: element,
				querySelectorAll: (selector) => {
					if (selector === 'a[target="_blank"]') return links;
					return body.querySelectorAll(selector);
				},
				addEventListener: (type, handler) => { handlers[type] = handler; },
			};
			frame.listeners.load();
			return { links, handlers, body, regions };
		},
	};
}

test('generic manifest tree editor adds nested items, reorders and removes them with Undo/Redo', async () => {
	const manifest = treeManifest;
	const requests = [];
	const ui = editor(async (url, request) => {
		requests.push(JSON.parse(request.body.get('R[BuilderComponent][OptionsJson]')));
		return { ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>' }) };
	}, { Name: manifest.name, Defaults: manifest.defaults, Settings: manifest.settings });
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src);
	ui.componentButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	regions[0].querySelector('.builder-insert').listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	await tick();
	let component = regions[0].querySelector('[data-builder-node]');
	component.children[0].children[2].listeners.click();
	const section = ui.elements['builder-options-fields'].querySelector('fieldset');
	const add = section.children[2];
	add.listeners.click();
	const first = section.children[1].children[0];
	first.querySelectorAll('input')[0].value = 'First';
	first.querySelectorAll('input')[1].value = '/first';
	first.querySelector('select').value = '0';
	const childSection = first.children[4];
	childSection.children[1].listeners.click();
	const child = childSection.children[0].children[0];
	child.querySelectorAll('input')[0].value = 'Child';
	child.querySelectorAll('input')[1].value = '/child';
	child.querySelector('select').value = '1';
	add.listeners.click();
	const second = section.children[1].children[1];
	second.querySelectorAll('input')[0].value = 'Second';
	second.querySelectorAll('input')[1].value = '/second';
	second.querySelector('select').value = '0';
	second.children[3].children[0].listeners.click();
	// Scalar select values are indexes in the generated popup.
	ui.elements['builder-options-fields'].querySelector('select').value = '0';
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-ok'] });
	await tick();
	assert.deepEqual(requests[1].items, [
		{ Title: 'Second', Url: '/second', Target: '_self', Children: [] },
		{ Title: 'First', Url: '/first', Target: '_self', Children: [
			{ Title: 'Child', Url: '/child', Target: '_blank', Children: [] },
		] },
	]);
	component.children[0].children[2].listeners.click();
	const reopened = ui.elements['builder-options-fields'].querySelector('fieldset');
	[...reopened.children[1].children].forEach((row) => row.children[3].children[2].listeners.click());
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-ok'] });
	await tick();
	assert.deepEqual(requests[2].items, []);
	ui.elements['builder-prev'].listeners.click();
	component = regions[0].querySelector('[data-builder-node]');
	assert.equal(JSON.parse(component.dataset.builderConfig).options.items[1].Children[0].Title, 'Child');
	ui.elements['builder-next'].listeners.click();
	component = regions[0].querySelector('[data-builder-node]');
	assert.deepEqual(JSON.parse(component.dataset.builderConfig).options.items, []);
	assert.equal(ui.elements['builder-save'].disabled, true);
});

test('opening an unchanged nullable tree does not replace existing provider data', async () => {
	const manifest = treeManifest;
	const ui = editor(undefined, { Name: manifest.name, Defaults: manifest.defaults, Settings: manifest.settings });
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src);
	ui.componentButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	regions[0].querySelector('.builder-insert').listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	await tick();
	const component = regions[0].querySelector('[data-builder-node]');
	component.children[0].children[2].listeners.click();
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-ok'] });
	await tick();
	assert.equal(JSON.parse(component.dataset.builderConfig).options.items, null);
	assert.equal(ui.elements['builder-dialog'].open, false);
});

test('clean preview preserves unsaved nodes and history without navigating or permitting edits', () => {
	const ui = editor();
	const view = ui.elements['builder-view'];
	ui.load('https://example.test/site/');
	assert.equal(view.hidden, true);
	assert.equal(view.disabled, true);
	ui.elements['builder-edit'].listeners.click();
	assert.equal(view.disabled, true);
	const { regions } = ui.load(ui.frame.src);
	const url = ui.frame.src;
	const drop = () => regions[0].querySelector('.builder-insert').listeners.drop({
		dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {},
	});
	ui.containerButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	drop();
	const node = regions[0].querySelector('[data-builder-node]');
	assert.match(node.querySelector('[data-builder-children]').className, /row g-3/);
	const before = regions[0].innerHTML;
	const styles = ui.frame.contentDocument.head.children;
	view.listeners.click();
	assert.equal(view.textContent, 'Bearbeiten');
	assert.equal(view['aria-pressed'], 'true');
	assert.equal(styles[0].disabled, true);
	assert.equal(styles[1].disabled, false);
	assert.match(styles[1].textContent, /display: contents/);
	assert.equal(node.tabIndex, -1);
	assert.equal(ui.elements['builder-library'].hidden, true);
	assert.equal(ui.elements['builder-viewport'].className, 'col-12');
	assert.equal(ui.elements['builder-prev'].disabled, true);
	node.children[0].children[3].listeners.click();
	ui.containerButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	drop();
	assert.equal(regions[0].innerHTML, before);
	assert.equal(ui.frame.src, url);
	view.listeners.click();
	assert.equal(view.textContent, 'Vorschau');
	assert.equal(styles[0].disabled, false);
	assert.equal(styles[1].disabled, true);
	assert.equal(node.tabIndex, 0);
	assert.equal(ui.elements['builder-library'].hidden, false);
	assert.equal(ui.elements['builder-prev'].disabled, false);
	ui.elements['builder-prev'].listeners.click();
	assert.equal(regions[0].querySelector('[data-builder-node]'), null);
	ui.elements['builder-next'].listeners.click();
	assert.ok(regions[0].querySelector('[data-builder-node]'));
	view.listeners.click();
	ui.elements['builder-cancel'].listeners.click();
	assert.equal(view.hidden, true);
	assert.equal(view.disabled, true);
	assert.equal(view['aria-pressed'], 'false');
	assert.equal(ui.frame.src, 'https://example.test/site/');
	assert.equal(ui.elements['builder-save'].disabled, true);
});

test('window tabs apply shared appearance, reject invalid CSS and retain settings through history', async () => {
	const ui = editor();
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src);
	ui.containerButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	regions[0].querySelector('.builder-insert').listeners.drop({
		dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {},
	});
	let node = regions[0].querySelector('[data-builder-node]');
	const open = () => node.children[0].children[2].listeners.click();
	open();
	assert.equal(ui.elements['builder-dialog-title'].textContent, 'Grid');
	assert.equal(ui.elements['builder-component-tab'].textContent, 'Grid');
	ui.elements['builder-general-tab'].listeners.click();
	assert.equal(ui.elements['builder-options-fields'].hidden, true);
	assert.equal(ui.elements['builder-general-fields'].hidden, false);
	const inputs = ui.elements['builder-general-fields'].querySelectorAll('input');
	inputs[0].value = 'custom-panel highlighted';
	ui.elements['builder-general-fields'].querySelector('textarea').value = 'color: red; padding: 8px;';
	inputs[1].checked = false;
	inputs[2].checked = false;
	inputs[2].listeners.change();
	assert.ok(inputs.slice(3).every((input) => !input.checked));
	inputs[5].checked = true;
	inputs[5].listeners.change();
	assert.equal(inputs[2].checked, false);
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-ok'] });
	await tick();
	const general = JSON.parse(node.dataset.builderGeneral);
	assert.deepEqual(general, { CustomCssClass: 'custom-panel highlighted', CustomCss: 'color: red; padding: 8px;', Active: false, Viewports: ['MD'] });
	assert.equal(node.querySelector('.builder-element').hidden, true);
	assert.match(node.querySelector('.builder-element').className, /d-none d-sm-none d-md-block d-lg-none/);
	open();
	assert.equal(ui.elements['builder-general-fields'].querySelector('input').value, 'custom-panel highlighted');
	ui.elements['builder-general-fields'].querySelector('textarea').value = 'body { display:none }';
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-ok'] });
	assert.equal(ui.elements['builder-dialog'].open, true);
	assert.equal(ui.elements['builder-general-fields'].hidden, false);
	assert.equal(ui.elements['builder-options-error'].hidden, false);
	assert.deepEqual(JSON.parse(node.dataset.builderGeneral), general);
	ui.elements['builder-options-cancel'].listeners.click();
	ui.elements['builder-prev'].listeners.click();
	node = regions[0].querySelector('[data-builder-node]');
	assert.equal(node.dataset.builderGeneral, undefined);
	ui.elements['builder-next'].listeners.click();
	node = regions[0].querySelector('[data-builder-node]');
	assert.deepEqual(JSON.parse(node.dataset.builderGeneral), general);
	ui.elements['builder-view'].listeners.click();
	assert.equal(node.querySelector('.builder-element').hidden, true);
	assert.equal(ui.elements['builder-save'].disabled, true);
});

test('Save serializes all authorized regions without HTML, keeps source references and tracks saved history', async () => {
	const requests = [];
	let finish;
	const ui = editor(async (url, request) => {
		if (request.body.get('R[ACTION]') === 'save') {
			requests.push(request);
			return new Promise((resolve) => { finish = resolve; });
		}
		return { ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>' }) };
	});
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src, 'frontend', 'DE', 2, true);
	const save = ui.elements['builder-save'];
	assert.equal(save.disabled, false);
	const dataTransfer = { setData() {} };
	const drop = (target) => target.listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	ui.containerButton.listeners.dragstart({ dataTransfer });
	drop(regions[0].querySelector('.builder-insert'));
	const container = regions[0].querySelector('[data-builder-node]');
	ui.componentButton.listeners.dragstart({ dataTransfer });
	drop(container.querySelector('.builder-insert'));
	await tick();
	const component = container.querySelector('[data-builder-node]');
	component.dataset.builderConfig = JSON.stringify({ options: { text: 'Source' }, data: { SecretResolved: 'not persisted' }, source: ['NAVIGATION', 'D', 'main'] });
	container.dataset.builderGeneral = JSON.stringify({ Active: false, CustomCssClass: 'custom', CustomCss: 'padding: 2px;', Viewports: ['MD'] });
	assert.equal(save.disabled, false);
	const saving = save.listeners.click();
	assert.equal(save.disabled, true);
	assert.equal(ui.elements['builder-cancel'].disabled, true);
	ui.elements['builder-cancel'].listeners.click();
	assert.ok(ui.frame.src.includes('BuilderPreview'));
	await save.listeners.click();
	assert.equal(requests.length, 1);
	const request = requests[0];
	assert.equal(request.method, 'POST');
	assert.equal(request.credentials, 'same-origin');
	assert.equal(request.body.get('R[BuilderToken]'), 'test');
	const payload = JSON.parse(request.body.get('R[BuilderRegionsJson]'));
	assert.equal(payload.length, 2);
	assert.equal(payload[0].token, 'signed-0');
	assert.equal(JSON.parse(payload[0].target).language, 'DE');
	assert.deepEqual(payload[1].nodes, []);
	assert.equal(payload[0].nodes[0].options.columns, '6+6');
	assert.equal(payload[0].nodes[0].general.Active, false);
	assert.deepEqual(payload[0].nodes[0].children[0].source, ['NAVIGATION', 'D', 'main']);
	assert.equal(payload[0].nodes[0].children[0].data, undefined);
	assert.doesNotMatch(request.body.get('R[BuilderRegionsJson]'), /SecretResolved|innerHTML|builder-node-tools|builder-insert/);
	finish({ ok: true, json: async () => ({ saved: true, regions: 2, revisions: ['a'.repeat(64), 'b'.repeat(64)] }) });
	await saving;
	assert.equal(save.disabled, true);
	assert.equal(ui.elements['builder-cancel'].disabled, false);
	assert.match(ui.elements['builder-preview-status'].textContent, /Entwurf gespeichert/);
	ui.elements['builder-prev'].listeners.click();
	assert.equal(save.disabled, false);
	ui.elements['builder-next'].listeners.click();
	assert.equal(save.disabled, true);
});

test('failed saves remain editable and retryable; missing target metadata never enables Save', async () => {
	const ui = editor(async () => ({ ok: false, json: async () => ({ error: 'Session expired' }) }));
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src, 'frontend', 'DE', 1, true);
	ui.containerButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	regions[0].querySelector('.builder-insert').listeners.drop({
		dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {},
	});
	await ui.elements['builder-save'].listeners.click();
	assert.match(ui.elements['builder-preview-status'].textContent, /Speichern fehlgeschlagen: Session expired/);
	assert.equal(ui.elements['builder-save'].disabled, false);
	assert.equal(ui.elements['builder-cancel'].disabled, false);
	assert.equal(regions[0].querySelectorAll('[data-builder-node]').length, 1);
	ui.elements['builder-view'].listeners.click();
	assert.equal(ui.elements['builder-save'].disabled, false);
	ui.elements['builder-cancel'].listeners.click();
	assert.equal(ui.elements['builder-save'].disabled, true);
});

test('managed navigation selection applies immediately without closing, OK closes and X discards pending fields', async () => {
	const manifest = JSON.parse(fs.readFileSync(path.join(__dirname, '../../frontend/system/template/components/navigation/component.json'), 'utf8'));
	manifest.settings.menu.choices.push({ value: 'main', label: 'Main menu' }, { value: 'footer', label: 'Footer menu' });
	const requests = [];
	const ui = editor(async (url, request) => {
		requests.push(JSON.parse(request.body.get('R[BuilderComponent][OptionsJson]')));
		return { ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>' }) };
	}, { Name: manifest.name, Defaults: manifest.defaults, Settings: manifest.settings });
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src);
	ui.componentButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	regions[0].querySelector('.builder-insert').listeners.drop({
		dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {},
	});
	await tick();
	const node = regions[0].querySelector('[data-builder-node]');
	const open = () => node.children[0].children[2].listeners.click();
	open();
	assert.equal(ui.elements['builder-component-tab'].className, 'nav-link active');
	assert.equal(ui.elements['builder-options-fields'].querySelector('fieldset'), null);
	assert.deepEqual(Object.keys(manifest.settings), ['variant', 'menu']);
	assert.equal(ui.elements['builder-options-fields'].querySelector('input'), null);
	const menu = ui.elements['builder-options-fields'].querySelectorAll('select')[1];
	assert.equal(menu.children[1].textContent, 'Main menu');
	menu.value = '1';
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-apply'] });
	assert.equal(ui.elements['builder-options-ok'].disabled, true);
	ui.elements['builder-dialog-close'].listeners.click();
	assert.equal(ui.elements['builder-dialog'].open, true);
	await tick();
	assert.equal(ui.elements['builder-dialog'].open, true);
	assert.equal(JSON.parse(node.dataset.builderConfig).options.menu, 'main');
	assert.equal(ui.elements['builder-save'].disabled, true);
	menu.value = '2';
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-ok'] });
	await tick();
	assert.equal(ui.elements['builder-dialog'].open, false);
	assert.equal(JSON.parse(node.dataset.builderConfig).options.menu, 'footer');
	assert.equal(requests.length, 3);
	open();
	ui.elements['builder-options-fields'].querySelectorAll('select')[1].value = '1';
	ui.elements['builder-dialog-close'].listeners.click();
	assert.equal(ui.elements['builder-dialog'].open, false);
	assert.equal(JSON.parse(node.dataset.builderConfig).options.menu, 'footer');
	ui.elements['builder-prev'].listeners.click();
	assert.equal(JSON.parse(regions[0].querySelector('[data-builder-node]').dataset.builderConfig).options.menu, 'main');
});

test('translated settings keep common options separate, persist activation and hide only in clean preview', async () => {
	const requests = [];
	const ui = editor(async (url, request) => {
		if (request.body.get('R[ACTION]') === 'save') {
			requests.push(JSON.parse(request.body.get('R[BuilderRegionsJson]')));
			return { ok: true, json: async () => ({ saved: true, revisions: ['a'.repeat(64)] }) };
		}
		const translations = JSON.parse(request.body.get('R[BuilderComponent][TranslationsJson]'));
		assert.equal(request.body.get('R[BuilderComponent][Language]'), 'DE');
		return { ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>', visible: translations.DE?.active === true }) };
	}, {
		Name: 'Translated text', Defaults: { text: 'Default', variant: 'plain' },
		Settings: {
			text: { type: 'string', label: 'Text', translatable: true },
			variant: { type: 'string', label: 'Variant' },
		},
	});
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src, 'frontend', 'DE', 1, true);
	ui.componentButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	regions[0].querySelector('.builder-insert').listeners.drop({
		dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {},
	});
	await tick();
	let node = regions[0].querySelector('[data-builder-node]');
	const config = JSON.parse(node.dataset.builderConfig);
	assert.deepEqual(config.options, { variant: 'plain' });
	assert.deepEqual(config.translations, { DE: { active: true, options: { text: 'Default' } } });
	config.translations.EN = { active: true, options: { text: 'English' } };
	node.dataset.builderConfig = JSON.stringify(config);
	node.children[0].children[2].listeners.click();
	let inputs = ui.elements['builder-options-fields'].querySelectorAll('input');
	assert.equal(inputs[0].checked, true);
	assert.equal(inputs[1].checked, true);
	inputs[1].checked = false;
	inputs[1].listeners.change();
	assert.equal(inputs[2].disabled, true);
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-ok'] });
	await tick();
	let updated = JSON.parse(node.dataset.builderConfig);
	assert.deepEqual(updated.translations.DE, { active: true });
	assert.deepEqual(updated.translations.EN, config.translations.EN);
	assert.equal(node.querySelector('.builder-element').hidden, false);
	node.children[0].children[2].listeners.click();
	inputs = ui.elements['builder-options-fields'].querySelectorAll('input');
	assert.equal(inputs[2].value, 'English');
	inputs[0].checked = false;
	inputs[0].listeners.change();
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-ok'] });
	await tick();
	assert.equal(node.querySelector('.builder-element').hidden, true);
	await ui.elements['builder-save'].listeners.click();
	assert.deepEqual(requests[0][0].nodes[0].translations.DE, { active: false });
	assert.deepEqual(requests[0][0].nodes[0].options, { variant: 'plain' });
	ui.elements['builder-prev'].listeners.click();
	node = regions[0].querySelector('[data-builder-node]');
	assert.equal(node.querySelector('.builder-element').hidden, false);
	assert.equal(JSON.parse(node.dataset.builderConfig).translations.DE.active, true);
	assert.equal(ui.errors.length, 0);
});

test('Core Text binds its real WYSIWYG TPL and edits DE independently of the preview language', async () => {
	const manifest = JSON.parse(fs.readFileSync(path.join(__dirname, '../system/template/components/text/component.json'), 'utf8'));
	for (const language of ['DE', 'EN']) {
		const saves = [];
		const ui = editor(async (url, request) => {
			if (request.body.get('R[ACTION]') === 'save') {
				saves.push(JSON.parse(request.body.get('R[BuilderRegionsJson]')));
				return { ok: true, json: async () => ({ saved: true, revisions: ['c'.repeat(64)] }) };
			}
			assert.equal(request.body.get('R[BuilderComponent][Language]'), language);
			return { ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>', visible: language === 'DE' }) };
		}, { Name: manifest.name, Defaults: manifest.defaults, Settings: manifest.settings, EditorLanguage: manifest.editorLanguage });
		ui.load('https://example.test/site/');
		ui.elements['builder-edit'].listeners.click();
		const { regions } = ui.load(ui.frame.src, 'frontend', language, 1, true);
		ui.componentButton.listeners.dragstart({ dataTransfer: { setData() {} } });
		regions[0].querySelector('.builder-insert').listeners.drop({
			dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {},
		});
		await tick();
		let node = regions[0].querySelector('[data-builder-node]');
		assert.deepEqual(JSON.parse(node.dataset.builderConfig).translations, { DE: { active: true, options: { text: '' } } });
		node.children[0].children[2].listeners.click();
		assert.equal(ui.elements['builder-dialog-title'].textContent, 'Text');
		const textarea = ui.elements['builder-options-fields'].querySelector('textarea');
		assert.ok(textarea);
		assert.equal(textarea.rows, 5);
		assert.equal(textarea.disabled, false);
		const wysiwyg = ui.elements['builder-options-fields'].querySelector('iframe');
		assert.ok(wysiwyg);
		for (let ancestor = wysiwyg.parentElement; ancestor; ancestor = ancestor.parentElement) {
			assert.notEqual(ancestor.tagName, 'LABEL', 'An editor-wide label would activate the first toolbar button.');
		}
		assert.equal(wysiwyg.getAttribute('sandbox'), 'allow-same-origin');
		wysiwyg.listeners.load();
		assert.equal(wysiwyg.contentDocument.body.style.backgroundColor, 'rgb(33, 37, 41)');
		assert.equal(wysiwyg.contentDocument.body.style.color, 'rgb(222, 226, 230)');
		assert.equal(wysiwyg.contentDocument.body.style.colorScheme, 'dark');
		textarea.value = '<p>Deutscher <strong>Text</strong></p>';
		wysiwyg.contentDocument.body.innerHTML = textarea.value;
		ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-apply'] });
		await tick();
		assert.equal(ui.elements['builder-dialog'].open, true);
		assert.equal(saves.length, 0);
		assert.deepEqual(JSON.parse(node.dataset.builderConfig).translations, { DE: { active: true, options: { text: textarea.value } } });
		assert.equal(node.querySelector('.builder-element').hidden, language !== 'DE');
		const activation = ui.elements['builder-options-fields'].querySelectorAll('input')[0];
		activation.checked = false;
		activation.listeners.change();
		assert.equal(wysiwyg.contentDocument.body.contentEditable, 'false');
		assert.equal(textarea.disabled, true);
		activation.checked = true;
		activation.listeners.change();
		assert.equal(wysiwyg.contentDocument.body.contentEditable, 'true');
		ui.elements['builder-options-cancel'].listeners.click();
		ui.elements['builder-prev'].listeners.click();
		node = regions[0].querySelector('[data-builder-node]');
		assert.equal(JSON.parse(node.dataset.builderConfig).translations.DE.options.text, '');
		ui.elements['builder-next'].listeners.click();
		await ui.elements['builder-save'].listeners.click();
		assert.deepEqual(saves[0][0].nodes[0].options, {});
		assert.equal(saves[0][0].nodes[0].translations.DE.options.text, '<p>Deutscher <strong>Text</strong></p>');
		assert.equal(ui.errors.length, 0);
	}
});

test('missing form templates report errors without opening an unusable dialog', () => {
	const ui = editor();
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src);
	delete ui.elements['builder-component-form-fremeo/core:container'];
	ui.containerButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	regions[0].querySelector('.builder-insert').listeners.drop({
		dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {},
	});

	regions[0].querySelector('[data-builder-node]').children[0].children[2].listeners.click();
	assert.match(ui.elements['builder-preview-status'].textContent, /Einstellungen konnten nicht geladen werden: Grid-Einstellungs-TPL fehlt/);
	assert.ok(!ui.elements['builder-dialog'].open);
	assert.equal(ui.errors.length, 1);
});

test('scope controls create independent overrides, fall back on deactivation and save only existing levels', async () => {
		for (const entity of [null, '0', 'kontakt']) {
			const requests = [];
			const ui = editor(async (url, request) => {
				const entries = JSON.parse(request.body.get('R[BuilderRegionsJson]'));
				requests.push({ action: request.body.get('R[ACTION]'), entries });
				return { ok: true, json: async () => request.body.get('R[ACTION]') === 'publish'
					? { published: true } : { saved: true, revisions: entries.map(() => 'a'.repeat(64)) } };
			});
			ui.load('https://example.test/site/');
			ui.elements['builder-edit'].listeners.click();
			const scopes = entity === null ? ['page', 'global'] : ['individual', 'page', 'global'];
			const { regions } = ui.load(ui.frame.src, 'frontend', 'DE', scopes.length, true, (regions) => {
				regions.forEach((region, index) => {
					const scope = scopes[index];
					region.id = 'scope-' + scope;
					Object.assign(region.dataset, {
						builderRegion: 'content', builderScope: scope, builderExists: '0', builderActive: '0',
						builderTarget: JSON.stringify({ module: 'test/provider', page: 'frontend__page', region: 'content', language: 'DE',
							scope, branch: 'frontend', ...(entity !== null ? { contextEntity: entity } : {}), ...(scope === 'individual' ? { entity } : {}) }),
					});
				});
			});
			const rows = () => ui.elements['builder-regions'].querySelector('[data-scope-levels]').children;
			assert.equal(rows().length, scopes.length);
			assert.ok(rows().every((row) => row.querySelector('[data-scope-edit]') && row.querySelector('[data-scope-delete]')));
			assert.ok(rows().every((row) => row.querySelector('[data-scope-delete]').disabled));
			assert.equal(rows()[scopes.indexOf('page')].querySelector('span').textContent, 'Seitentyp: frontend__page');
			assert.equal(rows().at(-1).querySelector('span').textContent, 'Allgemein');
			if (entity !== null) assert.equal(rows()[0].querySelector('span').textContent, 'Diese Seite: ' + entity);
			assert.equal(ui.elements['builder-save'].disabled, true);
			const global = regions.at(-1);
			rows().at(-1).querySelector('button').listeners.click();
			assert.equal(ui.errors.length, 0, JSON.stringify(ui.errors));
			assert.equal(global.hidden, false);
			ui.containerButton.listeners.dragstart({ dataTransfer: { setData() {} } });
			global.querySelector('.builder-insert').listeners.drop({
				dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {},
			});
			assert.equal(global.querySelectorAll('[data-builder-node]').length, 1);
			rows()[0].querySelector('button').listeners.click();
			assert.equal(regions[0].querySelectorAll('[data-builder-node]').length, 1);
			assert.equal(global.querySelectorAll('[data-builder-node]').length, 1);
			assert.notEqual(regions[0].querySelector('[data-builder-node]').dataset.builderNode, global.querySelector('[data-builder-node]').dataset.builderNode);
			assert.equal(regions[0].hidden, false);
			assert.equal(global.hidden, true);
			const active = rows()[0].querySelector('input');
			active.checked = false;
			active.listeners.change();
			assert.equal(global.hidden, false);
			assert.equal(regions[0].hidden, true);
			assert.equal(global.dataset.builderLocked, '1', 'Inherited content requires explicit editing.');
			rows().at(-1).querySelector('button').listeners.click();
			assert.equal(global.dataset.builderLocked, '0');
			assert.equal(regions[0].dataset.builderActive, '0', 'Editing the shared level must not change individual activation.');
			rows()[0].querySelector('button').listeners.click();
			assert.equal(regions[0].hidden, false, 'Editing an inactive level must not activate it.');
			assert.equal(regions[0].dataset.builderActive, '0');
			ui.elements['builder-view'].listeners.click();
			assert.equal(global.hidden, false, 'Clean preview must use the active inherited level.');
			assert.equal(regions[0].hidden, true);
			ui.elements['builder-view'].listeners.click();
			assert.equal(regions[0].hidden, false);
			ui.elements['builder-prev'].listeners.click();
			assert.equal(regions[0].dataset.builderActive, '1');
			assert.equal(regions[0].hidden, false);
			ui.elements['builder-next'].listeners.click();
			assert.equal(regions[0].dataset.builderActive, '0');
			assert.equal(global.hidden, false);
			await ui.elements['builder-save'].listeners.click();
			assert.equal(requests[0].entries.length, 2);
			assert.equal(requests[0].entries[0].active, false);
			assert.equal(requests[0].entries[1].target, global.dataset.builderTarget);
			assert.equal(ui.elements['builder-publish'].disabled, false);
			await ui.elements['builder-publish'].listeners.click();
			assert.ok(requests[1].entries.every((entry) => entry.revision === 'a'.repeat(64)));
			const globalActive = rows().at(-1).querySelector('input');
			globalActive.checked = false;
			globalActive.listeners.change();
			assert.ok(regions.every((region) => region.hidden), 'No disabled content should appear without explicit editing.');
			rows().at(-1).querySelector('[data-scope-delete]').listeners.click();
			assert.equal(global.dataset.builderDeleted, '1');
			assert.equal(global.dataset.builderExists, '0');
			assert.equal(global.querySelectorAll('[data-builder-node]').length, 0);
			ui.elements['builder-prev'].listeners.click();
			assert.equal(global.dataset.builderExists, '1');
			assert.equal(global.dataset.builderDeleted, '0');
			assert.equal(global.querySelectorAll('[data-builder-node]').length, 1);
			ui.elements['builder-next'].listeners.click();
			assert.equal(global.dataset.builderDeleted, '1');
			await ui.elements['builder-save'].listeners.click();
			assert.equal(requests[2].entries.at(-1).deleted, true);
			assert.equal(requests[2].entries.at(-1).active, false);
			assert.deepEqual(JSON.parse(JSON.stringify(requests[2].entries.at(-1).nodes)), []);
			await ui.elements['builder-publish'].listeners.click();
			assert.equal(requests[3].action, 'publish');
			assert.equal(global.dataset.builderDeleted, '0', 'Successful publication clears the pending deletion.');
			assert.equal(ui.errors.length, 0);
		}
});

test('Core container applies 12-unit responsive widths without replacing nested content and rejects invalid grids', async () => {
	const ui = editor();
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src);
	const drop = (target) => target.listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	const transfer = { dataTransfer: { setData() {} } };
	ui.containerButton.listeners.dragstart(transfer);
	drop(regions[0].querySelector('.builder-insert'));
	let container = regions[0].querySelector('[data-builder-node]');
	const row = container.querySelector('[data-builder-children]');
	for (let index = 0; index < 2; index++) {
		ui.componentButton.listeners.dragstart(transfer);
		drop(row.children[index].querySelector('.builder-insert'));
		await tick();
	}
	const nodes = row.querySelectorAll('[data-builder-node]');
	container.children[0].children[2].listeners.click();
	const fields = Object.fromEntries(ui.elements['builder-options-fields'].querySelectorAll('input').map((input) => [input.dataset.gridOption, input]));
	fields.sm.value = '6+6';
	fields.md.value = '8+4';
	fields.lg.value = '9+3';
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-apply'] });
	await tick();
	const cells = row.children.filter((item) => item.classList.contains('col'));
	assert.match(cells[0].className, /col-12 col-sm-6 col-md-8 col-lg-9/);
	assert.match(cells[1].className, /col-12 col-sm-6 col-md-4 col-lg-3/);
	assert.equal(cells[0].querySelector('[data-builder-node]'), nodes[0]);
	assert.equal(cells[1].querySelector('[data-builder-node]'), nodes[1]);
	const previous = container.dataset.builderConfig;
	fields.md.value = Array(13).fill('1').join('+');
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-apply'] });
	await tick();
	assert.match(ui.elements['builder-options-error'].textContent, /maximal 12/);
	assert.equal(container.dataset.builderConfig, previous);
	ui.elements['builder-options-cancel'].listeners.click();
	ui.elements['builder-prev'].listeners.click();
	container = regions[0].querySelector('[data-builder-node]');
	assert.equal(JSON.parse(container.dataset.builderConfig).options.columns, '6+6');
	assert.equal(container.querySelectorAll('[data-builder-node]').length, 2);
	ui.elements['builder-next'].listeners.click();
	container = regions[0].querySelector('[data-builder-node]');
	assert.equal(JSON.parse(container.dataset.builderConfig).options.md, '8+4');
	assert.match(container.querySelector('.col').className, /col-md-8/);
});

test('Grid column selector updates four previews, creates empty drop slots and persists sparse positions and viewport activation', async () => {
	const saves = [];
	const ui = editor(async (url, request) => {
		if (request.body.get('R[ACTION]') === 'save') {
			saves.push(JSON.parse(request.body.get('R[BuilderRegionsJson]')));
			return { ok: true, json: async () => ({ saved: true, regions: 1, revisions: ['a'.repeat(64)] }) };
		}
		return { ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>' }) };
	});
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src, 'frontend', 'DE', 1, true);
	const transfer = { dataTransfer: { setData() {} } };
	const drop = (target) => target.listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	const apply = async () => {
		ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-apply'] });
		await tick();
	};
	ui.containerButton.listeners.dragstart(transfer);
	drop(regions[0].querySelector('.builder-insert'));
	let grid = regions[0].querySelector('[data-builder-node]');
	const row = grid.querySelector('[data-builder-children]');
	grid.children[0].children[2].listeners.click();
	const fields = ui.elements['builder-options-fields'];
	const select = fields.querySelector('select');
	assert.equal(select.children.length, 12);
	assert.equal(fields.querySelectorAll('input').length, 8);
	for (const [size, expected] of [['xs', '12+12'], ['sm', '12+12'], ['md', '6+6'], ['lg', '6+6']]) {
		assert.equal(fields.querySelector('[data-grid-field="' + size + '"]').querySelector('input').placeholder, expected);
	}
	for (let count = 1; count <= 12; count++) {
		select.value = String(count);
		select.listeners.change();
		for (const size of ['xs', 'sm', 'md', 'lg']) {
			const widths = fields.querySelector('[data-grid-field="' + size + '"]').querySelector('input').value.split('+').map(Number);
			assert.equal(widths.length, count);
			assert.equal(widths.reduce((sum, width) => sum + width, 0), 12);
		}
	}
	select.value = '3';
	select.listeners.change();
	const inputs = Object.fromEntries(fields.querySelectorAll('input').map((input) => [input.dataset.gridOption, input]));
	for (const size of ['lg', 'md', 'sm', 'xs']) assert.equal(inputs[size].value, '4+4+4');
	const previews = fields.querySelectorAll('[data-grid-preview]');
	assert.deepEqual(previews.map((preview) => preview.dataset.gridPreview), ['lg', 'md', 'sm', 'xs']);
	for (const [index, width] of ['100', '77.4', '58.1', '37.8'].entries()) {
		assert.ok(previews[index].getAttribute('style').includes('width:' + width + '%'));
	}
	assert.match(previews[3].getAttribute('style'), /aspect-ratio:9\/19.5/);
	for (const [size, label] of [['lg', 'LG (>992px)'], ['md', 'MD (>768px)'], ['sm', 'SM (>576px)']]) {
		assert.equal(fields.querySelector('[data-grid-field="' + size + '"]').querySelector('span').textContent, label);
	}
	assert.ok(previews.every((preview) => preview.children.length === 3));
	inputs.xs.value = '12+12+12';
	inputs.xs.listeners.input();
	assert.match(previews[3].children[0].style.cssText, /100%/);
	inputs.activeXs.checked = false;
	inputs.activeXs.listeners.change();
	assert.equal(previews[3].style.opacity, '0.35');
	await apply();
	assert.equal(ui.elements['builder-dialog'].open, true);
	assert.equal(saves.length, 0);
	assert.equal(row.children.filter((child) => child.classList.contains('col')).length, 3);
	assert.equal(row.querySelectorAll('.builder-insert').length, 3);
	assert.match(row.className, /d-none d-sm-flex d-md-flex d-lg-flex/);
	ui.elements['builder-options-cancel'].listeners.click();
	ui.componentButton.listeners.dragstart(transfer);
	drop(row.querySelectorAll('.builder-insert')[2]);
	await tick();
	const component = row.querySelector('[data-builder-node]');
	grid.children[0].children[2].listeners.click();
	const smaller = fields.querySelector('select');
	smaller.value = '1';
	smaller.listeners.change();
	await apply();
	assert.equal(JSON.parse(grid.dataset.builderConfig).options.columns, '12');
	assert.equal(row.querySelector('[data-builder-node]'), component);
	assert.equal(row.children.filter((child) => child.classList.contains('col')).length, 2);
	ui.elements['builder-options-cancel'].listeners.click();
	ui.elements['builder-prev'].listeners.click();
	grid = regions[0].querySelector('[data-builder-node]');
	assert.equal(JSON.parse(grid.dataset.builderConfig).options.columns, '4+4+4');
	await ui.elements['builder-save'].listeners.click();
	const saved = saves[0][0].nodes[0];
	assert.equal(saved.options.activeXs, false);
	assert.equal(saved.options.xs, '12+12+12');
	assert.equal(saved.children[0].slot, 2);
	assert.equal(saved.children[0].id, component.dataset.builderNode);
	assert.equal(ui.errors.length, 0);
});

test('Grid cells offer compact empty drop areas above and below every child and save stacked order', async () => {
	const saves = [];
	const ui = editor(async (url, request) => {
		if (request.body.get('R[ACTION]') === 'save') {
			saves.push(JSON.parse(request.body.get('R[BuilderRegionsJson]')));
			return { ok: true, json: async () => ({ saved: true, regions: 1, revisions: ['b'.repeat(64)] }) };
		}
		return { ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>' }) };
	});
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src, 'frontend', 'DE', 1, true);
	const transfer = { dataTransfer: { setData() {} } };
	const drop = (target) => target.listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	ui.containerButton.listeners.dragstart(transfer);
	drop(regions[0].querySelector('.builder-insert'));
	const grid = regions[0].querySelector('[data-builder-node]');
	const row = grid.querySelector('[data-builder-children]');
	const cell = row.children[0];
	const nodes = () => cell.children.filter((child) => child.hasAttribute('data-builder-node'));
	const insert = async (position) => {
		ui.componentButton.listeners.dragstart(transfer);
		drop(cell.querySelectorAll('.builder-insert')[position]);
		await tick();
	};
	await insert(0);
	const middle = nodes()[0];
	assert.equal(cell.querySelectorAll('.builder-insert').length, 2);
	await insert(1);
	const last = nodes()[1];
	await insert(0);
	const first = nodes()[0];
	assert.deepEqual(nodes(), [first, middle, last]);
	assert.equal(row.children.length, 2);
	assert.equal(cell.querySelectorAll('.builder-insert').length, 4);
	for (const area of regions[0].querySelectorAll('.builder-insert')) {
		assert.ok(!area.textContent);
		assert.equal(area['aria-label'], 'Element hier einfuegen');
		assert.equal(area.listeners.click, undefined);
	}
	await ui.elements['builder-save'].listeners.click();
	assert.deepEqual(saves[0][0].nodes[0].children.map((child) => [child.id, child.slot]), [first, middle, last].map((node) => [node.dataset.builderNode, 0]));
	ui.elements['builder-prev'].listeners.click();
	let restoredCell = regions[0].querySelector('[data-builder-children]').children[0];
	assert.equal(restoredCell.querySelectorAll('[data-builder-node]').length, 2);
	assert.equal(restoredCell.querySelectorAll('.builder-insert').length, 3);
	ui.elements['builder-next'].listeners.click();
	restoredCell = regions[0].querySelector('[data-builder-children]').children[0];
	assert.deepEqual(restoredCell.querySelectorAll('[data-builder-node]').map((node) => node.dataset.builderNode), [first, middle, last].map((node) => node.dataset.builderNode));
	const restoredFirst = restoredCell.querySelector('[data-builder-node]');
	restoredFirst.children[0].children[1].listeners.dragstart(transfer);
	drop(restoredCell.querySelectorAll('.builder-insert')[3]);
	assert.deepEqual(restoredCell.querySelectorAll('[data-builder-node]').map((node) => node.dataset.builderNode), [middle, last, first].map((node) => node.dataset.builderNode));
	restoredFirst.children[0].children[3].listeners.click();
	assert.equal(restoredCell.querySelectorAll('[data-builder-node]').length, 2);
	assert.equal(restoredCell.querySelectorAll('.builder-insert').length, 3);
	assert.equal(ui.errors.length, 0);
});

test('publication sends saved revisions only, blocks unsaved changes and cancellation, and permits retry', async () => {
	let finish;
	const requests = [];
	let fail = true;
	const ui = editor(async (url, request) => {
		const action = request.body.get('R[ACTION]');
		requests.push({ action, regions: JSON.parse(request.body.get('R[BuilderRegionsJson]')) });
		if (action === 'save') return { ok: true, json: async () => ({ saved: true, revisions: ['a'.repeat(64)] }) };
		if (fail) return { ok: false, json: async () => ({ error: 'Changed draft' }) };
		return new Promise((resolve) => { finish = resolve; });
	});
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src, 'frontend', 'DE', 1, true);
	assert.equal(ui.elements['builder-publish'].disabled, true);
	const toggle = ui.elements['builder-regions'].querySelector('input');
	toggle.checked = false;
	toggle.listeners.change();
	assert.equal(ui.elements['builder-publish'].disabled, true);
	await ui.elements['builder-save'].listeners.click();
	assert.equal(ui.elements['builder-publish'].disabled, false);
	await ui.elements['builder-publish'].listeners.click();
	assert.match(ui.elements['builder-preview-status'].textContent, /Changed draft/);
	assert.equal(ui.elements['builder-publish'].disabled, false);
	fail = false;
	const pending = ui.elements['builder-publish'].listeners.click();
	assert.equal(ui.elements['builder-cancel'].disabled, true);
	ui.elements['builder-cancel'].listeners.click();
	assert.equal(ui.elements['builder-edit'].hidden, true);
	assert.equal(ui.elements['builder-prev'].disabled, true);
	assert.deepEqual(Object.keys(requests[2].regions[0]), ['target', 'token', 'revision']);
	assert.equal(requests[2].regions[0].revision, 'a'.repeat(64));
	finish({ ok: true, json: async () => ({ published: true }) });
	await pending;
	assert.equal(ui.elements['builder-publish'].disabled, true);
	assert.match(ui.elements['builder-preview-status'].textContent, /Seite veroeffentlicht/);
	assert.equal(ui.elements['builder-cancel'].disabled, false);
	ui.elements['builder-prev'].listeners.click();
	assert.equal(ui.elements['builder-publish'].disabled, true);
	assert.equal(regions[0].dataset.builderActive, '1');
});

test('region activation belongs to saved snapshots and Undo/Redo without removing editable nodes', async () => {
	const saved = [];
	const ui = editor(async (url, request) => {
		saved.push(JSON.parse(request.body.get('R[BuilderRegionsJson]')));
		return { ok: true, json: async () => ({ saved: true, revisions: ['a'.repeat(64)] }) };
	});
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src, 'frontend', 'DE', 1, true);
	let toggle = ui.elements['builder-regions'].querySelector('input');
	toggle.checked = false;
	toggle.listeners.change();
	assert.equal(regions[0].dataset.builderActive, '0');
	assert.equal(ui.elements['builder-save'].disabled, false);
	ui.elements['builder-prev'].listeners.click();
	assert.equal(regions[0].dataset.builderActive, '1');
	assert.equal(ui.elements['builder-regions'].querySelector('input').checked, true);
	ui.elements['builder-next'].listeners.click();
	assert.equal(regions[0].dataset.builderActive, '0');
	assert.equal(ui.elements['builder-regions'].querySelector('input').checked, false);
	await ui.elements['builder-save'].listeners.click();
	assert.equal(saved[0][0].active, false);
	assert.equal(ui.elements['builder-save'].disabled, true);
	ui.elements['builder-view'].listeners.click();
	const clean = ui.frame.contentDocument.head.children[1];
	assert.match(clean.textContent, /\[data-builder-region\]\[data-builder-active="0"\]/);
	assert.equal(ui.errors.length, 0);
});

test('title bar dragging stays within viewport and ignores its close button', () => {
	const ui = editor();
	const header = ui.elements['builder-dialog-header'];
	const dialog = ui.elements['builder-dialog'];
	let prevented = false;
	header.listeners.pointerdown({ button: 0, target: { closest: () => null }, pointerId: 7, clientX: 120, clientY: 110, preventDefault() { prevented = true; } });
	assert.equal(prevented, true);
	assert.equal(header.pointerId, 7);
	header.listeners.pointermove({ clientX: 500, clientY: 300 });
	assert.equal(dialog.style.left, '480px');
	assert.equal(dialog.style.top, '290px');
	header.listeners.pointermove({ clientX: 2000, clientY: -10 });
	assert.equal(dialog.style.left, '600px');
	assert.equal(dialog.style.top, '0px');
	header.listeners.pointerup();
	header.listeners.pointermove({ clientX: 0, clientY: 0 });
	assert.equal(dialog.style.left, '600px');
	header.listeners.pointerdown({ button: 0, target: { closest: () => ({}) } });
	header.listeners.pointermove({ clientX: 10, clientY: 10 });
	assert.equal(dialog.style.left, '600px');
});

test('homepage and linked SEO page stay in browse mode until Edit', () => {
	const ui = editor();
	const { links } = ui.load('https://example.test/site/');
	assert.equal(ui.elements['builder-edit'].disabled, false);
	assert.equal(ui.elements['builder-library'].hidden, true);
	assert.equal(links[0].target, '_self');
	ui.load('https://example.test/site/gallery/album?album=42#photos', 'frontend', 'EN');
	ui.elements['builder-edit'].listeners.click();
	const target = new URL(ui.frame.src);
	assert.equal(target.pathname, '/site/gallery/album');
	assert.equal(target.searchParams.get('album'), '42');
	assert.equal(target.hash, '#photos');
	assert.equal(target.searchParams.get('R[BuilderPreview]'), '1');
	assert.equal(target.searchParams.get('R[BuilderLanguage]'), 'EN');
	assert.equal(ui.elements['builder-library'].hidden, false);
	assert.equal(ui.elements['builder-save'].disabled, true);
});

test('containers and components insert only at generated targets, move and remove without orphan cells', async () => {
	let renders = 0;
	const ui = editor(async (url, request) => {
		renders++;
		assert.equal(request.method, 'POST');
		assert.equal(request.body.get('R[BuilderToken]'), 'test');
		return { ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>' }) };
	});
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions, body } = ui.load(ui.frame.src);
	const dataTransfer = { setData() {} };
	ui.containerButton.listeners.dragstart({ dataTransfer });
	regions[0].children[0].listeners.drop({ dataTransfer: { getData: () => 'foreign' }, preventDefault() {}, stopPropagation() {} });
	assert.equal(regions[0].querySelector('[data-builder-node]'), null);
	regions[0].children[0].listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	const container = regions[0].querySelector('[data-builder-node]');
	const children = container.querySelector('[data-builder-children]');
	assert.equal(JSON.parse(container.dataset.builderConfig).options.columns, '6+6');
	ui.componentButton.listeners.dragstart({ dataTransfer });
	children.querySelector('.builder-insert').listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	await tick();
	assert.equal(renders, 1);
	assert.equal(children.querySelectorAll('[data-builder-kind]').length, 1);
	const component = children.querySelector('[data-builder-node]');
	const tools = component.children[0];
	tools.children[1].listeners.dragstart({ dataTransfer });
	regions[1].querySelector('.builder-insert').listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	assert.equal(component.parentElement, regions[1]);
	assert.equal(children.children.filter((item) => item.classList.contains('col')).length, 2);
	assert.equal(body.querySelectorAll('[data-builder-node]').length, 2);
	tools.children[3].listeners.click();
	assert.equal(body.querySelectorAll('[data-builder-node]').length, 1);
	ui.elements['builder-prev'].listeners.click();
	assert.equal(regions[1].querySelectorAll('[data-builder-node]').length, 1);
	ui.elements['builder-prev'].listeners.click();
	assert.equal(regions[1].querySelectorAll('[data-builder-node]').length, 0);
	assert.equal(regions[0].querySelector('[data-builder-children]').querySelectorAll('[data-builder-node]').length, 1);
	ui.elements['builder-next'].listeners.click();
	assert.equal(regions[1].querySelectorAll('[data-builder-node]').length, 1);
	assert.equal(ui.elements['builder-save'].disabled, true);
});

test('cycles are rejected, repeat insertions create unique instances, Cancel ignores late render responses', async () => {
	let complete;
	const ui = editor(() => new Promise((resolve) => { complete = resolve; }));
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions, body } = ui.load(ui.frame.src);
	const drop = (target) => target.listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	const dataTransfer = { setData() {} };
	ui.containerButton.listeners.dragstart({ dataTransfer });
	drop(regions[0].querySelector('.builder-insert'));
	const container = regions[0].querySelector('[data-builder-node]');
	container.children[0].children[1].listeners.dragstart({ dataTransfer });
	drop(container.querySelector('.builder-insert'));
	assert.match(ui.elements['builder-preview-status'].textContent, /nicht in sich selbst/);
	assert.equal(container.parentElement, regions[0]);
	ui.containerButton.listeners.dragstart({ dataTransfer });
	drop(regions[1].querySelector('.builder-insert'));
	assert.equal(new Set(body.querySelectorAll('[data-builder-node]').map((node) => node.dataset.builderNode)).size, 2);
	ui.componentButton.listeners.dragstart({ dataTransfer });
	drop(regions[0].querySelector('.builder-insert'));
	ui.elements['builder-cancel'].listeners.click();
	complete({ ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>' }) });
	await tick();
	assert.equal(body.querySelectorAll('[data-builder-node]').length, 2);
	assert.equal(ui.elements['builder-library'].hidden, true);
});

test('render failures are visible and do not insert half-finished nodes', async () => {
	const ui = editor(async () => ({ ok: false, json: async () => ({ error: 'Invalid component' }) }));
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions, body } = ui.load(ui.frame.src);
	ui.componentButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	regions[0].querySelector('.builder-insert').listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	await tick();
	assert.equal(body.querySelectorAll('[data-builder-node]').length, 0);
	assert.match(ui.elements['builder-preview-status'].textContent, /Invalid component/);
	assert.equal(ui.elements['builder-preview-status'].hidden, false);
});

test('drop areas do not respond to clicks or reuse a previous selection; Undo and Redo restore changes', () => {
	const ui = editor();
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src);
	const drop = () => regions[0].querySelector('.builder-insert').listeners.drop({
		dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {},
	});
	assert.equal(regions[0].querySelector('.builder-insert').listeners.click, undefined);
	assert.equal(ui.containerButton.listeners.click, undefined);
	ui.containerButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	drop();
	drop();
	assert.equal(regions[0].querySelectorAll('[data-builder-node]').length, 1);
	assert.equal(ui.elements['builder-prev'].disabled, false);
	ui.elements['builder-prev'].listeners.click();
	assert.equal(regions[0].querySelectorAll('[data-builder-node]').length, 0);
	assert.equal(ui.elements['builder-prev'].disabled, true);
	assert.equal(ui.elements['builder-next'].disabled, false);
	ui.elements['builder-next'].listeners.click();
	assert.equal(regions[0].querySelectorAll('[data-builder-node]').length, 1);
	ui.elements['builder-prev'].listeners.click();
	ui.containerButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	drop();
	assert.equal(ui.elements['builder-next'].disabled, true);
});

test('settings use manifest fields, preserve data, rerender temporarily and participate in history', async () => {
	const requests = [];
	const ui = editor(async (url, request) => {
		requests.push(JSON.parse(request.body.get('R[BuilderComponent][OptionsJson]')));
		return { ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>' }) };
	});

	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src);
	ui.componentButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	regions[0].querySelector('.builder-insert').listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	await tick();
	let component = regions[0].querySelector('[data-builder-node]');
	component.children[0].children[2].listeners.click();
	assert.equal(ui.elements['builder-dialog'].open, true);
	assert.equal(ui.elements['builder-prev'].disabled, true);
	const input = ui.elements['builder-options-fields'].querySelector('input');
	assert.equal(input.value, 'Default');
	input.value = 'Changed';
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-ok'] });
	await tick();
	assert.equal(ui.elements['builder-dialog'].open, false);
	assert.deepEqual(requests, [{ text: 'Default' }, { text: 'Changed' }]);
	assert.equal(JSON.parse(component.dataset.builderConfig).options.text, 'Changed');
	ui.elements['builder-prev'].listeners.click();
	component = regions[0].querySelector('[data-builder-node]');
	assert.equal(JSON.parse(component.dataset.builderConfig).options.text, 'Default');
	ui.elements['builder-next'].listeners.click();
	component = regions[0].querySelector('[data-builder-node]');
	assert.equal(JSON.parse(component.dataset.builderConfig).options.text, 'Changed');
	component.children[0].children[2].listeners.click();
	ui.elements['builder-options-fields'].querySelector('input').value = 'Discarded';
	ui.elements['builder-options-cancel'].listeners.click();
	assert.equal(JSON.parse(component.dataset.builderConfig).options.text, 'Changed');
	assert.equal(ui.elements['builder-save'].disabled, true);
});

test('failed or cancelled settings do not alter the node or history', async () => {
	let complete;
	let initial = true;
	const ui = editor(async () => {
		if (initial) return { ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>' }) };
		return new Promise((resolve) => { complete = resolve; });
	});
	ui.load('https://example.test/site/');
	ui.elements['builder-edit'].listeners.click();
	const { regions } = ui.load(ui.frame.src);
	ui.componentButton.listeners.dragstart({ dataTransfer: { setData() {} } });
	regions[0].querySelector('.builder-insert').listeners.drop({ dataTransfer: { getData: () => 'builder' }, preventDefault() {}, stopPropagation() {} });
	await tick();
	initial = false;
	const component = regions[0].querySelector('[data-builder-node]');
	component.children[0].children[2].listeners.click();
	ui.elements['builder-options-fields'].querySelector('input').value = 'Invalid';
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-ok'] });
	complete({ ok: false, json: async () => ({ error: 'Rejected' }) });
	await tick();
	assert.equal(ui.elements['builder-dialog'].open, true);
	assert.equal(ui.elements['builder-options-error'].textContent, 'Rejected');
	assert.equal(JSON.parse(component.dataset.builderConfig).options.text, 'Default');
	ui.elements['builder-options-cancel'].listeners.click();
	assert.equal(ui.elements['builder-dialog'].open, false);
	assert.equal(JSON.parse(component.dataset.builderConfig).options.text, 'Default');
	component.children[0].children[2].listeners.click();
	ui.elements['builder-options-fields'].querySelector('input').value = 'Late';
	ui.elements['builder-options-form'].listeners.submit({ preventDefault() {}, submitter: ui.elements['builder-options-ok'] });
	ui.elements['builder-cancel'].listeners.click();
	complete({ ok: true, json: async () => ({ html: '<div data-component-module="test/provider"></div>' }) });
	await tick();
	assert.equal(JSON.parse(component.dataset.builderConfig).options.text, 'Default');
	assert.equal(regions[0].querySelectorAll('[data-builder-node]').length, 1);
});

test('Edit marks current page; Cancel returns to its unchanged visitor URL', () => {
	const ui = editor();
	const original = 'https://example.test/site/?R%5BPage%5D=frontend__album&id=9';
	ui.load(original);
	ui.elements['builder-edit'].listeners.click();
	const { handlers } = ui.load(ui.frame.src, 'frontend', 'DE', 2, true);
	assert.equal(ui.elements['builder-preview-status'].hidden, true);
	let prevented = 0;
	handlers.click({
		target: { closest: (selector) => selector === '.builder-insert, .builder-node-tools' ? null : ({}) },
		preventDefault: () => { prevented++; },
		stopImmediatePropagation() {},
	});
	handlers.submit({ preventDefault: () => { prevented++; }, stopImmediatePropagation() {} });
	assert.equal(prevented, 2);
	ui.elements['builder-cancel'].listeners.click();
	assert.equal(ui.frame.src, original);
	ui.load(original);
	assert.equal(ui.elements['builder-cancel'].hidden, true);
	assert.equal(ui.elements['builder-edit'].disabled, false);
	assert.equal(ui.elements['builder-viewport'].className, 'col-12');
});

test('non-frontend, foreign origin and missing language cannot enable Edit', () => {
	for (const args of [
		['https://example.test/site/login', 'admin'],
		['https://other.test/', 'frontend'],
		['https://example.test/site/', 'frontend', 'invalid'],
	]) {
		const ui = editor();
		ui.load(...args);
		assert.equal(ui.elements['builder-edit'].disabled, true);
		assert.match(ui.elements['builder-preview-status'].textContent, /nicht bearbeitbar/);
		assert.equal(ui.errors.length, 1);
	}
});

test('failed preview keeps Cancel available; empty regions are reported', () => {
	const ui = editor();
	const original = 'https://example.test/site/';
	ui.load(original);
	ui.elements['builder-edit'].listeners.click();
	const preview = ui.frame.src;
	ui.load('https://example.test/site/login', 'admin');
	assert.equal(ui.elements['builder-cancel'].hidden, false);
	assert.equal(ui.elements['builder-edit'].disabled, true);
	ui.load(preview, 'frontend', 'DE', 0);
	assert.match(ui.elements['builder-preview-status'].textContent, /Keine Builder-Stellen/);
	ui.elements['builder-cancel'].listeners.click();
	assert.equal(ui.frame.src, original);
});
