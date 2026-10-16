<?php
// plugin/tests/phpunit/Seeder/ContentResolverTest.php

use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\EntrySpec;
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

	public function test_a_language_pattern_is_preferred_when_registered() {
		register_block_pattern( 'x/about', [ 'title' => 'About', 'content' => '<p>english</p>' ] );
		register_block_pattern( 'x/about-de', [ 'title' => 'Über uns', 'content' => '<p>deutsch</p>' ] );

		$spec     = new EntrySpec( 'about', 'page', 'About', 'about', null, 'x/about', null, false, false, 0, [] );
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$this->assertSame( '<p>deutsch</p>', $resolver->resolve( $spec, 'de', 'en' ) );
		$this->assertSame( '<p>english</p>', $resolver->resolve( $spec, 'en', 'en' ) );
	}

	public function test_a_missing_language_pattern_falls_back_and_is_recorded() {
		register_block_pattern( 'x/solo', [ 'title' => 'Solo', 'content' => '<p>english</p>' ] );

		$spec     = new EntrySpec( 'solo', 'page', 'Solo', 'solo', null, 'x/solo', null, false, false, 0, [] );
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$this->assertSame( '<p>english</p>', $resolver->resolve( $spec, 'de', 'en' ) );
		$this->assertSame( [ 'solo|de' => 'x/solo-de' ], $resolver->missingPatterns() );
	}

	public function test_an_unregistered_default_pattern_still_throws() {
		$spec     = new EntrySpec( 'ghost', 'page', 'Ghost', 'ghost', null, 'x/ghost', null, false, false, 0, [] );
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$this->expectException( ManifestError::class );
		$resolver->resolve( $spec, 'en', 'en' );
	}

	public function test_an_unregistered_default_language_override_still_throws() {
		register_block_pattern( 'x/about-default-override', [ 'title' => 'About', 'content' => '<p>english</p>' ] );

		// The manifest declares a per-language pattern override for 'en',
		// which is ALSO the default language, and that override is not
		// registered. patternFor() checks the override before it checks
		// default-ness, so this must still be fatal, not a soft fallback.
		$spec     = new EntrySpec(
			'about-default-override',
			'page',
			'About',
			'about-default-override',
			null,
			'x/about-default-override',
			null,
			false,
			false,
			0,
			[],
			[ 'en' => [ 'pattern' => 'x/about-default-override-missing' ] ]
		);
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$this->expectException( ManifestError::class );
		$resolver->resolve( $spec, 'en', 'en' );
	}

	public function test_an_unregistered_non_default_override_falls_back_and_is_recorded() {
		register_block_pattern( 'x/about-non-default-override', [ 'title' => 'About', 'content' => '<p>english</p>' ] );

		$spec     = new EntrySpec(
			'about-non-default-override',
			'page',
			'About',
			'about-non-default-override',
			null,
			'x/about-non-default-override',
			null,
			false,
			false,
			0,
			[],
			[ 'de' => [ 'pattern' => 'x/about-non-default-override-missing' ] ]
		);
		$resolver = new ContentResolver( new MediaMap( [] ) );

		$this->assertSame( '<p>english</p>', $resolver->resolve( $spec, 'de', 'en' ) );
		$this->assertSame(
			[ 'about-non-default-override|de' => 'x/about-non-default-override-missing' ],
			$resolver->missingPatterns()
		);
	}

	/*
	 * Placeholders inside block JSON. `{"id":{{media_id:hero}}}` is not valid
	 * JSON until it is resolved, and WP_Block_Patterns_Registry::get_registered()
	 * (WP 6.4+) re-parses the content through apply_block_hooks_to_content()
	 * before it hands it out — so a block whose JSON holds a placeholder lost
	 * every attribute. The resolver must read the pattern raw, resolve, and only
	 * then let Block Hooks run.
	 */

	private const PLACEHOLDER_MARKUP = '<!-- wp:image {"id":{{media_id:hero}},"sizeSlug":"large","linkDestination":"none","className":"is-style-rounded"} -->'
		. '<figure class="wp-block-image size-large is-style-rounded"><img src="{{media_url:hero}}" alt="" class="wp-image-{{media_id:hero}}"/></figure>'
		. '<!-- /wp:image -->';

	/** @var string[] */
	private array $test_patterns = array();

	public function tear_down(): void {
		foreach ( $this->test_patterns as $name ) {
			if ( WP_Block_Patterns_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_pattern( $name );
			}
		}
		remove_all_filters( 'hooked_block_types' );
		parent::tear_down();
	}

	private function register_test_pattern( string $name, array $properties ): void {
		register_block_pattern( $name, $properties + array( 'title' => 'Placeholder test' ) );
		$this->test_patterns[] = $name;
	}

	private function hero(): int {
		return self::factory()->attachment->create_object(
			array( 'file' => '2026/06/hero.jpg', 'post_mime_type' => 'image/jpeg' )
		);
	}

	/** @param string $content Resolved markup holding one core/image block. */
	private function assert_image_kept_its_attributes( string $content, int $attachment ): void {
		$blocks = array_values( array_filter( parse_blocks( $content ), static fn( $b ) => 'core/image' === $b['blockName'] ) );
		$this->assertCount( 1, $blocks, $content );
		$attrs = $blocks[0]['attrs'];
		// Block Hooks record the hooked blocks they handled here, as core does
		// for any pattern; that is not one of the pattern's own attributes.
		unset( $attrs['metadata'] );
		$this->assertSame(
			array(
				'id'              => $attachment,
				'sizeSlug'        => 'large',
				'linkDestination' => 'none',
				'className'       => 'is-style-rounded',
			),
			$attrs
		);
		$this->assertStringContainsString( 'wp-image-' . $attachment, $content );
		$this->assertStringContainsString( wp_get_attachment_url( $attachment ), $content );
	}

	public function test_a_placeholder_in_the_block_json_of_a_registered_pattern_keeps_every_attribute() {
		$attachment = $this->hero();
		$this->register_test_pattern( 'client/placeholder-content', array( 'content' => self::PLACEHOLDER_MARKUP ) );
		$resolver = new ContentResolver( new MediaMap( array( 'hero' => $attachment ) ) );

		$content = $resolver->resolve( $this->entry( array( 'pattern' => 'client/placeholder-content' ) ) );

		$this->assert_image_kept_its_attributes( $content, $attachment );
		$this->assertSame( array(), $resolver->unresolvedMediaKeys() );
	}

	/** A theme's patterns/*.php file registers by `filePath` (WP 6.5+), loaded lazily by the registry. */
	public function test_a_placeholder_in_a_file_backed_pattern_keeps_every_attribute() {
		$attachment = $this->hero();
		$file       = get_temp_dir() . 'pediment-placeholder-pattern.php';
		file_put_contents( $file, "<?php\n/**\n * Title: Placeholder file\n * Slug: client/placeholder-file\n */\n?>\n" . self::PLACEHOLDER_MARKUP );
		$this->register_test_pattern( 'client/placeholder-file', array( 'filePath' => $file ) );
		$resolver = new ContentResolver( new MediaMap( array( 'hero' => $attachment ) ) );

		$content = $resolver->resolve( $this->entry( array( 'pattern' => 'client/placeholder-file' ) ) );

		$this->assert_image_kept_its_attributes( $content, $attachment );
	}

	/** Block Hooks still run, as core runs them for a pattern — on the resolved markup. */
	public function test_hooked_blocks_are_still_inserted_into_a_seeded_pattern() {
		$attachment = $this->hero();
		$this->register_test_pattern( 'client/placeholder-hooked', array( 'content' => self::PLACEHOLDER_MARKUP ) );
		add_filter(
			'hooked_block_types',
			static function ( $hooked, $position, $anchor ) {
				if ( 'core/image' === $anchor && 'after' === $position ) {
					$hooked[] = 'core/loginout';
				}
				return $hooked;
			},
			10,
			3
		);
		$resolver = new ContentResolver( new MediaMap( array( 'hero' => $attachment ) ) );

		$content = $resolver->resolve( $this->entry( array( 'pattern' => 'client/placeholder-hooked' ) ) );

		$this->assert_image_kept_its_attributes( $content, $attachment );
		$this->assertStringContainsString( '<!-- wp:loginout /-->', $content, 'the hooked block core would insert is still inserted' );
	}
}
