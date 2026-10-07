<?php
/** Load real WordPress, Gravity Forms, Spellbook and Code Chest in a disposable test site. */
$autoload = getenv( 'CODE_CHEST_TEST_AUTOLOAD' );
if ( ! $autoload || ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) || ! getenv( 'GF_TEST_PLUGIN_FILE' ) || ! getenv( 'SPELLBOOK_TEST_PLUGIN_FILE' ) ) {
	throw new RuntimeException( 'Configure the native integration environment described in tests/integration/README.md.' );
}
require $autoload;
require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';
tests_add_filter( 'muplugins_loaded', static function () {
	require getenv( 'GF_TEST_PLUGIN_FILE' );
	GFForms::include_feed_addon_framework();
	GFForms::register_services();
	GFFormsModel::drop_tables();
	gf_upgrade()->upgrade_schema();
	require getenv( 'SPELLBOOK_TEST_PLUGIN_FILE' );
	define( 'GWIZ_GF_CODE_CHEST_VERSION', 'integration-test' );
	require __DIR__ . '/../../class-gwiz-gf-code-chest.php';
	GFAddOn::register( 'GWiz_GF_Code_Chest' );
	require __DIR__ . '/../../includes/class-gwiz-gf-code-chest-spellbook.php';
	// The main plugin file's contract declaration: the native editor's page draft and its guidance.
	add_action( 'spellbook_assistant_contracts_register', static function () {
		Spellbook_Assistant_Contracts::declare( dirname( __DIR__, 2 ) . '/gf-code-chest.php' );
	} );
} );
require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';
