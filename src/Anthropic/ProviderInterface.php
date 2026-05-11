<?php
/**
 * Contract for an Anthropic-compatible message provider.
 *
 * @package StarterAi
 */

declare(strict_types=1);

namespace StarterAi\Anthropic;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ProviderInterface {
	/**
	 * @param array<string,mixed> $args Anthropic Messages request body.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function messages( array $args );
}
