<?php

class SocialLinksTest extends WP_UnitTestCase {
	/**
	 * Render the block with the given links as block attributes.
	 *
	 * @param array<int, array<string, string>> $links
	 */
	private function render( array $links = array() ): string {
		if ( empty( $links ) ) {
			return do_blocks( '<!-- wp:pediment/social-links /-->' );
		}
		$attrs = wp_json_encode( array( 'links' => $links ) );
		return do_blocks( '<!-- wp:pediment/social-links ' . $attrs . ' /-->' );
	}

	public function test_returns_empty_string_when_no_links_configured() {
		$html = $this->render();
		$this->assertSame( '', trim( $html ) );
	}

	public function test_renders_one_anchor_per_configured_link() {
		$html = $this->render(
			array(
				array( 'platform' => 'twitter', 'url' => 'https://twitter.com/x' ),
				array( 'platform' => 'github',  'url' => 'https://github.com/x' ),
			)
		);
		// Two <a> elements, one per configured link.
		$this->assertSame( 2, substr_count( $html, '<a ' ) );
		$this->assertStringContainsString( 'href="https://twitter.com/x"', $html );
		$this->assertStringContainsString( 'href="https://github.com/x"', $html );
	}

	public function test_known_platform_renders_inline_svg_icon() {
		$html = $this->render(
			array( array( 'platform' => 'github', 'url' => 'https://github.com/x' ) )
		);
		$this->assertStringContainsString( '<span class="starter-social-links__icon" aria-hidden="true">', $html );
		$this->assertStringContainsString( '<svg', $html );
		$this->assertStringContainsString( '<title>GitHub</title>', $html );
		$this->assertStringNotContainsString( '<span class="starter-social-links__label">', $html );
	}

	public function test_unknown_platform_renders_text_label_fallback_with_ucfirst() {
		$html = $this->render(
			array( array( 'platform' => 'bluesky', 'url' => 'https://bsky.app/profile/x' ) )
		);
		$this->assertStringContainsString( '<span class="starter-social-links__label">Bluesky</span>', $html );
		$this->assertStringNotContainsString( '<svg', $html );
	}

	public function test_twitter_and_x_aliases_render_the_same_icon() {
		$html = $this->render(
			array(
				array( 'platform' => 'twitter', 'url' => 'https://twitter.com/x' ),
				array( 'platform' => 'x',       'url' => 'https://x.com/x' ),
			)
		);
		// Both entries should produce an <svg> with the X path data.
		$this->assertSame( 2, substr_count( $html, '<svg' ) );
		// Both titles read "X (Twitter)" per the Simple Icons canonical title.
		$this->assertSame( 2, substr_count( $html, '<title>X (Twitter)</title>' ) );
	}

	public function test_skips_entries_with_empty_platform_or_url() {
		$html = $this->render(
			array(
				array( 'platform' => 'github',   'url' => 'https://github.com/x' ),
				array( 'platform' => '',         'url' => 'https://example.com' ),  // empty platform
				array( 'platform' => 'linkedin', 'url' => '' ),                       // empty url
				array( 'platform' => 'youtube',  'url' => 'https://youtube.com/@x' ),
			)
		);
		$this->assertSame( 2, substr_count( $html, '<a ' ), 'only github and youtube should render — empty fields skipped' );
	}

	public function test_each_anchor_has_rel_noopener_noreferrer() {
		$html = $this->render(
			array( array( 'platform' => 'github', 'url' => 'https://github.com/x' ) )
		);
		$this->assertStringContainsString( 'rel="noopener noreferrer"', $html );
	}

	public function test_each_anchor_has_aria_label_matching_platform() {
		$html = $this->render(
			array(
				array( 'platform' => 'github',   'url' => 'https://github.com/x' ),
				array( 'platform' => 'linkedin', 'url' => 'https://linkedin.com/in/x' ),
			)
		);
		$this->assertStringContainsString( 'aria-label="GitHub"', $html );
		$this->assertStringContainsString( 'aria-label="LinkedIn"', $html );
	}
}
