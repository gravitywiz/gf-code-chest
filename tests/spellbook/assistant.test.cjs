// Code Chest's side of its Spellbook form draft binding: reading, linting and writing the native editors.
// code-chest-draft.test.cjs drives this binding through the shared draft engine.
const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const vm = require('node:vm');
const createDraft = require('../../assets/js/spellbook-assistant.js');

const identity = 'e'.repeat(64);
class Target {
	constructor() { this.events = new Map(); this.isConnected = true; }
	addEventListener(name, fn) { if (!this.events.has(name)) this.events.set(name, new Set()); this.events.get(name).add(fn); }
	removeEventListener(name, fn) { this.events.get(name)?.delete(fn); }
	dispatchEvent(event) { this.events.get(event.type)?.forEach((fn) => fn(event)); }
}

function fixture({ codeMirror = true, lint = true, failCss = false } = {}) {
	const values = { js: new Target(), css: new Target(), scope: new Target() };
	const mirrors = {};
	values.js.value = 'console.log("original");'; values.css.value = '.old { color: red; }'; values.scope.checked = true;
	for (const language of ['js', 'css']) {
		const control = values[language];
		let code = control.value; const events = new Set();
		const mirror = { getValue: () => code, lastLine: () => code.split('\n').length - 1, getLine: (index) => code.split('\n')[index], operation: (run) => run(), save: () => { control.value = code; }, setValue: (value) => { code = value; events.forEach((fn) => fn()); }, replaceRange: (value) => { if (failCss && language === 'css') throw new Error('Simulated failed native control'); mirror.setValue(value); }, on: (_name, fn) => events.add(fn) };
		mirrors[language] = mirror;
		control.parentElement = { querySelector: () => codeMirror ? { CodeMirror: mirror } : null };
	}
	const document = { getElementById: (id) => ({ code_chest_js: values.js, code_chest_css: values.css, _gform_setting_code_chest_scope_css_to_form: values.scope }[id]) };
	const lintCode = (value) => value.includes('BROKEN') ? [{ severity: 'error', from: { line: 1 }, message: 'Expected <b>}</b>.' }] : value.includes('WARN') ? [{ severity: 'warning', from: { line: 0 }, message: 'Review this expression.' }] : [];
	const root = { CSSLint: { verify: () => ({ messages: [] }) }, JSHINT: () => {}, Event: class Event { constructor(type) { this.type = type; } }, wp: { CodeMirror: { lint: lint ? { javascript: lintCode, css: lintCode } : {} } } };
	const draft = createDraft(document, root);
	const current = () => ({ formId: 7, identity, values: draft.read() });
	const proposal = (patch = {}) => ({ ...current().values, css: '.new { color: blue; }', ...patch });
	return { draft, values, mirrors, current, proposal, document, root };
}

test('the binding reads the native editors and scoping while they are on the page, and has no title, budget or validation of its own', () => {
	const { draft, values } = fixture();
	assert.deepEqual(Object.keys(draft).sort(), ['check', 'read', 'subscribe', 'write']);
	assert.deepEqual(draft.read(), { js: 'console.log("original");', css: '.old { color: red; }', scopeCssToForm: true });
	values.css.isConnected = false; assert.equal(draft.read(), null);
	assert.equal(fixture({ codeMirror: false }).draft.read().css, '.old { color: red; }', 'plain native textareas work when CodeMirror is disabled');
	assert.equal(createDraft({ getElementById: () => null }, {}), null, 'without the editors there is nothing to bind');
});

test('oversized code is read as written, never truncated; Spellbook\'s schema decides it is unavailable', () => {
	const { draft, values, mirrors } = fixture();
	mirrors.js.setValue('é'.repeat(8193));
	assert.equal(draft.read().js.length, 8193); assert.equal(values.js.value, 'console.log("original");');
});

test('a write updates CodeMirror, the backing textarea and scoping as one native edit', async () => {
	const { draft, mirrors, values, proposal } = fixture(); let changes = 0;
	draft.subscribe(() => changes++);
	const after = proposal({ js: 'const quote = "< & > \\"";\nconsole.log(quote);', scopeCssToForm: false });
	assert.equal(await draft.write(after), undefined);
	assert.equal(changes, 1);
	assert.equal(mirrors.js.getValue(), after.js); assert.equal(values.js.value, after.js);
	assert.equal(mirrors.css.getValue(), after.css); assert.equal(values.css.value, after.css); assert.equal(values.scope.checked, false);
});

test('manual CodeMirror edits and checkbox changes notify the draft engine', () => {
	const { draft, mirrors, values } = fixture(); let changes = 0;
	const unsubscribe = draft.subscribe(() => changes++);
	mirrors.js.setValue('manual edit'); values.scope.checked = false; values.scope.dispatchEvent({ type: 'change' });
	assert.equal(changes, 2);
	unsubscribe(); mirrors.css.setValue('ignored'); assert.equal(changes, 2);
});

test('a partial native write rolls back both editors and scoping', async () => {
	const { draft, current, proposal } = fixture({ failCss: true }); const before = current().values;
	await assert.rejects(draft.write(proposal({ js: 'changed();', scopeCssToForm: false })), /Simulated/);
	assert.deepEqual(current().values, before);
});

test('lint refuses syntax errors with a plain reason, reports warnings and missing lint, and never evaluates code', async () => {
	const { draft, current, proposal } = fixture();
	assert.deepEqual(await draft.check(proposal({ js: 'BROKEN' }), current()), { status: 'refused_before_write', message: 'JS line 2: Expected ‹b›}‹/b›.' });
	assert.deepEqual(await draft.check(proposal({ css: '.x { WARN }' }), current()), { warnings: ['CSS: native lint reported 1 warning(s). Review the editor markers before saving.'] });
	assert.deepEqual(await draft.check(proposal({ css: '.old { color: red; }' }), current()), { warnings: [] }, 'unchanged languages are not linted');
	const native = fixture({ lint: false });
	const missing = await native.draft.check(native.proposal({ js: 'throw new Error("MUST NOT RUN");' }), native.current());
	assert.equal(missing.warnings.length, 2); assert.match(missing.warnings[0], /lint is unavailable or disabled/);
	const failing = fixture(); failing.root.wp.CodeMirror.lint.css = () => { throw new Error('PRIVATE_TRACE'); };
	assert.deepEqual(await failing.draft.check(failing.proposal(), failing.current()), { status: 'refused_before_write', message: 'Native code lint could not complete. Review the code in the editor.' });
});

test('async native lint keeps the editor parser options; a present helper without its parser is not called', async () => {
	const { draft, mirrors, root, current, proposal } = fixture();
	let receivedOptions;
	mirrors.js.getOption = () => ({ espreeModuleUrl: 'https://example.test/espree.js', esversion: 'latest' });
	root.wp.CodeMirror.lint.javascript = async (_code, options) => { receivedOptions = options; return []; };
	assert.deepEqual(await draft.check(proposal({ js: 'console.log("proposed");', css: '.old { color: red; }' }), current()), { warnings: [] });
	assert.equal(receivedOptions.espreeModuleUrl, 'https://example.test/espree.js');
	const missing = fixture(); delete missing.root.CSSLint; delete missing.root.JSHINT;
	missing.root.wp.CodeMirror.lint.css = missing.root.wp.CodeMirror.lint.javascript = () => { throw new Error('Must not call an unavailable parser'); };
	const response = await missing.draft.check(missing.proposal({ js: 'console.log("candidate");' }), missing.current());
	assert.equal(response.warnings.length, 2); assert.match(response.warnings[0], /unavailable or disabled/);
});

test('the page script, loaded after the assistant registry, registers one Spellbook binding for its draft', () => {
	const { document, root } = fixture(); let registered = null; let registrations = [];
	Object.assign(root, { document, SpellbookAssistant: { registerBinding: (id, create) => { registrations.push(id); registered = create({ formId: 7, identity, extras: {} }); return () => {}; } } });
	document.readyState = 'complete';
	vm.runInNewContext(fs.readFileSync(require.resolve('../../assets/js/spellbook-assistant.js'), 'utf8'), { window: root, TextEncoder });
	assert.deepEqual(registrations, ['gf-code-chest']); assert.equal(registered.read().css, '.old { color: red; }');
});
