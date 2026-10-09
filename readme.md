# Gravity Forms Code Chest

_by [Gravity Wiz](https://gravitywiz.com)_

Effortlessly add custom Javascript and CSS to your forms ✨.

## Usage

See our [blog post](https://gravitywiz.com/gravity-forms-code-chest/) for usage information and more details.

## Spellbook AI integration

Spellbook can inspect this form's CSS and JavaScript, prepare a code diff, and
apply approved changes to the native Code Chest draft. Open **Form Settings → Code Chest**, then open Spellbook. Review the
editors and use **Save Settings** to persist changes; Apply never saves or runs
code. Undo and Redo restore the whole suggestion while the draft still matches.

The optional companion requires both Gravity Forms form-editing and Code Chest
form-settings permissions. See the [integration guide](docs/spellbook-integration.md)
for limits, API ownership, and recovery. The isolated contract checks in `tests/spellbook/` run
through Spellbook's conformance kit: from this checkout, with Spellbook checked out beside it, run
`node ../spellbook/tests/conformance/run.cjs`.

## License

[GPLv2](https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt)
