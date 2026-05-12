<?php
/**
 * Action Scheduler worker for compose + edit jobs.
 *
 * @package StarterAi
 */

declare(strict_types=1);

namespace StarterAi\Jobs;

use StarterAi\Anthropic\ProviderInterface;
use StarterAi\Anthropic\SchemaBuilder;
use StarterAi\Anthropic\ToolUseParser;
use StarterAi\BlockTree\Validator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Long-running worker that calls Anthropic, validates the result, and persists it.
 */
final class ComposeJob {
	public function __construct(
		private readonly JobStore $store,
		private readonly ProviderInterface $provider
	) {}

	public function run( int $job_id ): void {
		$job = $this->store->getById( $job_id );
		if ( ! $job ) {
			return;
		}
		$this->store->updateStatus( $job_id, 'composing' );

		$schema  = ( new SchemaBuilder() )->build();
		$request = $this->buildRequest( $job, $schema );

		$response = $this->provider->messages( $request );
		if ( is_wp_error( $response ) ) {
			$this->store->fail( $job_id, $response->get_error_message() );
			return;
		}

		$parsed = ( new ToolUseParser() )->parse( $response );
		foreach ( $parsed['urls_fetched'] as $url ) {
			$this->store->appendEvent( $job_id, [ 'url_fetched' => $url ] );
		}

		if ( null === $parsed['tool'] ) {
			$this->store->fail( $job_id, 'Model did not call the emit tool.' );
			return;
		}

		$tree = $parsed['input']['blocks'] ?? [];
		if ( ! is_array( $tree ) ) {
			$this->store->fail( $job_id, 'Model emitted an invalid block tree.' );
			return;
		}

		$errors = ( new Validator( $schema['blocks'] ) )->validate( $tree );
		if ( ! empty( $errors ) ) {
			$this->store->fail( $job_id, 'Validation failed: ' . implode( '; ', $errors ) );
			return;
		}

		$this->store->complete(
			$job_id,
			[
				'blocks'       => $tree,
				'urls_fetched' => $parsed['urls_fetched'],
				'usage'        => $response['usage'] ?? null,
				'model'        => $response['model'] ?? null,
			]
		);

		do_action( 'starter_ai_job_completed', $job_id, $response, $job['kind'] );
	}

	/**
	 * @param array<string,mixed>                          $job    Job record.
	 * @param array{blocks:array<string,array<string,mixed>>} $schema Block schema.
	 * @return array<string,mixed>
	 */
	private function buildRequest( array $job, array $schema ): array {
		return [
			'model'      => apply_filters( 'starter_ai_model_' . $job['kind'], 'claude-sonnet-4-6' ),
			'max_tokens' => 4096,
			'tools'      => [
				[ 'type' => 'web_fetch_20250910', 'name' => 'web_fetch' ],
				[
					'name'         => 'emit_page',
					'description'  => 'Emit the final page as a block tree.',
					'input_schema' => [
						'type'       => 'object',
						'properties' => [
							'blocks' => [
								'type'  => 'array',
								'items' => [
									'type'       => 'object',
									'properties' => [
										'name'        => [ 'type' => 'string', 'enum' => array_keys( $schema['blocks'] ) ],
										'attributes'  => [ 'type' => 'object' ],
										'innerBlocks' => [ 'type' => 'array' ],
									],
									'required'   => [ 'name', 'attributes' ],
								],
							],
						],
						'required' => [ 'blocks' ],
					],
				],
			],
			'tool_choice' => [ 'type' => 'any' ],
			'messages'    => [
				[
					'role'    => 'user',
					'content' => [
						[ 'type' => 'text', 'text' => $this->systemBlock( $schema ), 'cache_control' => [ 'type' => 'ephemeral' ] ],
						[ 'type' => 'text', 'text' => $this->userBlock( $job ) ],
					],
				],
			],
		];
	}

	private function systemBlock( array $schema ): string {
		$brand   = class_exists( '\\Starter\\Brand' ) ? (string) \Starter\Brand::get( 'brand_name', '' ) : (string) get_option( 'blogname', '' );
		$lines   = [];
		$lines[] = 'You are a copywriter and page composer for a WordPress block theme.';
		$lines[] = 'Your output IS the final page content — not a draft, not a skeleton. Treat every text attribute as a publishing slot that must contain real, finished copy.';
		$lines[] = 'Always respond by calling the emit_page tool with a complete block tree.';
		$lines[] = '';
		$lines[] = 'Writing rules:';
		$lines[] = '- Fill every text attribute with concrete copy. Empty headlines, body text, labels, or button text are a failure — never emit `""` for a content slot.';
		$lines[] = '- Headlines, labels, button text: tight and scannable (3–8 words). Body, subheadline, answer fields: complete sentences.';
		$lines[] = '- Set structural attributes (variant, level, ordered) to whichever value best fits the section.';
		$lines[] = '- An attribute may be omitted only if it is genuinely optional (e.g. `secondaryText`/`secondaryUrl` on a CTA when one button is enough, `mediaId` on a hero when no image is needed).';
		$lines[] = '- Compose like a copywriter: specific, benefit-led, no filler. Avoid generic openers like "Welcome" or "About us."';
		$lines[] = '';
		// Block-supports adds these to every block; they're frame/policy, not content slots.
		$framework_attrs = [
			'lock', 'metadata', 'align', 'anchor', 'className', 'style', 'layout',
			'backgroundColor', 'textColor', 'gradient', 'fontSize', 'fontFamily',
		];
		$lines[] = 'Available blocks (use these — do not invent block names):';
		foreach ( $schema['blocks'] as $name => $info ) {
			$description = isset( $info['description'] ) ? (string) $info['description'] : '';
			$attr_keys   = array_diff( array_keys( (array) ( $info['attributes'] ?? [] ) ), $framework_attrs );
			$attr_list   = empty( $attr_keys ) ? 'none' : implode( ', ', $attr_keys );
			$prefix      = '- ' . $name;
			$lines[]     = '' !== $description
				? "{$prefix} — {$description} (attributes: {$attr_list})"
				: "{$prefix} (attributes: {$attr_list})";
		}
		if ( '' !== $brand ) {
			$lines[] = '';
			$lines[] = "Brand name: {$brand}. Use it where natural — hero, CTAs, FAQ answers — but don't force it into every block.";
		}
		$lines[] = '';
		$lines[] = 'You may fetch URLs the user provides or that you decide are relevant for context.';
		return implode( "\n", $lines );
	}

	private function userBlock( array $job ): string {
		$lines = [];
		if ( ! empty( $job['payload']['page_type'] ) ) {
			$lines[] = 'Page type: ' . $job['payload']['page_type'];
		}
		if ( ! empty( $job['payload']['tone'] ) ) {
			$lines[] = 'Tone: ' . $job['payload']['tone'];
		}
		if ( ! empty( $job['payload']['existing_tree'] ) ) {
			$lines[] = 'Existing block tree:';
			$lines[] = wp_json_encode( $job['payload']['existing_tree'] );
			$lines[] = 'Edit instruction:';
		}
		$lines[] = (string) ( $job['payload']['prompt'] ?? '' );
		return implode( "\n\n", $lines );
	}
}
