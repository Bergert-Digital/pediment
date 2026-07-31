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

	public static function provider(): LanguageProvider {
		if ( self::$provider instanceof LanguageProvider ) {
			return self::$provider;
		}

		/**
		 * Filter the active language provider.
		 *
		 * @param LanguageProvider $provider Defaults to the monolingual NullProvider.
		 */
		$filtered = apply_filters( 'pediment_language_provider', new NullProvider() );

		self::$provider = $filtered instanceof LanguageProvider ? $filtered : new NullProvider();

		return self::$provider;
	}

	public static function reset(): void {
		self::$provider = null;
	}
}
