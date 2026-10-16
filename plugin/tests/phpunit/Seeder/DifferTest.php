<?php

use Pediment\Seeder\ActualEntry;
use Pediment\Seeder\DesiredEntry;
use Pediment\Seeder\Differ;
use Pediment\Seeder\Plan;
use Pediment\Seeder\PlanItem;

class DifferTest extends WP_UnitTestCase {

	private function desired( array $over = [] ): DesiredEntry {
		$d = array_merge(
			[
				'key' => 'home', 'language' => '', 'postType' => 'page', 'title' => 'Home',
				'slug' => 'home', 'parentKey' => null, 'content' => '<p>new</p>',
				'frontPage' => false, 'postsPage' => false, 'menuOrder' => 0, 'terms' => [],
			],
			$over
		);
		return new DesiredEntry(
			$d['key'], $d['language'], $d['postType'], $d['title'], $d['slug'], $d['parentKey'],
			$d['content'], $d['frontPage'], $d['postsPage'], $d['menuOrder'], $d['terms']
		);
	}

	private function actual( array $over = [] ): ActualEntry {
		$d = array_merge(
			[
				'id' => 7, 'key' => 'home', 'language' => '', 'postType' => 'page', 'title' => 'Home',
				'slug' => 'home', 'parentId' => 0, 'status' => 'publish', 'menuOrder' => 0,
			],
			$over
		);
		return new ActualEntry(
			$d['id'], $d['key'], $d['language'], $d['postType'], $d['title'], $d['slug'], $d['parentId'],
			$d['status'], $d['menuOrder']
		);
	}

	private function item( array $desired, array $actual, array $duplicates = [] ): PlanItem {
		$plan = ( new Differ() )->diff( $desired, $actual, $duplicates );
		return $plan->items()[0];
	}

	public function test_missing_entry_is_created() {
		$item = $this->item( [ 'home|' => $this->desired() ], [] );

		$this->assertSame( PlanItem::CREATE, $item->action );
		$this->assertSame( '<p>new</p>', $item->changes['content']['to'] );
		$this->assertSame( 0, $item->postId );
	}

	public function test_an_existing_entry_is_never_changed() {
		// Every field differs from the manifest: a person retitled, rewrote,
		// renamed, moved and reordered the page. None of it is the seeder's.
		$actual = $this->actual( [ 'title' => 'Welcome', 'slug' => 'startseite', 'parentId' => 12, 'menuOrder' => 5 ] );
		$item   = $this->item(
			[ 'home|' => $this->desired( [ 'title' => 'Home', 'content' => '<p>from git</p>', 'menuOrder' => 1 ] ) ],
			[ 'home|' => $actual ]
		);

		$this->assertSame( PlanItem::UNCHANGED, $item->action );
		$this->assertSame( [], $item->changes );
		$this->assertFalse( $item->writes() );
		$this->assertSame( 7, $item->postId );
		$this->assertSame( '', $item->note );
	}

	public function test_a_trashed_entry_stays_in_the_trash_and_says_so() {
		$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual( [ 'status' => 'trash' ] ) ] );

		$this->assertSame( PlanItem::UNCHANGED, $item->action );
		$this->assertSame( [], $item->changes );
		$this->assertStringContainsString( 'in the trash', $item->note );
	}

	public function test_draft_and_pending_are_editorial_states_the_seeder_leaves_alone() {
		foreach ( [ 'draft', 'pending' ] as $status ) {
			$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual( [ 'status' => $status ] ) ] );

			$this->assertSame( PlanItem::UNCHANGED, $item->action, $status );
			$this->assertSame( [], $item->changes, $status );
			$this->assertSame( '', $item->note, $status );
		}
	}

	public function test_orphans_are_reported_and_never_deleted() {
		$plan = ( new Differ() )->diff( [], [ 'legacy|' => $this->actual( [ 'key' => 'legacy', 'id' => 42 ] ) ], [] );

		$item = $plan->items()[0];
		$this->assertSame( PlanItem::ORPHAN, $item->action );
		$this->assertSame( 42, $item->postId );
		$this->assertSame( [], $item->changes );
		$this->assertFalse( $plan->hasErrors(), 'an orphan is a report, not an error' );
	}

	public function test_duplicate_keys_abort_the_plan() {
		$plan = ( new Differ() )->diff( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual() ], [ 'home|' => [ 7, 9 ] ] );

		$this->assertTrue( $plan->hasErrors() );
		$this->assertStringContainsString( '7', $plan->errors()[0] );
		$this->assertStringContainsString( '9', $plan->errors()[0] );
	}

	public function test_a_post_type_mismatch_is_an_error_not_a_rewrite() {
		$plan = ( new Differ() )->diff(
			[ 'home|' => $this->desired() ],
			[ 'home|' => $this->actual( [ 'postType' => 'post' ] ) ],
			[]
		);

		$this->assertTrue( $plan->hasErrors() );
		$this->assertStringContainsString( 'post_type', $plan->errors()[0] );
	}

	public function test_counts_summarize_the_plan() {
		$plan = ( new Differ() )->diff(
			[ 'home|' => $this->desired(), 'guide|' => $this->desired( [ 'key' => 'guide' ] ) ],
			[ 'guide|' => $this->actual( [ 'key' => 'guide', 'id' => 8 ] ) ],
			[]
		);

		$this->assertSame( 1, $plan->counts()[ PlanItem::CREATE ] );
		$this->assertSame( 1, $plan->counts()[ PlanItem::UNCHANGED ] );
		$this->assertArrayNotHasKey( 'protected', $plan->counts() );
		$this->assertFalse( $plan->isEmpty() );
	}

	public function test_plan_helpers_slice_and_merge() {
		$create = new PlanItem( PlanItem::CREATE, PlanItem::KIND_ENTRY, 'home', '', 0, [ 'slug' => [ 'from' => null, 'to' => 'home' ] ] );
		$media  = new PlanItem( PlanItem::UNCHANGED, PlanItem::KIND_MEDIA, 'logo', '', 4 );
		$plan   = Plan::merge( new Plan( [ $create ] ), new Plan( [ $media ], [ 'boom' ] ) );

		$this->assertSame( [ $create ], $plan->byAction( PlanItem::CREATE ) );
		$this->assertSame( [ $media ], $plan->byKind( PlanItem::KIND_MEDIA ) );
		$this->assertSame( 'home|', $create->mapKey() );
		$this->assertTrue( $create->writes() );
		$this->assertFalse( $media->writes() );
		$this->assertSame( [ 'boom' ], $plan->errors() );
		$this->assertFalse( $plan->isEmpty(), 'an errored plan is blocked, not idle' );
		$this->assertSame( 0, $plan->counts()[ PlanItem::ORPHAN ], 'counts() reports every action, including empty ones' );
	}
}
