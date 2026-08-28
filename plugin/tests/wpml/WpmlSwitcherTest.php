<?php

use Pediment\Language\WpmlProvider;

class WpmlSwitcherTest extends WpmlTestCase {

	public function test_switcher_emits_the_native_attributeless_wpml_block() {
		$this->assertSame(
			'<!-- wp:wpml/language-switcher /-->',
			( new WpmlProvider() )->languageSwitcherBlock( true )
		);
	}

	public function test_array_config_still_emits_the_bare_block() {
		// WPML's block accepts no attributes; a manifest override cannot change it.
		$this->assertSame(
			'<!-- wp:wpml/language-switcher /-->',
			( new WpmlProvider() )->languageSwitcherBlock( [ 'dropdown' => false ] )
		);
	}
}
