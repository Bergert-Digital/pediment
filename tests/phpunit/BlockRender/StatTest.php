<?php

class StatTest extends WP_UnitTestCase {
	public function test_renders_value_and_label() {
		$html = do_blocks( '<!-- wp:starter/stat {"value":"99%","label":"Uptime"} /-->' );
		$this->assertStringContainsString( '99%', $html );
		$this->assertStringContainsString( 'Uptime', $html );
	}

	public function test_renders_context_when_provided() {
		$html = do_blocks( '<!-- wp:starter/stat {"value":"10x","label":"Faster","context":"vs industry"} /-->' );
		$this->assertStringContainsString( 'vs industry', $html );
	}

	public function test_omits_context_when_empty() {
		$html = do_blocks( '<!-- wp:starter/stat {"value":"5","label":"x"} /-->' );
		$this->assertStringNotContainsString( 'starter-stat__context', $html );
	}
}
