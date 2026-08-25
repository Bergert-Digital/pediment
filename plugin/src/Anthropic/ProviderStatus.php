<?php
/**
 * Reports whether the AI provider is actually able to answer.
 *
 * @package Pediment
 */

declare(strict_types=1);

namespace Pediment\Anthropic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the provider through the same filter chain the chat uses and
 * classifies it, so the editor can tell the user when replies are canned
 * fixtures (mock mode) or when no Anthropic key is configured at all.
 */
final class ProviderStatus {
	public const MOCK        = 'mock';
	public const MISSING_KEY = 'missing_key';
	public const OK          = 'ok';

	/**
	 * Classify the provider a chat turn would run against right now.
	 *
	 * @return string One of the MOCK / MISSING_KEY / OK constants.
	 */
	public static function current(): string {
		$key = ( new \Pediment\Settings\OptionsStore() )->getApiKey();

		// Same filter application as ChatController::processTurn(), so the
		// reported status always matches the provider a turn would really use.
		$provider = apply_filters( 'pediment_ai_provider', new Client( $key ) );

		if ( $provider instanceof \Pediment\Mock\MockProvider ) {
			return self::MOCK;
		}
		// A third-party provider swapped in via the filter brings its own
		// credentials; only the stock client depends on the stored key.
		if ( $provider instanceof Client && '' === $key ) {
			return self::MISSING_KEY;
		}
		return self::OK;
	}
}
