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

	public function test_script_in_a_static_block_is_an_error(): void {
		$result = $this->check( '<!-- wp:paragraph --><p>Hi<script>alert(1)</script></p><!-- /wp:paragraph -->' );
		$this->assertTrue( $this->has( $result['errors'], 'core/paragraph: <script> is not allowed in block markup' ), wp_json_encode( $result['errors'] ) );
	}

	public function test_script_in_a_containers_own_markup_is_an_error(): void {
		$result = $this->check( '<!-- wp:group {"metadata":{"name":"Intro"}} --><div class="wp-block-group"><SCRIPT>alert(1)</SCRIPT><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group -->' );
		$this->assertTrue( $this->has( $result['errors'], 'core/group: <script> is not allowed in block markup' ), wp_json_encode( $result['errors'] ) );
	}

	public function test_other_dangerous_tags_are_errors(): void {
		foreach ( array( 'style', 'iframe', 'object', 'embed' ) as $tag ) {
			$result = $this->check( "<!-- wp:paragraph --><p>x</p><{$tag} src=\"https://x.test\"><!-- /wp:paragraph -->" );
			$this->assertTrue( $this->has( $result['errors'], "<{$tag}> is not allowed" ), $tag );
		}
	}

	public function test_event_handler_attributes_are_errors(): void {
		$result = $this->check( '<!-- wp:paragraph --><p onclick="steal()">x</p><!-- /wp:paragraph -->' );
		$this->assertTrue( $this->has( $result['errors'], 'core/paragraph: onclick attribute is not allowed' ), wp_json_encode( $result['errors'] ) );
	}

	public function test_javascript_urls_are_errors(): void {
		$result = $this->check( '<!-- wp:paragraph --><p><a href=" JaVa&#x09;script:alert(1)">x</a></p><!-- /wp:paragraph -->' );
		$this->assertTrue( $this->has( $result['errors'], 'core/paragraph: javascript: URL is not allowed in block markup' ), wp_json_encode( $result['errors'] ) );
	}

	public function test_inline_literal_styles_are_errors_and_presets_pass(): void {
		$bad = $this->check( '<!-- wp:paragraph --><p style="color:#ff0000;font-size:78px;display:block">Hi</p><!-- /wp:paragraph -->' );
		$this->assertTrue( $this->has( $bad['errors'], 'core/paragraph: inline style color:#ff0000' ), wp_json_encode( $bad['errors'] ) );
		$this->assertTrue( $this->has( $bad['errors'], 'inline style font-size:78px' ), wp_json_encode( $bad['errors'] ) );
		$this->assertFalse( $this->has( $bad['errors'], 'display' ) );

		$ok = $this->check( '<!-- wp:paragraph --><p style="color:var(--wp--preset--color--accent);font-size:var(--wp--preset--font-size--large);padding:var(--wp--preset--spacing--30) 0;background-image:url(data:image/png;base64,AAA);border-radius:50%">Hi</p><!-- /wp:paragraph -->' );
		$this->assertSame( array(), $ok['errors'] );
	}

	public function test_inline_literal_gradient_and_shadow_are_errors(): void {
		$result = $this->check( '<!-- wp:group {"metadata":{"name":"X"}} --><div class="wp-block-group" style="background:linear-gradient(135deg,#fff,#000);box-shadow:0 0 4px red"></div><!-- /wp:group -->' );
		$this->assertTrue( $this->has( $result['errors'], 'inline style background:linear-gradient' ), wp_json_encode( $result['errors'] ) );
		$this->assertTrue( $this->has( $result['errors'], 'inline style box-shadow:0 0 4px red' ), wp_json_encode( $result['errors'] ) );
	}

	public function test_literal_duotone_is_an_error_and_a_preset_passes(): void {
		$bad = $this->check( '<!-- wp:group {"metadata":{"name":"X"},"style":{"color":{"duotone":["#000000","#ffffff"]}}} --><div class="wp-block-group"></div><!-- /wp:group -->' );
		$this->assertTrue( $this->has( $bad['errors'], 'style.color.duotone' ), wp_json_encode( $bad['errors'] ) );

		$ok = $this->check( '<!-- wp:group {"metadata":{"name":"X"},"style":{"color":{"duotone":"var:preset|duotone|dark-grayscale"}}} --><div class="wp-block-group"></div><!-- /wp:group -->' );
		$this->assertSame( array(), $ok['errors'] );
	}

	public function test_unknown_style_color_and_spacing_keys_are_checked(): void {
		$result = $this->check( '<!-- wp:group {"metadata":{"name":"X"},"style":{"color":{"made-up":"#123456"},"spacing":{"made-up":"9px"}}} --><div class="wp-block-group"></div><!-- /wp:group -->' );
		$this->assertTrue( $this->has( $result['errors'], 'style.color.made-up' ) );
		$this->assertTrue( $this->has( $result['errors'], 'style.spacing.made-up' ) );
	}

	public function test_filter_cannot_allow_core_html_or_non_strings(): void {
		add_filter( 'pediment_allowed_blocks', static fn( array $core ): array => array_merge( $core, array( 'core/html', array( 'core/table' ), 12 ) ) );
		$allowed = Policy::allowedBlocks();
		$this->assertNotContains( 'core/html', $allowed );
		$this->assertContains( 'core/paragraph', $allowed );
		$result = $this->check( '<!-- wp:html --><p>x</p><!-- /wp:html -->' );
		$this->assertTrue( $this->has( $result['errors'], 'core/html: block is not allowed' ) );
	}

	public function test_unterminated_trailing_tag_cannot_hide_an_event_handler(): void {
		$result = $this->check( '<!-- wp:paragraph --><p>x</p><img src=x onerror=alert(1) <!-- /wp:paragraph --><!-- wp:paragraph --><p>y</p><!-- /wp:paragraph -->' );
		$this->assertTrue( $this->has( $result['errors'], 'core/paragraph: onerror attribute is not allowed' ), wp_json_encode( $result['errors'] ) );
	}

	public function test_noscript_parsing_differential_is_an_error(): void {
		$result = $this->check( '<!-- wp:paragraph --><noscript><p title="</noscript><img src=x onerror=alert(1)>"></p></noscript><!-- /wp:paragraph -->' );
		$this->assertTrue( $this->has( $result['errors'], '<noscript> is not allowed' ), wp_json_encode( $result['errors'] ) );
		$this->assertTrue( $this->has( $result['errors'], 'onerror attribute is not allowed' ), wp_json_encode( $result['errors'] ) );
	}

	public function test_javascript_in_any_attribute_is_an_error(): void {
		$payloads = array(
			'<a xlink:href="javascript:alert(1)">x</a>',
			'<a action=" &#106;avascript&colon;alert(1)">x</a>',
			'<button formaction="java&NewLine;script:alert(1)">x</button>',
		);
		foreach ( $payloads as $payload ) {
			$result = $this->check( "<!-- wp:paragraph --><p>{$payload}</p><!-- /wp:paragraph -->" );
			$this->assertTrue( $this->has( $result['errors'], 'javascript: URL is not allowed' ), $payload );
		}
	}

	public function test_form_svg_and_other_active_tags_are_errors(): void {
		foreach ( array( 'svg', 'math', 'form', 'base', 'meta', 'link', 'template', 'frame', 'frameset', 'applet', 'noscript' ) as $tag ) {
			$result = $this->check( "<!-- wp:paragraph --><p>x</p><{$tag}><!-- /wp:paragraph -->" );
			$this->assertTrue( $this->has( $result['errors'], "<{$tag}> is not allowed" ), $tag );
		}
		$result = $this->check( '<!-- wp:paragraph --><svg><animate attributeName=href values=javascript:alert(1) /><set attributeName=onclick to=alert(1) /></svg><!-- /wp:paragraph -->' );
		$this->assertTrue( $this->has( $result['errors'], '<svg> is not allowed' ) );
		$this->assertTrue( $this->has( $result['errors'], 'javascript: URL is not allowed' ) );
	}

	public function test_unterminated_trailing_tag_cannot_hide_a_literal_inline_style(): void {
		$result = $this->check( '<!-- wp:paragraph --><p>x</p><img src=x style="color:#ff0000;font-size:78px <!-- /wp:paragraph -->' );
		$this->assertTrue( $this->has( $result['errors'], 'inline style color:#ff0000' ), wp_json_encode( $result['errors'] ) );
		$result = $this->check( '<!-- wp:paragraph --><p style=padding:9px>x</p><!-- /wp:paragraph -->' );
		$this->assertTrue( $this->has( $result['errors'], 'inline style padding:9px' ), wp_json_encode( $result['errors'] ) );
		$result = $this->check( '<!-- wp:paragraph --><p style="color:&#35;f00">x</p><!-- /wp:paragraph -->' );
		$this->assertTrue( $this->has( $result['errors'], 'inline style color:#f00' ), wp_json_encode( $result['errors'] ) );
	}

	public function test_normal_core_markup_with_presets_has_no_false_positives(): void {
		$result = $this->check(
			'<!-- wp:group {"metadata":{"name":"Hero"},"className":"is-style-band","style":{"spacing":{"padding":{"top":"var:preset|spacing|50"}},"color":{"background":"var:preset|color|base"}}} -->'
			. '<div class="wp-block-group is-style-band has-background" style="background-color:var(--wp--preset--color--base);padding-top:var(--wp--preset--spacing--50)">'
			. '<!-- wp:cover {"url":"/wp-content/uploads/a.jpg","dimRatio":50,"metadata":{"name":"Cover"}} --><div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="/wp-content/uploads/a.jpg" data-object-fit="cover"/><div class="wp-block-cover__inner-container">'
			. '<!-- wp:paragraph {"style":{"typography":{"fontSize":"var:preset|font-size|large"}}} --><p style="font-size:var(--wp--preset--font-size--large)">Welcome online. Style it.</p><!-- /wp:paragraph -->'
			. '</div></div><!-- /wp:cover -->'
			. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.com/?a=1&amp;online=1">Go</a></div><!-- /wp:button --></div><!-- /wp:buttons -->'
			. '<!-- wp:image {"id":3} --><figure class="wp-block-image"><img src="/wp-content/uploads/b.jpg" alt="B" class="wp-image-3"/></figure><!-- /wp:image -->'
			. '</div><!-- /wp:group -->'
		);
		$this->assertSame( array(), $result['errors'] );
		$this->assertSame( array(), $result['warnings'] );
	}

	public function test_legacy_blocks_in_markup(): void {
		$this->assertSame(
			array( 'pediment/faq', 'pediment/hero' ),
			( new Policy() )->legacyBlocksIn( '<!-- wp:pediment/hero /--><!-- wp:group --><div class="wp-block-group"><!-- wp:pediment/faq /--></div><!-- /wp:group --><!-- wp:pediment/hero /-->' )
		);
	}
}
