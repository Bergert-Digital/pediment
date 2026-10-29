<?php
/**
 * The empty / partial / hostile sweep (docs/PRODUCT_SENSE.md) over every
 * Pediment block, driven by each block.json so a new block joins on its own.
 */

class EmptyStateTest extends WP_UnitTestCase {

	private const HOSTILE     = '"><script>alert(1)</script><b>';
	private const HOSTILE_URL = 'javascript:alert(1)';
	private const VOID        = [ 'AREA', 'BASE', 'BR', 'COL', 'EMBED', 'HR', 'IMG', 'INPUT', 'LINK', 'META', 'SOURCE', 'TRACK', 'WBR' ];

	/** @return array<string,array{0:string,1:string}> */
	public function blocks(): array {
		$cases = [];
		foreach ( glob( dirname( __DIR__, 3 ) . '/src/blocks/*/block.json' ) as $file ) {
			$name = json_decode( (string) file_get_contents( $file ), true )['name'];
			foreach ( [ 'no attributes', 'blank values', 'hostile values' ] as $mode ) {
				$cases[ "$name, $mode" ] = [ $file, $mode ];
			}
		}
		return $cases;
	}

	public function test_every_block_is_covered(): void {
		$this->assertCount( 7, glob( dirname( __DIR__, 3 ) . '/src/blocks/*/block.json' ), 'A block was added or removed; check that this sweep still fits it.' );
	}

	/**
	 * @dataProvider blocks
	 */
	public function test_block_renders_clean_markup( string $file, string $mode ): void {
		$json  = json_decode( (string) file_get_contents( $file ), true );
		$attrs = [];
		if ( 'no attributes' !== $mode ) {
			foreach ( $json['attributes'] ?? [] as $key => $schema ) {
				$value = $this->valueFor( $schema, (string) $key, 'hostile values' === $mode );
				if ( null !== $value ) {
					$attrs[ $key ] = $value;
				}
			}
		}

		$notices = [];
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- the sweep must see notices a render swallows.
		set_error_handler(
			static function ( int $errno, string $message ) use ( &$notices ): bool {
				$notices[] = $message;
				return true;
			}
		);
		try {
			$html = render_block(
				[
					'blockName'    => $json['name'],
					'attrs'        => $attrs,
					'innerBlocks'  => [],
					'innerHTML'    => '',
					'innerContent' => [],
				]
			);
		} finally {
			restore_error_handler();
		}

		$this->assertSame( [], $notices, 'PHP notices or warnings while rendering.' );
		$this->assertDoesNotMatchRegularExpression( '/\s(href|src|action)=""/', $html, 'An empty URL attribute.' );
		$this->assertStringNotContainsString( '<script>alert', $html, 'Unescaped hostile markup.' );
		$this->assertStringNotContainsString( self::HOSTILE_URL, $html, 'An unsanitized javascript: URL.' );
		$this->assertSame( [], $this->unbalancedTags( $html ), 'Unbalanced tags.' );
	}

	/**
	 * A value for one attribute schema. Blank mode fills strings with '' and
	 * arrays with one item whose fields are blank; hostile mode puts a script
	 * into every string and a javascript: URL into every URL field.
	 *
	 * @param array<string,mixed> $schema
	 * @return mixed Null leaves the attribute to its default.
	 */
	private function valueFor( array $schema, string $key, bool $hostile ) {
		switch ( $schema['type'] ?? '' ) {
			case 'string':
				if ( ! $hostile ) {
					return '';
				}
				return preg_match( '/url|href/i', $key ) ? self::HOSTILE_URL : self::HOSTILE;
			case 'array':
				$item = $this->valueFor( $schema['items'] ?? [ 'type' => 'object' ], $key, $hostile );
				return [ $item ?? [] ];
			case 'object':
				$value = [];
				foreach ( $schema['properties'] ?? [] as $prop => $propSchema ) {
					$propValue = $this->valueFor( $propSchema, (string) $prop, $hostile );
					if ( null !== $propValue ) {
						$value[ $prop ] = $propValue;
					}
				}
				return $value;
			default:
				return null;
		}
	}

	/** @return string[] Tags opened and never closed, or closed and never opened. */
	private function unbalancedTags( string $html ): array {
		$stack     = [];
		$problems  = [];
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag( [ 'tag_closers' => 'visit' ] ) ) {
			$tag = $processor->get_tag();
			if ( in_array( $tag, self::VOID, true ) || $processor->has_self_closing_flag() ) {
				continue;
			}
			if ( ! $processor->is_tag_closer() ) {
				$stack[] = $tag;
				continue;
			}
			if ( end( $stack ) === $tag ) {
				array_pop( $stack );
				continue;
			}
			$problems[] = "</$tag> without <$tag>";
		}
		foreach ( $stack as $tag ) {
			$problems[] = "<$tag> never closed";
		}
		return $problems;
	}
}
