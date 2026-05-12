<?php

class CtaTest extends WP_UnitTestCase {
	private function render( array $attrs ): string {
		return do_blocks( '<!-- wp:starter/cta ' . wp_json_encode( $attrs ) . ' /-->' );
	}

	public function test_renders_title_body_and_primary_button() {
		$html = $this->render(
			array(
				'title'       => 'Ready?',
				'body'        => 'Let us help.',
				'primaryText' => 'Start',
				'primaryUrl'  => '/start',
			)
		);
		$this->assertStringContainsString( 'Ready?', $html );
		$this->assertStringContainsString( 'Let us help.', $html );
		$this->assertStringContainsString( 'href="/start"', $html );
	}

	public function test_renders_secondary_button_when_provided() {
		$html = $this->render(
			array(
				'title'         => 'Ready?',
				'primaryText'   => 'Start',
				'primaryUrl'    => '/start',
				'secondaryText' => 'Learn more',
				'secondaryUrl'  => '/about',
			)
		);
		$this->assertStringContainsString( 'href="/about"', $html );
		$this->assertStringContainsString( 'Learn more', $html );
	}

	public function test_omits_secondary_button_when_url_missing() {
		$html = $this->render(
			array(
				'title'         => 'X',
				'primaryText'   => 'A',
				'primaryUrl'    => '/a',
				'secondaryText' => 'B',
				'secondaryUrl'  => '',
			)
		);
		$this->assertStringNotContainsString( '>B<', $html );
	}
}
