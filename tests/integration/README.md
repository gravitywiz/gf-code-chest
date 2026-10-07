# Native saved-code review checks

`SavedCodeReviewTest.php` uses the real WordPress test library, Gravity Forms feed
storage and Code Chest add-on. Point it at a disposable database: the WordPress
bootstrap installs its test schema and the integration resets Gravity Forms tables.

The test libraries are not Code Chest dependencies. Install them in a directory of
their own:

```sh
mkdir -p /path/to/code-chest-phpunit
composer --working-dir=/path/to/code-chest-phpunit require phpunit/phpunit:^9 wp-phpunit/wp-phpunit yoast/phpunit-polyfills
```

Then provide the environment through these variables:

- `CODE_CHEST_TEST_AUTOLOAD`: that directory's `vendor/autoload.php`.
- `WP_PHPUNIT__TESTS_CONFIG`: WordPress test config targeting the disposable DB.
- `WP_PHPUNIT__DIR`: WordPress test library; wp-phpunit's autoloader sets this.
- `GF_TEST_PLUGIN_FILE`: Gravity Forms' `gravityforms.php`.
- `SPELLBOOK_TEST_PLUGIN_FILE`: Spellbook's `spellbook.php`.

Run its PHPUnit from this product root:

```sh
/path/to/code-chest-phpunit/vendor/bin/phpunit --no-configuration --bootstrap tests/integration/bootstrap.php tests/integration/SavedCodeReviewTest.php
```

The five cases drive Spellbook's saved-review service as the review card does.
They cover reviewed saves, native encoding and unknown metadata;
stale/owner/permission rejection and refresh; first-feed legacy JavaScript preservation
with mounted editor precedence; and retained product permissions for cross-page
history. They also cover editing another saved form while the Code Chest editor
is open, with the mounted form restricted to its native draft tools.
