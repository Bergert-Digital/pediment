<?php
/**
 * Resolves the active LanguageProvider.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LanguageRegistry {
	private static ?LanguageProvider $provider = null;
	private static ?LanguageSetup $setup = null;

	public static function provider(): LanguageProvider {
		if ( self::$provider instanceof LanguageProvider ) {
			return self::$provider;
		}

		// Detection, not configuration: an activated-but-unconfigured Polylang
		// is NOT multilingual, and treating it as one crosses the manifest with
		// zero languages and writes nothing while reporting success.
		//
		// Precedence: Polylang, then WPML, then monolingual. Polylang wins the
		// (unsupported) both-active tie for backward compatibility; either can be
		// forced via the pediment_language_provider filter below.
		if ( PolylangProvider::isActive() ) {
			$detected = new PolylangProvider();
		} elseif ( WpmlProvider::isActive() ) {
			$detected = new WpmlProvider();
		} else {
			$detected = new NullProvider();
		}

		/**
		 * Filter the active language provider.
		 *
		 * @param LanguageProvider $provider PolylangProvider when Polylang is
		 *                                   active and configured, else NullProvider.
		 */
		$filtered = apply_filters( 'pediment_language_provider', $detected );

		self::$provider = $filtered instanceof LanguageProvider ? $filtered : $detected;

		return self::$provider;
	}

	public static function setup(): LanguageSetup {
		if ( self::$setup instanceof LanguageSetup ) {
			return self::$setup;
		}

		// Precedence mirrors provider(): Polylang, then WPML, then Polylang as the
		// default. PolylangSetup::configure() returns a clean "Polylang is not
		// active" error when neither is present, and LanguagesCommand already
		// short-circuits on an empty manifest — so a monolingual site never
		// reaches a write.
		if ( PolylangProvider::isActive() ) {
			$detected = new PolylangSetup();
		} elseif ( WpmlProvider::isActive() ) {
			$detected = new WpmlSetup();
		} else {
			$detected = new PolylangSetup();
		}

		/**
		 * Filter the active language setup.
		 *
		 * @param LanguageSetup $setup Defaults to PolylangSetup; a WPML build
		 *                             swaps WpmlSetup in via Task 9's detection.
		 */
		$filtered = apply_filters( 'pediment_language_setup', $detected );

		self::$setup = $filtered instanceof LanguageSetup ? $filtered : $detected;

		return self::$setup;
	}

	public static function reset(): void {
		self::$provider = null;
		self::$setup    = null;
	}
}
