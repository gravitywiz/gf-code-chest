/**
 * Code Chest's native editors as Spellbook's form draft binding. Code Chest reads, lints and writes the editors;
 * Spellbook's draft engine checks what they hold against the draft's descriptor and owns revisions, Apply/Undo/Redo
 * and the conversation.
 */
(function (root, factory) {
	'use strict';
	if (typeof module === 'object' && module.exports) { module.exports = factory; return; }
	// Enqueued after the spellbook-assistant-registry handle; the binding waits only for the native editor markup.
	function mount() {
		root.SpellbookAssistant.registerBinding('gf-code-chest', () => factory(root.document, root));
	}
	if (root.document.readyState === 'loading') root.jQuery(mount);
	else mount();
}(typeof window === 'undefined' ? globalThis : window, function (document, root) {
	'use strict';
	const js = document.getElementById('code_chest_js');
	const css = document.getElementById('code_chest_css');
	const scope = document.getElementById('_gform_setting_code_chest_scope_css_to_form');
	if (!js || !css || !scope) return null;
	const listeners = new Set();
	let mutating = false;
	const mirrors = {};
	const controls = { js: js, css: css };
	const record = (value) => value && typeof value === 'object' && !Array.isArray(value);
	const connected = () => js.isConnected && css.isConnected && scope.isConnected;
	const read = () => ({ js: mirrors.js ? mirrors.js.getValue() : js.value, css: mirrors.css ? mirrors.css.getValue() : css.value, scopeCssToForm: scope.checked });
	const changed = () => { if (!mutating) listeners.forEach((listener) => listener()); };
	Object.keys(controls).forEach((language) => {
		const textarea = controls[language];
		const wrapper = textarea.parentElement.querySelector('.CodeMirror');
		const mirror = wrapper && wrapper.CodeMirror;
		if (mirror && typeof mirror.getValue === 'function' && typeof mirror.replaceRange === 'function' && typeof mirror.save === 'function') {
			mirrors[language] = mirror;
			mirror.on('change', changed);
		}
		textarea.addEventListener('input', changed);
		textarea.addEventListener('change', changed);
	});
	scope.addEventListener('change', changed);

	/** Replace the document as one native edit; never run code or click Save. */
	function write(value) {
		const before = read();
		mutating = true;
		try {
			Object.keys(controls).forEach((language) => {
				const mirror = mirrors[language];
				if (mirror && mirror.getValue() !== value[language]) {
					mirror.operation(() => {
						const end = mirror.lastLine();
						mirror.replaceRange(value[language], { line: 0, ch: 0 }, { line: end, ch: mirror.getLine(end).length }, 'spellbook');
						mirror.save();
					});
				}
				controls[language].value = value[language];
			});
			scope.checked = value.scopeCssToForm;
			const after = read();
			if (after.js !== value.js || after.css !== value.css || after.scopeCssToForm !== value.scopeCssToForm || js.value !== value.js || css.value !== value.css) throw new Error('The native code editors could not accept this draft.');
			Object.values(controls).forEach((control) => control.dispatchEvent(new root.Event('input', { bubbles: true })));
			scope.dispatchEvent(new root.Event('change', { bubbles: true }));
		} catch (failure) {
			// A failed second control must not leave a partially applied proposal.
			Object.keys(controls).forEach((language) => { if (mirrors[language]) { mirrors[language].setValue(before[language]); mirrors[language].save(); } controls[language].value = before[language]; });
			scope.checked = before.scopeCssToForm;
			throw failure;
		} finally { mutating = false; changed(); }
	}

	const refuse = (message) => ({ status: 'refused_before_write', message: message.replace(/[\x00-\x1f\x7f]/g, ' ').replace(/</g, '‹').replace(/>/g, '›').slice(0, 400) });
	/** Native lint for each changed language: an error refuses the Apply; warnings, or missing lint, are reported. */
	async function check(after, before) {
		const lint = root.wp && root.wp.CodeMirror && root.wp.CodeMirror.lint;
		const warnings = [];
		for (const language of Object.keys(controls)) {
			if (before.values[language] === after[language]) continue;
			let issues;
			const mirror = mirrors[language];
			const nativeOptions = mirror && typeof mirror.getOption === 'function' ? mirror.getOption('lint') : null;
			const editorSettings = root.editor_settings && root.editor_settings[language + '_code_editor'];
			const fallbackOptions = editorSettings && editorSettings[language === 'js' ? 'jshint' : 'csslint'];
			const options = Object.assign({}, fallbackOptions || {}, record(nativeOptions) ? nativeOptions : {});
			const checker = typeof options.getAnnotations === 'function' ? options.getAnnotations : lint && lint[language === 'js' ? 'javascript' : 'css'];
			const backendAvailable = language === 'css' ? root.CSSLint && typeof root.CSSLint.verify === 'function' : typeof root.JSHINT === 'function' || typeof options.espreeModuleUrl === 'string';
			if (nativeOptions === false || typeof checker !== 'function' || !backendAvailable) {
				warnings.push('Native ' + language.toUpperCase() + ' lint is unavailable or disabled. Review and test the code before saving.');
				continue;
			}
			// WordPress 7 loads its JS parser asynchronously; Spellbook rereads the draft after this check resolves.
			try { issues = await checker(after[language], options); }
			catch (_error) { return refuse('Native code lint could not complete. Review the code in the editor.'); }
			if (!Array.isArray(issues)) return refuse('Native code lint returned an unsupported result. Review the code in the editor.');
			const errors = issues.filter((issue) => issue.severity === 'error');
			if (errors.length) return refuse(language.toUpperCase() + ' line ' + (errors[0].from.line + 1) + ': ' + errors[0].message);
			if (issues.length) warnings.push(language.toUpperCase() + ': native lint reported ' + issues.length + ' warning(s). Review the editor markers before saving.');
		}
		return { warnings: warnings };
	}

	return {
		// The editors' code and scoping; Spellbook checks them against the draft's schema and its byte budget.
		read: () => connected() ? read() : null,
		check: check,
		write: async (values) => { write(values); },
		subscribe: (listener) => { listeners.add(listener); return () => listeners.delete(listener); },
	};
}));
