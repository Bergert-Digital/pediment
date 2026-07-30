<?php

class StepsTest extends WP_UnitTestCase {
	public function test_steps_wraps_step_children() {
		$html = do_blocks(
			'<!-- wp:pediment/steps -->' .
			'<!-- wp:pediment/step {"title":"Diagnose","text":"Size it"} /-->' .
			'<!-- wp:pediment/step {"title":"Deliver","text":"Ship it"} /-->' .
			'<!-- /wp:pediment/steps -->'
		);
		$this->assertStringContainsString( 'starter-steps', $html );
		$this->assertStringContainsString( 'starter-step__num', $html );
		$this->assertStringContainsString( 'Diagnose', $html );
		$this->assertStringContainsString( 'Deliver', $html );
		$this->assertSame( 2, substr_count( $html, 'starter-step__title' ) );
	}

	public function test_step_skips_empty() {
		$html = do_blocks( '<!-- wp:pediment/step {"title":"","text":""} /-->' );
		$this->assertStringNotContainsString( 'starter-step__title', $html );
	}
}
