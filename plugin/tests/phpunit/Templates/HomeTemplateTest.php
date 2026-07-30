<?php

class HomeTemplateTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		$this->assertNotNull( $this->template(), 'pediment//home must be a registered block template' );
	}

	/**
	 * The home template is registered from the plugin (Pediment\Templates\Registrar)
	 * regardless of which theme is active, so look it up in the registry
	 * instead of reading a theme file (Task 6 of the plugin-absorbs-theme
	 * migration).
	 */
	private function template(): ?WP_Block_Template {
		return WP_Block_Templates_Registry::get_instance()->get_registered( 'pediment//home' );
	}

	private function template_blocks(): array {
		return parse_blocks( $this->template()->content );
	}

	private function find_first_block( array $blocks, string $name ): ?array {
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) === $name ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$nested = $this->find_first_block( $block['innerBlocks'], $name );
				if ( null !== $nested ) {
					return $nested;
				}
			}
		}
		return null;
	}

	private function find_all_blocks( array $blocks, string $name ): array {
		$out = array();
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) === $name ) {
				$out[] = $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$out = array_merge( $out, $this->find_all_blocks( $block['innerBlocks'], $name ) );
			}
		}
		return $out;
	}

	public function test_template_has_header_part_and_footer_pattern(): void {
		$blocks = $this->template_blocks();

		$parts = $this->find_all_blocks( $blocks, 'core/template-part' );
		$slugs = array_map( static fn( $b ) => $b['attrs']['slug'] ?? '', $parts );
		$this->assertContains( 'header', $slugs, 'header stays a DB-seeded template part' );

		$patterns      = $this->find_all_blocks( $blocks, 'core/pattern' );
		$pattern_slugs = array_map( static fn( $b ) => $b['attrs']['slug'] ?? '', $patterns );
		$this->assertContains( 'pediment/footer', $pattern_slugs, 'footer is now the pediment/footer pattern' );
	}

	public function test_template_query_results_are_numerically_indexed(): void {
		$templates = get_block_templates( array( 'slug__in' => array( 'page' ) ) );

		$this->assertNotEmpty( $templates );
		$this->assertSame( array_values( $templates ), $templates );
		$this->assertSame( 'page', $templates[0]->slug );
	}

	public function test_template_has_heading_band_with_h1(): void {
		$blocks = $this->template_blocks();
		// First band group with kicker + h1 + lead.
		$groups = $this->find_all_blocks( $blocks, 'core/group' );
		$bands  = array_filter(
			$groups,
			static fn( $g ) => isset( $g['attrs']['className'] )
				&& str_contains( $g['attrs']['className'], 'starter-band' )
		);
		$this->assertNotEmpty( $bands, 'must contain at least one starter-band group' );
		$heading_band = array_values( $bands )[0];
		$h1           = $this->find_first_block( $heading_band['innerBlocks'], 'core/heading' );
		$this->assertNotNull( $h1, 'heading band must contain a core/heading' );
		$this->assertSame( 1, (int) ( $h1['attrs']['level'] ?? 2 ), 'heading must be level 1' );
	}

	public function test_template_has_query_with_insights_grid_class_and_inherit(): void {
		$blocks = $this->template_blocks();
		$query  = $this->find_first_block( $blocks, 'core/query' );
		$this->assertNotNull( $query, 'template must contain a core/query block' );
		$this->assertTrue(
			(bool) ( $query['attrs']['query']['inherit'] ?? false ),
			'query must use inherit:true'
		);
		$this->assertStringContainsString(
			'is-style-insights-grid',
			(string) ( $query['attrs']['className'] ?? '' ),
			'query must carry is-style-insights-grid className'
		);
	}

	public function test_query_contains_required_post_blocks(): void {
		$blocks = $this->template_blocks();
		$query  = $this->find_first_block( $blocks, 'core/query' );
		$this->assertNotNull( $query );
		foreach (
			array(
				'core/post-template',
				'core/post-featured-image',
				'core/post-terms',
				'core/post-date',
				'core/post-title',
				'core/post-excerpt',
				'core/read-more',
				'core/query-pagination',
				'core/query-no-results',
			) as $needle
		) {
			$this->assertNotNull(
				$this->find_first_block( $query['innerBlocks'], $needle ),
				"core/query must contain a $needle block"
			);
		}
	}

	public function test_post_terms_block_targets_category_taxonomy(): void {
		$blocks = $this->template_blocks();
		$terms  = $this->find_first_block( $blocks, 'core/post-terms' );
		$this->assertNotNull( $terms );
		$this->assertSame( 'category', $terms['attrs']['term'] ?? '' );
	}
}
