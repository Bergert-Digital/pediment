<?php

class SeedNavTest extends WP_UnitTestCase {

	public function test_menu_blocks_contain_curated_items() {
		$blocks = starter_nav_menu_blocks();
		$this->assertStringContainsString( '"label":"About"', $blocks );
		$this->assertStringContainsString( '"label":"Blog"', $blocks );
		$this->assertStringContainsString( '"label":"Contact"', $blocks );
		$this->assertStringNotContainsString( 'nav-cta', $blocks );
		$this->assertSame( 3, substr_count( $blocks, 'wp:navigation-link' ) );
	}

	public function test_menu_blocks_include_starter_mega_menu() {
		$blocks = starter_nav_menu_blocks();
		$this->assertStringContainsString( 'wp:starter/mega-menu', $blocks );
		$this->assertStringContainsString( '"label":"Products"', $blocks );
		$this->assertSame( 1, substr_count( $blocks, 'wp:starter/mega-menu' ) );
	}

	public function test_pristine_fallback_detection() {
		$this->assertTrue( starter_nav_is_pristine_fallback( '<!-- wp:page-list /-->' ) );
		$this->assertTrue( starter_nav_is_pristine_fallback( "\n <!-- wp:page-list /-->  \n" ) );
		$this->assertFalse( starter_nav_is_pristine_fallback( starter_nav_menu_blocks() ) );
		$this->assertFalse( starter_nav_is_pristine_fallback( '' ) );
	}

	public function test_creates_entity_when_none_exists() {
		$this->assertSame( 0, starter_nav_find_entity_id() );

		$id = starter_nav_seed_entity();

		$this->assertGreaterThan( 0, $id );
		$post = get_post( $id );
		$this->assertSame( 'wp_navigation', $post->post_type );
		$this->assertSame( starter_nav_menu_blocks(), $post->post_content );
		$this->assertSame( '1', get_post_meta( $id, STARTER_NAV_MARKER, true ) );
		$this->assertSame( $id, starter_nav_find_entity_id() );
	}

	public function test_is_idempotent_and_preserves_user_edits() {
		$id = starter_nav_seed_entity();
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => '<!-- wp:navigation-link {"label":"Edited","url":"/x","kind":"custom"} /-->',
			)
		);

		$again = starter_nav_seed_entity();

		$this->assertSame( $id, $again, 'Re-seeding must not create a duplicate' );
		$this->assertStringContainsString(
			'"label":"Edited"',
			get_post( $id )->post_content,
			'Re-seeding must not clobber user edits to the marked entity'
		);
	}

	public function test_adopts_pristine_page_list_fallback() {
		$fallback_id = wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'Navigation',
				'post_content' => '<!-- wp:page-list /-->',
			),
			true
		);

		$id = starter_nav_seed_entity();

		$this->assertSame( (int) $fallback_id, $id, 'Pristine fallback must be adopted, not duplicated' );
		$this->assertSame( starter_nav_menu_blocks(), get_post( $id )->post_content );
		$this->assertSame( '1', get_post_meta( $id, STARTER_NAV_MARKER, true ) );
	}

	public function test_does_not_touch_user_authored_navigation() {
		$user_nav_id = wp_insert_post(
			array(
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_title'   => 'My Menu',
				'post_content' => '<!-- wp:navigation-link {"label":"Custom","url":"/c","kind":"custom"} /-->',
			),
			true
		);

		$id = starter_nav_seed_entity();

		$this->assertNotSame( (int) $user_nav_id, $id, 'User-authored menu must not be adopted' );
		$this->assertStringContainsString(
			'"label":"Custom"',
			get_post( $user_nav_id )->post_content,
			'User-authored menu content must be left untouched'
		);
		$this->assertSame( starter_nav_menu_blocks(), get_post( $id )->post_content );
	}

	public function test_bind_ref_sets_ref_on_bare_navigation_block() {
		$id = starter_nav_seed_entity();

		$bound = starter_nav_bind_ref(
			array(
				'blockName' => 'core/navigation',
				'attrs'     => array(),
			)
		);
		$this->assertSame( $id, $bound['attrs']['ref'] );
	}

	public function test_bind_ref_leaves_other_blocks_and_existing_ref_alone() {
		starter_nav_seed_entity();

		$paragraph = starter_nav_bind_ref(
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
			)
		);
		$this->assertArrayNotHasKey( 'ref', $paragraph['attrs'] );

		$preset = starter_nav_bind_ref(
			array(
				'blockName' => 'core/navigation',
				'attrs'     => array( 'ref' => 999 ),
			)
		);
		$this->assertSame( 999, $preset['attrs']['ref'] );
	}
}
