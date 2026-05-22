<?php

class FeatureGridTest extends WP_UnitTestCase {
	public function test_grid_wraps_features_with_icon_title_link() {
		$html = do_blocks(
			'<!-- wp:pediment/feature-grid -->' .
			'<!-- wp:pediment/feature {"icon":"gear","title":"Ops","text":"Run it","linkText":"More","linkUrl":"/ops"} /-->' .
			'<!-- wp:pediment/feature {"icon":"stack","title":"Digital","text":"Ship it"} /-->' .
			'<!-- /wp:pediment/feature-grid -->'
		);
		$this->assertStringContainsString( 'starter-feature-grid', $html );
		$this->assertStringContainsString( 'starter-feature', $html );
		$this->assertStringContainsString( 'Ops', $html );
		$this->assertStringContainsString( 'Digital', $html );
		$this->assertStringContainsString( 'href="/ops"', $html );
		$this->assertStringContainsString( 'href="#ph-gear"', $html );
		$this->assertStringContainsString( 'href="#ph-stack"', $html );
	}

	public function test_feature_omits_link_when_url_missing() {
		$html = do_blocks(
			'<!-- wp:pediment/feature {"title":"T","text":"D","linkText":"More","linkUrl":""} /-->'
		);
		$this->assertStringNotContainsString( 'starter-feature__more', $html );
	}
}
