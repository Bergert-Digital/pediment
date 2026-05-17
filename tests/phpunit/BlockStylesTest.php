<?php

class BlockStylesTest extends WP_UnitTestCase {
	private function styles_for( string $block ): array {
		$reg = WP_Block_Styles_Registry::get_instance();
		return array_keys( $reg->get_registered_styles_for_block( $block ) );
	}

	public function test_group_band_styles_registered() {
		do_action( 'init' );
		$names = $this->styles_for( 'core/group' );
		$this->assertContains( 'band-surface', $names );
		$this->assertContains( 'band-navy', $names );
	}
}
