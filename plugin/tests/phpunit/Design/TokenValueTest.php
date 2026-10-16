<?php

use Pediment\Design\TokenValue;

class TokenValueTest extends WP_UnitTestCase {

	public function test_tokens_keywords_and_percentages(): void {
		foreach ( array( 'var:preset|spacing|50', 'var(--wp--preset--color--accent)', 'var(--wp--custom--radius--md)', '0', 0, 'inherit', 'currentColor', 'transparent', '50%' ) as $ok ) {
			$this->assertTrue( TokenValue::isToken( $ok ), wp_json_encode( $ok ) );
		}
		foreach ( array( '78px', '#fff', '1.2rem', 'red', 12, true, null ) as $bad ) {
			$this->assertFalse( TokenValue::isToken( $bad ), wp_json_encode( $bad ) );
		}
	}

	public function test_leaves_are_a_list_and_skip_pure_refs(): void {
		$this->assertSame(
			array( array( 'p.top', '1px' ), array( 'p.top.x', '2px' ) ),
			TokenValue::leaves( array( 'top' => '1px', 'top.x' => '2px', 'r' => array( 'ref' => 'styles.x' ) ), 'p' )
		);
	}

	public function test_paths_list_the_token_checked_style_paths_once(): void {
		$this->assertCount( 17, TokenValue::PATHS );
		$this->assertContains( 'spacing.padding', TokenValue::PATHS );
		$this->assertContains( 'filter.duotone', TokenValue::PATHS );
	}
}
