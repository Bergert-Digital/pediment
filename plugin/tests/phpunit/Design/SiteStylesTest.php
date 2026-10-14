<?php

use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;

class SiteStylesTest extends WP_UnitTestCase {

	private SiteStyles $store;

	public function set_up(): void {
		parent::set_up();
		$this->store = new SiteStyles( new StyleValidator() );
	}

	private function spec( array $styles = array(), string $slug = 'promo' ): array {
		return array(
			'slug'       => $slug,
			'label'      => 'Promo',
			'blockTypes' => array( 'core/group' ),
			'styles'     => $styles + array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
		);
	}

	public function test_post_type_is_private_and_revisioned(): void {
		$type = get_post_type_object( SiteStyles::POST_TYPE );
		$this->assertNotNull( $type );
		$this->assertFalse( $type->public );
		$this->assertFalse( $type->show_in_rest );
		$this->assertTrue( post_type_supports( SiteStyles::POST_TYPE, 'revisions' ) );
	}

	public function test_save_and_get_round_trip(): void {
		$result = $this->store->save( $this->spec() );
		$this->assertIsArray( $result );
		$stored = $this->store->get( 'promo' );
		$this->assertSame( 'Promo', $stored['label'] );
		$this->assertSame( array( 'core/group' ), $stored['blockTypes'] );
		$this->assertSame( SiteStyles::STATE_ACTIVE, $stored['state'] );
	}

	public function test_css_with_special_characters_survives_without_unfiltered_html(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		add_filter( 'map_meta_cap', static fn( $caps, $cap ) => 'unfiltered_html' === $cap ? array( 'do_not_allow' ) : $caps, 10, 2 );
		kses_init();
		$this->assertFalse( current_user_can( 'unfiltered_html' ) );
		$css = '&>.x::after{content:"→ \'q\' \\"q\\"";width:26px}';
		$this->store->save( $this->spec( array( 'css' => $css ) ) );
		$this->assertSame( $css, $this->store->get( 'promo' )['styles']['css'] );
	}

	public function test_unencodable_spec_is_not_stored(): void {
		// wp_json_encode() repairs invalid UTF-8 but returns false for INF.
		$result = $this->store->save( $this->spec( array( 'typography' => array( 'lineHeight' => INF ) ) ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_style_encode_failed', $result->get_error_code() );
		$this->assertNull( $this->store->get( 'promo' ) );
		$this->assertSame( array(), get_posts( array( 'post_type' => SiteStyles::POST_TYPE, 'post_status' => 'any' ) ) );
	}

	public function test_saving_the_same_slug_updates_and_adds_a_revision(): void {
		$first  = $this->store->save( $this->spec() );
		$second = $this->store->save( $this->spec( array( 'shadow' => 'var:preset|shadow|subtle' ) ) );
		$this->assertSame( $first['id'], $second['id'] );
		$this->assertCount( 1, $this->store->all() );
		$this->assertNotSame( $first['revision'], $second['revision'] );
		$this->assertGreaterThan( 0, $second['revision'] );
	}

	public function test_invalid_spec_is_rejected_with_errors(): void {
		$result = $this->store->save( $this->spec( array( 'border' => array( 'radius' => '22px' ) ) ) );
		$this->assertWPError( $result );
		$this->assertSame( 'pediment_style_invalid', $result->get_error_code() );
		$this->assertNotEmpty( $result->get_error_data()['errors'] );
		$this->assertNull( $this->store->get( 'promo' ) );
	}

	public function test_state_filter_and_delete(): void {
		$this->store->save( $this->spec() );
		$this->store->save( $this->spec( array(), 'other' ) );
		$this->assertTrue( $this->store->setState( 'other', SiteStyles::STATE_PROMOTED ) );
		$this->assertSame( array( 'promo' ), array_column( $this->store->all( SiteStyles::STATE_ACTIVE ), 'slug' ) );
		$this->assertTrue( $this->store->delete( 'promo' ) );
		$this->assertNull( $this->store->get( 'promo' ) );
		$this->assertFalse( $this->store->delete( 'missing' ) );
	}
}
