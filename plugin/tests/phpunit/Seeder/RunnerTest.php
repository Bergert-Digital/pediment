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

	public function test_a_reseed_never_updates_existing_content() {
		$first   = ( new Runner() )->run();
		$aboutId = $first->ids['about|'];
		$homeId  = $first->ids['home|'];
		$navId   = (int) get_posts( [ 'post_type' => 'wp_navigation', 'posts_per_page' => 1, 'fields' => 'ids' ] )[0];

		// A person edits the page, the menu and the reading settings in wp-admin.
		wp_update_post( [ 'ID' => $aboutId, 'post_title' => 'About us', 'post_content' => '<p>written live</p>', 'post_name' => 'ueber-uns', 'menu_order' => 4 ] );
		wp_update_post( [ 'ID' => $navId, 'post_content' => wp_slash( '<!-- wp:navigation-link {"label":"Shop","url":"/shop/","kind":"custom"} /-->' ) ] );
		update_option( 'page_on_front', $aboutId );
		$before    = get_post( $aboutId );
		$navBefore = get_post( $navId )->post_content;
		$revisions = count( wp_get_post_revisions( $aboutId ) );

		// The manifest moves on: new copy, title and slug for about, a new page,
		// and the new page in the menu.
		$this->manifest['pages']['about']             = [ 'title' => 'About', 'content' => '<p>from git</p>', 'slug' => 'about-git' ];
		$this->manifest['pages']['team']              = [ 'title' => 'Team', 'content' => '<p>team</p>' ];
		$this->manifest['navs']['primary']['items'][] = [ 'entry' => 'team' ];

		$second = ( new Runner() )->run();

		$this->assertTrue( $second->ok(), implode( "\n", array_merge( $second->errors, $second->problems ) ) );
		$after = get_post( $aboutId );
		$this->assertSame( $before->post_title, $after->post_title );
		$this->assertSame( $before->post_content, $after->post_content );
		$this->assertSame( 'ueber-uns', $after->post_name );
		$this->assertSame( 4, $after->menu_order );
		$this->assertCount( $revisions, wp_get_post_revisions( $aboutId ), 'no write, so no new revision' );
		$this->assertSame( $navBefore, get_post( $navId )->post_content, 'the menu a person edited stays theirs' );
		$this->assertSame( $aboutId, (int) get_option( 'page_on_front' ), 'the reading setting a person chose stays' );
		$this->assertGreaterThan( 0, $second->ids['team|'] ?? 0, 'a page the manifest added is still created' );
		$this->assertSame( $homeId, $second->ids['home|'] );

		$actions = [];
		foreach ( $second->plan->items() as $item ) {
			$actions[ $item->kind . ':' . $item->key ] = $item->action;
		}
		$this->assertSame( PlanItem::UNCHANGED, $actions['entry:home'] );
		$this->assertSame( PlanItem::UNCHANGED, $actions['entry:about'] );
		$this->assertSame( PlanItem::UNCHANGED, $actions['nav:primary'] );
		$this->assertSame( PlanItem::CREATE, $actions['entry:team'] );

		$third = ( new Runner() )->run( [ 'dry_run' => true ] );
		$this->assertTrue( $third->plan->isEmpty(), 'once the new page exists there is nothing left to write' );
		$this->assertStringContainsString( '0 to write,', \Pediment\Seeder\Reporter::summaryLine( $third ) );
	}

	public function test_a_new_menu_leaves_out_a_page_in_the_trash_without_failing_the_run() {
		$first = ( new Runner() )->run();
		wp_trash_post( $first->ids['about|'] );
		$this->manifest['navs']['footer'] = [ 'title' => 'Footer', 'items' => [ [ 'entry' => 'home' ], [ 'entry' => 'about' ] ] ];

		$second = ( new Runner() )->run();

		$this->assertTrue( $second->ok(), implode( "\n", array_merge( $second->errors, $second->problems ) ) );
		$this->assertSame( [ 'menu footer: about is in the trash, left out' ], $second->menuNotices );
		$this->assertStringContainsString( "MENUS\n  - menu footer: about is in the trash, left out", \Pediment\Seeder\Reporter::text( $second ) );
		$footer = get_posts( [ 'post_type' => 'wp_navigation', 'meta_key' => Meta::KEY, 'meta_value' => 'footer', 'posts_per_page' => 1 ] )[0];
		$this->assertStringNotContainsString( '"id":' . $first->ids['about|'], $footer->post_content );
	}

	public function test_a_page_a_person_renamed_keeps_its_slug_and_the_seed_stays_green() {
		$first = ( new Runner() )->run();
		wp_update_post( [ 'ID' => $first->ids['about|'], 'post_name' => 'ueber-uns' ] );

		$second = ( new Runner() )->run();

		$this->assertTrue( $second->ok(), implode( "\n", array_merge( $second->errors, $second->problems ) ) );
		$this->assertSame( 'ueber-uns', get_post( $first->ids['about|'] )->post_name );
		$this->assertSame( [], array_values( array_filter( $second->plan->byKind( PlanItem::KIND_ENTRY ), static fn( PlanItem $i ): bool => $i->writes() ) ) );
	}

	public function test_a_missing_pattern_for_an_existing_page_is_not_an_error() {
		register_block_pattern( 'acme/about', [ 'title' => 'About', 'content' => '<p>about</p>' ] );
		$this->manifest['pages']['about'] = [ 'title' => 'About', 'pattern' => 'acme/about' ];
		( new Runner() )->run();

		// The theme drops the pattern file: the page now lives only on the site.
		unregister_block_pattern( 'acme/about' );
		$second = ( new Runner() )->run();

		$this->assertTrue( $second->ok(), implode( "\n", array_merge( $second->errors, $second->problems ) ) );
		$about = array_values( array_filter( $second->plan->byKind( PlanItem::KIND_ENTRY ), static fn( PlanItem $i ): bool => 'about' === $i->key ) );
		$this->assertSame( PlanItem::UNCHANGED, $about[0]->action );
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

	public function test_a_menu_a_person_unpublished_is_left_alone() {
		( new Runner() )->run();
		$navs = get_posts( [ 'post_type' => 'wp_navigation', 'posts_per_page' => -1 ] );
		wp_update_post( [ 'ID' => $navs[0]->ID, 'post_status' => 'draft' ] );

		$second = ( new Runner() )->run();

		$this->assertTrue( $second->ok(), 'the seeder does not overrule a person who took the menu offline' );
		$this->assertSame( 'draft', get_post_status( $navs[0]->ID ) );
		$this->assertStringContainsString( 'does not render', $second->plan->byKind( PlanItem::KIND_NAV )[0]->note );
	}

	public function test_a_typoed_media_key_is_reported_when_the_page_is_created() {
		// Nothing else notices: the media plan has no such key and the Verifier
		// only walks declared media, so the literal sentinel lands in the new
		// page. After that the seeder never reads the pattern again — fixing
		// the live page is a job for the editor or the MCP tools.
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
		$this->assertFalse( $first->ok() );
		$this->assertTrue( $second->plan->isEmpty(), 'nothing changed, so nothing is planned' );
		$this->assertStringNotContainsString( 'hreo', implode( "\n", $second->problems ), 'the page exists, so its pattern is not read again' );
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
