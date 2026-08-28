<?php

use Pediment\Language\WpmlProvider;

class WpmlSwitcherTest extends WpmlTestCase {

	/**
	 * The DEFAULT renderable form: a hover-to-reveal dropdown. A bare
	 * `<!-- wp:wpml/language-switcher /-->` fatals on the WPML front end
	 * (Parser::parse() returns null for empty saved HTML, Render.php then
	 * dereferences it), so the provider emits the block WITH the `data-wpml` item
	 * template WPML clones per active language. The dropdown behaviour is expressed
	 * in the saved markup's structure + CSS classes (`wpml-ls-dropdown`
	 * `open-on-hover-click`, a `wp-block-navigation-submenu__toggle` for the current
	 * language, and a `wp-block-navigation__submenu-container` for the others), not
	 * in block attributes. `wpml-language-switcher-block` wraps `wpml-ls-dropdown`
	 * so the block's front-end hover CSS matches. Verified by rendering on the live
	 * WPML env in both language contexts (see tests/wpml/WPML-API-REFERENCE.md).
	 */
	private const DROPDOWN_SWITCHER =
		'<!-- wp:wpml/language-switcher -->'
		. '<div class="wpml-language-switcher-block wpml-ls">'
		. '<div class="wpml-ls-dropdown open-on-hover-click">'
		. '<ul class="wp-block-navigation__container">'
		. '<li class="wp-block-navigation-item has-child wp-block-navigation-submenu open-on-hover-click">'
		. '<div class="wp-block-navigation-item__content wp-block-navigation-submenu__toggle" aria-expanded="false" aria-haspopup="true" aria-controls="wpml-ls-submenu-default" tabindex="0">'
		. '<span data-wpml="current-language-item" class="wpml-ls-item wpml-ls-current-language current-language-item">'
		. '<a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a>'
		. '</span>'
		. '</div>'
		. '<ul id="wpml-ls-submenu-default" class="wp-block-navigation__submenu-container">'
		. '<li data-wpml="language-item" class="wpml-ls-item wp-block-navigation-item">'
		. '<a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a>'
		. '</li>'
		. '</ul>'
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
