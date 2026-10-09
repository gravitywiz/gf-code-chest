<?php
/** Code Chest's form draft on Spellbook's page-draft engine and its saved-code helpers, run through Spellbook's conformance kit. */
require SPELLBOOK_TESTS . '/php/wordpress-schema.php';
class GFCommon {
	public static $caps = array( 'gravityforms_edit_forms' => true, 'gf-code-chest_form_settings' => true );
	public static function current_user_can_any( $cap ) { return ! empty( self::$caps[ $cap ] ); }
}
class GFAPI {
	public static $form;
	public static function get_form( $id ) { return $id === 7 ? self::$form : false; }
}
class GWiz_GF_Code_Chest {
	public $feeds = array();
	public function get_feeds( $id ) { return $this->feeds; }
}
$addon = new GWiz_GF_Code_Chest();
function gwiz_gf_code_chest() { global $addon; return $addon; }
class Spellbook_Assistant_Extensions {
	public static $definitions = array();
	public static function register( $id, $definition ) {
		self::$definitions[ $id ] = $definition;
		return true;
	}
}
/** Gravity Forms' form settings access for the Code Chest add-on, as Spellbook's feed service checks it. */
class Spellbook_Assistant_Feeds {
	public static function authorize_resource( $form_id, $slug, $feed_id = null ) {
		return $slug === 'gf-code-chest' && $feed_id === null && GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) && GFCommon::current_user_can_any( 'gf-code-chest_form_settings' ) && GFAPI::get_form( $form_id ) ? array(
			'addon' => gwiz_gf_code_chest(),
			'form'  => GFAPI::get_form( $form_id ),
			'feed'  => null,
		) : new WP_Error( 'forbidden' );
	}
}
require dirname( __DIR__, 2 ) . '/includes/class-gwiz-gf-code-chest-spellbook.php';
check( spellbook_test_declare( 'gf-code-chest' ) === true, 'Code Chest declares its form draft.' );
$draft = spellbook_test_page_draft( 'gf-code-chest' );
GFAPI::$form = array( 'id' => 7, 'title' => 'Contact', 'custom_js' => 'legacy(&quot;text&quot;);', 'fields' => array( (object) array( 'id' => 1, 'label' => 'Name', 'adminLabel' => 'Full name', 'type' => 'text' ) ) );
$saved       = GWiz_GF_Code_Chest_Spellbook::saved( GFAPI::$form );
check( $saved['js'] === 'legacy("text");' && $saved['scopeCssToForm'] === true, 'legacy script decoded once for the native textarea, with native scoping by default' );
$addon->feeds = array( array( 'meta' => array( 'code_chest_js' => '', 'code_chest_css' => '.x { content: &quot;&lt; &amp; &gt;&quot;; }', 'code_chest_scope_css_to_form' => false ) ) );
$saved        = GWiz_GF_Code_Chest_Spellbook::saved( GFAPI::$form );
check( $saved['js'] === '' && $saved['css'] === '.x { content: "< & >"; }' && $saved['scopeCssToForm'] === false, 'explicit empty does not revive legacy script; saved CSS decoded and explicit false retained' );

// The mounted editor's form draft: its form, the saved code it opened on, and its unsaved values.
$values  = array(
	'css'            => '.field { color: red; }',
	'js'             => '',
	'scopeCssToForm' => true,
);
$context = function () use ( $draft, &$values ) {
	$config = $draft->configuration( array( 'formId' => 7 ) );
	return is_wp_error( $config ) ? $config : $config + array(
		'revision' => 'draft-1',
		'values'   => $values,
	);
};
$current = $context();
check( ! is_wp_error( $current ) && ! is_wp_error( $draft->prepare_context( $current ) ), 'The editor\'s unsaved code is a draft of its form.' );
$inspect = $draft->execute_tool( (object) array( 'name' => 'gf_code_chest_inspect_settings', 'arguments' => (object) array() ), $current );
check( $inspect['data']['sources'] === array( array( 'id' => '1', 'label' => 'Full name', 'type' => 'text' ) ), 'Field references prefer the native admin label.' );
$propose = function ( $patch, $from = null ) use ( $draft, &$current ) {
	return $draft->execute_tool( (object) array( 'name' => 'gf_code_chest_propose_settings', 'arguments' => (object) array( 'title' => 'Improve contrast', 'patch' => $patch ) ), $from === null ? $current : $from );
};
$result  = $propose( array( 'css' => '.field { color: black; }' ) );
check( ! is_wp_error( $result ) && $result['data']['after'] === array( 'css' => '.field { color: black; }', 'js' => '', 'scopeCssToForm' => true ), 'A proposal replaces a language whole and preserves unrelated code and scoping.' );
check( $result['data']['changes'] === array( array( 'property' => 'css', 'label' => 'CSS', 'before' => '.field { color: red; }', 'after' => '.field { color: black; }', 'display' => 'code' ) ), 'The changed code displays as a code diff of its whole document.' );
check( $addon->feeds[0]['meta']['code_chest_scope_css_to_form'] === false, 'A proposal never saves.' );
$scoped = $propose( array( 'scopeCssToForm' => false ) );
check( $scoped['data']['changes'] === array( array( 'property' => 'scopeCssToForm', 'label' => 'Scope CSS to this form', 'before' => 'Enabled', 'after' => 'Disabled' ) ), 'A scoping change shows as a setting.' );
foreach ( array( array( 'css' => str_repeat( 'é', 8193 ) ), array( 'php' => '<?php' ), array( 'css' => '.field { color: red; }' ), array() ) as $patch ) {
	check( is_wp_error( $propose( $patch ) ), 'Oversized, unsupported, unchanged and empty proposals are refused.' );
}
// 8 KiB is UTF-8 bytes, the bound the saved code reads back within: 3,000 arrows fit 8,192 characters but not 8 KiB.
$arrows = $propose( array( 'css' => str_repeat( '→', 3000 ) ) );
check( is_wp_error( $arrows ) && strpos( $arrows->get_error_message(), '8 KiB' ) !== false, 'Multibyte code beyond 8 KiB is refused with the size limit, as saved code would be.' );
$fits = $propose( array( 'css' => str_repeat( '→', 2730 ) ) );
check( ! is_wp_error( $fits ) && strlen( $fits['data']['after']['css'] ) === 8190, 'Multibyte code within 8 KiB is proposed.' );
$saved_meta                                = $addon->feeds[0]['meta'];
$addon->feeds[0]['meta']['code_chest_css'] = $fits['data']['after']['css'];
check( GWiz_GF_Code_Chest_Spellbook::saved( GFAPI::$form )['css'] === $fits['data']['after']['css'] && $draft->configuration( array( 'formId' => 7 ) )['identity'] !== '', 'Applied code saved natively reads back, so the draft stays available.' );
$addon->feeds[0]['meta'] = $saved_meta;
$oversized              = $current;
$oversized['values']['js'] = str_repeat( 'x', 8193 );
check( is_wp_error( $draft->prepare_context( $oversized ) ), 'An oversized draft is refused rather than truncated.' );
$oversized['values']['js'] = str_repeat( '→', 3000 );
check( is_wp_error( $draft->prepare_context( $oversized ) ), 'A draft of multibyte code beyond 8 KiB is refused rather than truncated.' );
$addon->feeds[0]['meta']['code_chest_css'] = '.saved-elsewhere {}';
check( is_wp_error( $draft->prepare_context( $current ) ), 'Code saved after the editor opened requires a reload.' );
$current = $context();
check( ! is_wp_error( $draft->prepare_context( $current ) ), 'The reloaded editor is accepted.' );
$addon->feeds[] = $addon->feeds[0];
check( is_wp_error( $context() ), 'Duplicate native feeds leave the draft unavailable.' );
array_pop( $addon->feeds );
foreach ( array( 'gf-code-chest_form_settings', 'gravityforms_edit_forms' ) as $capability ) {
	GFCommon::$caps[ $capability ] = false;
	check( is_wp_error( $draft->prepare_context( $current ) ) && is_wp_error( $propose( array( 'css' => '.x {}' ) ) ), 'Both native settings capabilities are required at every request.' );
	GFCommon::$caps[ $capability ] = true;
}
check( is_wp_error( $draft->configuration( array( 'formId' => 8 ) ) ), 'An unknown form has no draft.' );

// Saved code keeps its own extension, tools and review domain; the mounted form's draft is the draft extension's.
GWiz_GF_Code_Chest_Spellbook_Saved::register();
$extension = Spellbook_Assistant_Extensions::$definitions['gf-code-chest-saved'];
check( isset( $extension['global_context'], $extension['conversation_contexts'] ) && $extension['tools'] === array( 'GWiz_GF_Code_Chest_Spellbook_Saved', 'tools' ) && ! isset( Spellbook_Assistant_Extensions::$definitions['gf-code-chest'] ), 'Saved-code tools are their own global extension beside Spellbook\'s draft extension.' );
check( GWiz_GF_Code_Chest_Spellbook_Saved::ui_result( array( 'tool' => GWiz_GF_Code_Chest_Spellbook_Saved::READ ) ) === null, 'Saved code reads are not a redundant visible card.' );
$native = GWiz_GF_Code_Chest_Spellbook::proposal_arguments();
$saved  = GWiz_GF_Code_Chest_Spellbook_Saved::tools();
check( $saved[0]['properties']['name']['enum'] === array( GWiz_GF_Code_Chest_Spellbook_Saved::READ ) && $saved[0]['properties']['arguments']['required'] === array( 'formId' ) && array_keys( $saved[0]['properties']['arguments']['properties'] ) === array( 'formId' ), 'the saved read takes only a form' );
check( $saved[1]['properties']['name']['enum'] === array( GWiz_GF_Code_Chest_Spellbook_Saved::PREPARE ) && $saved[1]['properties']['arguments']['properties'] === $native + array( 'formId' => $saved[0]['properties']['arguments']['properties']['formId'] ) && $saved[1]['properties']['arguments']['required'] === array_merge( array_keys( $native ), array( 'formId' ) ), 'the saved prepare takes the proposal arguments and a form' );
$unlabelled = GWiz_GF_Code_Chest_Spellbook::display( array( 'before' => $values, 'after' => $values, 'fields' => array( array( 'id' => 4, 'label' => '', 'type' => 'html' ) ) ) );
check( $unlabelled['fieldLabels'] === array( 'Field 4' ), 'an unlabelled field is named by its ID' );

$other   = array( 'another-product' => array( array(
	'kind'   => 'ask',
	'label'  => 'Help',
	'prompt' => 'Help with this product.',
) ) );
$actions = GWiz_GF_Code_Chest_Spellbook::product_actions( $other, array(
	'screen' => 'entries',
	'formId' => 7,
) );
check( $actions['another-product'] === $other['another-product'] && $actions['gf-code-chest'][0]['kind'] === 'link', 'native action preserves other product actions' );
parse_str( parse_url( $actions['gf-code-chest'][0]['href'], PHP_URL_QUERY ), $route );
check( $route === array(
	'page'    => 'gf_edit_forms',
	'view'    => 'settings',
	'subview' => 'gf-code-chest',
	'id'      => '7',
), 'entries page links only to the selected form code editor' );
check( GWiz_GF_Code_Chest_Spellbook::product_actions( $other, array( 'formId' => null ) ) === $other && GWiz_GF_Code_Chest_Spellbook::product_actions( $other, array( 'formId' => 8 ) ) === $other, 'no native action without an existing selected form' );
GFAPI::$form['is_trash'] = true;
check( GWiz_GF_Code_Chest_Spellbook::product_actions( $other, array( 'formId' => 7 ) ) === $other, 'trashed form has no navigation action' );
unset( GFAPI::$form['is_trash'] );
foreach ( array( 'gravityforms_edit_forms', 'gf-code-chest_form_settings' ) as $capability ) {
	GFCommon::$caps[ $capability ] = false;
	check( GWiz_GF_Code_Chest_Spellbook::product_actions( $other, array( 'formId' => 7 ) ) === $other, 'native action requires both settings permissions' );
	GFCommon::$caps[ $capability ] = true;
}
$guidance = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/includes/spellbook-guidance.json' ), true );
check( is_array( $guidance ) && strlen( json_encode( $guidance ) ) <= 16384, 'product declares a bounded owned guidance document' );
