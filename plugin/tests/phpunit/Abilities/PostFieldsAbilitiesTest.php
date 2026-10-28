<?php

require_once __DIR__ . '/AbilityTestCase.php';

use Pediment\Abilities\FeaturedImage;

/**
 * write-page fields beyond title and markup: excerpt, menu_order and the
 * featured image (imported from a URL or picked from the media library).
 */
class PostFieldsAbilitiesTest extends AbilityTestCase {

	private const GOOD = "<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->";
	private const URL  = 'https://images.example.test/photos/lake.jpg';

	/**
	 * URLs the stubbed HTTP layer was asked for.
	 *
	 * @var list<string>
	 */
	private array $fetched = array();

	/**
	 * File the stubbed HTTP layer serves.
	 */
	private string $serve = '';

	public function set_up(): void {
		parent::set_up();
		// Shaped like workation's wc_activity: no revisions, menu order, thumbnail, excerpt.
		register_post_type( 'activity', array( 'public' => true, 'show_in_rest' => true, 'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ) ) );
		$this->serve = DIR_TESTDATA . '/images/canola.jpg';
		add_filter( 'pre_http_request', array( $this, 'stub_http' ), 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'stub_http' ), 10 );
		unregister_post_type( 'activity' );
		parent::tear_down();
	}

	/**
	 * Serves $this->serve for every request instead of the network.
	 *
	 * @param false|array|WP_Error $pre  Short-circuit value.
	 * @param array                $args Request args.
	 * @param string               $url  URL.
	 * @return array|WP_Error
	 */
	public function stub_http( $pre, $args, $url ) {
		$this->fetched[] = $url;
		if ( '' === $this->serve ) {
			return new WP_Error( 'http_request_failed', 'Could not resolve host' );
		}
		copy( $this->serve, $args['filename'] );
		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => $args['filename'],
		);
	}

	private function attachments(): int {
		return count( get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => -1, 'fields' => 'ids' ) ) );
	}

	private function activities(): int {
		return (int) ( new WP_Query( array( 'post_type' => 'activity', 'post_status' => 'any' ) ) )->found_posts;
	}

	private function write( array $input ) {
		return $this->run_as( $this->user( 'editor' ), 'pediment/write-page', array_merge( array( 'post_type' => 'activity', 'title' => 'Swimming', 'markup' => self::GOOD ), $input ) );
	}

	public function test_excerpt_and_menu_order_are_written_and_read(): void {
		$result = $this->write( array( 'excerpt' => 'Swim in the lake.', 'menu_order' => 110, 'status' => 'publish' ) );
		$this->assertIsArray( $result );
		$this->assertSame( 0, $result['featured_image'] );

		$read = $this->run_as( $this->user( 'editor' ), 'pediment/read-page', array( 'post_type' => 'activity', 'id' => $result['id'] ) );
		$this->assertSame( 'Swim in the lake.', $read['excerpt'] );
		$this->assertSame( 110, $read['menu_order'] );
		$this->assertNull( $read['featured_image'] );
	}

	public function test_excerpt_with_markup_is_refused_with_the_other_violations(): void {
		$result = $this->write( array( 'excerpt' => 'Swim <img src=x onerror=alert(1)>', 'markup' => "<!-- wp:html -->\n<p>raw</p>\n<!-- /wp:html -->" ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_content_invalid', $result->get_error_code() );
		$this->assertStringContainsString( 'excerpt: markup is not allowed', $result->get_error_message() );
		$this->assertStringContainsString( 'core/html: block is not allowed', $result->get_error_message() );
		$this->assertSame( 0, $this->activities() );
	}

	public function test_fields_the_type_does_not_support_are_refused_together(): void {
		$page = $this->run_as( $this->user( 'editor' ), 'pediment/write-page', array( 'title' => 'P', 'markup' => self::GOOD, 'excerpt' => 'x' ) );
		$this->assertWPError( $page );
		$this->assertSame( 'pediment_field_unsupported', $page->get_error_code() );
		$this->assertStringContainsString( 'excerpt: post_type "page" does not support excerpt', $page->get_error_message() );

		$post = $this->run_as( $this->user( 'editor' ), 'pediment/write-page', array( 'post_type' => 'post', 'title' => 'P', 'markup' => self::GOOD, 'menu_order' => 3 ) );
		$this->assertWPError( $post );
		$this->assertStringContainsString( 'menu_order: post_type "post" does not support page-attributes', $post->get_error_message() );

		register_post_type( 'note', array( 'public' => true, 'show_in_rest' => true, 'supports' => array( 'title', 'editor' ) ) );
		$note = $this->run_as( $this->user( 'editor' ), 'pediment/write-page', array( 'post_type' => 'note', 'title' => 'N', 'markup' => self::GOOD, 'featured_image' => array( 'url' => self::URL ) ) );
		unregister_post_type( 'note' );
		$this->assertWPError( $note );
		$this->assertStringContainsString( 'featured_image: post_type "note" has no featured image', $note->get_error_message() );

		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$bad     = $this->write(
			array( 'featured_image' => array( 'url' => 'ftp://images.example.test/a.jpg', 'id' => $page_id, 'alt' => '<b>x</b>' ) )
		);
		$this->assertWPError( $bad );
		$this->assertSame( 'pediment_field_unsupported', $bad->get_error_code() );
		foreach ( array( 'pass exactly one of url or id', 'use an http or https URL', "{$page_id} is not an image", 'featured_image.alt: markup is not allowed' ) as $needle ) {
			$this->assertStringContainsString( $needle, $bad->get_error_message() );
		}
		$this->assertSame( array(), $this->fetched, 'nothing was downloaded' );
		$this->assertSame( 0, $this->activities() );
	}

	public function test_an_image_url_is_imported_once_and_each_post_keeps_its_alt_text(): void {
		$before = $this->attachments();
		$first  = $this->write( array( 'featured_image' => array( 'url' => self::URL, 'alt' => 'A lake in the hills' ) ) );
		$this->assertIsArray( $first );
		$image = $first['featured_image'];
		$this->assertGreaterThan( 0, $image );
		$this->assertTrue( wp_attachment_is_image( $image ) );
		$this->assertSame( self::URL, get_post_meta( $image, FeaturedImage::SOURCE_META, true ) );
		$this->assertSame( 'A lake in the hills', get_post_meta( $image, '_wp_attachment_image_alt', true ), 'a new image gets the first alt text' );
		$this->assertSame( array( self::URL ), $this->fetched );

		$second = $this->write( array( 'title' => 'Schwimmen', 'featured_image' => array( 'url' => self::URL, 'alt' => 'Ein See in den Huegeln' ) ) );
		$this->assertSame( $image, $second['featured_image'], 'the same URL reuses the attachment' );
		$this->assertSame( array( self::URL ), $this->fetched, 'and downloads nothing' );
		$this->assertSame( $before + 1, $this->attachments() );
		$this->assertSame( 'A lake in the hills', get_post_meta( $image, '_wp_attachment_image_alt', true ), 'the attachment alt stays' );

		$this->assertStringContainsString( 'alt="A lake in the hills"', get_the_post_thumbnail( $first['id'] ) );
		$this->assertStringContainsString( 'alt="Ein See in den Huegeln"', get_the_post_thumbnail( $second['id'] ) );
		$this->assertStringContainsString( 'alt="A lake in the hills"', wp_get_attachment_image( $image ), 'outside a post thumbnail the attachment alt applies' );

		$read = $this->run_as( $this->user( 'editor' ), 'pediment/read-page', array( 'post_type' => 'activity', 'id' => $second['id'] ) );
		$this->assertSame( $image, $read['featured_image']['id'] );
		$this->assertSame( 'Ein See in den Huegeln', $read['featured_image']['alt'] );
		$this->assertSame( self::URL, $read['featured_image']['source'] );
		$this->assertSame( wp_get_attachment_url( $image ), $read['featured_image']['url'] );
	}

	public function test_a_url_without_extension_is_named_by_its_image_type(): void {
		$result = $this->write( array( 'featured_image' => array( 'url' => 'https://images.example.test/photos/123/photo?auto=compress&w=1200' ) ) );
		// wp_unique_filename() adds -2, -3 … when an earlier run left photo.jpg in uploads.
		$this->assertMatchesRegularExpression( '/^photo(-\d+)?\.jpg$/', wp_basename( get_attached_file( $result['featured_image'] ) ) );
	}

	public function test_a_file_that_is_not_an_image_writes_nothing(): void {
		$text = get_temp_dir() . 'pediment-not-an-image.jpg';
		file_put_contents( $text, 'hello' );
		$this->serve = $text;
		$before      = $this->attachments();

		$result = $this->write( array( 'featured_image' => array( 'url' => self::URL ) ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_featured_image_failed', $result->get_error_code() );
		$this->assertStringContainsString( 'is not a JPEG, PNG, GIF, WebP or AVIF image', $result->get_error_message() );
		$this->assertSame( 0, $this->activities() );
		$this->assertSame( $before, $this->attachments() );
	}

	public function test_a_failed_download_writes_nothing_and_names_the_url(): void {
		$this->serve = '';
		$result      = $this->write( array( 'featured_image' => array( 'url' => self::URL ) ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_featured_image_failed', $result->get_error_code() );
		$this->assertStringContainsString( 'could not download ' . self::URL, $result->get_error_message() );
		$this->assertSame( 0, $this->activities() );
	}

	public function test_a_media_library_image_is_set_by_id_and_removed_with_zero(): void {
		$image  = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$result = $this->write( array( 'featured_image' => array( 'id' => $image, 'alt' => 'Canola' ) ) );
		$this->assertSame( $image, $result['featured_image'] );
		$this->assertSame( array(), $this->fetched );

		$kept = $this->write( array( 'id' => $result['id'], 'title' => 'No image field' ) );
		$this->assertSame( $image, $kept['featured_image'], 'a write without featured_image leaves it as it is' );
		$this->assertSame( 'Canola', get_post_meta( $result['id'], FeaturedImage::ALT_META, true ) );

		$removed = $this->write( array( 'id' => $result['id'], 'featured_image' => array( 'id' => 0 ) ) );
		$this->assertSame( 0, $removed['featured_image'] );
		$this->assertSame( '', get_post_meta( $result['id'], FeaturedImage::ALT_META, true ) );
	}

	public function test_importing_needs_upload_rights_but_picking_an_id_does_not(): void {
		$user = $this->user( 'editor' );
		get_userdata( $user )->add_cap( 'upload_files', false );
		$this->assertFalse( user_can( $user, 'upload_files' ) );

		$refused = $this->run_as( $user, 'pediment/write-page', array( 'post_type' => 'activity', 'title' => 'S', 'markup' => self::GOOD, 'featured_image' => array( 'url' => self::URL ) ) );
		$this->assertWPError( $refused );
		$this->assertSame( 'ability_invalid_permissions', $refused->get_error_code() );
		$this->assertSame( array(), $this->fetched );

		$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$ok    = $this->run_as( $user, 'pediment/write-page', array( 'post_type' => 'activity', 'title' => 'S', 'markup' => self::GOOD, 'featured_image' => array( 'id' => $image ) ) );
		$this->assertSame( $image, $ok['featured_image'] );
	}
}
