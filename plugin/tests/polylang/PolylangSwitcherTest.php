<?php

use Pediment\Language\PolylangProvider;

class PolylangSwitcherTest extends PolylangTestCase {

	public function test_switcher_block_matches_the_historic_polylang_string() {
		$block = ( new PolylangProvider() )->languageSwitcherBlock( true );
		$this->assertSame(
			'<!-- wp:polylang/navigation-language-switcher {"dropdown":true} /-->',
			$block
		);
	}

	public function test_switcher_block_merges_array_overrides() {
		$block = ( new PolylangProvider() )->languageSwitcherBlock( [ 'dropdown' => false, 'showFlags' => true ] );
		$this->assertSame(
			'<!-- wp:polylang/navigation-language-switcher {"dropdown":false,"showFlags":true} /-->',
			$block
		);
	}

	public function test_current_language_reads_polylang() {
		// Default language configured by the harness is 'en'.
		$this->assertSame( 'en', ( new PolylangProvider() )->currentLanguage() );
	}
}
