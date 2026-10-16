<?php
// plugin/tests/phpunit/Seeder/ClaimerTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Claimer;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;

class ClaimerTest extends WP_UnitTestCase {

	private function manifest( array $pages ): Manifest {
		return Manifest::fromArray( [ 'pages' => $pages ], '/tmp/theme' );
	}

	private function page( string $slug, array $args = [] ): int {
		return self::factory()->post->create(
			array_merge(
				[ 'post_type' => 'page', 'post_title' => 'Legacy', 'post_name' => $slug, 'post_status' => 'publish' ],
				$args
			)
		);
	}

	/** @return array<string,PlanItem> mapKey => item */
	private function byMapKey( \Pediment\Seeder\Plan $plan ): array {
		$out = [];
		foreach ( $plan->items() as $item ) {
			$out[ $item->mapKey() ] = $item;
		}
		return $out;
	}

	public function test_an_unkeyed_page_matching_slug_and_type_is_claimed() {
		$id       = $this->page( 'about' );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$plan = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );

		$item = $this->byMapKey( $plan )['about|'];
		$this->assertSame( PlanItem::CLAIM, $item->action );
		$this->assertSame( $id, $item->postId );
		$this->assertTrue( $item->writes() );
	}

	public function test_a_page_carrying_another_seed_key_is_never_taken() {
		$id = $this->page( 'about' );
		update_post_meta( $id, Meta::KEY, 'legacy-about' );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$plan = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );

		$item = $this->byMapKey( $plan )['about|'];
		$this->assertSame( PlanItem::NO_MATCH, $item->action );
		$this->assertSame( 0, $item->postId );
	}

	public function test_a_trashed_page_is_never_claimed() {
		$this->page( 'about', [ 'post_status' => 'trash' ] );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$plan = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );

		$this->assertSame( PlanItem::NO_MATCH, $this->byMapKey( $plan )['about|']->action );
	}

	public function test_two_candidates_are_reported_and_nothing_is_planned() {
		$first  = $this->page( 'about', [ 'post_type' => 'page' ] );
		$second = self::factory()->post->create(
			[ 'post_type' => 'page', 'post_name' => 'about', 'post_status' => 'draft', 'post_title' => 'About draft' ]
		);
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$item = $this->byMapKey( ( new Claimer( new NullProvider() ) )->plan( $manifest, [] ) )['about|'];

		$this->assertSame( PlanItem::AMBIGUOUS, $item->action );
		$this->assertFalse( $item->writes() );
		$this->assertStringContainsString( (string) $first, $item->note );
		$this->assertStringContainsString( (string) $second, $item->note );
	}

	public function test_a_child_is_matched_under_its_claimed_parent() {
		$parent = $this->page( 'guide' );
		$right  = $this->page( 'faq', [ 'post_parent' => $parent ] );
		$wrong  = $this->page( 'faq' ); // Same slug, top level.

		$manifest = $this->manifest(
			[
				'guide'     => [ 'title' => 'Guide', 'content' => '<p>g</p>' ],
				'guide/faq' => [ 'title' => 'FAQ', 'slug' => 'faq', 'parent' => 'guide', 'content' => '<p>f</p>' ],
			]
		);

		$items = $this->byMapKey( ( new Claimer( new NullProvider() ) )->plan( $manifest, [] ) );

		$this->assertSame( $parent, $items['guide|']->postId );
		$this->assertSame( PlanItem::CLAIM, $items['guide/faq|']->action );
		$this->assertSame( $right, $items['guide/faq|']->postId );
		$this->assertNotSame( $wrong, $items['guide/faq|']->postId );
	}

	public function test_a_top_level_entry_does_not_match_a_nested_page() {
		$parent = $this->page( 'guide' );
		$this->page( 'about', [ 'post_parent' => $parent ] );

		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );

		$this->assertSame(
			PlanItem::NO_MATCH,
			$this->byMapKey( ( new Claimer( new NullProvider() ) )->plan( $manifest, [] ) )['about|']->action
		);
	}

	public function test_apply_writes_only_the_key_and_is_idempotent() {
		$id       = $this->page( 'about', [ 'post_content' => 'live copy', 'post_title' => 'Live title' ] );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );
		$claimer  = new Claimer( new NullProvider() );

		$first = $claimer->apply( $claimer->plan( $manifest, [] ) );

		$this->assertSame( 1, $first['claimed'] );
		$this->assertSame( [], $first['errors'] );
		$this->assertSame( 'about', get_post_meta( $id, Meta::KEY, true ) );
		$this->assertSame( '', get_post_meta( $id, '_pediment_seed_hash', true ) );
		$this->assertSame( '', get_post_meta( $id, '_pediment_seed_source', true ) );
		$this->assertSame( 'live copy', get_post( $id )->post_content );
		$this->assertSame( 'Live title', get_post( $id )->post_title );

		// A second run sees the row in actual state and plans nothing.
		$actual  = ( new \Pediment\Seeder\StateReader( new NullProvider() ) )->read();
		$replan  = $claimer->plan( $manifest, $actual );
		$this->assertSame( [], $replan->byAction( PlanItem::CLAIM ) );
	}

	public function test_a_claimed_page_is_left_alone_by_the_next_seed() {
		$id       = $this->page( 'about', [ 'post_content' => 'live copy' ] );
		$manifest = $this->manifest( [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ] );
		$claimer  = new Claimer( new NullProvider() );
		$claimer->apply( $claimer->plan( $manifest, [] ) );

		$reader  = new \Pediment\Seeder\StateReader( new NullProvider() );
		$actual  = $reader->read();
		$desired = ( new \Pediment\Seeder\DesiredState(
			new NullProvider(),
			new \Pediment\Seeder\ContentResolver( new \Pediment\Seeder\MediaMap( [] ) )
		) )->build( $manifest, $actual );
		$plan    = ( new \Pediment\Seeder\Differ() )->diff( $desired, $actual, $reader->duplicates() );

		$item = $plan->items()[0];
		$this->assertSame( PlanItem::UNCHANGED, $item->action );
		$this->assertFalse( $item->writes() );
		$this->assertSame( 'live copy', get_post( $id )->post_content );
	}

	private function nav( string $title, string $slug ): int {
		return self::factory()->post->create(
			[ 'post_type' => 'wp_navigation', 'post_title' => $title, 'post_name' => $slug, 'post_status' => 'publish' ]
		);
	}

	public function test_the_only_unclaimed_navigation_is_claimed_for_a_single_nav_manifest() {
		$id       = $this->nav( 'Primary', 'primary' );
		$manifest = Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'home' ] ] ] ],
			],
			'/tmp/theme'
		);

		$plan  = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] );
		$navs  = $plan->byKind( PlanItem::KIND_NAV );

		$this->assertCount( 1, $navs );
		$this->assertSame( PlanItem::CLAIM, $navs[0]->action );
		$this->assertSame( $id, $navs[0]->postId );
	}

	public function test_the_only_unclaimed_navigation_is_claimed_even_when_its_slug_does_not_match() {
		// 'primary-2' is what a previous seeder run left behind, not the slug
		// NavSeeder::slugFor() would derive today ('primary'). The single
		// unclaimed candidate rule must claim it anyway.
		$id       = $this->nav( 'Primary', 'primary-2' );
		$manifest = Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'home' ] ] ] ],
			],
			'/tmp/theme'
		);

		$navs = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] )->byKind( PlanItem::KIND_NAV );

		$this->assertSame( PlanItem::CLAIM, $navs[0]->action );
		$this->assertSame( $id, $navs[0]->postId );
	}

	public function test_two_unclaimed_navigations_fall_back_to_slug_matching() {
		$this->nav( 'Footer', 'footer-menu' );
		$primary  = $this->nav( 'Primary', 'primary' );
		$manifest = Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'home' ] ] ] ],
			],
			'/tmp/theme'
		);

		$navs = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] )->byKind( PlanItem::KIND_NAV );

		$this->assertSame( PlanItem::CLAIM, $navs[0]->action );
		$this->assertSame( $primary, $navs[0]->postId );
	}

	public function test_a_nav_already_carrying_its_seed_key_produces_no_plan_item() {
		$id = $this->nav( 'Primary', 'primary' );
		update_post_meta( $id, Meta::KEY, 'primary' );
		$manifest = Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'home' ] ] ] ],
			],
			'/tmp/theme'
		);

		$navs = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] )->byKind( PlanItem::KIND_NAV );

		$this->assertSame( [], $navs );
	}

	/**
	 * The dangerous case: a stray, unrelated wp_navigation post must never be
	 * claimed under a key another post already carries. Before the fix, an
	 * already-claimed nav is invisible to the claim-candidate query, so the
	 * stray becomes the single unclaimed candidate and the 1-declared/1-
	 * candidate rule (Claimer.php) claims it — two posts then share one
	 * _pediment_seed_key, which the engine treats as fatal.
	 */
	public function test_a_claimed_nav_does_not_let_a_stray_be_claimed_under_its_key() {
		$claimed = $this->nav( 'Primary', 'primary' );
		update_post_meta( $claimed, Meta::KEY, 'primary' );
		$stray = $this->nav( 'Stray', 'stray-menu' );

		$manifest = Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>h</p>', 'front_page' => true ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => [ [ 'entry' => 'home' ] ] ] ],
			],
			'/tmp/theme'
		);

		$navs = ( new Claimer( new NullProvider() ) )->plan( $manifest, [] )->byKind( PlanItem::KIND_NAV );

		$this->assertSame( [], $navs );
		$this->assertSame( '', (string) get_post_meta( $stray, Meta::KEY, true ) );
	}

	/*
	 * Media. A claim matches a manifest media entry to an existing attachment
	 * by the basename of the manifest `file`, the same way a page is matched
	 * by its slug. Media identity is language-less (Pediment locks Polylang's
	 * media_support off), so every media item is planned once, with ''.
	 */

	/** @param array<string,string> $files media key => file name under seed/media/ */
	private function mediaManifest( array $files ): Manifest {
		$dir = get_temp_dir() . 'pediment-claim-media';
		wp_mkdir_p( $dir . '/seed/media' );
		$media = [];
		foreach ( $files as $key => $name ) {
			file_put_contents( $dir . '/seed/media/' . $name, 'x' );
			$media[ $key ] = [ 'file' => 'seed/media/' . $name, 'title' => ucfirst( $key ) ];
		}
		return Manifest::fromArray( [ 'media' => $media ], $dir );
	}

	/** An attachment whose `_wp_attached_file` is $relative, as an upload leaves it. */
	private function attachment( string $relative, array $meta = [] ): int {
		$id = self::factory()->attachment->create(
			[
				'file'           => $relative,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Legacy image',
			]
		);
		update_post_meta( $id, '_wp_attached_file', $relative );
		if ( [] !== $meta ) {
			wp_update_attachment_metadata( $id, $meta );
		}
		return $id;
	}

	public function test_an_unkeyed_attachment_with_the_same_file_name_is_claimed() {
		$id = $this->attachment( '2026/06/hero.jpg' );

		$plan  = ( new Claimer( new NullProvider() ) )->plan( $this->mediaManifest( [ 'hero' => 'hero.jpg' ] ), [] );
		$media = $plan->byKind( PlanItem::KIND_MEDIA );

		$this->assertCount( 1, $media );
		$this->assertSame( PlanItem::CLAIM, $media[0]->action );
		$this->assertSame( 'hero', $media[0]->key );
		$this->assertSame( '', $media[0]->language );
		$this->assertSame( $id, $media[0]->postId );
		$this->assertTrue( $media[0]->writes() );
		$this->assertSame( sprintf( 'attachment "2026/06/hero.jpg" (ID %d)', $id ), $media[0]->note, 'the line names the stored file, so the owner can find it' );
	}

	/**
	 * WordPress stores a big upload as `<name>-scaled.<ext>` and keeps the
	 * upload's own name in the metadata's `original_image`. The theme ships
	 * the original, so that name has to match too.
	 */
	public function test_a_scaled_attachment_is_matched_by_its_original_file_name() {
		$id = $this->attachment(
			'2026/06/team-scaled.jpg',
			[ 'file' => '2026/06/team-scaled.jpg', 'original_image' => 'team.jpg', 'width' => 2560, 'height' => 1707 ]
		);

		$media = ( new Claimer( new NullProvider() ) )
			->plan( $this->mediaManifest( [ 'team' => 'team.jpg' ] ), [] )
			->byKind( PlanItem::KIND_MEDIA );

		$this->assertSame( PlanItem::CLAIM, $media[0]->action );
		$this->assertSame( $id, $media[0]->postId );
	}

	public function test_two_attachments_with_the_same_file_name_are_ambiguous() {
		$first  = $this->attachment( '2026/05/hero.jpg' );
		$second = $this->attachment( '2026/06/hero.jpg' );

		$media = ( new Claimer( new NullProvider() ) )
			->plan( $this->mediaManifest( [ 'hero' => 'hero.jpg' ] ), [] )
			->byKind( PlanItem::KIND_MEDIA );

		$this->assertSame( PlanItem::AMBIGUOUS, $media[0]->action );
		$this->assertSame( 0, $media[0]->postId );
		$this->assertFalse( $media[0]->writes() );
		$this->assertStringContainsString( $first . ' (2026/05/hero.jpg)', $media[0]->note );
		$this->assertStringContainsString( $second . ' (2026/06/hero.jpg)', $media[0]->note );
		// Content often uses an attachment by ID: never advise deleting one blind.
		$this->assertStringNotContainsString( 'delete', strtolower( $media[0]->note ) );
		$this->assertStringContainsString( 'in use', $media[0]->note );
		$this->assertStringContainsString( 'renaming its file', $media[0]->note );
	}

	public function test_a_media_entry_without_an_attachment_of_that_name_is_no_match() {
		$this->attachment( '2026/06/other.jpg' );

		$media = ( new Claimer( new NullProvider() ) )
			->plan( $this->mediaManifest( [ 'hero' => 'hero.jpg' ] ), [] )
			->byKind( PlanItem::KIND_MEDIA );

		$this->assertSame( PlanItem::NO_MATCH, $media[0]->action );
		$this->assertSame( 0, $media[0]->postId );
		$this->assertFalse( $media[0]->writes() );
	}

	public function test_an_attachment_carrying_another_seed_key_is_never_taken() {
		$id = $this->attachment( '2026/06/hero.jpg' );
		update_post_meta( $id, Meta::KEY, 'banner' );

		$media = ( new Claimer( new NullProvider() ) )
			->plan( $this->mediaManifest( [ 'hero' => 'hero.jpg' ] ), [] )
			->byKind( PlanItem::KIND_MEDIA );

		$this->assertSame( PlanItem::NO_MATCH, $media[0]->action );
		$this->assertSame( 'banner', get_post_meta( $id, Meta::KEY, true ) );
	}

	public function test_a_media_key_an_attachment_already_carries_is_absent_from_the_plan() {
		$keyed = $this->attachment( '2026/05/hero.jpg' );
		update_post_meta( $keyed, Meta::KEY, 'hero' );
		$stray = $this->attachment( '2026/06/hero.jpg' );

		$media = ( new Claimer( new NullProvider() ) )
			->plan( $this->mediaManifest( [ 'hero' => 'hero.jpg' ] ), [] )
			->byKind( PlanItem::KIND_MEDIA );

		$this->assertSame( [], $media );
		$this->assertSame( '', (string) get_post_meta( $stray, Meta::KEY, true ) );
	}

	public function test_a_trashed_attachment_is_never_claimed() {
		$id = $this->attachment( '2026/06/hero.jpg' );
		wp_trash_post( $id );

		$media = ( new Claimer( new NullProvider() ) )
			->plan( $this->mediaManifest( [ 'hero' => 'hero.jpg' ] ), [] )
			->byKind( PlanItem::KIND_MEDIA );

		$this->assertSame( PlanItem::NO_MATCH, $media[0]->action );
	}

	public function test_apply_writes_only_the_seed_key_onto_the_attachment() {
		$id      = $this->attachment( '2026/06/hero.jpg' );
		$claimer = new Claimer( new NullProvider() );
		$meta    = get_post_meta( $id );

		$result = $claimer->apply( $claimer->plan( $this->mediaManifest( [ 'hero' => 'hero.jpg' ] ), [] ) );

		$this->assertSame( 1, $result['claimed'] );
		$this->assertSame( [], $result['errors'] );
		$this->assertSame( 'hero', get_post_meta( $id, Meta::KEY, true ) );
		$after = get_post_meta( $id );
		unset( $after[ Meta::KEY ] );
		$this->assertSame( $meta, $after, 'a claim writes the seed key and nothing else' );
		$this->assertSame( '2026/06/hero.jpg', get_post_meta( $id, '_wp_attached_file', true ) );
	}

	public function test_a_claimed_attachment_is_unchanged_in_the_next_seed_plan() {
		$id       = $this->attachment( '2026/06/hero.jpg' );
		$manifest = $this->mediaManifest( [ 'hero' => 'hero.jpg' ] );
		$claimer  = new Claimer( new NullProvider() );
		$claimer->apply( $claimer->plan( $manifest, [] ) );

		$plan = ( new \Pediment\Seeder\MediaSeeder() )->plan( $manifest );

		$this->assertSame( [], $plan->byAction( PlanItem::CREATE ) );
		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertSame( $id, $plan->items()[0]->postId );
	}

	public function test_a_private_attachment_is_claimed_and_the_seed_leaves_it_alone() {
		$id = $this->attachment( '2026/06/hero.jpg' );
		wp_update_post( [ 'ID' => $id, 'post_status' => 'private' ] );
		$manifest = $this->mediaManifest( [ 'hero' => 'hero.jpg' ] );
		$claimer  = new Claimer( new NullProvider() );

		$plan = $claimer->plan( $manifest, [] );
		$this->assertSame( PlanItem::CLAIM, $plan->byKind( PlanItem::KIND_MEDIA )[0]->action );
		$claimer->apply( $plan );

		$seed = ( new \Pediment\Seeder\MediaSeeder() )->plan( $manifest );
		$this->assertSame( [], $seed->byAction( PlanItem::CREATE ), 'a claimed private image must not be uploaded again' );
		$this->assertSame( $id, $seed->items()[0]->postId );
	}

	/**
	 * docs/seeding.md promises that names compare after sanitize_file_name()
	 * and in lower case: what an upload does to a name.
	 */
	public function test_a_file_name_matches_its_sanitized_lower_case_upload() {
		$id = $this->attachment( '2026/06/team-photo.jpg' );

		$media = ( new Claimer( new NullProvider() ) )
			->plan( $this->mediaManifest( [ 'team' => 'Team Photo.JPG' ] ), [] )
			->byKind( PlanItem::KIND_MEDIA );

		$this->assertSame( PlanItem::CLAIM, $media[0]->action );
		$this->assertSame( $id, $media[0]->postId );
	}

	public function test_a_no_match_line_names_the_manifest_file_as_written() {
		$media = ( new Claimer( new NullProvider() ) )
			->plan( $this->mediaManifest( [ 'team' => 'Team Photo.JPG' ] ), [] )
			->byKind( PlanItem::KIND_MEDIA );

		$this->assertSame( PlanItem::NO_MATCH, $media[0]->action );
		$this->assertStringContainsString( '"Team Photo.JPG"', $media[0]->note );
	}

	/** A WordPress dedupe copy (`hero-1.jpg`) is a different name and never matches. */
	public function test_a_numbered_dedupe_copy_does_not_match() {
		$this->attachment( '2026/06/hero-1.jpg' );

		$media = ( new Claimer( new NullProvider() ) )
			->plan( $this->mediaManifest( [ 'hero' => 'hero.jpg' ] ), [] )
			->byKind( PlanItem::KIND_MEDIA );

		$this->assertSame( PlanItem::NO_MATCH, $media[0]->action );
	}

	public function test_two_media_keys_with_the_same_file_name_claim_one_attachment_once() {
		$id  = $this->attachment( '2026/06/hero.jpg' );
		$dir = get_temp_dir() . 'pediment-claim-media-twice';
		wp_mkdir_p( $dir . '/seed/media/alt' );
		file_put_contents( $dir . '/seed/media/hero.jpg', 'x' );
		file_put_contents( $dir . '/seed/media/alt/hero.jpg', 'x' );
		$manifest = Manifest::fromArray(
			[
				'media' => [
					'hero'     => [ 'file' => 'seed/media/hero.jpg' ],
					'hero-alt' => [ 'file' => 'seed/media/alt/hero.jpg' ],
				],
			],
			$dir
		);
		$claimer = new Claimer( new NullProvider() );

		$plan  = $claimer->plan( $manifest, [] );
		$items = $this->byMapKey( $plan );

		$this->assertSame( PlanItem::CLAIM, $items['hero|']->action );
		$this->assertSame( $id, $items['hero|']->postId );
		$this->assertSame( PlanItem::NO_MATCH, $items['hero-alt|']->action );

		$result = $claimer->apply( $plan );
		$this->assertSame( [], $result['errors'] );
		$this->assertSame( 1, $result['claimed'] );
		$this->assertSame( [ 'hero' ], get_post_meta( $id, Meta::KEY ), 'one attachment, one key' );
	}
}
