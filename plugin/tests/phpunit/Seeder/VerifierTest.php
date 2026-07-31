<?php
// plugin/tests/phpunit/Seeder/VerifierTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;
use Pediment\Seeder\Meta;
use Pediment\Seeder\NavSeeder;
use Pediment\Seeder\Verifier;

class VerifierTest extends WP_UnitTestCase {

	private function manifest(): Manifest {
		return Manifest::fromArray(
			[ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ] ],
			'/tmp/theme'
		);
	}

	private function desired( Manifest $m ): array {
		return ( new DesiredState( new NullProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $m );
	}

	public function test_a_correctly_seeded_site_reports_no_problems() {
		$m  = $this->manifest();
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Home' ] );
		update_post_meta( $id, Meta::KEY, 'home' );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );

		$this->assertSame( [], ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ) ) );
	}

	public function test_a_missing_post_is_a_problem() {
		$m = $this->manifest();

		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [], new MediaMap( [] ) );

		$this->assertNotEmpty( $problems );
		$this->assertStringContainsString( 'home', $problems[0] );
	}

	public function test_a_uniquified_slug_is_a_problem() {
		// WordPress appends -2 when a slug collides; silently accepting that is
		// how seeded URLs drift from the manifest.
		$m  = $this->manifest();
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'home-2', 'post_title' => 'Home' ] );
		update_post_meta( $id, Meta::KEY, 'home' );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );

		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ) );

		$this->assertStringContainsString( 'home-2', implode( "\n", $problems ) );
	}

	public function test_a_front_page_option_pointing_elsewhere_is_a_problem() {
		$m  = $this->manifest();
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Home' ] );
		update_post_meta( $id, Meta::KEY, 'home' );
		update_option( 'show_on_front', 'posts' );

		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'home|' => $id ], new MediaMap( [] ) );

		$this->assertStringContainsString( 'front page', implode( "\n", $problems ) );
	}

	public function test_a_child_whose_parent_has_no_post_is_a_problem() {
		// The expectation used to fall back to 0 when the parent was missing, so
		// a child sitting at the site root compared equal to it and the check
		// could never fail.
		$m = Manifest::fromArray(
			[
				'pages' => [
					'guide'         => [ 'title' => 'Guide', 'content' => '' ],
					'guide/pricing' => [ 'title' => 'Pricing', 'content' => '', 'parent' => 'guide' ],
				],
			],
			'/tmp/theme'
		);
		$child = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'pricing', 'post_title' => 'Pricing', 'post_parent' => 0 ] );
		update_post_meta( $child, Meta::KEY, 'guide/pricing' );

		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, $this->desired( $m ), [ 'guide/pricing|' => $child ], new MediaMap( [] ) );

		$this->assertStringContainsString( 'landed at the site root', implode( "\n", $problems ) );
	}

	public function test_nav_problems_name_the_language() {
		// Without the language a five-language site gets five identical lines,
		// and no way to tell which menu is broken.
		$lang = new class() extends NullProvider {
			public function languages(): array {
				return [ 'en', 'de' ];
			}
		};
		$m = Manifest::fromArray(
			[ 'navs' => [ 'primary' => [ 'title' => 'Primary', 'items' => [] ] ] ],
			'/tmp/theme'
		);

		$problems = ( new Verifier( $lang, new NavSeeder( $lang ) ) )->verify( $m, [], [], new MediaMap( [] ) );

		$this->assertContains( 'navs.primary|en: no navigation entity exists for this seed key.', $problems );
		$this->assertContains( 'navs.primary|de: no navigation entity exists for this seed key.', $problems );
	}

	public function test_unresolved_media_is_a_problem() {
		$dir = get_temp_dir() . 'pediment-verify-test';
		wp_mkdir_p( $dir . '/seed/media' );
		file_put_contents( $dir . '/seed/media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
		$m = Manifest::fromArray( [ 'media' => [ 'logo' => [ 'file' => 'seed/media/logo.svg' ] ] ], $dir );

		$problems = ( new Verifier( new NullProvider(), new NavSeeder( new NullProvider() ) ) )->verify( $m, [], [], new MediaMap( [] ) );

		$this->assertStringContainsString( 'logo', implode( "\n", $problems ) );
	}
}
