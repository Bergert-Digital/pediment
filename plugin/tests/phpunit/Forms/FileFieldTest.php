<?php

namespace Pediment\Tests\Forms;

/**
 * File fields: rendering, upload checks, private storage, mail attachments
 * and clean-up.
 */
class FileFieldTest extends \WP_UnitTestCase {
	private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

	private $server;
	private int $post_id;
	private string $form_key;
	private array $tmp   = array();
	private ?array $mail = null;

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		// PHP only sees a real upload in a real HTTP request.
		add_filter( 'pediment_form_is_uploaded_file', '__return_true' );
		add_filter( 'wp_mail_from', static fn() => 'wordpress@example.com' );
		add_filter(
			'wp_mail',
			function ( $args ) {
				$this->mail = $args;
				return $args;
			}
		);
		reset_phpmailer_instance();

		$markup         = '<!-- wp:pediment/form {"destination":"jobs"} -->'
			. '<!-- wp:pediment/form-field {"label":"Name","fieldName":"name","required":true} /-->'
			. '<!-- wp:pediment/form-field {"fieldType":"file","label":"CV","fieldName":"cv","required":true,"fileTypes":"pdf, docx","maxSize":1} /-->'
			. '<!-- wp:pediment/form-field {"fieldType":"file","label":"Certificates","fieldName":"certificates","multiple":true} /-->'
			. '<!-- /wp:pediment/form -->';
		$this->post_id  = (int) self::factory()->post->create( array( 'post_content' => $markup ) );
		$inner          = parse_blocks( $markup )[0]['innerBlocks'];
		$this->form_key = pediment_form_form_key( pediment_form_collect_fields( $inner ) );

		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			array(
				array(
					'id'            => 'jobs',
					'type'          => 'email',
					'label'         => 'Applications',
					'to'            => array( 'jobs@example.com' ),
					'subject'       => '',
					'body_template' => '',
				),
			)
		);
	}

	public function tear_down(): void {
		foreach ( get_posts( array( 'post_type' => PEDIMENT_FORM_CPT, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( $this->tmp as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		remove_all_filters( 'pediment_form_is_uploaded_file' );
		remove_all_filters( 'pediment_form_mail_attachment_limit' );
		remove_all_filters( 'wp_mail_from' );
		remove_all_filters( 'wp_mail' );
		reset_phpmailer_instance();
		parent::tear_down();
	}

	private function upload( string $name, string $content ): array {
		$path        = wp_tempnam( $name );
		$this->tmp[] = $path;
		file_put_contents( $path, $content );
		return array(
			'name'     => $name,
			'type'     => 'application/octet-stream',
			'tmp_name' => $path,
			'error'    => UPLOAD_ERR_OK,
			'size'     => strlen( $content ),
		);
	}

	/**
	 * Build $_FILES the way PHP does for `files[<field>][]`.
	 *
	 * @param array<string,array<int,array<string,mixed>>> $by_field Field => uploads.
	 */
	private function files_param( array $by_field ): array {
		$out = array( 'files' => array() );
		foreach ( $by_field as $field => $uploads ) {
			foreach ( $uploads as $i => $upload ) {
				foreach ( $upload as $prop => $value ) {
					$out['files'][ $prop ][ $field ][ $i ] = $value;
				}
			}
		}
		return $out;
	}

	private function submit( array $fields, array $files ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/pediment/v1/forms' );
		$request->set_body_params(
			array(
				'post_id'  => (string) $this->post_id,
				'form_key' => $this->form_key,
				'hp_field' => '',
				'_t'       => (string) ( time() - 10 ),
				'fields'   => $fields,
			)
		);
		$request->set_file_params( $this->files_param( $files ) );
		return $this->server->dispatch( $request );
	}

	private function latest_submission(): int {
		$ids = get_posts( array( 'post_type' => PEDIMENT_FORM_CPT, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
		return (int) ( $ids[0] ?? 0 );
	}

	public function test_file_field_renders_input_with_accept_limit_and_hint() {
		$html = do_blocks( '<!-- wp:pediment/form-field {"fieldType":"file","label":"CV","fieldName":"cv","fileTypes":".PDF, docx, svg, php","maxSize":2,"multiple":true} /-->' );
		$this->assertStringContainsString( 'type="file"', $html );
		$this->assertStringContainsString( 'accept=".pdf,.docx"', $html );
		$this->assertStringContainsString( 'data-max-size="' . min( 2 * MB_IN_BYTES, wp_max_upload_size() ) . '"', $html );
		$this->assertStringContainsString( ' multiple', $html );
		$this->assertStringContainsString( 'PDF, DOCX', $html );
		$this->assertStringNotContainsString( 'svg', $html );
	}

	public function test_file_field_with_empty_attributes_accepts_pdf() {
		$html = do_blocks( '<!-- wp:pediment/form-field {"fieldType":"file"} /-->' );
		$this->assertStringContainsString( 'accept=".pdf"', $html );
		$this->assertStringNotContainsString( ' multiple', $html );
	}

	public function test_form_with_file_field_carries_the_upload_limit() {
		$html = do_blocks( (string) get_post_field( 'post_content', $this->post_id ) );
		$this->assertStringContainsString( 'data-max-upload="' . wp_max_upload_size() . '"', $html );
	}

	public function test_unsafe_extensions_fall_back_to_pdf() {
		$this->assertSame( array( 'pdf' ), pediment_form_file_extensions( 'svg, php, html' ) );
		$this->assertSame( array( 'pdf', 'docx' ), pediment_form_file_extensions( array( '.pdf', 'DOCX', 'pdf' ) ) );
	}

	public function test_valid_pdf_is_stored_privately_and_named_in_the_value() {
		$r = $this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $this->upload( 'Lebenslauf Ada (2026).pdf', self::PDF ) ) ) );

		$this->assertSame( 200, $r->get_status(), wp_json_encode( $r->get_data() ) );
		$id    = $this->latest_submission();
		$files = pediment_form_submission_files( $id );
		$this->assertCount( 1, $files['cv'] );
		$this->assertSame( 'Lebenslauf-Ada-2026.pdf', $files['cv'][0]['name'] );
		$this->assertFileExists( $files['cv'][0]['abs'] );

		$base = pediment_form_files_base_dir();
		$this->assertStringStartsWith( $base . '/', $files['cv'][0]['abs'] );
		$this->assertMatchesRegularExpression( '/pediment-forms-[a-z0-9]{32}$/', $base );
		$this->assertFileExists( $base . '/.htaccess' );
		$this->assertStringContainsString( 'Require all denied', (string) file_get_contents( $base . '/.htaccess' ) );
		$this->assertFileExists( dirname( $files['cv'][0]['abs'] ) . '/.htaccess' );

		$fields = json_decode( (string) get_post_meta( $id, '_fields', true ), true );
		$this->assertSame( 'Lebenslauf-Ada-2026.pdf', $fields['cv']['value'] );
	}

	public function test_mail_attaches_the_files() {
		$this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $this->upload( 'cv.pdf', self::PDF ) ) ) );

		$this->assertSame( 'sent', get_post_meta( $this->latest_submission(), '_delivery_status', true ) );
		$this->assertCount( 1, $this->mail['attachments'] );
		$this->assertSame( 'cv.pdf', basename( $this->mail['attachments'][0] ) );
		$this->assertStringContainsString( 'CV: cv.pdf', $this->mail['message'] );
	}

	public function test_mail_links_instead_of_attaching_when_turned_off() {
		$dests                    = get_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		$dests[0]['attach_files'] = false;
		update_option( PEDIMENT_FORM_DESTINATIONS_OPTION, $dests );

		$this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $this->upload( 'cv.pdf', self::PDF ) ) ) );

		$this->assertSame( array(), $this->mail['attachments'] );
		$this->assertStringContainsString( 'not attached', $this->mail['message'] );
		$this->assertStringContainsString( 'post.php?post=' . $this->latest_submission(), $this->mail['message'] );
	}

	public function test_mail_links_instead_of_attaching_above_the_limit() {
		add_filter( 'pediment_form_mail_attachment_limit', static fn() => 10 );

		$this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $this->upload( 'cv.pdf', self::PDF ) ) ) );

		$this->assertSame( array(), $this->mail['attachments'] );
		$this->assertStringContainsString( 'larger than', $this->mail['message'] );
	}

	public function test_multiple_files_on_a_multiple_field_are_stored() {
		$r = $this->submit(
			array( 'name' => 'Ada' ),
			array(
				'cv'           => array( $this->upload( 'cv.pdf', self::PDF ) ),
				'certificates' => array( $this->upload( 'a.pdf', self::PDF ), $this->upload( 'a.pdf', self::PDF ) ),
			)
		);

		$this->assertSame( 200, $r->get_status() );
		$files = pediment_form_submission_files( $this->latest_submission() );
		$this->assertSame( array( 'a.pdf', 'a-1.pdf' ), wp_list_pluck( $files['certificates'], 'name' ) );
	}

	public function test_text_disguised_as_pdf_is_rejected() {
		$r = $this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $this->upload( 'cv.pdf', "<?php echo 'hi';\n" ) ) ) );

		$this->assertSame( 400, $r->get_status() );
		$this->assertArrayHasKey( 'cv', $r->get_data()['data']['errors'] );
		$this->assertSame( 0, $this->latest_submission() );
	}

	public function test_extension_outside_the_field_list_is_rejected() {
		$r = $this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $this->upload( 'cv.txt', "plain text\n" ) ) ) );
		$this->assertSame( 400, $r->get_status() );
		$this->assertStringContainsString( 'PDF, DOCX', $r->get_data()['data']['errors']['cv'] );
	}

	public function test_file_above_the_field_limit_is_rejected() {
		$upload         = $this->upload( 'cv.pdf', self::PDF );
		$upload['size'] = 2 * MB_IN_BYTES;
		$r              = $this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $upload ) ) );
		$this->assertSame( 400, $r->get_status() );
		$this->assertStringContainsString( 'too large', $r->get_data()['data']['errors']['cv'] );
	}

	public function test_php_size_error_is_reported_as_too_large() {
		$upload          = $this->upload( 'cv.pdf', self::PDF );
		$upload['error'] = UPLOAD_ERR_INI_SIZE;
		$r               = $this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $upload ) ) );
		$this->assertStringContainsString( 'too large', $r->get_data()['data']['errors']['cv'] );
	}

	public function test_missing_required_file_is_rejected() {
		$r = $this->submit( array( 'name' => 'Ada' ), array() );
		$this->assertSame( 400, $r->get_status() );
		$this->assertSame( 'CV is required.', $r->get_data()['data']['errors']['cv'] );
	}

	public function test_two_files_on_a_single_field_are_rejected() {
		$r = $this->submit(
			array( 'name' => 'Ada' ),
			array( 'cv' => array( $this->upload( 'a.pdf', self::PDF ), $this->upload( 'b.pdf', self::PDF ) ) )
		);
		$this->assertSame( 400, $r->get_status() );
		$this->assertArrayHasKey( 'cv', $r->get_data()['data']['errors'] );
	}

	public function test_upload_under_a_non_file_field_is_rejected() {
		$r = $this->submit(
			array( 'name' => 'Ada' ),
			array(
				'cv'   => array( $this->upload( 'cv.pdf', self::PDF ) ),
				'name' => array( $this->upload( 'x.pdf', self::PDF ) ),
			)
		);
		$this->assertSame( 400, $r->get_status() );
		$this->assertSame( 'pediment_unknown_field', $r->get_data()['code'] );
	}

	public function test_file_that_is_no_upload_is_rejected() {
		remove_all_filters( 'pediment_form_is_uploaded_file' );
		$r = $this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $this->upload( 'cv.pdf', self::PDF ) ) ) );
		$this->assertSame( 400, $r->get_status() );
	}

	public function test_deleting_the_submission_deletes_its_files() {
		$this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $this->upload( 'cv.pdf', self::PDF ) ) ) );
		$id   = $this->latest_submission();
		$path = pediment_form_submission_files( $id )['cv'][0]['abs'];

		wp_delete_post( $id, true );

		$this->assertFileDoesNotExist( $path );
		$this->assertDirectoryDoesNotExist( dirname( $path ) );
	}

	public function test_retention_cleanup_deletes_old_files() {
		$this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $this->upload( 'cv.pdf', self::PDF ) ) ) );
		$id   = $this->latest_submission();
		$path = pediment_form_submission_files( $id )['cv'][0]['abs'];
		wp_update_post(
			array(
				'ID'            => $id,
				'post_date'     => '2020-01-01 00:00:00',
				'post_date_gmt' => '2020-01-01 00:00:00',
			)
		);

		pediment_form_cleanup();

		$this->assertNull( get_post( $id ) );
		$this->assertFileDoesNotExist( $path );
	}

	public function test_stored_path_outside_the_private_folder_is_ignored() {
		$id = self::factory()->post->create( array( 'post_type' => PEDIMENT_FORM_CPT ) );
		pediment_form_files_base_dir();
		update_post_meta( $id, PEDIMENT_FORM_FILES_META, wp_slash( wp_json_encode( array( 'cv' => array( array( 'name' => 'x', 'path' => '../../../wp-config.php' ) ) ) ) ) );

		$this->assertSame( array(), pediment_form_submission_files( $id ) );
	}

	public function test_meta_box_links_files_to_the_download() {
		$this->submit( array( 'name' => 'Ada' ), array( 'cv' => array( $this->upload( 'cv.pdf', self::PDF ) ) ) );
		$id = $this->latest_submission();

		ob_start();
		pediment_form_render_submission_box( get_post( $id ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'action=pediment_form_file', $html );
		$this->assertStringContainsString( '>cv.pdf</a>', $html );
		$this->assertStringNotContainsString( 'pediment-forms-', $html );
	}

	public function test_email_destination_attach_files_is_saved_and_checked() {
		$saved = pediment_form_sanitize_destination(
			array(
				'type'         => 'email',
				'id'           => 'x',
				'to'           => 'a@example.com',
				'attach_files' => '0',
			)
		);
		$this->assertFalse( $saved['dest']['attach_files'] );
		$this->assertSame( array(), $saved['errors'] );

		$errors = pediment_form_validate_email_destination(
			array(
				'to'           => 'a@example.com',
				'attach_files' => 'no',
			)
		);
		$this->assertArrayHasKey( 'attach_files', $errors );
	}
}
