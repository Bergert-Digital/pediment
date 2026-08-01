<?php
// plugin/tests/phpunit/Seeder/RunnerTest.php

use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Runner;

class RunnerTest extends WP_UnitTestCase {

	private array $manifest;

	public function set_up(): void {
		parent::set_up();
		$this->manifest = [
			'pages' => [
				'home'  => [ 'title' => 'Home', 'content' => '<p>one</p>', 'front_page' => true ],
				'about' => [ 'title' => 'About', 'content' => '<p>about</p>' ],
			],
			'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'about' ] ] ] ],
		];
		add_filter( 'pediment_seed_manifest', fn() => $this->manifest );
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		\Pediment\Seeder\Manifest::resetCache();
		parent::tear_down();
	}

	public function test_dry_run_writes_nothing() {
		$result = ( new Runner() )->run( [ 'dry_run' => true ] );

		$this->assertFalse( $result->applied );
		$this->assertCount( 2, $result->plan->byKind( PlanItem::KIND_ENTRY ) );
		$this->assertSame( [], get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}

	public function test_a_full_run_creates_verifies_and_reports_ok() {
		$result = ( new Runner() )->run();

		$this->assertTrue( $result->ok(), implode( "\n", array_merge( $result->errors, $result->problems ) ) );
		$this->assertTrue( $result->applied );
		$this->assertSame( 'Home', get_post( $result->ids['home|'] )->post_title );
	}

	public function test_a_second_run_is_a_no_op() {
		( new Runner() )->run();

		$second = ( new Runner() )->run();

		$this->assertTrue( $second->plan->isEmpty(), 'a re-seed with no manifest change must plan no writes' );
		$this->assertTrue( $second->ok() );
	}

	public function test_a_client_edit_survives_a_content_release() {
		$first = ( new Runner() )->run();
		$id    = $first->ids['home|'];
		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>' ] );

		$this->manifest['pages']['home']['content'] = '<p>two</p>';
		$result                                     = ( new Runner() )->run();

		$this->assertSame( '<p>client copy</p>', get_post( $id )->post_content );
		$this->assertNotEmpty( $result->plan->byAction( PlanItem::PROTECTED ) );
		$this->assertTrue( $result->ok(), 'protecting content is a success, not a failure' );
	}

	public function test_a_duplicate_seed_key_aborts_before_any_write() {
		$first = ( new Runner() )->run();
		$clone = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Impostor' ] );
		update_post_meta( $clone, Meta::KEY, 'home' );

		$this->manifest['pages']['home']['content'] = '<p>two</p>';
		$result                                     = ( new Runner() )->run();

		$this->assertFalse( $result->ok() );
		$this->assertFalse( $result->applied );
		$this->assertStringContainsString( 'home', $result->errors[0] );
		$this->assertSame( '<p>one</p>', get_post( $first->ids['home|'] )->post_content );
	}

	public function test_a_manifest_error_is_reported_not_thrown() {
		$this->manifest['pages']['broken'] = [ 'content' => '' ]; // no title

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->ok() );
		$this->assertStringContainsString( 'title', $result->errors[0] );
	}

	public function test_no_manifest_reports_a_clear_error() {
		remove_all_filters( 'pediment_seed_manifest' );

		// The active theme in this shared test install is not this test's to
		// control — another theme in the environment (e.g. the e2e fixture
		// theme) may legitimately ship a real seed/manifest.php. Point at an
		// empty temp directory so "no manifest" is guaranteed, not incidental.
		$dir = get_temp_dir() . 'pediment-runner-no-manifest';
		wp_mkdir_p( $dir );
		add_filter( 'stylesheet_directory', static fn() => $dir );
		\Pediment\Seeder\Manifest::resetCache();

		$result = ( new Runner() )->run();

		remove_all_filters( 'stylesheet_directory' );
		$this->assertFalse( $result->ok() );
		$this->assertStringContainsString( 'seed/manifest.php', $result->errors[0] );
	}

	public function test_navigation_is_seeded_after_pages_so_links_resolve() {
		$result = ( new Runner() )->run();

		$navs = get_posts( [ 'post_type' => 'wp_navigation', 'posts_per_page' => -1 ] );
		$this->assertCount( 1, $navs );
		$this->assertStringContainsString( '"id":' . $result->ids['about|'], $navs[0]->post_content );
	}

	public function test_an_errored_plan_leaves_no_media_behind() {
		// Media used to be applied before the plan was checked, so an unrelated
		// error still created attachments and moved the site logo while the run
		// reported that nothing had been applied. The duplicate key and the
		// unseeded media must both be present on the FIRST run, or the media is
		// already `unchanged` and the regression cannot show.
		$dir = get_temp_dir() . 'pediment-runner-media';
		wp_mkdir_p( $dir . '/seed/media' );
		file_put_contents( $dir . '/seed/media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
		add_filter( 'stylesheet_directory', static fn() => $dir );
		$this->manifest['media'] = [ 'logo' => [ 'file' => 'seed/media/logo.svg' ] ];
		$this->manifest['site']  = [ 'logo' => 'logo' ];

		foreach ( [ 1, 2 ] as $ignored ) {
			$impostor = self::factory()->post->create( [ 'post_type' => 'page' ] );
			update_post_meta( $impostor, Meta::KEY, 'home' );
		}
		$logoBefore = get_theme_mod( 'custom_logo' );

		$result = ( new Runner() )->run();

		remove_all_filters( 'stylesheet_directory' );
		$this->assertFalse( $result->ok() );
		$this->assertFalse( $result->applied );
		$this->assertSame( [], get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids' ] ), 'an errored plan creates no attachments' );
		$this->assertSame( $logoBefore, get_theme_mod( 'custom_logo' ), 'an errored plan writes nothing at all' );
	}

	public function test_a_post_type_only_manifest_still_flushes_rewrite_rules() {
		// The plan must be EMPTY for this to prove anything: with pages still to
		// create, the old `! $plan->isEmpty()` gate would flush anyway.
		$GLOBALS['wp_rewrite']->set_permalink_structure( '/%postname%/' );
		( new Runner() )->run();

		$this->manifest['post_types'] = [ 'project' => [ 'label' => 'Projects', 'has_archive' => true ] ];
		\Pediment\Seeder\Manifest::resetCache();
		\Pediment\Seeder\PostTypes::registerFromManifest();
		delete_option( 'rewrite_rules' );

		$result = ( new Runner() )->run();

		$this->assertTrue( $result->plan->isEmpty(), 'nothing but the post type changed' );
		$this->assertStringContainsString( 'post_type=project', implode( "\n", (array) get_option( 'rewrite_rules' ) ) );
		unregister_post_type( 'project' );
	}

	public function test_a_non_public_post_type_entry_is_found_again_not_duplicated() {
		// `post_type => 'any'` silently drops types with exclude_from_search, which
		// register_post_type() derives from `public`. The row was then invisible to
		// phase 2 and every run planned another CREATE.
		$this->manifest['post_types'] = [ 'internal' => [ 'public' => false, 'label' => 'Internal' ] ];
		$this->manifest['entries']    = [ 'brief' => [ 'title' => 'Brief', 'content' => '<p>b</p>', 'post_type' => 'internal' ] ];
		\Pediment\Seeder\Manifest::resetCache();
		\Pediment\Seeder\PostTypes::registerFromManifest();

		( new Runner() )->run();
		$second = ( new Runner() )->run();
		$rows   = get_posts( [ 'post_type' => 'internal', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ] );

		unregister_post_type( 'internal' );
		$this->assertCount( 1, $rows, 'the second run must find the existing row, not create a second one' );
		$this->assertTrue( $second->plan->isEmpty(), 'a re-seed of a non-public entry must plan no writes' );
	}

	public function test_a_client_unpublishing_the_menu_is_reported() {
		$result = ( new Runner() )->run();
		$navs   = get_posts( [ 'post_type' => 'wp_navigation', 'posts_per_page' => -1 ] );
		wp_update_post( [ 'ID' => $navs[0]->ID, 'post_status' => 'draft' ] );

		$second = ( new Runner() )->run();

		$this->assertFalse( $second->ok(), 'a menu that will not render is not a successful seed' );
		$this->assertStringContainsString( 'navs.primary', implode( "\n", $second->problems ) );
	}

	public function test_a_typoed_media_key_is_reported_on_every_run() {
		// Nothing else notices: the media plan has no such key and the Verifier
		// only walks declared media, so the literal sentinel lands in the page
		// and is hashed as if it were correct.
		$dir = get_temp_dir() . 'pediment-runner-typo';
		wp_mkdir_p( $dir . '/seed/media' );
		file_put_contents( $dir . '/seed/media/hero.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
		add_filter( 'stylesheet_directory', static fn() => $dir );
		$this->manifest['media']                    = [ 'hero' => [ 'file' => 'seed/media/hero.svg' ] ];
		$this->manifest['pages']['home']['content'] = '<img src="{{media_url:hreo}}" />';

		$first  = ( new Runner() )->run();
		$second = ( new Runner() )->run();

		remove_all_filters( 'stylesheet_directory' );
		$this->assertStringContainsString( 'hreo', implode( "\n", $first->problems ) );
		$this->assertTrue( $second->plan->isEmpty(), 'nothing changed, so nothing is planned' );
		$this->assertStringContainsString( 'hreo', implode( "\n", $second->problems ), 'a key that can never resolve must not go quiet after the first run' );
		$this->assertFalse( $second->ok() );
	}

	public function test_a_declared_media_key_is_not_reported_as_a_typo_on_a_fresh_site() {
		$dir = get_temp_dir() . 'pediment-runner-fresh-media';
		wp_mkdir_p( $dir . '/seed/media' );
		file_put_contents( $dir . '/seed/media/hero.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
		add_filter( 'stylesheet_directory', static fn() => $dir );
		$this->manifest['media']                    = [ 'hero' => [ 'file' => 'seed/media/hero.svg' ] ];
		$this->manifest['pages']['home']['content'] = '<img src="{{media_url:hero}}" />';

		$result = ( new Runner() )->run();

		remove_all_filters( 'stylesheet_directory' );
		$this->assertTrue( $result->ok(), implode( "\n", array_merge( $result->errors, $result->problems ) ) );
	}

	public function test_a_manifest_declaring_languages_without_a_multilingual_plugin_is_blocked() {
		add_filter(
			'pediment_seed_manifest',
			static fn() => [
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			]
		);
		\Pediment\Seeder\Manifest::resetCache();

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertNotEmpty( $result->errors );
	}

	public function test_a_media_plan_error_is_reported_exactly_once() {
		// A missing file is rejected at manifest load, which never reaches the
		// media seeder. An unsupported extension on a file that DOES exist is a
		// media-plan error, which is the path that used to double-report.
		$dir = get_temp_dir() . 'pediment-runner-mime';
		wp_mkdir_p( $dir . '/seed/media' );
		file_put_contents( $dir . '/seed/media/notes.txt', 'not an image' );
		add_filter( 'stylesheet_directory', static fn() => $dir );
		$this->manifest['media'] = [ 'notes' => [ 'file' => 'seed/media/notes.txt' ] ];

		$result = ( new Runner() )->run();

		remove_all_filters( 'stylesheet_directory' );
		$this->assertFalse( $result->ok() );
		$this->assertSame( 1, substr_count( implode( "\n", $result->errors ), 'media.notes' ), 'reported once, not once per channel' );
	}
}
