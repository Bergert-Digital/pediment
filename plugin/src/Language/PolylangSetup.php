<?php
/**
 * Reconcile Polylang's own settings against the manifest's `languages`.
 *
 * Deliberately NOT part of a seed run: phase 4 must stay inspectable by
 * --dry-run, and writing another plugin's settings inside it is not. This runs
 * from `wp pediment languages`, before any content is written (spec §4.3).
 *
 * Polylang's free build ships no WP-CLI commands, so everything goes through
 * the PLL() API.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

use Pediment\Seeder\LanguageSpec;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PolylangSetup {
	/**
	 * @param array<string,LanguageSpec> $languages Declaration order, default first.
	 * @return array{changes:string[],errors:string[]}
	 */
	public function configure( array $languages, string $default, bool $dryRun = false ): array {
		$changes = [];
		$errors  = [];

		if ( ! function_exists( 'PLL' ) || ! PLL() ) {
			return [ 'changes' => [], 'errors' => [ 'Polylang is not active — install and activate it, or remove the manifest\'s `languages` section.' ] ];
		}
		if ( [] === $languages ) {
			return [ 'changes' => [], 'errors' => [ 'The manifest declares no languages — nothing to configure.' ] ];
		}

		$model    = PLL()->model;
		$existing = wp_list_pluck( $model->get_languages_list(), 'slug' );

		$index = 0;
		foreach ( $languages as $spec ) {
			if ( in_array( $spec->slug, $existing, true ) ) {
				++$index;
				continue;
			}

			$changes[] = sprintf( 'create language %s (%s, %s)', $spec->slug, $spec->name, $spec->locale );

			if ( $dryRun ) {
				++$index;
				continue;
			}

			// term_group is how Polylang orders languages, and the manifest's
			// declaration order is the one the site owner reasoned about.
			$added = $model->add_language(
				[
					'slug'       => $spec->slug,
					'name'       => $spec->name,
					'locale'     => $spec->locale,
					'flag'       => $spec->flag,
					'rtl'        => 0,
					'term_group' => $index,
				]
			);
			if ( is_wp_error( $added ) ) {
				$errors[] = sprintf( 'languages.%s: %s', $spec->slug, $added->get_error_message() );
			}
			++$index;
		}

		if ( ! $dryRun ) {
			$model->clean_languages_cache();
		}

		$options = PLL()->options;
		$desired = [
			'default_lang'  => $default,

			// wp_navigation must be translatable or a menu cannot exist per
			// language — and it can never be ticked by hand: Polylang's settings
			// screen lists only post types registered `public => true` and
			// `_builtin => false`, and wp_navigation is neither.
			'post_types'    => array_values( array_unique( array_merge( (array) $options['post_types'], [ 'wp_navigation' ] ) ) ),

			// One attachment and one term set serve every language. MediaMap
			// keys media globally and the engine's terms are create-only, so
			// per-language copies would drift with nothing to reconcile them.
			'media_support' => 0,
			'taxonomies'    => [],

			// Serve each language at its own root: /de/, not /de/startseite/.
			// Polylang defaults redirect_lang to 0, which makes a language's home
			// URL the permalink of its translated front page — every /de/ request
			// then 301s to /de/startseite/, and hreflang, the switcher and every
			// menu home link follow it there.
			'redirect_lang' => 1,

			// The default language keeps unprefixed URLs, which is what existing
			// single-language sites (and the e2e suite) already assume.
			'hide_default'  => 1,
		];

		$diff = [];
		foreach ( $desired as $key => $value ) {
			$current = $options[ $key ];

			// media_support/redirect_lang/hide_default round-trip through
			// Polylang's own boolean option type (Options\Primitive\Abstract_Boolean),
			// which normalizes the 0/1 this array (and Polylang's historic wire
			// format) uses into a real PHP bool on the way out of get(). Left
			// alone, `(array) false !== (array) 0` is true forever — every
			// already-configured site would report these three as changed on
			// every single call, which is not idempotent, it just always writes.
			if ( is_bool( $current ) ) {
				$current = (int) $current;
			}

			if ( (array) $current !== (array) $value ) {
				$diff[] = sprintf( 'set %s', $key );
			}
		}
		$changes = array_merge( $changes, $diff );

		if ( ! $dryRun && [] !== $diff ) {
			// Polylang's own sanitizer for the `post_types` option
			// (Options\Business\Post_Types::get_object_types(), reached from
			// every set()/merge() call, not just the settings screen) accepts
			// only post types registered `_builtin => false`. WordPress core
			// registers wp_navigation with `_builtin => true`
			// (wp-includes/post.php, create_initial_post_types()), so a plain
			// merge() silently intersects it straight back out — the write
			// reports success and stores everything except the one entry this
			// method exists to add. Flip WordPress's own registered-post-type
			// object for the span of this single write, so Polylang's sanitizer
			// accepts the value being asked of it, then restore it immediately:
			// nothing outside these three lines observes the flip, and every
			// other `_builtin` check WordPress core makes for wp_navigation
			// (admin menus, capability mapping, REST) keeps seeing `true`.
			$navigationType = get_post_type_object( 'wp_navigation' );
			$wasBuiltin     = $navigationType->_builtin ?? null;
			if ( null !== $navigationType ) {
				$navigationType->_builtin = false;
			}

			// Written through Polylang's own options object, never
			// update_option(). Since 3.7 Polylang holds its options in memory and
			// flushes them on `shutdown`: a raw write is invisible to the rest of
			// this process AND gets overwritten by the stale in-memory copy at
			// the end of it. merge() also applies keys in Polylang's registration
			// order, which matters because some options validate against others.
			$saved = $options->merge( $desired );

			if ( null !== $navigationType ) {
				$navigationType->_builtin = $wasBuiltin;
			}

			if ( is_wp_error( $saved ) && $saved->has_errors() ) {
				$errors[] = 'Polylang rejected an option write — ' . implode( '; ', $saved->get_error_messages() );
			}
			$options->save();

			// Every language object caches the home URL derived from those
			// options, and that cache outlives the write. Without dropping it, a
			// re-run saves the new setting and still serves the old URLs — which
			// reads exactly like "the fix did not work".
			$model->clean_languages_cache();
		}

		return [ 'changes' => $changes, 'errors' => $errors ];
	}
}
