<?php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Applier;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Differ;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\StateReader;

class ApplierTest extends WP_UnitTestCase {

	/** @var string[] */
	private array $lastErrors = [];

	/** Runs the four phases the way the Runner will, and returns the resolved IDs. */
	private function seed( array $raw, bool $expectClean = true ): array {
		$manifest = Manifest::fromArray( $raw, '/tmp/theme' );
		$lang     = new NullProvider();
		$desired  = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
		$reader   = new StateReader( $lang );
		$plan     = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
		$result   = ( new Applier( $lang ) )->apply( $plan, $desired );

		if ( $expectClean ) {
			$this->assertSame( [], $result->errors );
		}
		$this->lastErrors = $result->errors;

		return $result->ids;
	}

	/** Phases 1-3 only: the plan the Runner would compute, with nothing written. */
	private function plan( array $raw ): \Pediment\Seeder\Plan {
		$manifest = Manifest::fromArray( $raw, '/tmp/theme' );
		$lang     = new NullProvider();
		$desired  = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
		$reader   = new StateReader( $lang );

		return ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );
	}

	private function manifest( array $pages ): array {
		return [ 'pages' => $pages ];
	}

	public function test_creates_pages_with_key_slug_and_both_hashes() {
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>hi</p>' ] ] ) );
		$id  = $ids['home|'];

		$post = get_post( $id );
		$this->assertSame( 'page', $post->post_type );
		$this->assertSame( 'home', $post->post_name );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 'home', get_post_meta( $id, Meta::KEY, true ) );
		$this->assertSame( ContentHash::forPost( $id ), get_post_meta( $id, Meta::HASH, true ) );
		$this->assertSame( ContentHash::compute( 'Home', '<p>hi</p>' ), get_post_meta( $id, Meta::SOURCE, true ) );
	}

	public function test_block_attribute_json_survives_the_write() {
		// wp_update_post un-slashes post_content; unslashed block JSON fatals the
		// front end (docs/WORDPRESS_TRAPS.md). The applier must wp_slash().
		$markup = '<!-- wp:pediment/hero {"headline":"<span class=\"accent\">Hi</span>"} /-->';
		$ids    = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => $markup ] ] ) );

		$stored = get_post( $ids['home|'] )->post_content;
		$blocks = parse_blocks( $stored );

		$this->assertSame( 'pediment/hero', $blocks[0]['blockName'] );
		$this->assertIsArray( $blocks[0]['attrs'], 'attrs must not parse to null' );
		$this->assertSame( '<span class="accent">Hi</span>', $blocks[0]['attrs']['headline'] );
	}

	public function test_reseeding_unchanged_content_plans_nothing() {
		// Asserting on post_modified_gmt would prove nothing: it has one-second
		// granularity and both runs land inside the same second. Assert on the
		// plan, which is what actually decides whether a write happens.
		$m = $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>hi</p>' ] ] );
		$this->seed( $m );

		$plan = $this->plan( $m );

		$this->assertTrue( $plan->isEmpty(), 'a re-seed with no manifest change must plan no writes' );
		$this->assertSame( PlanItem::UNCHANGED, $plan->byKind( PlanItem::KIND_ENTRY )[0]->action );
	}

	public function test_changed_manifest_content_is_written_and_rehashed() {
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>one</p>' ] ] ) );
		$id  = $ids['home|'];

		$this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>two</p>' ] ] ) );

		$this->assertStringContainsString( 'two', get_post( $id )->post_content );
		$this->assertSame( ContentHash::forPost( $id ), get_post_meta( $id, Meta::HASH, true ) );
		$this->assertSame( ContentHash::compute( 'Home', '<p>two</p>' ), get_post_meta( $id, Meta::SOURCE, true ) );
	}

	public function test_a_structure_write_on_an_edited_page_never_restamps_the_hash() {
		// The failure this guards: a client edits the body AND renames the slug.
		// The Differ makes that an UPDATE carrying only the slug change, with the
		// content protected. Re-stamping the hash there would adopt the client's
		// prose as seeded, and the NEXT run would overwrite their page.
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>one</p>' ] ] ) );
		$id  = $ids['home|'];
		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>', 'post_name' => 'startseite' ] );
		$hashBefore = get_post_meta( $id, Meta::HASH, true );

		$this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>two</p>' ] ] ) );

		$this->assertSame( 'home', get_post( $id )->post_name, 'slug is structure and is reverted' );
		$this->assertSame( '<p>client copy</p>', get_post( $id )->post_content );
		$this->assertSame( $hashBefore, get_post_meta( $id, Meta::HASH, true ), 'a structure-only write must not adopt the client edit' );

		// And the page is still protected on the run after that.
		$this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>three</p>' ] ] ) );
		$this->assertSame( '<p>client copy</p>', get_post( $id )->post_content );
	}

	public function test_a_client_edit_is_never_overwritten() {
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>one</p>' ] ] ) );
		$id  = $ids['home|'];
		wp_update_post( [ 'ID' => $id, 'post_content' => '<p>client copy</p>', 'post_title' => 'Client title' ] );

		$this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '<p>two</p>' ] ] ) );

		$this->assertSame( '<p>client copy</p>', get_post( $id )->post_content );
		$this->assertSame( 'Client title', get_post( $id )->post_title );
	}

	public function test_a_client_slug_change_is_reverted() {
		$ids = $this->seed( $this->manifest( [ 'contact' => [ 'title' => 'Contact', 'content' => '' ] ] ) );
		$id  = $ids['contact|'];
		wp_update_post( [ 'ID' => $id, 'post_name' => 'kontakt' ] );

		$this->seed( $this->manifest( [ 'contact' => [ 'title' => 'Contact', 'content' => '' ] ] ) );

		$this->assertSame( 'contact', get_post( $id )->post_name );
	}

	public function test_nesting_menu_order_and_reading_options_are_applied() {
		$ids = $this->seed(
			$this->manifest(
				[
					'home'      => [ 'title' => 'Home', 'content' => '', 'front_page' => true ],
					'blog'      => [ 'title' => 'Blog', 'content' => '', 'posts_page' => true ],
					'guide'     => [ 'title' => 'Guide', 'content' => '' ],
					'guide/faq' => [ 'title' => 'FAQ', 'content' => '', 'parent' => 'guide', 'menu_order' => 3 ],
				]
			)
		);

		$this->assertSame( $ids['guide|'], get_post( $ids['guide/faq|'] )->post_parent );
		$this->assertSame( 3, get_post( $ids['guide/faq|'] )->menu_order );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( $ids['home|'], (int) get_option( 'page_on_front' ) );
		$this->assertSame( $ids['blog|'], (int) get_option( 'page_for_posts' ) );
	}

	public function test_terms_are_created_and_assigned() {
		$ids = $this->seed(
			[
				'posts' => [
					'sample-one' => [ 'title' => 'Sample', 'content' => '', 'terms' => [ 'category' => [ 'insights' ] ] ],
				],
			]
		);

		$terms = wp_get_post_terms( $ids['sample-one|'], 'category', [ 'fields' => 'slugs' ] );
		$this->assertContains( 'insights', $terms );
	}

	public function test_a_trashed_entry_is_restored_in_place() {
		$ids = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '' ] ] ) );
		$id  = $ids['home|'];
		wp_trash_post( $id );

		$again = $this->seed( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '' ] ] ) );

		$this->assertSame( $id, $again['home|'], 'restore, never re-create' );
		$this->assertSame( 'publish', get_post( $id )->post_status );
		$this->assertSame( 'home', get_post( $id )->post_name, 'wp_trash_post appends __trashed; the restore must undo it' );
		$this->assertSame( '', get_post_meta( $id, '_wp_trash_meta_status', true ), 'trash bookkeeping must not survive the restore' );
	}

	public function test_errors_in_the_plan_block_every_write() {
		$manifest = Manifest::fromArray( $this->manifest( [ 'home' => [ 'title' => 'Home', 'content' => '' ] ] ), '/tmp/theme' );
		$lang     = new NullProvider();
		$desired  = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
		$plan     = new \Pediment\Seeder\Plan( [], [ 'duplicate identity' ] );

		$result = ( new Applier( $lang ) )->apply( $plan, $desired );

		$this->assertSame( [ 'duplicate identity' ], $result->errors );
		$this->assertSame( [], get_posts( [ 'post_type' => 'page', 'fields' => 'ids' ] ) );
	}

	public function test_a_slug_collision_is_reported_instead_of_churning_forever() {
		// A pre-existing unseeded page holds /contact, so WordPress uniquifies the
		// seeded one to contact-2. Unreported, the Differ would rewrite the row on
		// every run and never converge.
		self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'contact', 'post_title' => 'Client contact' ] );

		$this->seed( $this->manifest( [ 'contact' => [ 'title' => 'Contact', 'content' => '' ] ] ), false );

		$this->assertNotEmpty( $this->lastErrors );
		$this->assertStringContainsString( 'contact-2', $this->lastErrors[0] );
	}

	public function test_client_added_terms_survive_a_structure_only_write() {
		$ids = $this->seed(
			[ 'posts' => [ 'sample' => [ 'title' => 'Sample', 'content' => '', 'terms' => [ 'category' => [ 'insights' ] ] ] ] ]
		);
		$id    = $ids['sample|'];
		$extra = self::factory()->category->create( [ 'slug' => 'client-pick' ] );
		wp_set_object_terms( $id, [ $extra ], 'category', true );

		wp_update_post( [ 'ID' => $id, 'post_name' => 'renamed-by-client' ] );
		$this->seed( [ 'posts' => [ 'sample' => [ 'title' => 'Sample', 'content' => '', 'terms' => [ 'category' => [ 'insights' ] ] ] ] ] );

		$this->assertContains( 'client-pick', wp_get_post_terms( $id, 'category', [ 'fields' => 'slugs' ] ) );
	}

	public function test_an_unregistered_taxonomy_is_reported_not_swallowed() {
		$this->seed(
			[ 'posts' => [ 'sample' => [ 'title' => 'Sample', 'content' => '', 'terms' => [ 'categories' => [ 'oops' ] ] ] ] ],
			false
		);

		$this->assertNotEmpty( $this->lastErrors );
		$this->assertStringContainsString( 'categories', $this->lastErrors[0] );
	}
}
