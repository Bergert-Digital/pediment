<?php

require_once __DIR__ . '/FakeMedia.php';

use Pediment\Convert\Converter;
use Pediment\Convert\Result;

/**
 * media-text and image-caption with valid and empty attributes.
 */
class MediaMappersTest extends WP_UnitTestCase {

	private function convert( string $markup ): Result {
		return Converter::withDefaults()->convert( $markup, new FakeMedia() );
	}

	public function test_media_position_defaults_to_right(): void {
		$inner = "\n<!-- wp:paragraph -->\n<p>x</p>\n<!-- /wp:paragraph -->\n";
		$right = parse_blocks( $this->convert( "<!-- wp:pediment/media-text -->{$inner}<!-- /wp:pediment/media-text -->" )->markup )[0];
		$left  = parse_blocks( $this->convert( "<!-- wp:pediment/media-text {\"mediaPosition\":\"left\"} -->{$inner}<!-- /wp:pediment/media-text -->" )->markup )[0];
		$this->assertSame( 'right', $right['attrs']['mediaPosition'] );
		$this->assertArrayNotHasKey( 'mediaPosition', $left['attrs'], 'left is core default' );
		$this->assertStringContainsString( '<figure class="wp-block-media-text__media"></figure>', $right['innerHTML'] );
	}

	public function test_empty_media_text_and_missing_image_caption_are_removed(): void {
		$result = $this->convert( "<!-- wp:pediment/media-text {\"mediaId\":4040} -->\n<!-- /wp:pediment/media-text --><!-- wp:pediment/image-caption {\"mediaId\":4040,\"caption\":\"Gone\"} /--><!-- wp:pediment/image-caption /-->" );
		$this->assertSame( '', $result->markup );
		$this->assertContains( 'pediment/image-caption: rendered nothing, removed (2 times)', $result->notes );
	}

	public function test_alt_override_wins_over_the_attachment_alt(): void {
		$markup = $this->convert( '<!-- wp:pediment/image-caption {"mediaId":77,"altOverride":"Own alt"} /--><!-- wp:pediment/image-caption {"mediaId":77} /-->' )->markup;
		$this->assertStringContainsString( 'alt="Own alt"', $markup );
		$this->assertStringContainsString( 'alt="The team at work"', $markup );
	}
}
