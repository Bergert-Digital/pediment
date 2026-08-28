<?php

use Pediment\Language\WpmlProvider;

class WpmlSwitcherTest extends WpmlTestCase {

	/**
	 * The DEFAULT renderable form: a hover-to-reveal dropdown that lists EVERY
	 * language. A bare `<!-- wp:wpml/language-switcher /-->` fatals on the WPML
	 * front end (Parser::parse() returns null for empty saved HTML, Render.php then
	 * dereferences it), so the provider emits the block WITH the `data-wpml` item
	 * template WPML clones per active language. WPML fills the current language
	 * exactly once, so to list ALL languages the markup is a SINGLE list
	 * (`ul.wpml-ls-menu`) carrying one `current-language-item` and one
	 * `language-item` template: Render fills the current and clones the language
	 * item per non-current language, so the list ends up with every active
	 * language. The current language carries `wpml-ls-current-language`, which the
	 * plugin's front-end CSS uses to make it the always-visible toggle and reveal
	 * the rest on hover. Verified by rendering on the live WPML env in both
	 * language contexts (see tests/wpml/WPML-API-REFERENCE.md).
	 */
	private const DROPDOWN_SWITCHER =
		'<!-- wp:wpml/language-switcher -->'
		. '<div class="wpml-language-switcher-block wpml-ls">'
		. '<div class="wpml-ls-dropdown open-on-hover-click">'
		. '<ul class="wpml-ls-menu">'
		. '<li data-wpml="current-language-item" class="wpml-ls-item wpml-ls-current-language">'
		. '<a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a>'
		. '</li>'
		. '<li data-wpml="language-item" class="wpml-ls-item">'
		. '<a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a>'
		. '</li>'
		. '</ul>'
		. '</div>'
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
