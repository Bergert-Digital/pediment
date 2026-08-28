<?php

use Pediment\Language\WpmlProvider;

class WpmlSwitcherTest extends WpmlTestCase {

	/**
	 * The DEFAULT renderable form: a compact toggle (the current language) with a
	 * hover/focus dropdown panel of the languages you can switch to, opened BELOW
	 * the toggle. A bare `<!-- wp:wpml/language-switcher /-->` fatals on the WPML
	 * front end (Parser::parse() returns null for empty saved HTML, Render.php then
	 * dereferences it), so the provider emits the block WITH the `data-wpml` item
	 * template WPML clones per active language. WPML fills the current language
	 * exactly once (two current-language-item nodes fatal in its Parser — see
	 * tests/wpml/WPML-API-REFERENCE.md), so the single `current-language-item` is
	 * the toggle and the `language-item` (cloned per non-current language) fills
	 * the panel. The plugin's front-end CSS (assets/css/theme.css) styles
	 * `.wpml-ls-toggle` as the always-visible control and `.wpml-ls-panel` as an
	 * absolutely-positioned card that opens on hover/focus, so the header never
	 * reflows. Verified by rendering on the live WPML env in both language
	 * contexts.
	 */
	private const DROPDOWN_SWITCHER =
		'<!-- wp:wpml/language-switcher -->'
		. '<div class="wpml-language-switcher-block wpml-ls">'
		. '<span data-wpml="current-language-item" class="wpml-ls-current-language wpml-ls-toggle" tabindex="0">'
		. '<a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a>'
		. '</span>'
		. '<ul class="wpml-ls-panel">'
		. '<li data-wpml="language-item" class="wpml-ls-item">'
		. '<a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a>'
		. '</li>'
		. '</ul>'
		. '</div>'
		. '<!-- /wp:wpml/language-switcher -->';

	/**
	 * The opt-out form: the original flat horizontal list, emitted only for
	 * `['dropdown' => false]`. Still the renderable native block WITH its
	 * `data-wpml` item template.
	 */
	private const FLAT_LIST_SWITCHER =
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

	public function test_default_true_config_emits_the_hover_dropdown_block() {
		$this->assertSame(
			self::DROPDOWN_SWITCHER,
			( new WpmlProvider() )->languageSwitcherBlock( true )
		);
	}

	public function test_dropdown_true_array_config_emits_the_hover_dropdown_block() {
		$this->assertSame(
			self::DROPDOWN_SWITCHER,
			( new WpmlProvider() )->languageSwitcherBlock( [ 'dropdown' => true ] )
		);
	}

	public function test_array_config_without_a_dropdown_key_still_emits_the_dropdown() {
		// Any other array keys are ignored; the dropdown is the default.
		$this->assertSame(
			self::DROPDOWN_SWITCHER,
			( new WpmlProvider() )->languageSwitcherBlock( [ 'unknown' => 'value' ] )
		);
	}

	public function test_dropdown_false_config_opts_out_to_the_flat_list() {
		$this->assertSame(
			self::FLAT_LIST_SWITCHER,
			( new WpmlProvider() )->languageSwitcherBlock( [ 'dropdown' => false ] )
		);
	}
}
