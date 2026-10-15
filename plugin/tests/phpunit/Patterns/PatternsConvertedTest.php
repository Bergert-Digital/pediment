<?php

/**
 * Plugin patterns ship core blocks only, so new sites get no legacy blocks.
 */
class PatternsConvertedTest extends WP_UnitTestCase {

	private const PAGE_PATTERNS = array( 'pediment/pediment-landing', 'pediment/hero-cta-faq', 'pediment/prose-article', 'pediment/contact-page' );

	public function test_plugin_patterns_hold_no_legacy_block(): void {
		do_action( 'init' );
		$policy = new \Pediment\Content\Policy();
		$seen   = 0;
		foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			if ( str_starts_with( $pattern['name'], 'pediment/' ) ) {
				++$seen;
				$this->assertSame( array(), $policy->legacyBlocksIn( $pattern['content'] ), $pattern['name'] );
			}
		}
		$this->assertGreaterThanOrEqual( 6, $seen );
	}

	public function test_page_patterns_pass_the_content_rules(): void {
		do_action( 'init' );
		$registry = WP_Block_Patterns_Registry::get_instance();
		$policy   = new \Pediment\Content\Policy();
		foreach ( self::PAGE_PATTERNS as $name ) {
			$this->assertSame( array(), $policy->check( $registry->get_registered( $name )['content'] )['errors'], $name );
		}
	}
}
