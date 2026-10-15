<?php
// plugin/tests/phpunit/Seeder/NavSeederTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\Meta;
use Pediment\Seeder\NavSeeder;
use Pediment\Seeder\PlanItem;

class NavSeederTest extends WP_UnitTestCase {

	private function manifest( array $items ): Manifest {
		return Manifest::fromArray(
			[
				'pages' => [ 'home' => [ 'title' => 'Home', 'content' => '' ], 'about' => [ 'title' => 'About', 'content' => '' ] ],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => $items ] ],
			],
			'/tmp/theme'
		);
	}

	public function test_serializes_entry_links_by_resolved_id() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ], [ 'url' => '/contact', 'label' => 'Contact' ] ] );

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'home|' => 12 ] );

		$this->assertStringContainsString( '"id":12', $markup );
		$this->assertStringContainsString( '"kind":"post-type"', $markup );
		$this->assertStringContainsString( '"label":"Contact"', $markup );
		$this->assertStringContainsString( 'wp:navigation-link', $markup );
	}

	public function test_serializes_a_language_switcher_item() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ], [ 'language_switcher' => true ] ] );

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'home|' => 12 ] );

		// NullProvider has no switcher block to emit — a monolingual site's
		// menu shows the ordinary links and nothing where the switcher item was.
		$this->assertStringNotContainsString( 'wp:polylang/navigation-language-switcher', $markup );
		$this->assertStringContainsString( 'wp:navigation-link', $markup );
	}

	public function test_creates_one_entity_per_nav_key() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];

		$navIds = $seeder->apply( $seeder->plan( $m ), $m, $ids );

		$nav = get_post( $navIds['primary|'] );
		$this->assertSame( 'wp_navigation', $nav->post_type );
		$this->assertSame( 'publish', $nav->post_status );
		$this->assertSame( 'primary', get_post_meta( $nav->ID, Meta::KEY, true ) );
	}

	public function test_a_manifest_membership_change_never_reaches_an_existing_nav() {
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page' ] ),
		];
		$first  = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$navIds = $seeder->apply( $seeder->plan( $first ), $first, $ids );
		$before = get_post( $navIds['primary|'] )->post_content;

		$second = $this->manifest( [ [ 'entry' => 'home' ], [ 'entry' => 'about' ] ] );
		$plan   = $seeder->plan( $second );
		$again  = $seeder->apply( $plan, $second, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertSame( [], $plan->items()[0]->changes );
		$this->assertSame( $navIds['primary|'], $again['primary|'] );
		$this->assertSame( $before, get_post( $navIds['primary|'] )->post_content );
		$this->assertSame( [], $seeder->created() );
	}

	public function test_an_unchanged_nav_is_not_rewritten() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$navIds = $seeder->apply( $seeder->plan( $m ), $m, $ids );
		$before = get_post( $navIds['primary|'] )->post_modified_gmt;

		$plan = $seeder->plan( $m );
		$seeder->apply( $plan, $m, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertSame( $before, get_post( $navIds['primary|'] )->post_modified_gmt );
	}

	public function test_a_nav_with_slashes_in_its_urls_is_stored_verbatim() {
		// KSES strips the `\/` escapes out of stored JSON unless the seeder
		// suspends it; the new menu must hold exactly what serialize() wrote.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'url' => '/contact/us', 'label' => 'Contact' ] ] );

		$navIds = $seeder->apply( $seeder->plan( $m ), $m, [] );

		$this->assertSame( $seeder->serialize( $m->navs()['primary'], '', [] ), get_post( $navIds['primary|'] )->post_content );
	}

	public function test_an_item_whose_entry_is_not_seeded_is_reported() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );

		$seeder->apply( $seeder->plan( $m ), $m, [] );

		$this->assertNotEmpty( $seeder->errors() );
		$this->assertStringContainsString( 'home', $seeder->errors()[0] );
	}

	public function test_a_trashed_nav_stays_in_the_trash_and_is_never_re_created() {
		// Restoring would be a write to a menu that exists; creating a second
		// one would put two navs under one identity, which the very next run
		// reports as fatal. Neither: the plan says where it is.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$navIds = $seeder->apply( $seeder->plan( $m ), $m, $ids );
		wp_trash_post( $navIds['primary|'] );

		$plan  = $seeder->plan( $m );
		$again = $seeder->apply( $plan, $m, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertStringContainsString( 'in the trash', $plan->items()[0]->note );
		$this->assertSame( $navIds['primary|'], $again['primary|'] );
		$this->assertSame( 'trash', get_post( $navIds['primary|'] )->post_status );
		$this->assertCount( 1, get_posts( [ 'post_type' => 'wp_navigation', 'post_status' => [ 'publish', 'trash' ], 'posts_per_page' => -1, 'fields' => 'ids' ] ) );
	}

	public function test_an_unpublished_nav_is_left_alone_and_says_why() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$navIds = $seeder->apply( $seeder->plan( $m ), $m, $ids );
		wp_update_post( [ 'ID' => $navIds['primary|'], 'post_status' => 'draft' ] );

		$plan = $seeder->plan( $m );
		$seeder->apply( $plan, $m, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertStringContainsString( 'does not render', $plan->items()[0]->note );
		$this->assertSame( 'draft', get_post( $navIds['primary|'] )->post_status );
	}

	public function test_two_entities_under_one_key_are_reported() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ];
		$seeder->apply( $seeder->plan( $m ), $m, $ids );
		$impostor = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		update_post_meta( $impostor, \Pediment\Seeder\Meta::KEY, 'primary' );

		$plan = $seeder->plan( $m );

		$this->assertTrue( $plan->hasErrors() );
		$this->assertStringContainsString( 'primary', $plan->errors()[0] );
	}

	public function test_an_existing_nav_is_not_checked_for_missing_links() {
		// The seeder no longer manages an existing menu's links, so an entry
		// the menu cannot resolve is not its error to report.
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );
		$navId  = self::factory()->post->create(
			[ 'post_type' => 'wp_navigation', 'post_content' => $seeder->serialize( $m->navs()['primary'], '', [] ) ]
		);
		update_post_meta( $navId, Meta::KEY, 'primary' );

		$plan = $seeder->plan( $m );
		$seeder->apply( $plan, $m, [] );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertSame( [], $seeder->errors() );
	}

	public function test_an_edited_nav_is_never_rewritten() {
		// A person reworked the menu in the Site Editor: dropped a link and
		// added their own. The manifest also changed. The menu stays theirs.
		$seeder = new NavSeeder( new NullProvider() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page' ] ),
		];
		$m      = $this->manifest( [ [ 'entry' => 'home' ], [ 'entry' => 'about' ] ] );
		$navId  = $seeder->apply( $seeder->plan( $m ), $m, $ids )['primary|'];
		wp_update_post(
			[
				'ID'           => $navId,
				'post_content' => wp_slash( '<!-- wp:navigation-link {"label":"Shop","url":"/shop/","kind":"custom"} /-->' ),
			]
		);
		$edited = get_post( $navId )->post_content;

		$changed = $this->manifest( [ [ 'entry' => 'about' ], [ 'url' => '/contact', 'label' => 'Contact' ] ] );
		$plan    = $seeder->plan( $changed );
		$seeder->apply( $plan, $changed, $ids );

		$this->assertSame( PlanItem::UNCHANGED, $plan->items()[0]->action );
		$this->assertFalse( $plan->items()[0]->writes() );
		$this->assertSame( $edited, get_post( $navId )->post_content );
		$this->assertSame( [], $seeder->errors() );
	}

	public function test_serialize_has_no_side_effects() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( [ [ 'entry' => 'home' ] ] );

		$seeder->serialize( $m->navs()['primary'], '', [] );
		$seeder->serialize( $m->navs()['primary'], '', [] );

		$this->assertSame( [], $seeder->errors(), 'serialize() is a formatter, not a reporter' );
	}

	private function submenuManifest( array $items ): Manifest {
		return Manifest::fromArray(
			[
				'pages' => [
					'home'  => [ 'title' => 'Home', 'content' => '' ],
					'guide' => [ 'title' => 'Guide', 'content' => '' ],
					'faq'   => [ 'title' => 'FAQ', 'content' => '' ],
				],
				'navs'  => [ 'primary' => [ 'title' => 'Primary', 'items' => $items ] ],
			],
			'/tmp/theme'
		);
	}

	public function test_an_item_with_children_serializes_as_a_submenu() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->submenuManifest(
			[
				[ 'entry' => 'home' ],
				[ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ],
			]
		);

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'home|' => 11, 'guide|' => 12, 'faq|' => 13 ] );

		$this->assertStringContainsString( '<!-- wp:navigation-submenu ', $markup );
		$this->assertStringContainsString( '<!-- /wp:navigation-submenu -->', $markup );
		$this->assertSame( 1, substr_count( $markup, '<!-- wp:navigation-submenu ' ) );
		$this->assertSame( 2, substr_count( $markup, 'wp:navigation-link' ), 'home and faq are links; guide is a submenu' );
		$this->assertStringContainsString( '"id":13', $markup );
	}

	public function test_a_submenu_parent_keeps_the_same_attribute_order_as_a_link() {
		$seeder = new NavSeeder( new NullProvider() );
		$id     = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Guide' ] );
		$m      = $this->submenuManifest( [ [ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ] ] );

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'guide|' => $id, 'faq|' => 13 ] );

		$this->assertMatchesRegularExpression(
			'/wp:navigation-submenu \{"label":".*?","type":".*?","id":\d+,"kind":"post-type","url":/',
			$markup
		);
	}

	public function test_a_url_item_may_carry_children() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->submenuManifest(
			[ [ 'url' => '/more', 'label' => 'More', 'children' => [ [ 'entry' => 'faq' ] ] ] ]
		);

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'faq|' => 13 ] );

		$this->assertStringContainsString( '"label":"More"', $markup );
		$this->assertStringContainsString( '"kind":"custom"', $markup );
		$this->assertSame( 1, substr_count( $markup, '<!-- wp:navigation-submenu ' ) );
	}

	public function test_an_unresolved_submenu_parent_takes_its_children_with_it() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->submenuManifest(
			[
				[ 'entry' => 'home' ],
				[ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ],
			]
		);

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'home|' => 11, 'faq|' => 13 ] );

		$this->assertStringNotContainsString( 'navigation-submenu', $markup );
		$this->assertStringNotContainsString( '"id":13', $markup, 'a child without its parent is not promoted to top level' );
		$this->assertSame( 1, substr_count( $markup, 'wp:navigation-link' ) );
	}

	public function test_an_unresolved_child_blocks_creating_the_menu() {
		// serialize() would drop the child, so creating now would ship a
		// shortened menu. The menu waits until the child's page exists.
		$seeder = new NavSeeder( new NullProvider() );
		$guide  = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$full   = $this->submenuManifest( [ [ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ] ] );

		$navIds = $seeder->apply( $seeder->plan( $full ), $full, [ 'guide|' => $guide ] );

		$this->assertArrayNotHasKey( 'primary|', $navIds );
		$this->assertSame( [], get_posts( [ 'post_type' => 'wp_navigation', 'post_status' => 'any', 'fields' => 'ids' ] ) );
		$this->assertContains(
			'navs.primary: "faq" has no seeded post yet — the menu was not created.',
			$seeder->errors()
		);
	}

	public function test_the_planned_item_count_includes_children() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->submenuManifest(
			[
				[ 'entry' => 'home' ],
				[ 'entry' => 'guide', 'children' => [ [ 'entry' => 'faq' ] ] ],
			]
		);

		$plan = $seeder->plan( $m );

		$this->assertSame( PlanItem::CREATE, $plan->items()[0]->action );
		$this->assertSame( 3, $plan->items()[0]->changes['items']['to'], 'home + guide + faq' );
	}

	private function megaItems( string $label = 'Products' ): array {
		return [
			[ 'entry' => 'home' ],
			[
				'mega' => [
					'label'   => $label,
					'columns' => [
						[
							'heading' => 'Pages',
							'icon'    => 'bank',
							'links'   => [
								[ 'entry' => 'about', 'description' => 'Who we are' ],
								[ 'label' => 'Savings', 'url' => '/savings/' ],
							],
						],
					],
				],
			],
		];
	}

	public function test_serializes_a_mega_item_with_fixed_key_order() {
		$seeder  = new NavSeeder( new NullProvider() );
		$aboutId = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] );
		$m       = $this->manifest( $this->megaItems() );

		$markup = $seeder->serialize( $m->navs()['primary'], '', [ 'home|' => 12, 'about|' => $aboutId ] );

		$this->assertStringContainsString(
			'<!-- wp:pediment/mega-menu {"label":"Products","columns":[{"heading":"Pages","icon":"bank","links":['
				. '{"label":"About","description":"Who we are","url":"' . get_permalink( $aboutId ) . '"},'
				. '{"label":"Savings","url":"/savings/"}'
				. ']}]} /-->',
			$markup
		);
		$this->assertStringContainsString( 'wp:navigation-link', $markup, 'ordinary items still serialize around it' );
	}

	public function test_optional_mega_keys_are_omitted_not_emitted_empty() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest(
			[ [ 'mega' => [ 'label' => 'P', 'columns' => [ [ 'heading' => 'H', 'links' => [ [ 'label' => 'L', 'url' => '/l/' ] ] ] ] ] ] ]
		);

		$markup = $seeder->serialize( $m->navs()['primary'], '', [] );

		$this->assertStringNotContainsString( '"icon"', $markup );
		$this->assertStringNotContainsString( '"description"', $markup );
	}

	public function test_an_unresolved_mega_entry_is_dropped_and_reported() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( $this->megaItems() );
		$ids    = [ 'home|' => self::factory()->post->create( [ 'post_type' => 'page' ] ) ]; // 'about' never seeded.

		$markup = $seeder->serialize( $m->navs()['primary'], '', $ids );
		$seeder->apply( $seeder->plan( $m ), $m, $ids );

		$this->assertStringNotContainsString( '"description":"Who we are"', $markup, 'the unresolved link is dropped' );
		$this->assertStringContainsString( '"label":"Savings"', $markup, 'resolvable links stay' );
		$this->assertNotEmpty( $seeder->errors() );
		$this->assertStringContainsString( 'about', $seeder->errors()[0] );
	}

	public function test_a_claimed_nav_is_left_exactly_as_it_is() {
		// A claimed nav carries a seed key and its hand-built content — exactly
		// what Claimer leaves behind. The seed after the claim keeps all of it.
		$seeder   = new NavSeeder( new NullProvider() );
		$m        = $this->manifest( $this->megaItems() );
		$ids      = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];
		$handmade = '<!-- wp:pediment/mega-menu {"label":"Hand-built","columns":[{"heading":"Mine","links":[{"label":"Keep","url":"/keep/"}]}]} /-->';
		$navId    = self::factory()->post->create(
			[ 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_content' => $handmade ]
		);
		update_post_meta( $navId, Meta::KEY, 'primary' );
		$before = get_post( $navId )->post_content;

		$seeder->apply( $seeder->plan( $m ), $m, $ids );

		$this->assertSame( $before, get_post( $navId )->post_content );
	}

	public function test_a_new_mega_nav_carries_no_hash_meta() {
		$seeder = new NavSeeder( new NullProvider() );
		$m      = $this->manifest( $this->megaItems() );
		$ids    = [
			'home|'  => self::factory()->post->create( [ 'post_type' => 'page' ] ),
			'about|' => self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] ),
		];

		$navId = $seeder->apply( $seeder->plan( $m ), $m, $ids )['primary|'];

		$this->assertStringContainsString( 'wp:pediment/mega-menu', get_post( $navId )->post_content );
		$this->assertSame( '', (string) get_post_meta( $navId, '_pediment_seed_mega_hash', true ) );
		$this->assertSame( [ 'primary|' ], $seeder->created() );
	}

	public function test_a_mega_free_manifest_serializes_exactly_as_before() {
		// Locks the byte format menus seeded by earlier versions carry, so a
		// menu created today looks exactly like one created before.
		$seeder  = new NavSeeder( new NullProvider() );
		$homeId  = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Home' ] );
		$aboutId = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'About' ] );
		$ids     = [ 'home|' => $homeId, 'about|' => $aboutId ];
		$m       = $this->manifest(
			[
				[ 'entry' => 'home' ],
				[ 'url' => '/contact', 'label' => 'Contact' ],
				[ 'entry' => 'about', 'children' => [ [ 'url' => '/faq', 'label' => 'FAQ' ] ] ],
				[ 'language_switcher' => true ],
			]
		);

		$expected = implode(
			"\n",
			[
				'<!-- wp:navigation-link {"label":"Home","type":"page","id":' . $homeId . ',"kind":"post-type","url":"' . get_permalink( $homeId ) . '"} /-->',
				'<!-- wp:navigation-link {"label":"Contact","url":"/contact","kind":"custom"} /-->',
				'<!-- wp:navigation-submenu {"label":"About","type":"page","id":' . $aboutId . ',"kind":"post-type","url":"' . get_permalink( $aboutId ) . '"} -->'
					. "\n" . '<!-- wp:navigation-link {"label":"FAQ","url":"/faq","kind":"custom"} /-->' . "\n"
					. '<!-- /wp:navigation-submenu -->',
				// NullProvider emits no switcher block; a monolingual site's
				// language_switcher item is simply omitted.
			]
		);

		$this->assertSame( $expected, $seeder->serialize( $m->navs()['primary'], '', $ids ) );
	}
}
