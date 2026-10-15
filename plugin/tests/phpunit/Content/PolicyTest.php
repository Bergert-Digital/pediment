<?php

use Pediment\Content\Policy;

class PolicyTest extends WP_UnitTestCase {

	private function check( string $markup ): array {
		return ( new Policy() )->check( $markup );
	}

	private function has( array $messages, string $needle ): bool {
		return array() !== array_filter( $messages, static fn( string $m ): bool => str_contains( $m, $needle ) );
	}

	public function test_clean_core_markup_passes(): void {
		$result = $this->check(
			"<!-- wp:group {\"metadata\":{\"name\":\"Intro\"},\"className\":\"is-style-band\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"var:preset|spacing|50\"}}}} -->\n<div class=\"wp-block-group is-style-band\" style=\"padding-top:var(--wp--preset--spacing--50)\"><!-- wp:paragraph -->\n<p>Hi</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->"
		);
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array(), $result['warnings'] );
	}

	public function test_html_block_and_unregistered_blocks_are_errors(): void {
		$result = $this->check( "<!-- wp:html --><script>alert(1)</script><!-- /wp:html --><!-- wp:acme/nope /-->" );
		$this->assertTrue( $this->has( $result['errors'], 'core/html: block is not allowed' ) );
		$this->assertTrue( $this->has( $result['errors'], 'acme/nope: block type is not registered' ) );
	}

	public function test_loose_content_is_an_error(): void {
		$result = $this->check( "<script>alert(1)</script>\n<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->" );
		$this->assertTrue( $this->has( $result['errors'], 'content outside a block' ) );
	}

	public function test_literal_design_values_are_errors(): void {
		$result = $this->check(
			'<!-- wp:group {"style":{"spacing":{"padding":{"top":"78px"}},"color":{"background":"#ffffff"},"border":{"radius":"22px"},"elements":{"link":{"color":{"text":"#a9621f"},":hover":{"color":{"text":"red"}}}}}} --><div class="wp-block-group"></div><!-- /wp:group -->'
		);
		foreach ( array( 'style.spacing.padding.top', 'style.color.background', 'style.border.radius', 'style.elements.link.color.text', 'style.elements.link.:hover.color.text' ) as $path ) {
			$this->assertTrue( $this->has( $result['errors'], $path ), $path );
		}
	}

	public function test_unknown_style_class_is_an_error_and_known_ones_pass(): void {
		$result = $this->check( '<!-- wp:group {"className":"is-style-nope"} --><div class="wp-block-group is-style-nope"></div><!-- /wp:group --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button">Go</a></div><!-- /wp:button -->' );
		$this->assertTrue( $this->has( $result['errors'], 'is-style-nope is not a registered style for core/group' ) );
		$this->assertFalse( $this->has( $result['errors'], 'is-style-outline' ) );
	}

	public function test_bad_comment_json_is_an_error(): void {
		$result = $this->check( '<!-- wp:group {"a":} --><div class="wp-block-group"></div><!-- /wp:group -->' );
		$this->assertTrue( $this->has( $result['errors'], 'core/group: block comment attributes are not valid JSON' ) );
	}

	public function test_warnings_for_unnamed_containers_external_images_and_legacy_blocks(): void {
		$result = $this->check( '<!-- wp:group --><div class="wp-block-group"><!-- wp:image --><figure class="wp-block-image"><img src="https://elsewhere.example/a.jpg" alt=""/></figure><!-- /wp:image --></div><!-- /wp:group --><!-- wp:pediment/section-head /-->' );
		$this->assertSame( array(), $result['errors'] );
		$this->assertTrue( $this->has( $result['warnings'], 'core/group: give it a readable metadata.name' ) );
		$this->assertTrue( $this->has( $result['warnings'], 'elsewhere.example' ) );
		$this->assertTrue( $this->has( $result['warnings'], 'pediment/section-head: legacy block' ) );
	}

	public function test_allowed_blocks_filter_and_functional_blocks(): void {
		add_filter( 'pediment_allowed_blocks', static fn( array $core ): array => array_merge( $core, array( 'core/table' ) ) );
		$allowed = Policy::allowedBlocks();
		$this->assertContains( 'core/table', $allowed );
		$this->assertContains( 'core/paragraph', $allowed );
		$this->assertContains( 'pediment/form', $allowed );
		$this->assertNotContains( 'core/html', $allowed );
		$this->assertNotContains( 'pediment/hero', $allowed );
	}

	public function test_legacy_blocks_in_markup(): void {
		$this->assertSame(
			array( 'pediment/faq', 'pediment/hero' ),
			( new Policy() )->legacyBlocksIn( '<!-- wp:pediment/hero /--><!-- wp:group --><div class="wp-block-group"><!-- wp:pediment/faq /--></div><!-- /wp:group --><!-- wp:pediment/hero /-->' )
		);
	}
}
