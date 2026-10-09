/**
 * Adds a custom Linter to CodeMirror to warn users when using the gform_post_render hooks on AJAX enabled forms.
 * This is because doing so can result in the hook callbacks being registered multiple times.
 */

(function() {
    if (typeof window.editor_settings !== 'undefined') {
        const originalInitialize = wp.codeEditor.initialize;
    
        wp.codeEditor.initialize = function(textarea, settings) {
            const editor = originalInitialize(textarea, settings);
            const mode = editor.codemirror.getOption('mode');
            const modeName = typeof mode === 'string' ? mode : mode && mode.name;
            const lintOptions = editor.codemirror.getOption('lint');
            // Preserve CSS's own parser and the user's disabled-lint preference.
            if (!lintOptions || !['javascript', 'text/javascript', 'application/javascript'].includes(modeName)) {
                return editor;
            }
            const customLinter = createCustomLinter(wp.CodeMirror);
            const options = typeof lintOptions === 'object' ? Object.assign({}, lintOptions) : {};
            const defaultLinter = options.getAnnotations || wp.CodeMirror.lint.javascript;
            options.getAnnotations = function(text, options, codeMirror) {
                const annotations = defaultLinter(text, options, codeMirror);
                const appendWarnings = function(items) { return items.concat(customLinter(text)); };
                // WordPress 7 uses an asynchronous Espree parser; older versions return an array.
                return annotations && typeof annotations.then === 'function' ? annotations.then(appendWarnings) : appendWarnings(annotations);
            };
            // Retain WordPress's parser URL, error notices and native lint configuration.
            editor.codemirror.setOption('lint', options);

            return editor;
        };
    }

    function createCustomLinter(CodeMirror) {
        return function customLinter(text) {
            const warnings = [];
            const regex = new RegExp(/gform_post_render|gform\/postRender|gform\/post_render/, 'g');
            let match;
  
            while ((match = regex.exec(text)) !== null) {
                const matchedString = match[0];

                warnings.push({
                    from: CodeMirror.Pos(
                        text.substr(0, match.index)
                            .split('\n')
                            .length - 1,
                        match.index - text.lastIndexOf('\n', match.index - 1) - 1
                    ),
                    to: CodeMirror.Pos(
                        text.substr(0, regex.lastIndex)
                            .split('\n')
                            .length - 1,
                        regex.lastIndex - text.lastIndexOf('\n', regex.lastIndex - 1) - 1
                    ),
                    message: `'${matchedString}' should not be used on AJAX enabled forms. Doing so can result in the hook callback being registered multiple times.`,
                    severity: 'warning'
                });
            }

            return warnings;
        }

    }
})();
