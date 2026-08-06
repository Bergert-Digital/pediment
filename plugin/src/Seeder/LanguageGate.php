<?php
/**
 * The precondition every manifest-driven write shares: the site's configured
 * languages must already be the ones the manifest declares.
 *
 * Extracted from Runner so ClaimRunner enforces the identical rule with the
 * identical message. A claim that runs before `wp pediment languages` sees
 * `NullProvider` (one empty language), keys only the default-slug rows, and
 * leaves every other language's live page unclaimed — so the seed that follows
 * hits Differ rule 1 and duplicates it. That is the catastrophe the claim step
 * exists to prevent, reachable just by running the two commands in the order
 * the docs imply.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Seeder;

use Pediment\Language\LanguageProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LanguageGate {
	/**
	 * Whether the configured languages disagree with the manifest's.
	 *
	 * Seeding into a language set the site does not actually have is the
	 * failure this returns instead of: content written with no language, which
	 * is invisible to every translation lookup and has previously removed a
	 * live site's navigation outright. The manifest is the declaration; the
	 * plugin's configuration must already match it (spec §4.3).
	 *
	 * @return string|null The operator-facing message, or null when they agree.
	 */
	public static function mismatch( Manifest $manifest, LanguageProvider $lang ): ?string {
		$declared = array_keys( $manifest->languages() );

		// A monolingual manifest imposes nothing: a site may run Polylang for
		// reasons of its own and still seed a single-language theme.
		if ( [] === $declared ) {
			return null;
		}

		$configured = $lang->languages();

		$declaredSorted = $declared;
		sort( $declaredSorted );

		$configuredSorted = $configured;
		sort( $configuredSorted );

		// Sorting erases order, so the set comparison above says nothing
		// about WHICH language is default — and everything downstream
		// (slug derivation, the front-page write, the adopt suffix) reads
		// $lang->defaultLanguage(), never the manifest's. A manifest
		// declaring `de` as default against a site configured with `en`
		// default must fail here, or Manifest::defaultLanguage() is silently
		// inert until someone happens to re-run `wp pediment languages`.
		$setsMatch     = $declaredSorted === $configuredSorted;
		$defaultsMatch = $manifest->defaultLanguage() === $lang->defaultLanguage();

		if ( $setsMatch && $defaultsMatch ) {
			return null;
		}

		$problems = [];
		if ( ! $setsMatch ) {
			$problems[] = sprintf(
				'declares [%s] but this site has [%s] configured',
				implode( ', ', $declared ),
				'' === implode( '', $configured ) ? 'none' : implode( ', ', $configured )
			);
		}
		if ( ! $defaultsMatch ) {
			$problems[] = sprintf(
				'declares "%s" as the default language but this site\'s default is "%s"',
				$manifest->defaultLanguage(),
				$lang->defaultLanguage()
			);
		}

		return sprintf(
			'Language mismatch: the manifest %s. Run `wp pediment languages` first — seeding into the wrong language set writes content no translation lookup can find.',
			implode( ', and it also ', $problems )
		);
	}
}
