<?php
/** Native WordPress/Gravity Forms coverage; see README.md for the integration bootstrap. */
class SavedCodeReviewTest extends WP_UnitTestCase {
	private $form_id;
	private $owner;

	public function set_up() {
		parent::set_up();
		$this->owner = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $this->owner );
		wp_get_current_user()->add_cap( 'gravityforms_edit_forms' );
		wp_get_current_user()->add_cap( 'gf-code-chest_form_settings' );
		$this->form_id = GFAPI::add_form( array(
			'title'  => 'Saved code integration',
			'fields' => array(
				array(
					'id'    => 1,
					'type'  => 'text',
					'label' => 'Name',
				),
			),
		) );
		$this->assertIsInt( $this->form_id );
		$this->assertTrue( GWiz_GF_Code_Chest_Spellbook_Saved::available() );
	}

	private function read() {
		$result = GWiz_GF_Code_Chest_Spellbook_Saved::execute( (object) array(
			'name'      => GWiz_GF_Code_Chest_Spellbook_Saved::READ,
			'arguments' => (object) array( 'formId' => $this->form_id ),
		), array( 'mode' => 'saved' ) );
		$this->assertNotWPError( $result );
		return $result['data'];
	}

	private function prepare( $css ) {
		$read   = $this->read();
		$result = GWiz_GF_Code_Chest_Spellbook_Saved::execute( (object) array(
			'name'      => GWiz_GF_Code_Chest_Spellbook_Saved::PREPARE,
			'arguments' => (object) array(
				'formId'         => $this->form_id,
				'revision'       => $read['revision'],
				'title'          => 'Style the form',
				'detail'         => 'Adjust the field border.',
				'changes'        => array(
					(object) array(
						'language' => 'css',
						'code'     => $css,
					),
				),
				'scopeCssToForm' => null,
				'fieldIds'       => array( 1 ),
			),
		), array( 'mode' => 'saved' ) );
		$this->assertNotWPError( $result );
		$this->assertSame( 'server_review', $result['data']['kind'] );
		return $result;
	}

	/** Spellbook's saved-review routes, as the review card calls them. */
	private function send( $action, $review ) {
		$request = new WP_REST_Request( 'POST', '/gwiz/v1/editor-assistant/saved-reviews/' . $action );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array(
			'approved'      => $action === 'approve',
			'approvalToken' => $review['data']['approvalToken'],
		) ) );
		return call_user_func( array( 'Spellbook_Assistant_Saved_Reviews', $action ), $request );
	}

	private function approve( $review ) {
		return $this->send( 'approve', $review );
	}

	public function test_review_preserves_native_metadata_and_saves_only_after_approval() {
		global $wpdb;
		$meta = (object) array(
			'code_chest_css' => esc_html( '.gfield { color: blue; }' ),
			'code_chest_js'  => esc_html( 'window.example = "keep & preserve";' ),
			'future_option'  => (object) array( 'empty_object' => new stdClass() ),
		);
		$wpdb->insert( $wpdb->prefix . 'gf_addon_feed', array(
			'form_id'    => $this->form_id,
			'addon_slug' => 'gf-code-chest',
			'is_active'  => 0,
			'meta'       => wp_json_encode( $meta ),
		) );
		$id     = (int) $wpdb->insert_id;
		$css    = '.gfield::before { content: "< & >"; }';
		$review = $this->prepare( $css );
		$this->assertSame( '.gfield { color: blue; }', $this->read()['code']['css'] );
		$this->assertCount( 1, $review['data']['codeChanges']['documents'] );
		$this->assertSame( 'gf-code-chest:' . $this->form_id, $review['data']['resourceKey'] );
		$this->assertArrayNotHasKey( 'endpoint', $review['data'] );
		$this->assertArrayHasKey( 'savedReview', $review['data'] );
		$provider = Spellbook_Assistant_Extensions::provider_result( $review, (object) array(
			'id'   => GWiz_GF_Code_Chest_Spellbook_Saved::EXTENSION,
			'data' => new stdClass(),
		) );
		$this->assertArrayNotHasKey( 'approvalToken', $provider['data'] );
		$this->assertArrayNotHasKey( 'codeChanges', $provider['data'] );
		$result = $this->approve( $review );
		$this->assertNotWPError( $result );
		$this->assertTrue( $result['applied'] );
		$this->assertSame( $result, $this->approve( $review ) );
		$this->assertSame( $css, $this->read()['code']['css'] );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT meta,is_active FROM {$wpdb->prefix}gf_addon_feed WHERE id=%d", $id ) );
		$saved = json_decode( $row->meta );
		$this->assertSame( esc_html( $css ), $saved->code_chest_css );
		$this->assertSame( $meta->code_chest_js, $saved->code_chest_js );
		$this->assertEquals( $meta->future_option, $saved->future_option );
		$this->assertSame( '0', $row->is_active );
	}

	public function test_stale_and_unauthorized_reviews_cannot_overwrite_saved_code() {
		$review = $this->prepare( '.gfield { color: red; }' );
		$this->assertNotWPError( GFAPI::add_feed( $this->form_id, array( 'code_chest_css' => 'native save' ), 'gf-code-chest' ) );
		$this->assertSame( 'gf_code_chest_assistant_stale', $this->approve( $review )->get_error_code() );
		$this->assertSame( 'gf_code_chest_assistant_stale', $this->send( 'refresh', $review )->get_error_code(), 'A refresh cannot carry the request over code saved since its revision.' );
		$this->assertSame( 'native save', $this->read()['code']['css'] );
		$review = $this->prepare( '.gfield { color: green; }' );
		$other  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $other );
		get_userdata( $other )->add_cap( 'gravityforms_edit_forms' );
		get_userdata( $other )->add_cap( 'gf-code-chest_form_settings' );
		$this->assertSame( 'spellbook_saved_review_approval', $this->approve( $review )->get_error_code() );
		wp_set_current_user( $this->owner );
		wp_get_current_user()->add_cap( 'gf-code-chest_form_settings', false );
		$this->assertSame( 'gf_code_chest_assistant_permission', $this->approve( $review )->get_error_code() );
		$this->assertNull( GWiz_GF_Code_Chest_Spellbook_Saved::context( (object) array(
			'screen' => 'entries',
			'formId' => $this->form_id,
		) ) );
	}

	public function test_cross_page_reads_retain_product_permission_in_history() {
		$context = (object) array(
			'screen' => 'global',
			'formId' => null,
		);
		$tools  = new Spellbook_Assistant_Tool_Table( $context );
		$result = $tools->execute( (object) array(
			'name'      => GWiz_GF_Code_Chest_Spellbook_Saved::READ,
			'arguments' => (object) array( 'formId' => $this->form_id ),
		) );
		$this->assertNotWPError( $result );
		$fields = $tools->response_fields();
		$this->assertArrayNotHasKey( 'reviews', $fields );
		$this->assertCount( 1, $fields['accessContexts'] );
		$this->assertSame( $this->form_id, $fields['accessContexts'][0]['formId'] );
		$message = array(
			'id'             => 1,
			'role'           => 'assistant',
			'content'        => 'Inspected the saved form code.',
			'context'        => $context,
			'accessContexts' => $fields['accessContexts'],
		);
		$request = new WP_REST_Request( 'POST' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array(
			'requestId' => wp_generate_uuid4(),
			'context'   => $context,
			'messages'  => array( $message ),
		) ) );
		$conversation = Spellbook_Assistant_Conversations::create( $request );
		$this->assertNotWPError( $conversation );
		$this->assertSame( $message['accessContexts'], $conversation['messages'][0]['accessContexts'] );
		$update = new WP_REST_Request( 'PUT' );
		$update->set_url_params( array( 'id' => $conversation['id'] ) );
		$update->set_header( 'content-type', 'application/json' );
		unset( $message['accessContexts'] );
		$update->set_body( wp_json_encode( array(
			'revision' => $conversation['revision'],
			'messages' => array( $message ),
		) ) );
		$this->assertWPError( Spellbook_Assistant_Conversations::update( $update ) );
		wp_get_current_user()->add_cap( 'gf-code-chest_form_settings', false );
		$this->assertWPError( Spellbook_Assistant_Conversations::authorize_reference( $conversation['id'] ) );
		$list = new WP_REST_Request( 'GET' );
		$list->set_query_params( array(
			'context' => wp_json_encode( $context ),
			'all'     => '1',
		) );
		$this->assertSame( array(), Spellbook_Assistant_Conversations::listing( $list )['sessions'] );
	}

	public function test_first_feed_keeps_legacy_js_and_native_draft_precedence() {
		$form             = GFAPI::get_form( $this->form_id );
		$form['customJS'] = 'window.legacy = "preserved";';
		$this->assertTrue( GFAPI::update_form( $form ) );
		$this->assertSame( $form['customJS'], $this->read()['code']['js'] );
		$review = $this->prepare( '.gfield { border: 1px solid; }' );
		$lock             = Spellbook_Assistant_Saved_Reviews::lock( 'gfcc_code_save', $this->form_id );
		$other_connection = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$this->assertSame( '1', $other_connection->get_var( $other_connection->prepare( 'SELECT GET_LOCK(%s,0)', $lock ) ) );
		try {
			$this->assertSame( 'spellbook_saved_review_busy', $this->approve( $review )->get_error_code() );
			$this->assertSame( array(), gwiz_gf_code_chest()->get_feeds( $this->form_id ) );
		} finally {
			$other_connection->get_var( $other_connection->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
			$other_connection->close();
		}
		$this->assertNotWPError( $this->approve( $review ) );
		$feeds = gwiz_gf_code_chest()->get_feeds( $this->form_id );
		$this->assertCount( 1, $feeds );
		$this->assertArrayNotHasKey( 'code_chest_js', $feeds[0]['meta'] );
		$this->assertSame( $form['customJS'], $this->read()['code']['js'] );
		$this->assertSame( 'saved', GWiz_GF_Code_Chest_Spellbook_Saved::context( (object) array(
			'screen' => 'entries',
			'formId' => $this->form_id,
		) )['mode'] );
		$this->assertNull( GWiz_GF_Code_Chest_Spellbook_Saved::context( (object) array(
			'screen'    => 'extension',
			'extension' => (object) array( 'id' => 'gf-code-chest' ),
		) ) );
		$this->assertNull( GWiz_GF_Code_Chest_Spellbook_Saved::context( (object) array(
			'screen'   => 'settings',
			'settings' => (object) array( 'addon' => 'gf-code-chest' ),
		) ) );
	}

	public function test_mounted_code_editor_can_review_another_form_without_bypassing_its_draft() {
		$other_form = GFAPI::add_form( array(
			'title'  => 'Other saved form',
			'fields' => array(
				array(
					'id'    => 1,
					'type'  => 'text',
					'label' => 'Name',
				),
			),
		) );
		$this->assertIsInt( $other_form );
		// The mounted editor's context, as Spellbook's gf-code-chest page draft configures and authorizes it.
		$draft  = new Spellbook_Assistant_Native_Drafts( 'gf-code-chest', Spellbook_Assistant_Settings::definition( 'gf-code-chest', 'page' ) );
		$config = $draft->configuration( array( 'formId' => $this->form_id ) );
		$this->assertNotWPError( $config );
		$native = Spellbook_Assistant_Extensions::prepare_context( (object) array(
			'id'   => 'gf-code-chest',
			'data' => json_decode( wp_json_encode( $config + array(
				'revision' => 'draft-1',
				'values'   => array(
					'css'            => '.unsaved { color: red; }',
					'js'             => '',
					'scopeCssToForm' => true,
				),
			) ) ),
		) );
		$this->assertNotWPError( $native );
		$context = (object) array(
			'screen'    => 'extension',
			'formId'    => $other_form,
			'extension' => $native,
		);
		// The native editor's authorized identity wins over a mismatched outer form ID.
		$this->assertSame( $this->form_id, GWiz_GF_Code_Chest_Spellbook_Saved::context( $context )['mountedFormId'] );
		$tools = new Spellbook_Assistant_Tool_Table( $context );
		$names = array_map( static function ( $schema ) {
			return $schema['properties']['name']['enum'][0];
		}, $tools->schemas() );
		$this->assertSame( array(), array_diff( array( 'gf_code_chest_inspect_settings', 'gf_code_chest_propose_settings', GWiz_GF_Code_Chest_Spellbook_Saved::READ, GWiz_GF_Code_Chest_Spellbook_Saved::PREPARE ), $names ) );
		$read_call = (object) array(
			'name'      => GWiz_GF_Code_Chest_Spellbook_Saved::READ,
			'arguments' => (object) array( 'formId' => $other_form ),
		);
		$read      = $tools->execute( $read_call );
		$this->assertNotWPError( $read );
		$this->assertSame( $other_form, $read['data']['formId'] );
		$prepare_call = (object) array(
			'name'      => GWiz_GF_Code_Chest_Spellbook_Saved::PREPARE,
			'arguments' => (object) array(
				'formId'         => $other_form,
				'revision'       => $read['data']['revision'],
				'title'          => 'Style another form',
				'detail'         => 'Preserve the mounted form draft.',
				'changes'        => array(
					(object) array(
						'language' => 'css',
						'code'     => '.other { color: blue; }',
					),
				),
				'scopeCssToForm' => null,
				'fieldIds'       => array( 1 ),
			),
		);
		$this->assertNotWPError( $tools->execute( $prepare_call ) );
		$reviews = array_column( $tools->response_fields()['reviews'], 'review' );
		$this->assertCount( 1, $reviews );
		$this->assertNotWPError( $this->approve( $reviews[0] ) );
		$this->assertSame( '.other { color: blue; }', $tools->execute( $read_call )['data']['code']['css'] );
		foreach ( array( $read_call, $prepare_call ) as $call ) {
			$call->arguments->formId = $this->form_id;
			$this->assertSame( 'gf_code_chest_assistant_draft', $tools->execute( $call )->get_error_code() );
		}
		$this->assertSame( '', $this->read()['code']['css'] );
		$this->assertSame( '.unsaved { color: red; }', $native->data['values']['css'] );
		wp_get_current_user()->add_cap( 'gf-code-chest_form_settings', false );
		$read_call->arguments->formId = $other_form;
		$this->assertWPError( $tools->execute( $read_call ) );
	}
}
