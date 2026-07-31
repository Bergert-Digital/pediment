<?php
// plugin/tests/phpunit/Seeder/DifferTest.php

use Pediment\Seeder\ActualEntry;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\DesiredEntry;
use Pediment\Seeder\Differ;
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
			$d['content'], $d['frontPage'], $d['postsPage'], $d['menuOrder'], $d['terms'],
			ContentHash::compute( $d['title'], $d['content'] )
		);
	}

	/** @param array $over storedHash/currentHash/sourceHash default to a consistent "seeded, untouched" row. */
	private function actual( array $over = [] ): ActualEntry {
		$persisted = ContentHash::compute( 'Home', '<p>old</p>' );
		$d         = array_merge(
			[
				'id' => 7, 'key' => 'home', 'language' => '', 'postType' => 'page', 'title' => 'Home',
				'slug' => 'home', 'parentId' => 0, 'status' => 'publish', 'menuOrder' => 0,
				'storedHash' => $persisted, 'currentHash' => $persisted,
				'sourceHash' => ContentHash::compute( 'Home', '<p>old</p>' ),
			],
			$over
		);
		return new ActualEntry(
			$d['id'], $d['key'], $d['language'], $d['postType'], $d['title'], $d['slug'], $d['parentId'],
			$d['status'], $d['menuOrder'], $d['storedHash'], $d['currentHash'], $d['sourceHash']
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

	public function test_untouched_entry_with_changed_source_is_updated() {
		$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual() ] );

		$this->assertSame( PlanItem::UPDATE, $item->action );
		$this->assertArrayHasKey( 'content', $item->changes );
		$this->assertSame( 7, $item->postId );
	}

	public function test_untouched_entry_with_unchanged_source_is_left_alone() {
		$desired = $this->desired( [ 'content' => '<p>old</p>' ] );
		$item    = $this->item( [ 'home|' => $desired ], [ 'home|' => $this->actual() ] );

		$this->assertSame( PlanItem::UNCHANGED, $item->action );
		$this->assertSame( [], $item->changes );
	}

	public function test_normalization_alone_never_triggers_a_rewrite() {
		// The persisted row differs from the source (WP normalizes on write);
		// only the SOURCE hash decides whether git changed.
		$actual = $this->actual(
			[
				'storedHash'  => ContentHash::compute( 'Home', '<p>old normalized </p>' ),
				'currentHash' => ContentHash::compute( 'Home', '<p>old normalized </p>' ),
				'sourceHash'  => ContentHash::compute( 'Home', '<p>old</p>' ),
			]
		);
		$item = $this->item( [ 'home|' => $this->desired( [ 'content' => '<p>old</p>' ] ) ], [ 'home|' => $actual ] );

		$this->assertSame( PlanItem::UNCHANGED, $item->action );
	}

	public function test_client_edited_content_is_protected() {
		$actual = $this->actual( [ 'currentHash' => ContentHash::compute( 'Home', '<p>client wrote this</p>' ) ] );
		$item   = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $actual ] );

		$this->assertSame( PlanItem::PROTECTED, $item->action );
		$this->assertSame( [], $item->changes );
		$this->assertArrayHasKey( 'content', $item->protectedFields );
		$this->assertStringContainsString( 'edited', $item->note );
	}

	public function test_a_missing_stored_hash_counts_as_edited() {
		// Step 6 property: on a pre-existing database the first run touches no content.
		$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual( [ 'storedHash' => '' ] ) ] );

		$this->assertSame( PlanItem::PROTECTED, $item->action );
	}

	public function test_a_foreign_hash_version_counts_as_edited() {
		$actual = $this->actual( [ 'storedHash' => '2:' . str_repeat( 'a', 64 ) ] );

		$this->assertSame( PlanItem::PROTECTED, $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $actual ] )->action );
	}

	public function test_a_missing_source_hash_self_heals_when_the_row_is_untouched() {
		$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $this->actual( [ 'sourceHash' => '' ] ) ] );

		$this->assertSame( PlanItem::UPDATE, $item->action );
		$this->assertArrayHasKey( 'content', $item->changes );
	}

	public function test_title_is_content_and_travels_with_the_hash() {
		$actual = $this->actual( [ 'currentHash' => ContentHash::compute( 'Home', '<p>client</p>' ) ] );
		$item   = $this->item( [ 'home|' => $this->desired( [ 'title' => 'Welcome', 'content' => '<p>old</p>' ] ) ], [ 'home|' => $actual ] );

		$this->assertSame( PlanItem::PROTECTED, $item->action );
		$this->assertArrayHasKey( 'title', $item->protectedFields );
	}

	public function test_slug_is_structure_and_is_reverted_even_when_content_is_protected() {
		$actual = $this->actual(
			[ 'slug' => 'kontakt', 'currentHash' => ContentHash::compute( 'Home', '<p>client</p>' ) ]
		);
		$item = $this->item( [ 'home|' => $this->desired() ], [ 'home|' => $actual ] );

		$this->assertSame( PlanItem::UPDATE, $item->action, 'a structure change still counts as an update' );
		$this->assertSame( [ 'from' => 'kontakt', 'to' => 'home' ], $item->changes['slug'] );
		$this->assertArrayHasKey( 'content', $item->protectedFields );
	}

	public function test_parent_and_menu_order_are_structure() {
		$desired = [
			'guide|'     => $this->desired( [ 'key' => 'guide', 'slug' => 'guide', 'content' => '<p>old</p>' ] ),
			'guide/faq|' => $this->desired( [ 'key' => 'guide/faq', 'slug' => 'faq', 'parentKey' => 'guide', 'content' => '<p>old</p>', 'menuOrder' => 3 ] ),
		];
		$actual  = [
			'guide|'     => $this->actual( [ 'id' => 10, 'key' => 'guide', 'slug' => 'guide' ] ),
			'guide/faq|' => $this->actual( [ 'id' => 11, 'key' => 'guide/faq', 'slug' => 'faq', 'parentId' => 0, 'menuOrder' => 0 ] ),
		];
		$plan  = ( new Differ() )->diff( $desired, $actual, [] );
		$items = [];
		foreach ( $plan->items() as $planned ) {
			$items[ $planned->key ] = $planned;
		}

		$this->assertSame( 'guide', $items['guide/faq']->changes['parent']['to'] );
		$this->assertSame( 3, $items['guide/faq']->changes['menu_order']['to'] );
		$this->assertSame( PlanItem::UNCHANGED, $items['guide']->action );
	}

	public function test_trashed_entries_are_restored() {
		$item = $this->item( [ 'home|' => $this->desired( [ 'content' => '<p>old</p>' ] ) ], [ 'home|' => $this->actual( [ 'status' => 'trash' ] ) ] );

		$this->assertSame( PlanItem::RESTORE, $item->action );
		$this->assertSame( [ 'from' => 'trash', 'to' => 'publish' ], $item->changes['status'] );
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
			[ 'home|' => $this->desired(), 'guide|' => $this->desired( [ 'key' => 'guide', 'content' => '<p>old</p>' ] ) ],
			[ 'guide|' => $this->actual( [ 'key' => 'guide', 'id' => 8 ] ) ],
			[]
		);

		$this->assertSame( 1, $plan->counts()[ PlanItem::CREATE ] );
		$this->assertSame( 1, $plan->counts()[ PlanItem::UNCHANGED ] );
		$this->assertFalse( $plan->isEmpty() );
	}
}
