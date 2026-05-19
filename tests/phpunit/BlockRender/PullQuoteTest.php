<?php

class PullQuoteTest extends WP_UnitTestCase {
	private function render( array $attrs ): string {
		$block_markup = '<!-- wp:starter/pull-quote ' . wp_json_encode( $attrs ) . ' /-->';
		return do_blocks( $block_markup );
	}

	public function test_renders_quote_and_citation() {
		$html = do_blocks( '<!-- wp:starter/pull-quote {"quote":"To be or not to be","citation":"Hamlet"} /-->' );
		$this->assertStringContainsString( '<blockquote', $html );
		$this->assertStringContainsString( 'To be or not to be', $html );
		$this->assertStringContainsString( '<cite', $html );
		$this->assertStringContainsString( 'Hamlet', $html );
	}

	public function test_omits_cite_when_citation_empty() {
		$html = do_blocks( '<!-- wp:starter/pull-quote {"quote":"Just a quote","citation":""} /-->' );
		$this->assertStringContainsString( 'Just a quote', $html );
		$this->assertStringNotContainsString( '<cite', $html );
	}

	public function test_block_json_variant_enum_is_exact_and_renderable() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/pull-quote/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertSame(
			array( 'default', 'testimonial' ),
			$data['attributes']['variant']['enum'],
			'block.json variant enum must list exactly the variants the renderer ships'
		);
		$html = $this->render( array( 'variant' => 'testimonial', 'quote' => 'Q' ) );
		$this->assertStringContainsString( 'is-variant-testimonial', $html );
	}

	public function test_block_json_description_mentions_testimonial() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/pull-quote/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertStringContainsStringIgnoringCase( 'testimonial', (string) $data['description'] );
	}

	public function test_default_variant_markup_unchanged() {
		$html = $this->render(
			array( 'variant' => 'default', 'quote' => 'Plain quote', 'citation' => 'Someone' )
		);
		$this->assertStringContainsString( 'is-variant-default', $html );
		$this->assertStringContainsString( '<blockquote', $html );
		$this->assertStringContainsString( 'Plain quote', $html );
		$this->assertStringContainsString( '<cite', $html );
		$this->assertStringNotContainsString( '<figure', $html );
		$this->assertStringNotContainsString( 'starter-pull-quote__by', $html );
		$this->assertStringNotContainsString( 'starter-pull-quote__avatar', $html );
	}

	public function test_testimonial_renders_quote_name_and_role() {
		$html = $this->render(
			array(
				'variant'    => 'testimonial',
				'quote'      => 'They stayed until it worked.',
				'authorName' => 'Sarah Klein',
				'authorRole' => 'Group COO, Vantage Industries',
			)
		);
		$this->assertStringContainsString( 'is-variant-testimonial', $html );
		$this->assertStringContainsString( '<figure', $html );
		$this->assertStringContainsString( 'starter-pull-quote__by', $html );
		$this->assertStringContainsString( 'They stayed until it worked.', $html );
		$this->assertStringContainsString( 'Sarah Klein', $html );
		$this->assertStringContainsString( 'Group COO, Vantage Industries', $html );
		$this->assertStringContainsString( 'starter-pull-quote__name', $html );
		$this->assertStringContainsString( 'starter-pull-quote__role', $html );
	}

	public function test_testimonial_omits_avatar_when_id_zero() {
		$html = $this->render(
			array(
				'variant'    => 'testimonial',
				'quote'      => 'No avatar here.',
				'authorName' => 'No Pic',
				'avatarId'   => 0,
			)
		);
		$this->assertStringContainsString( 'is-variant-testimonial', $html );
		$this->assertStringNotContainsString( 'starter-pull-quote__avatar', $html );
		$this->assertStringContainsString( 'No Pic', $html );
	}

	public function test_testimonial_renders_avatar_when_id_set() {
		$attachment_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/canola.jpg'
		);
		$html = $this->render(
			array(
				'variant'    => 'testimonial',
				'quote'      => 'With a face.',
				'authorName' => 'Has Pic',
				'avatarId'   => $attachment_id,
			)
		);
		$this->assertStringContainsString( 'starter-pull-quote__avatar', $html );
		$this->assertStringContainsString( '<img', $html );
		wp_delete_attachment( $attachment_id, true );
	}

	public function test_starter_pull_quote_variants_filter_is_default_superset() {
		$this->assertTrue( function_exists( 'starter_pull_quote_variants' ) );
		$this->assertSame(
			array( 'default', 'testimonial' ),
			starter_pull_quote_variants()
		);
	}

	public function test_filter_removing_testimonial_falls_back_to_default() {
		$cb = static function ( $variants ) {
			return array_values( array_diff( $variants, array( 'testimonial' ) ) );
		};
		add_filter( 'starter_pull_quote_variants', $cb );
		try {
			$html = $this->render(
				array(
					'variant'    => 'testimonial',
					'quote'      => 'Filtered.',
					'authorName' => 'X',
				)
			);
			$this->assertStringContainsString( 'is-variant-default', $html );
			$this->assertStringNotContainsString( 'is-variant-testimonial', $html );
			$this->assertStringNotContainsString( 'starter-pull-quote__by', $html );
		} finally {
			remove_filter( 'starter_pull_quote_variants', $cb );
		}
	}
}
