<?php

use Pediment\Language\WpmlProvider;

class WpmlSwitcherTest extends WpmlTestCase {

	/**
	 * The renderable form. A bare `<!-- wp:wpml/language-switcher /-->` fatals on
	 * the WPML front end (Parser::parse() returns null for empty saved HTML,
	 * Render.php then dereferences it). The block only renders when its saved HTML
	 * carries the `data-wpml` item template WPML clones per active language, so the
	 * provider emits the block WITH that template. Verified by rendering on the
	 * live WPML env (see tests/wpml/WPML-API-REFERENCE.md).
	 */
	private const RENDERABLE_SWITCHER =
		'<!-- wp:wpml/language-switcher -->'
		. '<div class="wpml-ls wpml-ls-legacy-list-horizontal">'
		. '<ul>'
		. '<li data-wpml="current-language-item" class="wpml-ls-item wpml-ls-current-language">'
		. '<a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a>'
		. '</li>'
		. '<li data-wpml="language-item" class="wpml-ls-item">'
		. '<a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a>'
		. '</li>'
		. '</ul>'
		. '</div>'
		. '<!-- /wp:wpml/language-switcher -->';

	public function test_switcher_emits_the_renderable_wpml_block_with_saved_template() {
		$this->assertSame(
			self::RENDERABLE_SWITCHER,
			( new WpmlProvider() )->languageSwitcherBlock( true )
		);
	}

	public function test_array_config_still_emits_the_same_renderable_block() {
		// WPML's block renders from its saved template + its own settings; a
		// manifest override cannot change it, so both config forms yield the same
		// working switcher.
		$this->assertSame(
			self::RENDERABLE_SWITCHER,
			( new WpmlProvider() )->languageSwitcherBlock( [ 'dropdown' => false ] )
		);
	}
}
