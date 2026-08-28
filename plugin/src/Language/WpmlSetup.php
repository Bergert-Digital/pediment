<?php
/**
 * Reconcile WPML's own settings against the manifest's `languages`. The WPML
 * analogue of PolylangSetup; runs from `wp pediment languages`, never inside a
 * seed. All WPML-specific writes are quarantined here.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WpmlSetup implements LanguageSetup {
	/**
	 * @param array<string,\Pediment\Seeder\LanguageSpec> $languages Declaration order, default first.
	 * @return array{changes:string[],errors:string[]}
	 */
	public function configure( array $languages, string $default, bool $dryRun = false ): array {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return [ 'changes' => [], 'errors' => [ 'WPML is not active — install and activate it, or remove the manifest\'s `languages` section.' ] ];
		}
		if ( [] === $languages ) {
			return [ 'changes' => [], 'errors' => [ 'The manifest declares no languages — nothing to configure.' ] ];
		}

		$changes = [];
		$errors  = [];

		$active  = (array) apply_filters( 'wpml_active_languages', null );
		$current = array_keys( $active );

		$wanted = [];
		foreach ( $languages as $spec ) {
			$wanted[] = $spec->slug;
			if ( ! in_array( $spec->slug, $current, true ) ) {
				$changes[] = sprintf( 'activate language %s (%s)', $spec->slug, $spec->locale );
			}
		}

		if ( (string) apply_filters( 'wpml_default_language', null ) !== $default ) {
			$changes[] = sprintf( 'set default language %s', $default );
		}

		if ( $dryRun || [] === $changes ) {
			return [ 'changes' => $changes, 'errors' => $errors ];
		}

		// Confirmed activation path (tests/wpml/WPML-API-REFERENCE.md): a raw
		// icl_sitepress_settings write does NOT flip the `active` flag in
		// wp_icl_languages, so wpml_active_languages stays empty. WPML's own
		// setup instance is what actually activates a language — the analogue of
		// PolylangSetup going through PLL()'s API rather than update_option().
		//
		// Confirmed idempotent against the live WPML env (Task 9): re-running
		// finish_step1()/set_active_languages()/finish_installation() against an
		// already-installed, already-active en+de site does not error and leaves
		// wpml_active_languages/wpml_default_language unchanged — see
		// task-9-report.md for the reproduction. The diff above still governs
		// whether this block runs at all, so an already-configured site never
		// reaches it.
		if ( ! function_exists( 'wpml_get_setup_instance' ) ) {
			$errors[] = 'WPML setup API unavailable — cannot activate languages.';
			return [ 'changes' => $changes, 'errors' => $errors ];
		}

		$setup = wpml_get_setup_instance();
		$setup->finish_step1( $default );        // Sets the default/first language.
		$setup->set_active_languages( $wanted );  // Reconciles the active set to the manifest.
		$setup->finish_installation();            // Marks setup complete; flips the active flags.

		if ( function_exists( 'wpml_reload_active_languages_setting' ) ) {
			wpml_reload_active_languages_setting( true );
		}

		// Trigger WPML's config parse so a headless `wp pediment languages`
		// deploy actually makes `wp_navigation` translatable. WPML consumes
		// `inc/wpml-compat.php`'s `wpml_config_array` filter and persists the
		// result to `custom_posts_sync_option` only via
		// `WPML_Config::load_config_run()`, which normally fires on an is_admin()
		// visit to a whitelisted admin page or the setup wizard's FinishStep —
		// never from WP-CLI. Without this, both languages stay collapsed to the
		// English header until someone opens wp-admin (see task-17-brief.md /
		// Finding 3). The static call re-parses through WPML's real config path
		// (nothing is hand-set) and is idempotent: WPML's own `$has_run` guard
		// makes it a no-op if already parsed this request, and re-running on an
		// already-configured site is harmless. Guarded so it no-ops when WPML is
		// absent (the ICL_SITEPRESS_VERSION check above already gates this path).
		if ( class_exists( 'WPML_Config' ) && method_exists( 'WPML_Config', 'load_config_run' ) ) {
			\WPML_Config::load_config_run();
		}

		return [ 'changes' => $changes, 'errors' => $errors ];
	}
}
