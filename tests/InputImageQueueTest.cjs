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

function setup(count, failure = null) {
	const requests = [], counters = [], listeners = {}, status = {}, bar = {}, plus = {}, errorText = {},
		badge = { classList: { add() {} } }, controls = [
		{ name: 'images[]', type: 'file', files: Array.from({ length: count }, (_, index) => ({ name: index + '.jpg', lastModified: 1600000000000 + index })), disabled: false },
		{ name: 'R[ACTION]', value: 'save', type: 'hidden', disabled: false },
		{ name: 'title', value: 'Album', type: 'text', disabled: false },
		{ type: 'submit', disabled: false },
	];
	const form = { dataset: {}, action: 'https://example.test/admin/gallery/edit/album1', elements: controls,
		querySelectorAll: () => [controls[0]], addEventListener: (type, handler) => { listeners[type] = handler; } };
	class FormData extends Map {
		constructor(form) {
			super();
			if (form) for (const input of controls.filter((input) => !input.disabled && input.name)) this.set(input.name, input.type === 'file' ? input.files : input.value);
		}
		append(name, file) { this.set(name, file); }
	}
	let redirected = null, attempt = 0;
	const unload = new Set();
	const context = {
		document: { createElement: () => Object.assign(errorText, { setAttribute() {} }) },
		window: { addEventListener: (name, handler) => unload.add(handler), removeEventListener: (name, handler) => unload.delete(handler) },
		location: { href: form.action, assign: (url) => { redirected = url; } }, URL, FormData,
		console: { error() {} },
		fetch: async (url, request) => {
			attempt++;
			counters.push(status.textContent);
			requests.push(request.body);
			const fail = failure && failure(attempt);
			return { ok: !fail, redirected: false, status: fail ? 413 : 200,
				json: async () => ({ saved: !fail, received: request.body.has('images[]') ? 1 : 0, error: fail ? 'Zu gross' : undefined }) };
		},
	};
	vm.runInNewContext(queue, context);
	context.initImageUploadQueue({ closest: () => form, dataset: { uploadToken: 'token' },
		querySelector: (selector) => ({ '.img-progress': status, '.img-progressbar': bar, '.img-add > i': plus, '.img-addcount': badge })[selector],
		nextElementSibling: { after(node) { assert.equal(node, errorText); } } });
	return { requests, counters, controls, status, bar, plus, errorText, unload, redirected: () => redirected,
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
