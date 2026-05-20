<?php

class HomeTemplateTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		$this->assertFileExists( $this->template_path(), 'templates/home.html must exist' );
	}

	private function template_path(): string {
		return get_theme_file_path( 'templates/home.html' );
	}

	private function template_blocks(): array {
		return parse_blocks( file_get_contents( $this->template_path() ) );
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

	public function test_template_has_header_and_footer_parts(): void {
		$blocks = $this->template_blocks();
		$parts  = $this->find_all_blocks( $blocks, 'core/template-part' );
		$slugs  = array_map( static fn( $b ) => $b['attrs']['slug'] ?? '', $parts );
		$this->assertContains( 'header', $slugs );
		$this->assertContains( 'footer', $slugs );
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
