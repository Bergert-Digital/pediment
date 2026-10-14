<?php

use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;
use Pediment\Design\Vocabulary;

class VocabularyTest extends WP_UnitTestCase {

	public function test_layers_merge_by_slug_with_the_top_layer_winning(): void {
		$store = new SiteStyles( new StyleValidator() );
		$store->save(
			array(
				'slug'       => 'card',
				'label'      => 'Card (site)',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'border' => array( 'radius' => 'var(--wp--custom--radius--sm)' ) ),
			)
		);
		$theme = array(
			array(
				'title'      => 'Band',
				'slug'       => 'band',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|primary' ) ),
			),
		);

		$by_slug = array_column( ( new Vocabulary( new StyleValidator(), $store ) )->styles( $theme ), null, 'slug' );

		$this->assertSame( 'site', $by_slug['card']['layer'] );
		$this->assertSame( 'plugin', $by_slug['card']['overrides'] );
		$this->assertSame( 'Card (site)', $by_slug['card']['label'] );
		$this->assertSame( 'theme', $by_slug['band']['layer'] );
		$this->assertSame( 'plugin', $by_slug['band']['overrides'] );
		$this->assertSame( 'plugin', $by_slug['eyebrow']['layer'] );
		$this->assertNull( $by_slug['eyebrow']['overrides'] );
		$list = ( new Vocabulary( new StyleValidator(), $store ) )->styles( $theme );
		$this->assertCount( count( $by_slug ), $list, 'one entry per slug' );
	}

	public function test_invalid_theme_style_is_reported_not_dropped(): void {
		$theme = array(
			array(
				'title'      => 'Hero box',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'spacing' => array( 'padding' => array( 'top' => '78px' ) ) ),
			),
		);
		$by_slug = array_column( ( new Vocabulary( new StyleValidator(), new SiteStyles( new StyleValidator() ) ) )->styles( $theme ), null, 'slug' );

		$this->assertArrayHasKey( 'hero-box', $by_slug );
		$this->assertSame( 'theme', $by_slug['hero-box']['layer'] );
		$this->assertStringContainsString( 'styles.spacing.padding.top', $by_slug['hero-box']['warnings'][0] );
	}
}
