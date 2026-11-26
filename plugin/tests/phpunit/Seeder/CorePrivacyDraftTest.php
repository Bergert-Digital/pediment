<?php

use Pediment\Seeder\CorePrivacyDraft;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Reporter;
use Pediment\Seeder\Runner;

/**
 * WordPress's own draft "Privacy Policy" page must not push a seeded page
 * with the same slug to `privacy-policy-2`.
 */
class CorePrivacyDraftTest extends WP_UnitTestCase {

	private array $manifest;

	public function set_up(): void {
		parent::set_up();
		$this->manifest = [
			'pages' => [
				'home'    => [ 'title' => 'Home', 'content' => '<p>home</p>', 'front_page' => true ],
				'privacy' => [ 'title' => 'Privacy policy', 'slug' => 'privacy-policy', 'content' => '<p>privacy</p>' ],
			],
		];
		add_filter( 'pediment_seed_manifest', fn() => $this->manifest );
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		\Pediment\Seeder\Manifest::resetCache();
		delete_option( CorePrivacyDraft::OPTION );
		parent::tear_down();
	}

	/** The draft as wp_install_defaults() makes it. */
	private function coreDraft(): int {
		$id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => 'Privacy Policy',
				'post_name'    => 'privacy-policy',
				'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Who we are</h2><!-- /wp:heading -->',
			]
		);
		update_option( CorePrivacyDraft::OPTION, $id );
		return $id;
	}

	/** A save through the editor: post_modified moves past post_date. */
	private function edit( int $id, array $fields ): void {
		global $wpdb;
		wp_update_post( array_merge( [ 'ID' => $id ], $fields ) );
		$wpdb->update( $wpdb->posts, [ 'post_modified_gmt' => '2030-01-01 00:00:00' ], [ 'ID' => $id ] );
		clean_post_cache( $id );
	}

	public function test_the_seed_trashes_the_untouched_draft_and_takes_its_slug(): void {
		$draft = $this->coreDraft();

		$result = ( new Runner() )->run();

		$this->assertTrue( $result->ok(), implode( "\n", array_merge( $result->errors, $result->problems ) ) );
		$privacy = $result->ids['privacy|'];
		$this->assertSame( 'privacy-policy', get_post_field( 'post_name', $privacy ) );
		$this->assertSame( 'trash', get_post_status( $draft ), 'the trash keeps the draft' );
		$this->assertSame( $privacy, (int) get_option( CorePrivacyDraft::OPTION ), 'the seeded page becomes the privacy policy page' );
	}

	public function test_the_dry_run_says_so_and_writes_nothing(): void {
		$draft = $this->coreDraft();

		$result = ( new Runner() )->run( [ 'dry_run' => true ] );

		$creates = array_values( array_filter( $result->plan->byAction( PlanItem::CREATE ), fn( PlanItem $i ) => 'privacy' === $i->key ) );
		$this->assertStringContainsString( 'Privacy Policy" page to the trash', $creates[0]->note );
		$this->assertStringContainsString( 'to the trash to free the slug "privacy-policy"', Reporter::text( $result ) );
		$this->assertSame( 'draft', get_post_status( $draft ) );
	}

	public function test_a_second_seed_plans_nothing(): void {
		$this->coreDraft();
		( new Runner() )->run();

		$this->assertTrue( ( new Runner() )->run( [ 'dry_run' => true ] )->plan->isEmpty() );
	}

	public function test_an_edited_draft_is_left_alone(): void {
		$draft = $this->coreDraft();
		$this->edit( $draft, [ 'post_content' => '<p>Our own text</p>' ] );

		$result = ( new Runner() )->run();

		$this->assertSame( 'draft', get_post_status( $draft ) );
		$this->assertSame( 'privacy-policy-2', get_post_field( 'post_name', $result->ids['privacy|'] ) );
		$this->assertSame( $draft, (int) get_option( CorePrivacyDraft::OPTION ) );
	}

	public function test_a_published_privacy_page_is_left_alone(): void {
		$draft = $this->coreDraft();
		wp_publish_post( $draft );

		( new Runner() )->run();

		$this->assertSame( 'publish', get_post_status( $draft ) );
		$this->assertSame( $draft, (int) get_option( CorePrivacyDraft::OPTION ) );
	}

	public function test_a_draft_with_another_slug_stays(): void {
		$draft                                  = $this->coreDraft();
		$this->manifest['pages']['privacy']['slug'] = 'datenschutz';

		$result = ( new Runner() )->run();

		$this->assertSame( 'draft', get_post_status( $draft ) );
		$this->assertSame( 'datenschutz', get_post_field( 'post_name', $result->ids['privacy|'] ) );
		$this->assertSame( $draft, (int) get_option( CorePrivacyDraft::OPTION ) );
	}

	public function test_a_seeded_page_is_never_mistaken_for_the_draft(): void {
		$draft = $this->coreDraft();
		update_post_meta( $draft, Meta::KEY, 'old-privacy' );

		$this->assertSame( 0, CorePrivacyDraft::id() );
	}

	public function test_a_privacy_setting_someone_chose_is_kept(): void {
		$this->coreDraft();
		$chosen = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Datenschutz' ] );

		// The draft is still WordPress's, but the setting names another page.
		update_option( CorePrivacyDraft::OPTION, $chosen );

		( new Runner() )->run();

		$this->assertSame( $chosen, (int) get_option( CorePrivacyDraft::OPTION ) );
	}
}
