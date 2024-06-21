<?php
/**
 * @package gf-code-chest
 * @copyright Copyright (c) 2022, Gravity Wiz, LLC
 * @author Gravity Wiz <support@gravitywiz.com>
 * @license GPLv2
 * @link https://github.com/gravitywiz/gf-code-chest
 */
defined( 'ABSPATH' ) || die();

GFForms::include_feed_addon_framework();

class GWiz_GF_Code_Chest extends GFFeedAddOn {
	/**
	 * @var Inc2734\WP_GitHub_Plugin_Updater\Bootstrap The updater instance.
	 */
	public $updater;

	/**
	 * @var null|GWiz_GF_Code_Chest
	 */
	private static $instance = null;

	protected $_version        = GWIZ_GF_CODE_CHEST_VERSION;
	protected $_path           = 'gf-code-chest/gf-code-chest.php';
	protected $_full_path      = __FILE__;
	protected $_slug           = 'gf-code-chest';
	protected $_title          = 'Gravity Forms Code Chest';
	protected $_short_title    = 'Code Chest';
	protected $_multiple_feeds = false;

	/**
	 * Defines the capabilities needed for the Add-On.
	 *
	 * @var array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array(
		'gf-cvustom-code',
		'gf-code-chest_uninstall',
		'gf-code-chest_results',
		'gf-code-chest_settings',
		'gf-code-chest_form_settings',
	);

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @var string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gf-code-chest_settings';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @var string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gf-code-chest_form_settings';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @var string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gf-code-chest_uninstall';

	/**
	 * Disable async feed processing for now as it can prevent results mapped to fields from working in notifications.
	 *
	 * @var bool
	 */
	protected $_async_feed_processing = false;

	/**
	 * Allow re-ordering of feeds.
	 *
	 * @var bool
	 */
	protected $_supports_feed_ordering = true;

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Give the form settings and plugin settings panels a nice shiny icon.
	 */
	public function get_menu_icon() {
		return $this->get_base_url() . '/assets/images/icon.svg';
	}

	/**
	 * Defines the minimum requirements for the add-on.
	 *
	 * @return array
	 */
	public function minimum_requirements() {
		return array(
			'gravityforms' => array(
				'version' => '2.7',
			),
			'wordpress'    => array(
				'version' => '4.8',
			),
		);
	}

	/**
	 * Load dependencies and initialize auto-updater
	 */
	public function pre_init() {
		parent::pre_init();

		$this->init_auto_updater();

		/**
		 * Hooks for exporting and importing feeds with forms.
		 */
		add_filter( 'gform_export_form', array( $this, 'export_feeds_with_form' ) );
		add_action( 'gform_forms_post_import', array( $this, 'import_feeds_with_form' ) );
	}

	/**
	 * Initialize the auto-updater.
	 */
	public function init_auto_updater() {
		// Initialize GitHub auto-updater
		add_filter(
			'inc2734_github_plugin_updater_plugins_api_gravitywiz/gf-code-chest',
			array( $this, 'filter_auto_updater_response' ), 10, 2
		);

		$this->updater = new Inc2734\WP_GitHub_Plugin_Updater\Bootstrap(
			plugin_basename( plugin_dir_path( __FILE__ ) . 'gf-code-chest.php' ),
			'gravitywiz',
			'gf-code-chest',
			array(
				'description_url' => 'https://raw.githubusercontent.com/gravitywiz/gf-code-chest/main/readme.md',
				'changelog_url'   => 'https://raw.githubusercontent.com/gravitywiz/gf-code-chest/main/changelog.txt',
				'icons'           => array(
					'svg' => 'https://raw.githubusercontent.com/gravitywiz/gf-code-chest/main/assets/images/icon.svg',
				),
				'banners'         => array(
					'low' => 'https://raw.githubusercontent.com/gravitywiz/gf-code-chest/main/assets/images/banner.jpg',
				),
				'requires_php'    => '5.6.0',
			)
		);
	}

	/**
	 * Filter the GitHub auto-updater response to remove sections we don't need and update various fields.
	 *
	 * @param stdClass $obj
	 * @param stdClass $response
	 *
	 * @return stdClass
	 */
	public function filter_auto_updater_response( $obj, $response ) {
		$remove_sections = array(
			'installation',
			'faq',
			'screenshots',
			'reviews',
			'other_notes',
		);

		foreach ( $remove_sections as $section ) {
			if ( isset( $obj->sections[ $section ] ) ) {
				unset( $obj->sections[ $section ] );
			}
		}

		if ( isset( $obj->active_installs ) ) {
			unset( $obj->active_installs );
		}

		$obj->homepage = 'https://gravitywiz.com/gf-code-chest/';
		$obj->author   = '<a href="https://gravitywiz.com/" target="_blank">Gravity Wiz</a>';

		$parsedown = new Parsedown();
		$changelog = trim( $obj->sections['changelog'] );

		// Remove the "Changelog" h1.
		$changelog = preg_replace( '/^# Changelog/m', '', $changelog );

		// Remove the tab before the list item so it's not treated as code.
		$changelog = preg_replace( '/^\t- /m', '- ', $changelog );

		// Convert h2 to h4 to avoid weird styles that add a lot of whitespace.
		$changelog = preg_replace( '/^## /m', '#### ', $changelog );

		$obj->sections['changelog'] = $parsedown->text( $changelog );

		return $obj;
	}

	/**
	 * Initialize the add-on. Similar to construct, but done later.
	 *
	 * @return void
	 */
	public function init() {
		parent::init();

		load_plugin_textdomain( $this->_slug, false, basename( dirname( __file__ ) ) . '/languages/' );

		if ( current_user_can( 'administrator' ) ) {
			add_filter( 'gform_tooltips', array( $this, 'tooltips' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_editor_script' ) );
			add_action( 'gform_post_save_feed_settings', array( $this, 'save_code_chest_settings' ), 10, 4 );
			add_filter( 'gform_noconflict_scripts', array( $this, 'noconflict_scripts' ) );
			add_filter( 'gform_noconflict_styles', array( $this, 'noconflict_styles' ) );

			// 11 so that this comes right after and can override the legacy Custom JS plugin setting config.
			add_filter( 'gform_form_settings_fields', array( $this, 'replace_custom_js_setting' ), 11, 2 );
		}

		add_filter( 'gform_register_init_scripts', array( $this, 'register_init_script' ), 99, 1 );
		add_filter( 'gform_register_init_scripts', array( $this, 'maybe_register_custom_js_scripts_first' ), 100, 1 );

		/**
		 * 90 so that this fires after the legacy Custom JS plugin action callbacks have been registered.
		 */
		add_filter( 'gform_register_init_scripts', array( $this, 'maybe_unhook_legacy_custom_js' ), 90, 1 );

		/**
		 * must come after other gform_register_init_scripts callbacks as this needs to be the last registered
		 * script so that the action runs only after all other scripts have been loaded.
		 */
		add_filter( 'gform_register_init_scripts', array( $this, 'register_gfcc_deferred_action_script' ), 101, 1 );
		add_filter( 'gform_form_after_open', array( $this, 'add_custom_css' ), 10, 2 );

		/**
		 * Hook for duplicating form feeds when duplicating a form.
		 */
		add_action( 'gform_post_form_duplicated', array( $this, 'duplicate_form_feeds' ), 10, 2 );
	}

	public function enqueue_editor_script() {
		if ( GFForms::get_page() !== 'form_settings_gf-code-chest' ) {
			return;
		}

		$editor_settings['js_code_editor']  = wp_enqueue_code_editor( array( 'type' => 'text/javascript' ) );
		$editor_settings['css_code_editor'] = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		wp_localize_script( 'jquery', 'editor_settings', $editor_settings );

		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );
	}

	public function noconflict_scripts( $scripts = array() ) {
		$scripts[] = 'code-editor';
		$scripts[] = 'jshint';
		$scripts[] = 'jsonlint';
		$scripts[] = 'wp-theme-plugin-editor';
		return $scripts;
	}

	public function noconflict_styles( $scripts = array() ) {
		$scripts[] = 'code-editor';
		$scripts[] = 'wp-codemirror';
		return $scripts;
	}

	public function save_code_chest_settings( $feed_id, $form_id, $settings, $feed_addon_instance ) {
		/**
		 * Note that this must be handled manually as we (almost) completelty override the
		 * settings form markup which apparently prevents GF from saving the settings.
		 */
		$settings['code_chest_js']                = esc_html( rgpost( 'code_chest_js' ) );
		$settings['code_chest_css']               = esc_html( rgpost( 'code_chest_css' ) );
		$settings['code_chest_scope_css_to_form'] = rgpost( '_gform_setting_code_chest_scope_css_to_form' ) === '1' ? true : false;

		$this->update_feed_meta( $feed_id, $settings );
	}

	public function register_init_script( $form ) {
		if ( ! $this->is_applicable_form( $form ) ) {
			return;
		}

		$allowed_entities = array(
			'&#039;' => '\'',
			'&quot;' => '"',
		);

		$script = html_entity_decode( str_replace( array_keys( $allowed_entities ), $allowed_entities, $this->get_custom_js( $form ) ) );
		$script = str_replace( 'GFFORMID', $form['id'], $script );
		$script = '( function( $ ) { ' . $script . ' } )( jQuery );';
		/**
		 * Add a newline plus whitespace in case the final line of user added
		 * JS is a comment. This prevents the comment from effecting other scripts
		 * that may come later. In other words, the newline will "push" any subsequent
		 * scripts to a newline so that the comment line does not effect it.
		*/
		$script = $script . "\n";

		$slug = "{$this->_slug}_{$form['id']}";

		GFFormDisplay::add_init_script( $form['id'], $slug, GFFormDisplay::ON_PAGE_RENDER, $script );
	}

	public function register_gfcc_deferred_action_script( $form ) {
		if ( ! $this->is_applicable_form( $form ) ) {
			return;
		}

		$form_id = $form['id'];
		$slug    = "{$this->_slug}_deferred_action_{$form['id']}";
		/**
		 * This action is fired after all other Code Chest scripts have been loaded
		 * and ran in the browser.
		 *
		 * @param string|int $form_id The ID of the form.
		 */
		$script = "window.gform.doAction('gfcc_deferred', {$form_id});";

		GFFormDisplay::add_init_script( $form['id'], $slug, GFFormDisplay::ON_PAGE_RENDER, $script );
	}

	public function maybe_register_custom_js_scripts_first( $form ) {
		/**
		 * Filters whether to load the custom JS before other scripts.
		 *
		 * This is useful as some perks (e.g. Copy Cat, Address Autocomplete)
		 * allow their initialization options to be filtered, but the Custom JS
		 * plugin outputs its scripts too late. This change registers (and consequently
		 * loads the GF Code Chest Javascript scripts first.
		 *
		 * @param bool $should_load_register_custom_js_first Whether to load the custom JS before other scripts.
		 * @param array $form The form object.
		 */
		if ( ! apply_filters( 'gwiz_gf_code_chest_register_custom_js_first', true, $form ) ) {
			return;
		}

		$scripts = rgar( GFFormDisplay::$init_scripts, $form['id'] );
		if ( empty( $scripts ) ) {
			return;
		}

		$filtered = array();
		foreach ( $scripts as $slug => $script ) {
			if ( strpos( $slug, $this->_slug ) === 0 ) {
				$filtered = array( $slug => $script ) + $filtered;
			} else {
				$filtered[ $slug ] = $script;
			}
		}

		GFFormDisplay::$init_scripts[ $form['id'] ] = $filtered;
	}

	public function maybe_unhook_legacy_custom_js( $form ) {
		if ( ! class_exists( 'GF_Custom_JS' ) ) {
			return;
		}

		$gf_custom_js_instance = GF_Custom_JS::get_instance();

		remove_filter( 'gform_register_init_scripts', array( $gf_custom_js_instance, 'register_init_script' ), 99 );
	}

	public function add_custom_css( $form_string, $form ) {
		$custom_css = $this->get_custom_css( $form );
		$custom_css = html_entity_decode( (string) $custom_css );
		$custom_css = str_replace( 'GFFORMID', $form['id'], $custom_css );

		$settings = $this->get_settings( $form['id'] );

		// check explicity if not set to false as the default value is "true"
		// and unset value, empty string, etc. implies that the user has not
		// explicity changed this.
		if ( rgar( $settings, 'code_chest_scope_css_to_form' ) !== false ) {
			// alternatively this could be scoped to the form element with `#gform_FORMID`
			$prefix     = '#gform_wrapper_' . $form['id'];
			$custom_css = $this->prefix_css_selectors( $custom_css, $prefix );
		}

		if ( ! empty( $custom_css ) ) {
			return $form_string . sprintf( '<style>%s</style>', $custom_css );
		}

		return $form_string;
	}

	public function is_applicable_form( $form ) {
		return ! empty( $this->get_custom_js( $form ) );
	}

	public function get_settings( $form_id ) {
		$feed_id = $this->get_default_feed_id( $form_id );
		$feed    = $this->get_feed( $feed_id );
		return rgar( $feed, 'meta' );
	}

	public function get_custom_js( $form ) {
		$settings = $this->get_settings( $form['id'] );

		/**
		 * default to settings from the legacy Custom JS plugin if
		 * the current form has never had code chest settings saved.
		 */
		if ( ! isset( $settings['code_chest_js'] ) ) {
			return rgar( $form, 'custom_js', rgar( $form, 'customJS' ) );
		}

		return rgar( $settings, 'code_chest_js' );

	}

	public function get_custom_css( $form ) {
		$settings = $this->get_settings( $form['id'] );
		return rgar( $settings, 'code_chest_css' );
	}

	/**
	 * Registers tooltips with Gravity Forms. Needed for some things like radio choices.
	 *
	 * @param $tooltips array Existing tooltips.
	 *
	 * @return array
	 */
	public function tooltips( $tooltips ) {
		return $tooltips;
	}

	public function feed_settings_fields() {
		$form_id = rgget( 'id' );
		$form    = GFAPI::get_form( $form_id );
		return array(
			array(
				'title'  => 'JavaScript',
				'fields' => array(
					array(
						'name'     => 'code_chest_js',
						'type'     => 'editor_js',
						'callback' => function ( $setting ) use ( $form ) {
							return $this->render_custom_js_setting( $form );
						},
					),
				),
			),
			array(
				'title'  => 'CSS',
				'fields' => array(
					array(
						'name'     => 'code_chest_css',
						'type'     => 'editor_css',
						'callback' => function ( $setting ) use ( $form ) {
							return $this->render_custom_css_setting( $form );
						},
					),
					array(
						'name'          => 'code_chest_scope_css_to_form',
						'type'          => 'toggle',
						'label'         => __( 'Scope CSS to this form only', 'gw-code-chest' ),
						'tooltip'       => __( 'When enabled, the custom CSS will only be applied to this form. This works by adding "#gform_wrapper_GFFORMID" before all detected selectors.', 'gw-code-chest' ),
						'default_value' => true,
					),
				),
			),
		);
	}

	public function render_custom_js_setting( $form ) {
		/**
		 * must use isset here as an empty string value is a valid value
		 * to save. if using rgpost, an empty string will evaluate to
		 * `false` and then the else block will incorrectly execute.
		 */
		if ( isset( $_POST['code_chest_js'] ) ) {
			$custom_js = rgpost( 'code_chest_js' );
		} else {
			$custom_js = $this->get_custom_js( $form );
		}

		return $this->get_code_editor_markup( 'js', $custom_js );
	}

	public function render_custom_css_setting( $form ) {
		// GF 2.5 may fire `gform_form_settings` before `save_custom_js_setting`
		$custom_css = $this->get_custom_css( $form );
		$post_css   = esc_html( rgpost( 'code_chest_css' ) );
		// Always favor posted JS if it's available
		$custom_css = ( $post_css ) ? $post_css : $custom_css;
		return $this->get_code_editor_markup( 'css', $custom_css );
	}

	/**
	 * @param $type string The type of code editor to get. One of 'js' or 'css
	 * @parap $code string The code to render in the editor.
	 */
	public function get_code_editor_markup( $type, $code ) {
		$type_display_name = $type === 'js' ? 'Javascript' : 'CSS';
		/* translators: %s: The string "Javascript" or "CSS". */
		$description  = sprintf( __( 'Add any custom %s that you would like to output wherever this form is rendered.' ), $type_display_name );
		$gform_id_msg = __( 'Use <code>GFFORMID</code> to automatically set the current form ID when the code is rendered.' );

		return <<<EOT
			<tr id="code_chest_{$type}_setting" class="child_setting_row">
				<td colspan="2">
					<p>{$description}<br>{$gform_id_msg}</p>
					<textarea id="code_chest_{$type}" name="code_chest_{$type}" spellcheck="false"
						style="width:100%%;height:14rem;">{$code}</textarea>
				</td>
			</td>
			<script>
				jQuery( document ).ready( function( $ ) {
					wp.codeEditor.initialize( $( "#code_chest_{$type}" ), editor_settings.{$type}_code_editor );
				} );
			</script>
			<style type="text/css">
				.CodeMirror-wrap { border: 1px solid #e1e1e1; }
			</style>
EOT;
	}

	/**
	 * Returns validation error message markup.
	 *
	 * @param string $validation_message  The validation message to add to the markup.
	 * @param array  $form                The submitted form data.
	 *
	 * @return false|string
	 */
	protected function get_validation_error_markup( $validation_message, $form ) {
		$error_classes = $this->get_validation_error_css_classes( $form );
		ob_start();

		if ( ! $this->is_gravityforms_supported( '2.5' ) ) {
			?>
			<div class="<?php echo esc_attr( $error_classes ); ?>"><?php echo esc_html( $validation_message ); ?></div>
			<?php
			return ob_get_clean();
		}
		?>
		<h2 class="<?php echo esc_attr( $error_classes ); ?>">
			<span class="gform-icon gform-icon--close"></span>
			<?php echo esc_html( $validation_message ); ?>
		</h2>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the CSS classes for the validation markup.
	 *
	 * @param array $form The submitted form data.
	 */
	protected function get_validation_error_css_classes( $form ) {
		$container_css = $this->is_gravityforms_supported( '2.5' ) ? 'gform_submission_error' : 'validation_error';

		return "{$container_css} hide_summary";
	}

	/**
	 * Export Code Chest Add-On feed when exporting forms.
	 *
	 * @param array $form The current form being exported.
	 *
	 * @return array
	 */
	public function export_feeds_with_form( $form ) {
		$feeds = $this->get_feeds( $form['id'] );

		if ( ! isset( $form['feeds'] ) ) {
			$form['feeds'] = array();
		}

		$form['feeds'][ $this->get_slug() ] = $feeds;

		return $form;
	}

	/**
	 * Import Code Chest Add-On feed when importing forms.
	 *
	 * @param array $forms Imported forms.
	 */
	public function import_feeds_with_form( $forms ) {
		foreach ( $forms as $import_form ) {
			// Ensure the imported form is the latest.
			$form = GFAPI::get_form( $import_form['id'] );

			if ( ! rgars( $form, 'feeds/' . $this->get_slug() ) ) {
				continue;
			}

			foreach ( rgars( $form, 'feeds/' . $this->get_slug() ) as $feed ) {
				GFAPI::add_feed( $form['id'], $feed['meta'], $this->get_slug() );
			}

			// Remove feeds from the form array as it's no longer needed.
			unset( $form['feeds'][ $this->get_slug() ] );

			if ( empty( $form['feeds'] ) ) {
				unset( $form['feeds'] );
			}

			GFAPI::update_form( $form );
		}
	}

	public function duplicate_form_feeds( $form_id, $new_form_id ) {
		$src_feeds = $this->get_feeds( $form_id );

		if ( count( $src_feeds ) > 0 ) {
			$copy_feed = $src_feeds[0];

			GFAPI::add_feed( $new_form_id, $copy_feed['meta'], $this->get_slug() );
		}
	}

	public function replace_custom_js_setting( $form_settings, $form ) {
		// Only replace the legacy settings if the legacy plugin is currently active.
		if ( ! rgar( $form_settings, 'Custom Code' ) ) {
			return $form_settings;
		}

		$form_settings['Custom Code'] = array(
			'title'  => esc_html__( 'Custom Code' ),
			'fields' => array(
				array(
					'name'     => 'code_chest_js',
					'type'     => 'editor_js',
					'callback' => function ( $setting ) use ( $form ) {
						$form_id = $form['id'];
						$markup_pieces = array(
							'<div id="gform_setting_code_chest_js_overridden_warning" class="gform-settings-field gform-settings-field__html">',
							'<div class="gform-settings-field"><b>',
							__( 'GF Code Chest replaces GF Custom JavaScript.', 'gf-code-chest' ),
							'</b>',
							__( 'Code Chest is now managing your existing Custom JavaScript scripts. You can safely deactivate the Custom JavaScript plugin immediately.', 'gf-code-chest' ),
							'</div>',
							'<a href="' . admin_url( "admin.php?subview=gf-code-chest&page=gf_edit_forms&id={$form_id}&view=settings" ) . '" class="gform-button gform-button--white">',
							__( 'Open Code Chest', 'gf-code-chest' ),
							'</a>',
							'</div>',
						);

						return implode( ' ', $markup_pieces );
					},
				),
			),
		);

		return $form_settings;
	}

	public function prefix_css_selectors( $css, $prefix ) {
		/**
		 * Find all CSS selectors, but ignore all `@` rules such as @font-face, @media, etc. as they are
		 * defined globally and not can't be scoped to a certion "parent" selector.
		 */
		return preg_replace_callback('/^\s*(?!@)([^\r\n,{}\/]+)(,(?=[^}]*{)|\s*{)/m', function( $matches ) use ( $prefix ) {
			return $prefix . ' ' . trim( $matches[1] ) . $matches[2];
		}, $css);
	}
}
