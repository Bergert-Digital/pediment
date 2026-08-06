<?php
// plugin/tests/phpunit/Seeder/ClaimRunnerTest.php

use Pediment\Seeder\ClaimRunner;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;

class ClaimRunnerTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		add_filter(
			'pediment_seed_manifest',
			static fn() => [ 'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '<p>hi</p>' ] ] ]
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		\Pediment\Seeder\Manifest::resetCache();
		parent::tear_down();
	}

	private function legacyHome(): int {
		return self::factory()->post->create(
			[ 'post_type' => 'page', 'post_name' => 'home', 'post_title' => 'Legacy home', 'post_status' => 'publish' ]
		);
	}

	public function test_a_dry_run_plans_but_writes_no_meta() {
		$id = $this->legacyHome();

		$result = ( new ClaimRunner() )->run( [ 'dry_run' => true ] );

		$this->assertFalse( $result->applied );
		$claims = array_filter(
			$result->plan->byKind( PlanItem::KIND_ENTRY ),
			static fn( PlanItem $item ) => PlanItem::CLAIM === $item->action
		);
		$this->assertCount( 1, $claims );
		$this->assertSame( '', get_post_meta( $id, Meta::KEY, true ) );
	}

	public function test_an_applied_run_writes_the_key() {
		$id = $this->legacyHome();

		$result = ( new ClaimRunner() )->run();

		$this->assertTrue( $result->applied );
		$this->assertSame( [], $result->errors );
		$this->assertSame( 'home', get_post_meta( $id, Meta::KEY, true ) );
		$this->assertSame( '', get_post_meta( $id, Meta::HASH, true ) );
	}

	/**
	 * Claiming before `wp pediment languages` has configured Polylang is the
	 * order spec §3.3 implies, and it used to under-claim in silence: this site
	 * runs NullProvider (one empty language), so a manifest declaring en/de
	 * would key only the default-slug rows and report the rest as no-match.
	 * The operator then configures languages, seeds, and Differ rule 1
	 * duplicates every page that went unclaimed.
	 *
	 * The legacy page must still be unkeyed afterwards — that, not the message,
	 * is the property. Delete the gate from ClaimRunner::run() and the page
	 * comes back carrying `home`.
	 */
	public function test_a_manifest_language_this_site_does_not_have_blocks_the_claim() {
		$id = $this->legacyHome();

		remove_all_filters( 'pediment_seed_manifest' );
		add_filter(
			'pediment_seed_manifest',
			static fn() => [
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '<p>hi</p>' ] ],
			]
		);
		\Pediment\Seeder\Manifest::resetCache();

		$result = ( new ClaimRunner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertNotEmpty( $result->errors );
		$this->assertStringContainsString( 'Language mismatch', $result->errors[0] );
		$this->assertStringContainsString( 'wp pediment languages', $result->errors[0] );
		$this->assertTrue( $result->plan->isEmpty(), 'nothing may be planned once the gate has fired' );
		$this->assertSame( '', (string) get_post_meta( $id, Meta::KEY, true ) );
	}

	/**
	 * A migration is precisely when a manifest has just been hand-edited, and
	 * on admin-only hosting the Seeding tab's claim buttons are the only door.
	 * `Manifest::load()` throws `ManifestError`; without the catch that is a
	 * WordPress critical-error screen instead of a report. Runner has always
	 * caught it — the seed buttons on the same tab behave correctly.
	 */
	public function test_a_malformed_manifest_returns_a_result_carrying_the_error() {
		remove_all_filters( 'pediment_seed_manifest' );
		// 'home' declares neither `pattern` nor `content`, which
		// Manifest::fromArray() rejects with a ManifestError.
		add_filter( 'pediment_seed_manifest', static fn() => [ 'pages' => [ 'home' => [ 'title' => 'Home' ] ] ] );
		\Pediment\Seeder\Manifest::resetCache();

		$result = ( new ClaimRunner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertSame( '', $result->manifestPath );
		$this->assertNotEmpty( $result->errors );
		$this->assertStringContainsString( "pages.home: declare either 'pattern' or 'content'.", $result->errors[0] );
		$this->assertTrue( $result->plan->isEmpty() );
	}

	public function test_a_missing_manifest_returns_a_result_carrying_the_error() {
		// Force "no manifest" regardless of whatever the active theme ships,
		// the same way RunnerTest's siblings override the filter rather than
		// relying on there being no manifest file on disk.
		remove_all_filters( 'pediment_seed_manifest' );
		add_filter( 'pediment_seed_manifest', static fn() => null );
		\Pediment\Seeder\Manifest::resetCache();

		$result = ( new ClaimRunner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertSame( '', $result->manifestPath );
		$this->assertNotEmpty( $result->errors );
		$this->assertStringContainsString( 'No seed manifest found', $result->errors[0] );
		$this->assertTrue( $result->plan->isEmpty() );
	}
}
