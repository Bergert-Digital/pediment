<?php

use Pediment\Content\Policy;

class LegacyBlocksTest extends WP_UnitTestCase {

	public function test_legacy_blocks_are_hidden_from_the_inserter(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( Policy::LEGACY as $name ) {
			$type = $registry->get_registered( $name );
			$this->assertNotNull( $type, $name );
			$this->assertFalse( $type->supports['inserter'] ?? true, $name );
		}
	}

	public function test_functional_blocks_stay_in_the_inserter(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( array( 'pediment/form', 'pediment/slider', 'pediment/blog-index', 'pediment/social-links', 'pediment/icon' ) as $name ) {
			$this->assertNotFalse( $registry->get_registered( $name )->supports['inserter'] ?? true, $name );
		}
	}

	public function test_legacy_blocks_still_render(): void {
		$html = do_blocks( '<!-- wp:pediment/pull-quote {"quote":"Still here"} /-->' );
		$this->assertStringContainsString( 'starter-pull-quote', $html );
		$this->assertStringContainsString( 'Still here', $html );
	}

	public function test_other_supports_are_kept(): void {
		$supports = WP_Block_Type_Registry::get_instance()->get_registered( 'pediment/hero' )->supports;
		$this->assertSame( array( 'wide', 'full' ), $supports['align'] );
	}
}
