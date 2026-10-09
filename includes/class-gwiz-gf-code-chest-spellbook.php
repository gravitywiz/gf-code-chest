<?php
/**
 * Code Chest's optional Spellbook integration: the callbacks of the native editor's draft in
 * includes/spellbook-settings.json, the script that binds the editor, and the code helpers reviewed saved code shares.
 *
 * @package gf-code-chest
 */

defined( 'ABSPATH' ) || exit;

// Native GF properties and the public browser JSON contract use camelCase.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

final class GWiz_GF_Code_Chest_Spellbook {

	const CODE_BYTES = 8192;

	public static function init() {
		require_once __DIR__ . '/class-gwiz-gf-code-chest-spellbook-saved.php';
		GWiz_GF_Code_Chest_Spellbook_Saved::init();
		add_filter( 'spellbook_assistant_product_actions', array( __CLASS__, 'product_actions' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 30 );
		add_filter( 'gform_noconflict_scripts', array( __CLASS__, 'noconflict' ) );
	}

	/** Link the current authorized form to its native code editor from any assistant page. */
	public static function product_actions( $actions, $context ) {
		$form_id = isset( $context['formId'] ) ? $context['formId'] : null;
		if ( is_wp_error( self::form( $form_id ) ) ) {
			return $actions;
		}
		$actions['gf-code-chest'] = array(
			array(
				'kind'  => 'link',
				'label' => __( 'Open Code Chest', 'gf-code-chest' ),
				'href'  => add_query_arg( array(
					'page'    => 'gf_edit_forms',
					'view'    => 'settings',
					'subview' => 'gf-code-chest',
					'id'      => $form_id,
				), admin_url( 'admin.php' ) ),
			),
		);
		return $actions;
	}

	/** The editor binding loads on the form's Code Chest settings page; Spellbook attaches its draft configuration. */
	public static function enqueue() {
		if ( ! wp_script_is( 'spellbook-assistant-registry', 'enqueued' ) || ! class_exists( 'GFForms' ) || GFForms::get_page() !== 'form_settings_gf-code-chest' || is_wp_error( self::form( absint( rgget( 'id' ) ) ) ) ) {
			return;
		}
		$file = dirname( __DIR__ ) . '/assets/js/spellbook-assistant.js';
		wp_enqueue_script( 'gf-code-chest-spellbook', plugins_url( 'assets/js/spellbook-assistant.js', dirname( __DIR__ ) . '/gf-code-chest.php' ), array( 'spellbook-assistant-registry', 'jquery', 'code-editor' ), (string) filemtime( $file ), true );
	}

	public static function noconflict( $scripts ) {
		$scripts[] = 'gf-code-chest-spellbook';
		return $scripts;
	}

	/** Permission checks are repeated on every read and proposal preparation. */
	public static function form( $form_id ) {
		if ( ! is_int( $form_id ) || $form_id < 1 || ! class_exists( 'GFAPI' ) || ! class_exists( 'GWiz_GF_Code_Chest' )
			|| ! GFCommon::current_user_can_any( 'gravityforms_edit_forms' ) || ! GFCommon::current_user_can_any( 'gf-code-chest_form_settings' ) ) {
			return self::error( 'permission', 'You cannot access Code Chest settings for this form.', 403 );
		}
		$form = GFAPI::get_form( $form_id );
		return is_array( $form ) && empty( $form['is_trash'] ) ? $form : self::error( 'form', 'This form is no longer available.', 404 );
	}

	/** The draft's fingerprint: the saved code the mounted editor opened with, which a save made elsewhere changes. */
	public static function identity( $resource ) {
		$saved = self::saved( $resource['form'] );
		return is_wp_error( $saved ) ? '' : hash( 'sha256', wp_json_encode( array( (int) $resource['form']['id'], $saved ) ) );
	}

	/** The form's first 100 fields as the references a proposal's selectors may target, admin labels first. */
	public static function sources( $resource ) {
		return array_map( static function ( $field ) {
			return array(
				'id'    => (string) $field['id'],
				'label' => self::clip( $field['label'], 200 ),
				'type'  => $field['type'],
			);
		}, array_slice( self::fields( $resource['form'] )['fields'], 0, 100 ) );
	}

	/** Up to 200 field references for proposals, admin labels first. */
	public static function fields( $form ) {
		$fields = array();
		foreach ( array_slice( $form['fields'], 0, 200 ) as $field ) {
			$fields[] = array(
				'id'    => (int) $field->id,
				'label' => self::clip( (string) ( ! empty( $field->adminLabel ) ? $field->adminLabel : $field->label ), 200 ),
				'type'  => self::clip( (string) $field->type, 100 ),
			);
		}
		return array(
			'fields'          => $fields,
			'fieldsTruncated' => count( $form['fields'] ) > 200,
		);
	}

	/** The form's saved code from its one code feed, if any. */
	public static function saved( $form ) {
		$feeds = gwiz_gf_code_chest()->get_feeds( $form['id'] );
		if ( ! is_array( $feeds ) || count( $feeds ) > 1 ) {
			return self::error( 'feeds', 'Code Chest found multiple or unreadable code feeds. Resolve them in native settings before requesting code changes.' );
		}
		return self::code_from_meta( $feeds ? $feeds[0]['meta'] : array(), $form );
	}

	/** Decode feed metadata as the native textareas do; a missing JavaScript key keeps the legacy form script, an explicit empty one does not. */
	public static function code_from_meta( $meta, $form ) {
		$meta = (array) $meta;
		$js   = isset( $meta['code_chest_js'] ) ? $meta['code_chest_js'] : ( isset( $form['custom_js'] ) ? $form['custom_js'] : ( isset( $form['customJS'] ) ? $form['customJS'] : '' ) );
		return self::snapshot( (object) array(
			'js'             => html_entity_decode( (string) $js, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'css'            => html_entity_decode( (string) ( isset( $meta['code_chest_css'] ) ? $meta['code_chest_css'] : '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'scopeCssToForm' => ! isset( $meta['code_chest_scope_css_to_form'] ) || $meta['code_chest_scope_css_to_form'] !== false,
		) );
	}

	/** The editor's draft holds only code its saved snapshot reads back, so code applied and then saved natively keeps the draft available. */
	public static function validate( $values ) {
		$code = self::snapshot( (object) $values );
		return is_wp_error( $code ) ? $code : true;
	}

	/** Code within the 8 KiB (8,192 UTF-8 bytes) per-language bound, or the reason it is not. */
	private static function snapshot( $value ) {
		if ( ! is_object( $value ) || count( get_object_vars( $value ) ) !== 3 || ! isset( $value->js, $value->css, $value->scopeCssToForm )
			|| ! self::text( $value->js, self::CODE_BYTES ) || ! self::text( $value->css, self::CODE_BYTES ) || ! is_bool( $value->scopeCssToForm ) ) {
			return self::error( 'size', 'Code Chest supports up to 8 KiB of CSS and 8 KiB of JavaScript per assistant draft. Use native settings for larger code.' );
		}
		return array(
			'js'             => $value->js,
			'css'            => $value->css,
			'scopeCssToForm' => $value->scopeCssToForm,
		);
	}

	/** The saved-code proposal's arguments, beside its formId. */
	public static function proposal_arguments() {
		$string = array(
			'type'      => 'string',
			'maxLength' => self::CODE_BYTES,
		);
		return array(
			'revision'       => array(
				'type'      => 'string',
				'maxLength' => 200,
			),
			'title'          => array(
				'type'      => 'string',
				'minLength' => 1,
				'maxLength' => 200,
			),
			'detail'         => array(
				'type'      => 'string',
				'maxLength' => 1200,
			),
			'changes'        => array(
				'type'     => 'array',
				'minItems' => 0,
				'maxItems' => 2,
				'items'    => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'language', 'code' ),
					'properties'           => array(
						'language' => array(
							'type' => 'string',
							'enum' => array( 'css', 'js' ),
						),
						'code'     => $string,
					),
				),
			),
			'scopeCssToForm' => array(
				'type'        => array( 'boolean', 'null' ),
				'description' => 'null preserves the current native option.',
			),
			'fieldIds'       => array(
				'type'        => 'array',
				'maxItems'    => 20,
				'uniqueItems' => true,
				'items'       => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
		);
	}

	public static function tool( $name, $description, $properties, $required ) {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'name', 'arguments' ),
			'description'          => $description,
			'properties'           => array(
				'name'      => array(
					'type' => 'string',
					'enum' => array( $name ),
				),
				'arguments' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => $properties,
					'required'             => $required,
				),
			),
		);
	}

	/** A result must fit Spellbook's 64 KiB result limit; a proposal carries its changed code in its diff too. */
	public static function too_large() {
		return self::error( 'size', 'This code diff is too large for an assistant card. Make a smaller change in native settings.' );
	}

	/**
	 * One saved-code proposal: complete replacements for the changed languages, an optional scoping change and the
	 * affected fields, applied to $code. The caller checks its revision first.
	 */
	public static function proposal( $code, $args, $fields ) {
		if ( ! self::text( $args->title, 200 ) || trim( $args->title ) === '' || ! self::text( $args->detail, 1200 ) ) {
			return self::error( 'proposal', 'Give the proposal a title and a bounded description.' );
		}
		$after = $code;
		$seen  = array();
		foreach ( $args->changes as $change ) {
			if ( ! in_array( $change->language, array( 'css', 'js' ), true ) || isset( $seen[ $change->language ] ) || ! self::text( $change->code, self::CODE_BYTES ) ) {
				return self::error( 'proposal', 'Each language can be changed once, using bounded code.' );
			}
			$after[ $change->language ] = $change->code;
			$seen[ $change->language ]  = true;
		}
		if ( $args->scopeCssToForm !== null ) {
			$after['scopeCssToForm'] = $args->scopeCssToForm;
		}
		if ( $after === $code ) {
			return self::error( 'unchanged', 'This proposal does not change the current code.' );
		}
		$affected = array();
		foreach ( $args->fieldIds as $id ) {
			$matches = array_values( array_filter( $fields, static function ( $field ) use ( $id ) {
				return $field['id'] === $id;
			} ) );
			if ( ! $matches ) {
				return self::error( 'field', 'The proposal refers to an unavailable form field.' );
			}
			$affected[] = $matches[0];
		}
		return array(
			'title'  => $args->title,
			'detail' => $args->detail,
			'before' => $code,
			'after'  => $after,
			'fields' => $affected,
		);
	}

	/** Spellbook's code diff for a proposal: its changed documents and options, and its affected field labels. */
	public static function display( $proposal ) {
		$documents = array();
		foreach ( array(
			'css' => 'CSS',
			'js'  => 'JavaScript',
		) as $language => $label ) {
			if ( $proposal['before'][ $language ] !== $proposal['after'][ $language ] ) {
				$documents[] = array(
					'label'    => $label,
					'language' => $language,
					'before'   => $proposal['before'][ $language ],
					'after'    => $proposal['after'][ $language ],
				);
			}
		}
		$options = array();
		if ( $proposal['before']['scopeCssToForm'] !== $proposal['after']['scopeCssToForm'] ) {
			$options[] = array(
				'label'  => __( 'Scope CSS to this form', 'gf-code-chest' ),
				'before' => $proposal['before']['scopeCssToForm'],
				'after'  => $proposal['after']['scopeCssToForm'],
			);
		}
		$labels = array();
		foreach ( $proposal['fields'] as $field ) {
			// translators: %d: form field ID.
			$labels[] = $field['label'] !== '' ? $field['label'] : sprintf( __( 'Field %d', 'gf-code-chest' ), $field['id'] );
		}
		return array(
			'codeChanges' => array(
				'documents' => $documents,
				'options'   => $options,
			),
			'fieldLabels' => $labels,
		);
	}

	private static function text( $value, $bytes ) {
		return is_string( $value ) && strlen( $value ) <= $bytes && ! preg_match( '/[\x00-\x08\x0b\x0c\x0e-\x1f]/', $value );
	}

	public static function clip( $value, $bytes ) {
		$value = substr( $value, 0, $bytes );
		while ( $value !== '' && ! preg_match( '//u', $value ) ) {
			$value = substr( $value, 0, -1 );
		}
		return $value;
	}

	private static function error( $code, $message, $status = 400 ) {
		return new WP_Error( 'gf_code_chest_assistant_' . $code, $message, array( 'status' => $status ) );
	}
}

GWiz_GF_Code_Chest_Spellbook::init();
