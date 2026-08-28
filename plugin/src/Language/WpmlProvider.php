<?php
/**
 * WPML implementation of the seeding engine's language seam.
 *
 * Everything WPML-specific in this product lives here, in WpmlSetup, and in
 * inc/wpml-compat.php. Nothing else may call a wpml_* or icl_* function, or
 * read an ICL_* constant.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WpmlProvider implements LanguageProvider {
	/**
	 * "WPML is active" is not enough, mirroring PolylangProvider: an
	 * installed-but-unconfigured WPML returns no active languages, and a seeder
	 * crossed with zero languages writes nothing while reporting success.
	 */
	public static function isActive(): bool {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return false;
		}
		$active = apply_filters( 'wpml_active_languages', null );

		return is_array( $active ) && [] !== $active;
	}

	/** Whether the WPML plugin is loaded (regardless of whether languages are configured yet). */
	public static function isLoaded(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' );
	}

	/**
	 * Configured language codes, default first — the order DesiredState and
	 * Applier depend on (a default that is not first writes children before
	 * their parent exists).
	 *
	 * @return string[]
	 */
	public function languages(): array {
		$active = (array) apply_filters( 'wpml_active_languages', null );
		$codes  = array_values( array_map( 'strval', array_keys( $active ) ) );

		$default = $this->defaultLanguage();
		if ( '' === $default ) {
			return $codes;
		}

		$rest = array_values( array_filter( $codes, static fn( string $c ): bool => $c !== $default ) );

		return array_merge( [ $default ], $rest );
	}

	public function defaultLanguage(): string {
		return (string) apply_filters( 'wpml_default_language', null );
	}

	public function currentLanguage(): string {
		return (string) apply_filters( 'wpml_current_language', null );
	}

	/**
	 * WPML resolves `get_permalink()` against its CURRENT language context, so
	 * a default-language (en) seed writes English URLs into German nav items
	 * (Finding 2). Switch the ambient language to $language around the call,
	 * then restore it.
	 *
	 * `wpml_switch_language` is WPML's own public action (inc/template-functions.php
	 * `wpml_switch_language_action()` → `SitePress::switch_lang()`); it is the same
	 * call WPML's switcher makes. We capture the current code first and switch
	 * back to it — never leaving the request in the target language.
	 */
	public function permalinkInLanguage( int $postId, string $language ): string {
		if ( $postId <= 0 || '' === $language ) {
			return (string) get_permalink( $postId );
		}

		$previous = $this->currentLanguage();

		do_action( 'wpml_switch_language', $language );
		try {
			return (string) get_permalink( $postId );
		} finally {
			// try/finally so the restore ALWAYS fires: if get_permalink() (or a
			// filter it triggers) throws, skipping the restore would leave WPML
			// stuck in $language for the rest of the seed run, so every later nav
			// item and anything reading the ambient language resolves wrong. A
			// null code restores WPML's original request language; pass the
			// captured code when we have one, else fall back to that reset.
			do_action( 'wpml_switch_language', '' !== $previous ? $previous : null );
		}
	}

	/**
	 * Whether a post carries a language assignment at all — the untagged-post
	 * signal Applier's repair relies on. WPML returns null for an element it
	 * has never seen.
	 */
	public function hasLanguage( int $postId ): bool {
		if ( $postId <= 0 ) {
			return false;
		}
		$code = apply_filters(
			'wpml_element_language_code',
			null,
			[ 'element_id' => $postId, 'element_type' => 'post_' . get_post_type( $postId ) ]
		);

		return null !== $code && '' !== (string) $code;
	}

	public function translationOf( int $postId, string $language ): int {
		if ( $postId <= 0 || '' === $language ) {
			return 0;
		}
		$translated = apply_filters(
			'wpml_object_id',
			$postId,
			get_post_type( $postId ),
			false, // return null, not the original, when the translation is absent.
			$language
		);

		return null === $translated ? 0 : (int) $translated;
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public function unscopedQuery( array $args ): array {
		// WPML scopes through the posts_* filters, which suppress_filters=true
		// turns off (the reason PolylangProvider::unscopedQuery already sets it).
		$args['suppress_filters'] = true;

		return $args;
	}

	public function setLanguage( int $postId, string $language ): void {
		if ( $postId <= 0 || '' === $language ) {
			return;
		}
		$type = 'post_' . get_post_type( $postId );

		// Reuse an existing trid so re-tagging a post keeps its group; false
		// tells WPML to mint a new translation group.
		$trid = apply_filters( 'wpml_element_trid', null, $postId, $type );

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'           => $postId,
				'element_type'         => $type,
				'trid'                 => $trid ?: false,
				'language_code'        => $language,
				'source_language_code' => null,
			]
		);
	}

	/**
	 * @param array<string,int> $map language code => post ID
	 */
	public function linkTranslations( array $map ): void {
		$clean = array_filter(
			$map,
			static fn( $postId, $language ): bool => is_int( $postId ) && $postId > 0 && '' !== $language,
			ARRAY_FILTER_USE_BOTH
		);

		if ( count( $clean ) < 2 ) {
			return;
		}

		// Anchor the group on one member's trid (the default language's when
		// present, else the first), then re-register every other member onto
		// that same trid with a source language. This is WPML's equivalent of
		// Polylang's "replace the whole group".
		$default    = $this->defaultLanguage();
		$anchorLang = isset( $clean[ $default ] ) ? $default : (string) array_key_first( $clean );
		$anchorId   = (int) $clean[ $anchorLang ];
		$anchorType = 'post_' . get_post_type( $anchorId );

		$trid = apply_filters( 'wpml_element_trid', null, $anchorId, $anchorType );
		if ( ! $trid ) {
			// Anchor must belong to a group first; assign it, then re-read.
			do_action(
				'wpml_set_element_language_details',
				[
					'element_id'           => $anchorId,
					'element_type'         => $anchorType,
					'trid'                 => false,
					'language_code'        => $anchorLang,
					'source_language_code' => null,
				]
			);
			$trid = apply_filters( 'wpml_element_trid', null, $anchorId, $anchorType );
		}

		foreach ( $clean as $language => $postId ) {
			if ( $language === $anchorLang ) {
				continue;
			}
			do_action(
				'wpml_set_element_language_details',
				[
					'element_id'           => (int) $postId,
					'element_type'         => 'post_' . get_post_type( (int) $postId ),
					'trid'                 => $trid,
					'language_code'        => (string) $language,
					'source_language_code' => $anchorLang,
				]
			);
		}
	}

	/**
	 * WPML's native `wpml/language-switcher` block is dynamic (server-rendered by
	 * WPML\BlockEditor\Blocks\LanguageSwitcher\Render), but its render callback is
	 * a *template filler*, not a generator: Parser::parse() returns null the
	 * moment the block's saved HTML is empty, and Render.php then fatals with
	 * "getCurrentLanguageItemTemplate() on null". A bare `<!-- wp:wpml/language-switcher /-->`
	 * therefore crashes the front end wherever it renders (confirmed on WPML 4.9.7;
	 * see tests/wpml/WPML-API-REFERENCE.md). The block only renders when its saved
	 * HTML carries the `data-wpml` item template Render clones per active language.
	 *
	 * So we emit the block WITH that saved template markup. WPML fills in each
	 * language's href, native name and aria-label at render time from its own
	 * Repository, so this markup is language- and settings-agnostic: two active
	 * languages or ten, en+de or any other pair, the same template renders the
	 * live switcher. A shortcode block (`[wpml_language_switcher]`) is not an
	 * option here — the switcher is seeded INSIDE a core/navigation (a template
	 * part), which renders via do_blocks() without the the_content do_shortcode
	 * pass, so the shortcode would survive as literal text.
	 *
	 * The block takes no meaningful per-instance attributes for our purposes, so
	 * the manifest's `language_switcher` override — if any — has nothing to apply
	 * to; every truthy config emits the same working switcher.
	 *
	 * @param bool|array<string,mixed> $config
	 */
	public function languageSwitcherBlock( $config ): string {
		$template = '<div class="wpml-ls wpml-ls-legacy-list-horizontal">'
			. '<ul>'
			. '<li data-wpml="current-language-item" class="wpml-ls-item wpml-ls-current-language">'
			. '<a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a>'
			. '</li>'
			. '<li data-wpml="language-item" class="wpml-ls-item">'
			. '<a data-wpml="link" href="#"><span data-wpml="label" data-wpml-label-type="native"></span></a>'
			. '</li>'
			. '</ul>'
			. '</div>';

		return '<!-- wp:wpml/language-switcher -->' . $template . '<!-- /wp:wpml/language-switcher -->';
	}
}
