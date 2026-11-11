const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../system/template/include/input.tpl'), 'utf8');
const queue = source.slice(source.indexOf('function initImageUploadQueue('), source.indexOf("$(function() {", source.indexOf('function initImageUploadQueue(')));

test('the add tile explicitly activates the file input instead of the labelable progress bar', () => {
	assert.match(source, /<label for="img_file_\{\$h\}" class="img-add/);
	assert.match(source, /<input type="file" id="img_file_\{\$h\}" class="img-file/);
});

function setup(count, failure = null, cover = false, extraQueue = 0) {
	const requests = [], counters = [], listeners = {}, status = {}, bar = {}, plus = {}, errorText = {},
		badge = { classList: { add() {} } }, controls = [
		{ name: 'images[]', type: 'file', files: Array.from({ length: count }, (_, index) => ({ name: index + '.jpg', lastModified: 1600000000000 + index })), disabled: false },
		{ name: 'R[ACTION]', value: 'save', type: 'hidden', disabled: false },
		{ name: 'title', value: 'Album', type: 'text', disabled: false },
		{ type: 'submit', disabled: false },
	];
	if (cover) controls.splice(1, 0, { name: 'cover[]', type: 'file', files: [{ name: 'cover.jpg' }], disabled: false });
	if (extraQueue) controls.splice(1, 0, { name: 'extra[]', type: 'file', files: Array.from({ length: extraQueue }, (_, i) => ({ name: 'extra-' + i + '.jpg' })), disabled: false });
	const form = { dataset: {}, action: 'https://example.test/admin/gallery/edit/album1', elements: controls,
		querySelectorAll: (selector) => selector === '.img-input' ? fields : controls.filter((input) => ['images[]', 'extra[]'].includes(input.name)),
		addEventListener: (type, handler) => { listeners[type] = handler; } };
	class FormData extends Map {
		constructor(form) {
			super();
			if (form) for (const input of controls.filter((input) => !input.disabled && input.name)) this.set(input.name, input.type === 'file' ? input.files : input.value);
		}
		append(name, file) { this.set(name, file); }
	}
	const coverStatus = {}, coverBar = {}, extraStatus = {}, extraBar = {};
	const fields = controls.filter((input) => input.type === 'file').map((input) => ({
		dataset: { uploadQueue: input.name !== 'cover[]' ? '1' : undefined },
		querySelector: (selector) => ({
			'.img-file': input, '.img-progress': input.name === 'images[]' ? status : input.name === 'cover[]' ? coverStatus : extraStatus,
			'.img-progressbar': input.name === 'images[]' ? bar : input.name === 'cover[]' ? coverBar : extraBar,
			'.img-add > i': plus, '.img-addcount': badge,
		})[selector],
		nextElementSibling: { after() {} },
	}));
	let redirected = null, attempt = 0;
	const unload = new Set();
	const context = {
		document: { createElement: () => Object.assign({}, { setAttribute() {} }) },
		window: { addEventListener: (name, handler) => unload.add(handler), removeEventListener: (name, handler) => unload.delete(handler) },
		location: { href: form.action, assign: (url) => { redirected = url; } }, URL, FormData,
		console: { error() {} },
		fetch: async (url, request) => {
			attempt++;
			counters.push(status.textContent);
			requests.push(request.body);
			const fail = failure && failure(attempt);
			return { ok: !fail, redirected: false, status: fail ? 413 : 200,
				json: async () => ({ saved: !fail, received: request.body.has('images[]') || request.body.has('extra[]') ? 1 : 0, error: fail ? 'Zu gross' : undefined }) };
		},
	};
	vm.runInNewContext(queue, context);
	context.initImageUploadQueue({ closest: () => form, dataset: { uploadToken: 'token' },
		querySelector: (selector) => ({ '.img-progress': status, '.img-progressbar': bar, '.img-add > i': plus, '.img-addcount': badge })[selector],
		nextElementSibling: { after(node) { assert.equal(node, errorText); } } });
	return { requests, counters, controls, status, bar, plus, coverStatus, coverBar, extraStatus, extraBar,
		fields,
		get errorText() { return fields[0].imageUploadError; }, unload, redirected: () => redirected,
		submit: () => listeners.submit({ preventDefault() {}, stopImmediatePropagation() {} }) };
}

test('100 selected images are acknowledged individually after one metadata save', async () => {
	const ui = setup(100);
	await ui.submit();
	assert.equal(ui.requests.length, 101);
	assert.equal(ui.requests[0].has('images[]'), false);
	assert.equal(ui.requests[0].get('title'), 'Album');
	assert.equal(ui.requests.slice(1).every((body) => body.get('R[UploadOnly]') === '1' && !body.has('title')), true);
	assert.equal(ui.requests.every((body) => body.get('R[UploadToken]') === 'token'), true);
	assert.deepEqual(ui.requests.slice(1).map((body) => body.get('images[]').name), Array.from({ length: 100 }, (_, index) => index + '.jpg'));
	assert.deepEqual(JSON.parse(ui.requests[1].get('R[FileModified]')), { '0.jpg': 1600000000000 });
	assert.equal(ui.unload.size, 0);
	assert.deepEqual(ui.counters, ['0/100', ...Array.from({ length: 100 }, (_, index) => index + '/100')]);
	assert.equal(ui.status.textContent, '100/100');
	assert.equal(ui.bar.value, 100);
	assert.equal(ui.bar.hidden, false);
	assert.equal(ui.plus.hidden, true);
	assert.ok(ui.redirected());
});

test('multiple queued fields keep separate counters and retries never reset completed fields', async () => {
	const ui = setup(2, (attempt) => attempt === 5, true, 3);
	await ui.submit();
	assert.equal(ui.coverStatus.textContent, '1/1');
	assert.equal(ui.status.textContent, '2/2');
	assert.equal(ui.extraStatus.textContent, '1/3');
	assert.match(ui.fields.find((field) => field.querySelector('.img-file').name === 'extra[]').imageUploadError.textContent, /1 \/ 3/);
	await ui.submit();
	assert.equal(ui.extraStatus.textContent, '3/3');
	assert.equal(ui.extraBar.value, 100);
	assert.equal(ui.status.textContent, '2/2');
	assert.equal(ui.requests.filter((body) => body.has('cover[]')).length, 1);
	assert.equal(ui.requests.filter((body) => body.has('images[]')).length, 2);
});

test('an error stops the queue and retry resumes without resending acknowledged files or metadata', async () => {
	const ui = setup(5, (attempt) => attempt === 4);
	await ui.submit();
	assert.equal(ui.requests.length, 4);
	assert.equal(ui.status.textContent, '2/5');
	assert.equal(ui.bar.value, 40);
	assert.match(ui.errorText.textContent, /2.jpg.*Zu gross.*2 \/ 5/);
	assert.equal(ui.redirected(), null);
	assert.equal(ui.controls.at(-1).disabled, false);
	assert.equal(ui.unload.size, 1);
	await ui.submit();
	assert.equal(ui.requests.length, 7);
	assert.deepEqual(ui.requests.slice(1).map((body) => body.get('images[]').name), ['0.jpg', '1.jpg', '2.jpg', '2.jpg', '3.jpg', '4.jpg']);
	assert.ok(ui.redirected());
});

test('a failed initial form save does not send files and can be retried', async () => {
	const ui = setup(3, (attempt) => attempt === 1);
	await ui.submit();
	assert.equal(ui.requests.length, 1);
	assert.equal(ui.controls[0].disabled, false);
	assert.equal(ui.unload.size, 0);
	await ui.submit();
	assert.equal(ui.requests.length, 5);
	assert.equal(ui.requests[1].get('title'), 'Album');
	assert.ok(ui.redirected());
});

test('cover is saved with metadata and never queued as an album image', async () => {
	for (const count of [0, 2]) {
		const ui = setup(count, null, true);
		await ui.submit();
		assert.equal(ui.requests.length, count + 1);
		assert.equal(ui.requests[0].get('cover[]')[0].name, 'cover.jpg');
		assert.equal(ui.requests[0].has('images[]'), false);
		assert.ok(ui.requests.slice(1).every((body) => !body.has('cover[]')));
		assert.equal(ui.coverStatus.textContent, '1/1');
		assert.equal(ui.coverBar.value, 100);
		assert.equal(ui.status.hidden, count === 0);
		if (count) assert.equal(ui.status.textContent, '2/2');
		assert.ok(ui.redirected());
	}
});

test('file drop assigns files and emits the same bubbling change event as file selection', () => {
	const listeners = {}, files = [{ name: 'cover.jpg' }], input = { disabled: false, dispatchEvent(event) {
		assert.equal(event.type, 'change');
		assert.equal(event.bubbles, true);
	} };
	class DataTransfer {
		constructor() { this.files = []; this.items = { add: (file) => this.files.push(file) }; }
	}
	const dropSource = source.slice(source.indexOf('function initImageFileDrop('), source.indexOf('function initImageUploadQueue('));
	const context = { DataTransfer, Event: class { constructor(type, options) { this.type = type; Object.assign(this, options); } } };
	vm.runInNewContext(dropSource, context);
	const classes = new Set();
	const child = {};
	const zone = {
		classList: { toggle(name, active) { if (active) classes.add(name); else classes.delete(name); } },
		contains: (node) => node === child,
		addEventListener: (name, handler) => { listeners[name] = handler; },
	};
	context.initImageFileDrop({
		querySelectorAll(selector) { assert.equal(selector, '.img-add, .img-tile'); return [zone]; },
		querySelector: () => input,
	});
	let prevented = 0;
	const event = { preventDefault() { prevented++; }, dataTransfer: { types: ['Files'], files } };
	listeners.dragover(event);
	assert.ok(classes.has('bg-warning-subtle'));
	listeners.dragleave({ relatedTarget: child });
	assert.ok(classes.has('bg-warning-subtle'));
	listeners.dragleave({ relatedTarget: null });
	assert.equal(classes.size, 0);
	listeners.dragover(event);
	listeners.drop(event);
	assert.equal(classes.size, 0);
	assert.equal(prevented, 3);
	assert.equal(input.files[0], files[0]);
	input.disabled = true;
	listeners.dragover(event);
	assert.equal(event.dataTransfer.dropEffect, 'none');
	assert.equal(classes.size, 0);
	listeners.drop(event);
	assert.equal(input.files[0], files[0]);
});
