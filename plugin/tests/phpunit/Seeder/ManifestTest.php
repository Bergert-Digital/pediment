<?php
// plugin/tests/phpunit/Seeder/ManifestTest.php

use Pediment\Seeder\Manifest;
use Pediment\Seeder\ManifestError;

class ManifestTest extends WP_UnitTestCase {

	private function raw(): array {
		return [
			'version' => 1,
			'pages'   => [
				'home'      => [ 'title' => 'Home', 'pattern' => 'pediment/pediment-landing', 'front_page' => true ],
				'blog'      => [ 'title' => 'Blog', 'content' => '', 'posts_page' => true ],
				'guide'     => [ 'title' => 'Guide', 'content' => '<p>g</p>' ],
				'guide/faq' => [ 'title' => 'FAQ', 'content' => '<p>f</p>', 'parent' => 'guide' ],
			],
			'posts'   => [
				'sample-one' => [ 'title' => 'Sample one', 'content' => '<p>s</p>', 'terms' => [ 'category' => [ 'insights' ] ] ],
			],
		];
	}

	public function test_defaults_are_derived_from_the_key() {
		$m   = Manifest::fromArray( $this->raw(), '/tmp/theme' );
		$faq = $m->entries()['guide/faq'];

		$this->assertSame( 'faq', $faq->slug, 'slug defaults to the last key segment' );
		$this->assertSame( 'guide', $faq->parent );
		$this->assertSame( 'page', $faq->postType );
		$this->assertSame( 0, $faq->menuOrder );
		$this->assertSame( 'post', $m->entries()['sample-one']->postType );
		$this->assertSame( [ 'category' => [ 'insights' ] ], $m->entries()['sample-one']->terms );
	}

	public function test_explicit_slug_wins_so_a_page_can_be_renamed_without_losing_identity() {
		$raw                            = $this->raw();
		$raw['pages']['guide']['slug']  = 'handbook';
		$this->assertSame( 'handbook', Manifest::fromArray( $raw, '/tmp/theme' )->entries()['guide']->slug );
	}

	public function test_dependency_order_puts_parents_first() {
		$keys = array_map(
			static fn( $e ) => $e->key,
			Manifest::fromArray( $this->raw(), '/tmp/theme' )->entriesInDependencyOrder()
		);
		$this->assertLessThan( array_search( 'guide/faq', $keys, true ), array_search( 'guide', $keys, true ) );
	}

	public function test_missing_title_is_a_validation_error() {
		$raw = $this->raw();
		unset( $raw['pages']['home']['title'] );

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/pages\.home.*title/' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_both_pattern_and_content_is_a_validation_error() {
		$raw                            = $this->raw();
		$raw['pages']['guide']['pattern'] = 'pediment/prose-article';

		$this->expectException( ManifestError::class );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_unknown_parent_is_a_validation_error() {
		$raw                                = $this->raw();
		$raw['pages']['guide/faq']['parent'] = 'nope';

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/nope/' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_parent_cycle_is_a_validation_error() {
		$raw = [
			'pages' => [
				'a' => [ 'title' => 'A', 'content' => '', 'parent' => 'b' ],
				'b' => [ 'title' => 'B', 'content' => '', 'parent' => 'a' ],
			],
		];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/cycle/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_a_key_reused_across_sections_is_a_validation_error() {
		$raw                    = $this->raw();
		$raw['posts']['home']   = [ 'title' => 'Clash', 'content' => '' ];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/duplicate seed key/i' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_more_than_one_front_page_is_a_validation_error() {
		$raw                                = $this->raw();
		$raw['pages']['guide']['front_page'] = true;

		$this->expectException( ManifestError::class );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_media_paths_resolve_against_the_manifest_directory() {
		$dir = get_temp_dir() . 'pediment-manifest-test';
		wp_mkdir_p( $dir . '/seed/media' );
		file_put_contents( $dir . '/seed/media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );

		$m = Manifest::fromArray(
			[ 'media' => [ 'logo' => [ 'file' => 'seed/media/logo.svg', 'title' => 'Logo' ] ], 'site' => [ 'logo' => 'logo' ] ],
			$dir
		);

		$this->assertSame( $dir . '/seed/media/logo.svg', $m->media()['logo']->file );
		$this->assertSame( 'logo', $m->siteLogo() );
	}

	public function test_missing_media_file_is_a_validation_error() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/gone\.jpg/' );
		Manifest::fromArray( [ 'media' => [ 'x' => [ 'file' => 'seed/media/gone.jpg' ] ] ], '/tmp/theme' );
	}

	public function test_site_logo_must_reference_a_declared_media_key() {
		$this->expectException( ManifestError::class );
		Manifest::fromArray( [ 'site' => [ 'logo' => 'nope' ] ], '/tmp/theme' );
	}

	public function test_nav_items_must_reference_declared_entries() {
		$raw         = $this->raw();
		$raw['navs'] = [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'ghost' ] ] ] ];

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessageMatches( '/ghost/' );
		Manifest::fromArray( $raw, '/tmp/theme' );
	}

	public function test_post_types_are_parsed_with_sane_defaults() {
		$m = Manifest::fromArray(
			[ 'post_types' => [ 'listing' => [ 'label' => 'Listings', 'has_archive' => true ] ] ],
			'/tmp/theme'
		);
		$spec = $m->postTypes()['listing'];

		$this->assertSame( 'listing', $spec->slug );
		$this->assertTrue( $spec->args['public'] );
		$this->assertTrue( $spec->args['show_in_rest'], 'CPT entries must be block-editable' );
		$this->assertSame( 'Listings', $spec->args['label'] );
	}

	public function test_load_returns_null_without_a_theme_manifest_and_honours_the_filter() {
		$this->assertNull( Manifest::load() );

		add_filter( 'pediment_seed_manifest', fn() => $this->raw() );
		$m = Manifest::load();
		remove_all_filters( 'pediment_seed_manifest' );

		$this->assertNotNull( $m );
		$this->assertCount( 5, $m->entries() );
	}
}
