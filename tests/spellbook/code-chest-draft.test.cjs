// Code Chest's browser draft, from its own checkout, on Spellbook's draft engine and registry.
const assert = require('node:assert/strict');
const test = require('node:test');
const path = require('node:path');
const { loadTs, spellbook, plain, settingsProjection } = require('spellbook/tests/conformance/kit.cjs');
const { createAssistantRegistry, performExtensionAction } = loadTs(path.join(spellbook, 'js/src/editor-assistant/extensions.ts'));
const createCodeChestDraft = require(path.resolve(__dirname, '../../assets/js/spellbook-assistant.js'));

class Target {
	constructor() { this.events = new Map(); this.isConnected = true; }
	addEventListener(name, fn) { if (!this.events.has(name)) this.events.set(name, new Set()); this.events.get(name).add(fn); }
	dispatchEvent(event) { this.events.get(event.type)?.forEach((fn) => fn(event)); }
}

// The Code Chest settings page: two CodeMirror editors over their textareas, the scoping toggle and native lint.
function page() {
	const values = { js: new Target(), css: new Target(), scope: new Target() };
	const mirrors = {};
	values.js.value = 'console.log("original");'; values.css.value = '.old { color: red; }'; values.scope.checked = true;
	for (const language of ['js', 'css']) {
		const control = values[language]; let code = control.value; const events = new Set();
		const mirror = mirrors[language] = { getValue: () => code, lastLine: () => code.split('\n').length - 1, getLine: (index) => code.split('\n')[index], operation: (run) => run(), save: () => { control.value = code; }, setValue: (value) => { code = value; events.forEach((fn) => fn()); }, replaceRange: (value) => mirror.setValue(value), on: (_name, fn) => events.add(fn) };
		control.parentElement = { querySelector: () => ({ CodeMirror: mirror }) };
	}
	let pendingLint = null;
	const lintCode = (value) => value.includes('BROKEN') ? [{ severity: 'error', from: { line: 1 }, message: 'Expected a closing brace.' }] : value.includes('WARN') ? [{ severity: 'warning', from: { line: 0 }, message: 'Review this.' }] : [];
	const lint = { css: lintCode, javascript: (value) => pendingLint ? new Promise((resolve) => { pendingLint.finish = () => resolve(lintCode(value)); }) : lintCode(value) };
	const root = { CSSLint: { verify: () => ({ messages: [] }) }, JSHINT: () => {}, Event: class Event { constructor(type) { this.type = type; } }, wp: { CodeMirror: { lint } } };
	const document = { getElementById: (id) => ({ code_chest_js: values.js, code_chest_css: values.css, _gform_setting_code_chest_scope_css_to_form: values.scope }[id]) };
	// The form binding Code Chest's PHP attaches before its script on the form's Code Chest settings page.
	const registry = createAssistantRegistry({ editor: { creation: {} }, settings: settingsProjection() }, { location: { search: '?page=gf_edit_forms&view=settings&subview=gf-code-chest&id=7' }, spellbookNativeDrafts: { 'gf-code-chest': { formId: 7, identity: 'e'.repeat(64), page: { page: 'gf_edit_forms', view: 'settings', subview: 'gf-code-chest', id: '7' }, extras: {} } } });
	registry.registerBinding('gf-code-chest', () => createCodeChestDraft(document, root));
	const context = () => registry.getActive()?.context;
	// What Code Chest's propose tool sends as the card's Apply payload.
	const proposal = (patch) => { const data = plain(context().data); return { formId: 7, identity: data.identity, revision: data.revision, before: data.values, after: { ...data.values, ...patch } }; };
	const perform = (action, payload) => performExtensionAction(registry, registry.getActive().adapter, context().resourceKey, action, payload);
	return { values, mirrors, context, proposal, perform, holdLint: () => { pendingLint = {}; return pendingLint; } };
}

test('Code Chest drafts its form: Apply lints, writes both editors and scoping, and Undo and Redo restore the whole suggestion', async () => {
	const native = page();
	assert.equal(native.context().resourceKey, 'gf-code-chest:7'); assert.equal(native.context().title, 'Code Chest');
	const change = native.proposal({ css: '.new { WARN }', js: 'run();', scopeCssToForm: false });
	const applied = await native.perform('apply_proposal', change);
	assert.equal(applied.applied, true); assert.deepEqual(plain(applied.warnings), ['CSS: native lint reported 1 warning(s). Review the editor markers before saving.']);
	assert.equal(native.mirrors.css.getValue(), '.new { WARN }'); assert.equal(native.values.js.value, 'run();'); assert.equal(native.values.scope.checked, false);
	const undone = await native.perform('undo', { revision: applied.revision });
	assert.deepEqual(plain(native.context().data.values), change.before);
	const redone = await native.perform('redo', { revision: undone.revision });
	assert.equal(redone.redone, true); assert.deepEqual(plain(native.context().data.values), change.after);
});

test('a syntax error refuses the Apply with Code Chest\'s reason and writes nothing', async () => {
	const native = page(); const before = plain(native.context().data.values);
	await assert.rejects(native.perform('apply_proposal', native.proposal({ js: 'BROKEN' })), /^Error: JS line 2: Expected a closing brace\.$/);
	assert.deepEqual(plain(native.context().data.values), before);
});

test('an edit made while lint runs wins over the reviewed proposal', async () => {
	const native = page(); const lint = native.holdLint();
	const applying = native.perform('apply_proposal', native.proposal({ js: 'console.log("proposed");' }));
	native.mirrors.js.setValue('console.log("typed while linting");'); lint.finish();
	await assert.rejects(applying, /changed while checking this proposal/);
	assert.equal(native.mirrors.js.getValue(), 'console.log("typed while linting");');
});

test('manual edits invalidate the card\'s history step and earlier proposals', async () => {
	const native = page(); const stale = native.proposal({ css: '.stale {}' });
	const applied = await native.perform('apply_proposal', native.proposal({ css: '.applied {}' }));
	await assert.rejects(native.perform('apply_proposal', stale), /changed/);
	native.values.scope.checked = false; native.values.scope.dispatchEvent({ type: 'change' });
	await assert.rejects(native.perform('undo', { revision: applied.revision }), /changed/);
});

test('code beyond the draft\'s schema leaves the draft unavailable, and no proposal can apply to it', async () => {
	const native = page(); const stale = native.proposal({ css: '.new {}' });
	native.mirrors.js.setValue('é'.repeat(8193));
	assert.equal(native.context(), undefined);
	native.mirrors.js.setValue('console.log("original");');
	await assert.rejects(native.perform('apply_proposal', stale), /changed/, 'a proposal from before the oversized code is stale');
	assert.equal((await native.perform('apply_proposal', native.proposal({ css: '.new {}' }))).applied, true, 'the draft returns once its code fits again');
});
