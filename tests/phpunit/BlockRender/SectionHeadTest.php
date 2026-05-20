<?php

class SectionHeadTest extends WP_UnitTestCase {
	private function render( array $attrs ): string {
		return do_blocks( '<!-- wp:starter/section-head ' . wp_json_encode( $attrs ) . ' /-->' );
	}

	public function test_block_is_registered() {
		do_action( 'init' );
		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( 'starter/section-head' ),
			'starter/section-head must auto-register from build/blocks/'
		);
	}

	public function test_renders_root_class() {
		$html = $this->render( array( 'eyebrow' => 'X', 'headline' => 'Y', 'lead' => 'Z' ) );
		$this->assertStringContainsString( 'starter-section-head', $html );
	}
}
