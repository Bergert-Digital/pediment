<?php
// plugin/tests/phpunit/Seeder/ContentResolverTest.php

use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\ManifestError;
use Pediment\Seeder\MediaMap;

class ContentResolverTest extends WP_UnitTestCase {

	private function entry( array $declared ): \Pediment\Seeder\EntrySpec {
		return Manifest::fromArray( [ 'pages' => [ 'x' => $declared + [ 'title' => 'X' ] ] ], '/tmp/theme' )->entries()['x'];
	}

	public function test_literal_content_passes_through() {
		$resolver = new ContentResolver( new MediaMap( [] ) );
		$this->assertSame( '<p>hi</p>', $resolver->resolve( $this->entry( [ 'content' => '<p>hi</p>' ] ) ) );
	}

	public function test_pattern_content_comes_from_the_registry() {
		do_action( 'init' );
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$content = $resolver->resolve( $this->entry( [ 'pattern' => 'pediment/prose-article' ] ) );

		$this->assertNotSame( '', $content );
		$this->assertSame(
			WP_Block_Patterns_Registry::get_instance()->get_registered( 'pediment/prose-article' )['content'],
			$content
		);
	}

	public function test_unregistered_pattern_fails_loudly() {
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/client\/ghost/' );
		$resolver->resolve( $this->entry( [ 'pattern' => 'client/ghost' ] ) );
	}

	public function test_media_placeholders_resolve_to_url_and_id() {
		$attachment = self::factory()->attachment->create_object(
			[ 'file' => 'hero.png', 'post_mime_type' => 'image/png' ]
		);
		$resolver = new ContentResolver( new MediaMap( [ 'hero' => $attachment ] ) );

		$content = $resolver->resolve(
			$this->entry( [ 'content' => '<img src="{{media_url:hero}}" data-id="{{media_id:hero}}" />' ] )
		);

		$this->assertStringContainsString( wp_get_attachment_url( $attachment ), $content );
		$this->assertStringContainsString( 'data-id="' . $attachment . '"', $content );
		$this->assertSame( [], $resolver->unresolvedMediaKeys() );
	}

	public function test_unseeded_media_url_resolves_to_a_reportable_sentinel() {
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$content = $resolver->resolve( $this->entry( [ 'content' => '<img src="{{media_url:hero}}" />' ] ) );

		$this->assertStringContainsString( 'PEDIMENT_SEED_MEDIA_URL:hero', $content );
		$this->assertSame( [ 'hero' ], $resolver->unresolvedMediaKeys() );
	}

	public function test_an_unseeded_media_id_is_reported_even_though_it_emits_a_bare_zero() {
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$content = $resolver->resolve( $this->entry( [ 'content' => '<!-- wp:image {"id":{{media_id:hero}}} /-->' ] ) );

		$this->assertStringContainsString( '"id":0', $content );
		$this->assertSame( [ 'hero' ], $resolver->unresolvedMediaKeys(), 'a bare 0 is invisible in the markup; the resolver must remember it' );
	}

	public function test_unresolved_keys_are_scoped_to_the_last_resolve_call() {
		$resolver = new ContentResolver( new MediaMap( [] ) );
		$resolver->resolve( $this->entry( [ 'content' => '{{media_url:hero}}' ] ) );

		$resolver->resolve( $this->entry( [ 'content' => '<p>no media here</p>' ] ) );

		$this->assertSame( [], $resolver->unresolvedMediaKeys() );
	}
}
