const assert = require('node:assert/strict');
const test = require('node:test');
const vm = require('node:vm');
const fs = require('node:fs');

function initialize(mode, annotations, enabled = true) {
	const nativeOptions = enabled ? { espreeModuleUrl: '/espree.js', onUpdateLinting: () => {}, esversion: 'latest' } : false;
	let options = nativeOptions; let inputOptions;
	const editor = { codemirror: { getOption: (key) => key === 'mode' ? mode : options, setOption: (_key, value) => { options = value; } } };
	const wp = { codeEditor: { initialize: () => editor }, CodeMirror: { Pos: (line, ch) => ({ line, ch }), lint: { javascript: (_value, receivedOptions) => { inputOptions = receivedOptions; return annotations; } } } };
	vm.runInNewContext(fs.readFileSync(require.resolve('../assets/js/code-mirror-custom-linter.js'), 'utf8'), { window: { editor_settings: {} }, wp });
	wp.codeEditor.initialize(null, {});
	return { options, nativeOptions, getInputOptions: () => inputOptions };
}

test('native JS annotations may be arrays or promises and retain WP parser and error notice options', async () => {
	for (const async of [false, true]) {
		const original = [{ severity: 'error', message: 'Native syntax error' }];
		const { options, nativeOptions, getInputOptions } = initialize('javascript', async ? Promise.resolve(original) : original);
		const annotations = await options.getAnnotations('gform_post_render', options);
		assert.equal(annotations.length, 2); assert.equal(annotations[0].message, 'Native syntax error');
		assert.equal(annotations[1].severity, 'warning');
		assert.equal(options.onUpdateLinting, nativeOptions.onUpdateLinting);
		assert.equal(getInputOptions().espreeModuleUrl, '/espree.js');
	}
});

test('CSS and disabled lint are left with their native configuration', () => {
	for (const fixture of [initialize('css', []), initialize('javascript', [], false)]) assert.equal(fixture.options, fixture.nativeOptions);
});
