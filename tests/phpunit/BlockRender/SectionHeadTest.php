<?php

class SectionHeadTest extends WP_UnitTestCase {
	private function render( array $attrs ): string {
		return do_blocks( '<!-- wp:starter/section-head ' . wp_json_encode( $attrs ) . ' /-->' );
	}

	public function test_block_is_registered() {
		do_action( 'init' );
		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( 'starter/section-head' )
		);
	}

	public function test_renders_root_class() {
		$html = $this->render( array( 'eyebrow' => 'X', 'headline' => 'Y', 'lead' => 'Z' ) );
		$this->assertStringContainsString( 'starter-section-head', $html );
	}

	public function test_renders_all_three_fields() {
		$html = $this->render(
			array(
				'eyebrow'  => 'What we do',
				'headline' => 'Our services',
				'lead'     => 'A short description.',
			)
		);
		$this->assertStringContainsString( '<p class="starter-section-head__eyebrow">What we do</p>', $html );
		$this->assertStringContainsString( '<h2 class="starter-section-head__headline">Our services</h2>', $html );
		$this->assertStringContainsString( '<p class="starter-section-head__lead">A short description.</p>', $html );
	}

	public function test_level_3_renders_h3() {
		$html = $this->render( array( 'headline' => 'Sub', 'level' => 3 ) );
		$this->assertStringContainsString( '<h3 class="starter-section-head__headline">Sub</h3>', $html );
	}

	public function test_alignment_start_emits_is_alignment_start() {
		$html = $this->render( array( 'headline' => 'X', 'alignment' => 'start' ) );
		$this->assertStringContainsString( 'is-alignment-start', $html );
		$this->assertStringNotContainsString( 'is-alignment-center', $html );
	}

	public function test_alignment_center_emits_is_alignment_center() {
		$html = $this->render( array( 'headline' => 'X', 'alignment' => 'center' ) );
		$this->assertStringContainsString( 'is-alignment-center', $html );
		$this->assertStringNotContainsString( 'is-alignment-start', $html );
	}

	public function test_empty_eyebrow_is_suppressed() {
		$html = $this->render( array( 'eyebrow' => '', 'headline' => 'Y', 'lead' => 'Z' ) );
		$this->assertStringNotContainsString( 'starter-section-head__eyebrow', $html );
		$this->assertStringContainsString( 'starter-section-head__headline', $html );
	}

	public function test_empty_headline_is_suppressed() {
		$html = $this->render( array( 'eyebrow' => 'X', 'headline' => '', 'lead' => 'Z' ) );
		$this->assertStringNotContainsString( 'starter-section-head__headline', $html );
		$this->assertStringContainsString( 'starter-section-head__lead', $html );
	}

	public function test_empty_lead_is_suppressed() {
		$html = $this->render( array( 'eyebrow' => 'X', 'headline' => 'Y', 'lead' => '' ) );
		$this->assertStringNotContainsString( 'starter-section-head__lead', $html );
		$this->assertStringContainsString( 'starter-section-head__headline', $html );
	}

	public function test_inner_column_wraps_fields() {
		$html = $this->render( array( 'headline' => 'X' ) );
		$this->assertStringContainsString( '<div class="starter-section-head__inner">', $html );
	}
}
