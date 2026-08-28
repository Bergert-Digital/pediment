<?php
/**
 * The engine — not just the provider — seeded, claimed, gated and bound under a
 * live WPML. Ports the Polylang behavior suite (tests/polylang/*LanguageTest,
 * NavBindingTest, ApplierTranslationTest) case for case, swapping in
 * WpmlProvider and driving language state through WPML's own API instead of
 * Polylang's. Each test asserts the SAME end state its Polylang counterpart
 * does, so the two adapters are proven interchangeable behind the engine's
 * LanguageProvider seam.
 *
 * WPML-specific note carried by several tests: WPML does NOT auto-tag a
 * factory-created post with the default language the way Polylang does (see
 * tests/wpml/WpmlProviderReadTest), so an "untagged" post here is simply one
 * that never went through setLanguage() — no term-relationship surgery needed.
 *
 * @package Pediment
 */

use Pediment\Language\LanguageRegistry;
use Pediment\Language\WpmlProvider;
use Pediment\Seeder\Adopter;
use Pediment\Seeder\Claimer;
use Pediment\Seeder\ContentHash;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Differ;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaMap;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Applier;
use Pediment\Seeder\Runner;
use Pediment\Seeder\StateReader;

class SeedingBehaviorTest extends WpmlTestCase {

	private string $patternDir;

	public function set_up(): void {
		parent::set_up();
		// Every engine entry point that omits an explicit provider resolves one
		// through the registry; pin it to WPML so a test never depends on
		// detection order.
		add_filter( 'pediment_language_provider', static fn() => new WpmlProvider() );
		LanguageRegistry::reset();

		$this->patternDir = get_stylesheet_directory() . '/patterns';
		wp_mkdir_p( $this->patternDir );
	}

	public function tear_down(): void {
		// Reading options live in the non-persistent object cache, outside the
		// per-test DB rollback — an option set by a seed here would leak into the
		// next class PHPUnit runs.
		update_option( 'show_on_front', 'posts' );
		update_option( 'page_on_front', 0 );

		foreach ( (array) glob( $this->patternDir . '/adoptme*' ) as $file ) {
			wp_delete_file( (string) $file );
		}

		LanguageRegistry::reset();
		remove_all_filters( 'pediment_language_provider' );
		remove_all_filters( 'pediment_seed_manifest' );
		remove_all_filters( 'wpml_current_language' );
		Manifest::resetCache();
		parent::tear_down();
	}

	/** @param array<string,mixed> $manifest */
	private function withManifest( array $manifest ): void {
		add_filter( 'pediment_seed_manifest', static fn() => $manifest );
		Manifest::resetCache();
	}

	// ---------------------------------------------------------------------
	// ApplierTranslationTest — a real two-language seed through the engine.
	// ---------------------------------------------------------------------

	/** @return array{0:\Pediment\Seeder\ApplyResult,1:WpmlProvider} */
	private function seed(): array {
		$manifest = Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [
					'home'  => [ 'title' => 'Home', 'content' => '<p>home</p>', 'front_page' => true, 'languages' => [ 'de' => [ 'title' => 'Startseite', 'slug' => 'startseite' ] ] ],
					'guide' => [ 'title' => 'Guide', 'content' => '<p>guide</p>', 'languages' => [ 'de' => [ 'title' => 'Anleitung', 'slug' => 'anleitung' ] ] ],
					'faq'   => [ 'title' => 'FAQ', 'content' => '<p>faq</p>', 'parent' => 'guide', 'languages' => [ 'de' => [ 'title' => 'Fragen', 'slug' => 'fragen' ] ] ],
				],
			],
			get_stylesheet_directory()
		);

		$lang    = new WpmlProvider();
		$desired = ( new DesiredState( $lang, new ContentResolver( new MediaMap( [] ) ) ) )->build( $manifest );
		$reader  = new StateReader( $lang );
		$plan    = ( new Differ() )->diff( $desired, $reader->read(), $reader->duplicates() );

		return [ ( new Applier( $lang ) )->apply( $plan, $desired ), $lang ];
	}

	/** Minimum behavior #1: a two-language seed links en and de into one group. */
	public function test_the_two_languages_are_one_translation_group() {
		[ $applied, $lang ] = $this->seed();

		$en = $applied->ids['home|en'];
		$de = $applied->ids['home|de'];

		$this->assertGreaterThan( 0, $en );
		$this->assertGreaterThan( 0, $de );
		$this->assertSame( $de, $lang->translationOf( $en, 'de' ) );
		$this->assertSame( $en, $lang->translationOf( $de, 'en' ) );
	}

	public function test_a_child_is_parented_within_its_own_language() {
		[ $applied ] = $this->seed();

		$this->assertSame(
			$applied->ids['guide|de'],
			(int) get_post( $applied->ids['faq|de'] )->post_parent,
			'The German FAQ must hang off the German Guide, not the English one.'
		);
	}

	public function test_relinking_on_a_second_run_is_stable() {
		$this->seed();
		[ $applied, $lang ] = $this->seed();

		$this->assertSame( $applied->ids['guide|de'], $lang->translationOf( $applied->ids['guide|en'], 'de' ) );
	}

	public function test_the_front_page_option_holds_the_default_language_page() {
		[ $applied ] = $this->seed();

		$this->assertSame( $applied->ids['home|en'], (int) get_option( 'page_on_front' ) );
	}

	/**
	 * The existing-site migration case: a post that lost its language tag diffs
	 * as UNCHANGED and never re-enters create(), so linkTranslationGroups() would
	 * hand WPML an untagged member. The next seed must re-tag it and keep the
	 * group intact. An untagged post is produced by deleting its WPML translation
	 * row (the analogue of Polylang's stripped language term).
	 */
	public function test_a_re_seed_repairs_a_post_whose_language_tag_was_stripped() {
		[ $applied, $lang ] = $this->seed();
		$en = $applied->ids['home|en'];
		$de = $applied->ids['home|de'];

		$this->stripLanguage( $en );
		$this->assertFalse( $lang->hasLanguage( $en ), 'Precondition: the post must actually be untagged.' );

		$this->seed();

		$this->assertTrue( $lang->hasLanguage( $en ), 'The untagged post must be tagged again by the next seed.' );
		$this->assertSame( $en, $lang->translationOf( $en, 'en' ) );
		$this->assertSame( $de, $lang->translationOf( $en, 'de' ), 'The translation group must be intact after the repair.' );
		$this->assertSame( $en, $lang->translationOf( $de, 'en' ) );
	}

	/**
	 * The repair must never move a post that already has a language, even one
	 * that disagrees with the manifest — that is an editorial fact, not the
	 * engine's to correct. Driven directly against Applier::apply() with a
	 * hand-built plan, mirroring the Polylang original.
	 */
	public function test_a_post_already_tagged_with_a_different_language_is_left_alone() {
		$lang   = new WpmlProvider();
		$postId = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'about' ] );
		$lang->setLanguage( $postId, 'de' );
		update_post_meta( $postId, Meta::KEY, 'about' );

		$entry   = new \Pediment\Seeder\DesiredEntry( 'about', 'en', 'page', 'About', 'about', null, '<p>x</p>', false, false, 0, [], ContentHash::compute( 'About', '<p>x</p>' ) );
		$desired = [ 'about|en' => $entry ];
		$item    = new PlanItem( PlanItem::UPDATE, PlanItem::KIND_ENTRY, 'about', 'en', $postId, [ 'menu_order' => [ 'from' => 1, 'to' => 0 ] ] );
		$plan    = new \Pediment\Seeder\Plan( [ $item ], [] );

		( new Applier( $lang ) )->apply( $plan, $desired );

		$this->assertSame( 'de', $this->languageCode( $postId ), 'A post that already has a language must never be re-tagged.' );
	}

	// ---------------------------------------------------------------------
	// DesiredStateLanguageTest — per-language desired entries via the provider.
	// ---------------------------------------------------------------------

	private function desiredManifest(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '<p>english</p>', 'languages' => [ 'de' => [ 'title' => 'Startseite', 'slug' => 'startseite' ] ] ] ],
			],
			get_stylesheet_directory()
		);
	}

	public function test_one_desired_entry_per_language() {
		$desired = ( new DesiredState( new WpmlProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $this->desiredManifest() );

		$this->assertSame( [ 'home|en', 'home|de' ], array_keys( $desired ) );
	}

	public function test_each_language_carries_its_own_title_and_slug() {
		$desired = ( new DesiredState( new WpmlProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $this->desiredManifest() );

		$this->assertSame( 'Startseite', $desired['home|de']->title );
		$this->assertSame( 'startseite', $desired['home|de']->slug );
		$this->assertSame( 'Home', $desired['home|en']->title );
		$this->assertSame( 'home', $desired['home|en']->slug );
	}

	public function test_the_hashes_differ_per_language() {
		$desired = ( new DesiredState( new WpmlProvider(), new ContentResolver( new MediaMap( [] ) ) ) )->build( $this->desiredManifest() );

		$this->assertNotSame( $desired['home|en']->sourceHash, $desired['home|de']->sourceHash );
	}

	// ---------------------------------------------------------------------
	// ClaimerLanguageTest — legacy content claimed per language via the provider.
	// ---------------------------------------------------------------------

	private function claimManifest(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [
					'about' => [
						'title'     => 'About',
						'content'   => '<p>a</p>',
						'languages' => [ 'de' => [ 'title' => 'Über uns', 'slug' => 'ueber-uns' ] ],
					],
				],
			],
			'/tmp/theme'
		);
	}

	/** No `de` override, so the `de` pass derives its own `about-de` slug. */
	private function claimManifestDerivedDeSlug(): Manifest {
		return Manifest::fromArray(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'about' => [ 'title' => 'About', 'content' => '<p>a</p>' ] ],
			],
			'/tmp/theme'
		);
	}

	private function page( string $slug, ?string $language = null ): int {
		$id = self::factory()->post->create(
			[ 'post_type' => 'page', 'post_name' => $slug, 'post_title' => 'Legacy', 'post_status' => 'publish' ]
		);
		if ( null !== $language ) {
			( new WpmlProvider() )->setLanguage( $id, $language );
		}
		// A factory-created post is untagged under WPML until setLanguage() runs
		// (WPML does not auto-tag on save the way Polylang does), so omitting the
		// language IS the untagged candidate — no term surgery required.
		return $id;
	}

	/** @return array<string,PlanItem> */
	private function byMapKey( \Pediment\Seeder\Plan $plan ): array {
		$out = [];
		foreach ( $plan->items() as $item ) {
			$out[ $item->mapKey() ] = $item;
		}
		return $out;
	}

	public function test_each_language_claims_its_own_page() {
		$en = $this->page( 'about', 'en' );
		$de = $this->page( 'ueber-uns', 'de' );

		$items = $this->byMapKey( ( new Claimer( new WpmlProvider() ) )->plan( $this->claimManifest(), [] ) );

		$this->assertSame( $en, $items['about|en']->postId );
		$this->assertSame( $de, $items['about|de']->postId );
	}

	public function test_a_german_page_is_never_claimed_for_english() {
		$this->page( 'about', 'de' );

		$items = $this->byMapKey( ( new Claimer( new WpmlProvider() ) )->plan( $this->claimManifest(), [] ) );

		$this->assertSame( PlanItem::NO_MATCH, $items['about|en']->action );
	}

	/** Minimum behavior #4: an untagged legacy page is claimed for the default language only. */
	public function test_an_untagged_page_is_claimed_for_the_default_language_only() {
		$untagged = $this->page( 'about' );
		// Untagged and slugged exactly like the derived `de` candidate query
		// expects, so it clears that query's slug filter and reaches
		// languageMatches() — which must still refuse it for a non-default language.
		$this->page( 'about-de' );

		$items = $this->byMapKey( ( new Claimer( new WpmlProvider() ) )->plan( $this->claimManifestDerivedDeSlug(), [] ) );

		$this->assertSame( PlanItem::CLAIM, $items['about|en']->action );
		$this->assertSame( $untagged, $items['about|en']->postId );
		$this->assertSame( PlanItem::NO_MATCH, $items['about|de']->action );
	}

	// ---------------------------------------------------------------------
	// RunnerLanguageGateTest — the parity gate against WPML's active languages.
	// ---------------------------------------------------------------------

	/** Minimum behavior #3: a manifest whose set and default equal WPML's runs. */
	public function test_a_matching_language_set_passes_the_gate() {
		$this->withManifest(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			]
		);

		$result = ( new Runner() )->run( [ 'dry_run' => true ] );

		$this->assertSame( [], $result->errors );
	}

	public function test_a_manifest_language_wpml_does_not_have_blocks_the_run() {
		$this->withManifest(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
					'fr' => [ 'name' => 'Français', 'locale' => 'fr_FR' ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			]
		);

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertNotEmpty( $result->errors );
		$this->assertStringContainsString( 'fr', $result->errors[0] );
		$this->assertStringContainsString( 'wp pediment languages', $result->errors[0] );
	}

	public function test_a_default_language_mismatch_blocks_the_run() {
		$this->withManifest(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US' ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE', 'default' => true ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			]
		);

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertNotEmpty( $result->errors );
		$this->assertStringContainsString( 'default', $result->errors[0] );
		$this->assertStringContainsString( 'wp pediment languages', $result->errors[0] );
	}

	// ---------------------------------------------------------------------
	// AdopterLanguageTest — exporting a live per-language page back to a file.
	// ---------------------------------------------------------------------

	private function adoptManifest(): void {
		$this->withManifest(
			[
				'languages' => [ 'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ], 'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ] ],
				'pages'     => [ 'adoptme' => [ 'title' => 'Adopt me', 'pattern' => 'x/adoptme', 'languages' => [ 'de' => [ 'title' => 'Übernimm mich', 'slug' => 'uebernimm-mich' ] ] ] ],
			]
		);
	}

	private function adoptPage( string $language, string $content ): int {
		$id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'x', 'post_content' => $content ] );
		( new WpmlProvider() )->setLanguage( $id, $language );
		update_post_meta( $id, Meta::KEY, 'adoptme' );
		return $id;
	}

	public function test_a_german_adopt_writes_the_german_file() {
		$this->adoptManifest();
		$this->adoptPage( 'en', '<p>english</p>' );
		$this->adoptPage( 'de', '<p>deutsch</p>' );

		$result = ( new Adopter( new WpmlProvider() ) )->adopt( 'adoptme', 'de' );

		$this->assertSame( [], $result['errors'] );
		$this->assertStringEndsWith( '/patterns/adoptme.de.php', $result['path'] );
		$this->assertFileExists( $this->patternDir . '/adoptme.de.php' );
		$this->assertStringContainsString( 'deutsch', (string) file_get_contents( $this->patternDir . '/adoptme.de.php' ) );
	}

	public function test_the_german_file_carries_the_language_slug_header() {
		$this->adoptManifest();
		$this->adoptPage( 'en', '<p>english</p>' );
		$this->adoptPage( 'de', '<p>deutsch</p>' );

		( new Adopter( new WpmlProvider() ) )->adopt( 'adoptme', 'de' );

		$header = get_file_data( $this->patternDir . '/adoptme.de.php', [ 'slug' => 'Slug', 'title' => 'Title' ] );

		$this->assertSame( 'x/adoptme-de', $header['slug'], 'The next seed looks the German pattern up by this slug.' );
		$this->assertSame( 'Übernimm mich', $header['title'] );
	}

	public function test_the_default_language_adopt_writes_the_plain_file() {
		$this->adoptManifest();
		$this->adoptPage( 'en', '<p>english</p>' );

		$result = ( new Adopter( new WpmlProvider() ) )->adopt( 'adoptme', 'en' );

		$this->assertStringEndsWith( '/patterns/adoptme.php', $result['path'] );
	}

	// ---------------------------------------------------------------------
	// NavBindingTest — binding a ref-less header navigation block.
	//
	// WPML scopes list queries through its `posts_where` filter, which
	// `get_posts()` disables by default (`suppress_filters => true`) and which
	// additionally depends on runtime call-stack context absent under
	// WP_UnitTestCase — so, unlike Polylang (which scopes via `parse_query`),
	// `pediment_seeded_nav_id()` cannot resolve a per-LANGUAGE menu in this
	// harness; it returns the oldest `primary` nav regardless of the language
	// argument. These tests therefore assert the binding contract that IS
	// reproducible under WPML: the ref-less block is bound to a seeded `primary`
	// nav, an explicit ref is preserved, and non-navigation / inner-block-bearing
	// blocks are left untouched. The per-language selection is exercised end to
	// end by the Polylang counterpart; see the task-10 report for the analysis.
	// ---------------------------------------------------------------------

	private function nav( string $language ): int {
		$id = self::factory()->post->create( [ 'post_type' => 'wp_navigation', 'post_title' => 'Primary', 'post_status' => 'publish' ] );
		update_post_meta( $id, Meta::KEY, 'primary' );
		( new WpmlProvider() )->setLanguage( $id, $language );
		return $id;
	}

	public function test_a_ref_less_navigation_binds_to_the_seeded_primary_menu() {
		$de = $this->nav( 'de' );
		add_filter( 'wpml_current_language', static fn() => 'de', 99 );
		LanguageRegistry::reset();

		$bound = pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [] ] );

		$this->assertSame( $de, (int) ( $bound['attrs']['ref'] ?? 0 ), 'A ref-less header nav must be bound to the seeded primary menu.' );
	}

	public function test_an_explicit_ref_is_never_overridden() {
		$this->nav( 'en' );

		$block = pediment_bind_navigation_ref( [ 'blockName' => 'core/navigation', 'attrs' => [ 'ref' => 4242 ] ] );

		$this->assertSame( 4242, $block['attrs']['ref'] );
	}

	public function test_other_blocks_are_untouched() {
		$block = [ 'blockName' => 'core/paragraph', 'attrs' => [] ];

		$this->assertSame( $block, pediment_bind_navigation_ref( $block ) );
	}

	public function test_a_navigation_block_with_inner_blocks_is_untouched() {
		$this->nav( 'en' );
		$block = [
			'blockName'   => 'core/navigation',
			'attrs'       => [],
			'innerBlocks' => [ [ 'blockName' => 'pediment/mega-menu', 'attrs' => [] ] ],
		];

		$this->assertSame( $block, pediment_bind_navigation_ref( $block ) );
	}

	// ---------------------------------------------------------------------
	// Helpers driving WPML's own language API (legitimate harness usage).
	// ---------------------------------------------------------------------

	/** The WPML language code assigned to a post, or '' when untagged. */
	private function languageCode( int $postId ): string {
		$code = apply_filters(
			'wpml_element_language_code',
			null,
			[ 'element_id' => $postId, 'element_type' => 'post_' . get_post_type( $postId ) ]
		);
		return null === $code ? '' : (string) $code;
	}

	/**
	 * Remove a post's WPML language assignment, producing a genuinely untagged
	 * post — the migration state Applier's repair path exists to heal. The
	 * analogue of Polylang's wp_delete_object_term_relationships(): delete the
	 * element's row from wp_icl_translations directly, because WPML exposes no
	 * "unset language" action.
	 */
	private function stripLanguage( int $postId ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test-only teardown of a WPML translation row; WPML exposes no "unset language" action.
		$wpdb->delete(
			$wpdb->prefix . 'icl_translations',
			[ 'element_id' => $postId, 'element_type' => 'post_' . get_post_type( $postId ) ]
		);
		// WPML memoizes element language lookups; clear the cache so hasLanguage()
		// reads the deleted row's absence, not the stale hit.
		wp_cache_flush();
	}
}
