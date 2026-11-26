<?php

use Pediment\Seeder\Meta;

/**
 * pediment/legal-links links the seeded legal pages by seed key.
 */
class LegalLinksTest extends WP_UnitTestCase {

	private function page( string $key, string $title, string $status = 'publish' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
		update_post_meta( $id, Meta::KEY, $key );
		return $id;
	}

	private function render( string $attrs = '' ): string {
		return do_blocks( '<!-- wp:pediment/legal-links ' . $attrs . ' /-->' );
	}

	public function test_renders_nothing_when_no_legal_page_exists(): void {
		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_links_the_impressum_and_the_privacy_policy_in_order(): void {
		$privacy = $this->page( 'privacy', 'Datenschutz' );
		$notice  = $this->page( 'legal-notice', 'Impressum' );

		$html = $this->render();

		$this->assertStringContainsString( '<nav ', $html );
		$this->assertStringContainsString( 'aria-label="Legal"', $html );
		$this->assertStringContainsString( 'class="wp-block-pediment-legal-links', $html );
		$this->assertStringContainsString( '<a href="' . esc_url( get_permalink( $notice ) ) . '">Impressum</a>', $html );
		$this->assertStringContainsString( '<a href="' . esc_url( get_permalink( $privacy ) ) . '">Datenschutz</a>', $html );
		$this->assertLessThan( strpos( $html, 'Datenschutz' ), strpos( $html, 'Impressum' ) );
	}

	public function test_skips_unpublished_pages_and_pages_without_the_key(): void {
		$this->page( 'legal-notice', 'Impressum', 'draft' );
		self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Privacy policy' ) );

		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_custom_keys_and_duplicates(): void {
		$terms = $this->page( 'terms', 'AGB' );

		$html = $this->render( '{"keys":["terms","terms","missing",""]}' );

		$this->assertSame( 1, substr_count( $html, '<li>' ) );
		$this->assertStringContainsString( esc_url( get_permalink( $terms ) ), $html );
	}

	public function test_marks_the_current_page(): void {
		$notice = $this->page( 'legal-notice', 'Impressum' );
		$this->go_to( get_permalink( $notice ) );

		$this->assertStringContainsString( 'aria-current="page">Impressum</a>', $this->render() );
	}

	public function test_escapes_the_title(): void {
		$this->page( 'legal-notice', 'Impressum <script>x</script> & Co' );

		$html = $this->render();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'Impressum', $html );
	}

	public function test_hostile_keys_are_ignored(): void {
		$this->page( 'legal-notice', 'Impressum' );
		$this->page( 'terms', 'AGB' );

		$this->assertSame( '', trim( $this->render( '{"keys":"terms"}' ) ) );

		$html = $this->render( '{"keys":[["terms"],42,"legal-notice"]}' );
		$this->assertSame( 1, substr_count( $html, '<li>' ) );
		$this->assertStringContainsString( '>Impressum</a>', $html );
	}
}
