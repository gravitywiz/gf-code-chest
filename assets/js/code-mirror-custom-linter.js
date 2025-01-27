/**
 * Adds a custom Linter to CodeMirror to warn users when using the gform_post_render hooks on AJAX enabled forms.
 * This is because doing so can result in the hook callbacks being registered multiple times.
 */

(function() {
    if (typeof window.editor_settings !== 'undefined') {
        const originalInitialize = wp.codeEditor.initialize;
    
        wp.codeEditor.initialize = function(textarea, settings) {
            const editor = originalInitialize(textarea, settings);
            const customLinter = createCustomLinter(wp.CodeMirror);

            editor.codemirror.setOption('lint', function(text, options) {
                const defaultLintAnnotations = wp.CodeMirror.lint.javascript(text, options);

                return defaultLintAnnotations.concat(customLinter(text));
            });

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
