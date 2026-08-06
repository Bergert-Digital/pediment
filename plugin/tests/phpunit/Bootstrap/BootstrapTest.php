<?php
// tests/phpunit/Bootstrap/BootstrapTest.php
class BootstrapTest extends WP_UnitTestCase {

	public function test_bootstrap_seeds_header_template_part(): void {
		pediment_bootstrap();

		$parts = get_posts(
			array(
				'post_type'   => 'wp_template_part',
				'name'        => 'header',
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		$this->assertNotEmpty( $parts, 'header template part should exist after bootstrap' );
	}

	public function test_bootstrap_leaves_permalink_structure_untouched(): void {
		// Bootstrap must not force pretty permalinks: in containerized installs
		// that breaks REST (rest_url() -> /wp-json/ 404s). See pediment#47.
		update_option( 'permalink_structure', '' );
		pediment_bootstrap();
		$this->assertSame(
			'',
			(string) get_option( 'permalink_structure' ),
			'bootstrap must not change the site permalink structure'
		);
	}

	public function test_bootstrap_is_idempotent(): void {
		pediment_bootstrap();
		pediment_bootstrap();
		$parts = get_posts(
			array(
				'post_type'   => 'wp_template_part',
				'name'        => 'header',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		$this->assertCount( 1, $parts, 'bootstrap must not duplicate the header part' );
	}

	/**
	 * Reproduces the real wp-env sequence: the plugin's bootstrap seeds a header
	 * part while one theme is active (e.g. the core default, active before the
	 * client theme is switched to), then the client theme becomes active and
	 * bootstrap runs again. Each theme must end up with its own header part
	 * whose stored post_name is the literal "header" -- the client theme's
	 * templates/index.html hard-codes {"slug":"header"}, so anything else
	 * (a missing part, or one suffixed "header-2") renders as a broken header.
	 */
	public function test_bootstrap_scopes_header_part_to_each_active_theme(): void {
		$original_theme = get_stylesheet();

		try {
			switch_theme( 'twentytwentyfive' );
			pediment_bootstrap_header_template_part();

			switch_theme( 'pediment-fixture' );
			pediment_bootstrap_header_template_part();

			foreach ( array( 'twentytwentyfive', 'pediment-fixture' ) as $theme ) {
				$parts = get_posts(
					array(
						'post_type'   => 'wp_template_part',
						'post_status' => 'publish',
						'numberposts' => -1,
						'tax_query'   => array(
							array(
								'taxonomy' => 'wp_theme',
								'field'    => 'name',
								'terms'    => $theme,
							),
						),
					)
				);

				$this->assertCount( 1, $parts, "theme \"$theme\" should have exactly one header part" );
				$this->assertSame(
					'header',
					$parts[0]->post_name,
					"the header part scoped to \"$theme\" must keep the literal slug \"header\", not a suffixed or missing one"
				);
			}
		} finally {
			switch_theme( $original_theme );
		}
	}

	private function headerPart(): WP_Post {
		$parts = get_posts(
			array(
				'post_type'   => 'wp_template_part',
				'post_name__in' => array( 'header' ),
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);
		$this->assertCount( 1, $parts, 'exactly one header part should exist' );
		return $parts[0];
	}

	public function test_a_theme_registered_header_pattern_supplies_the_initial_markup(): void {
		register_block_pattern(
			get_stylesheet() . '/header',
			array(
				'title'    => 'Header',
				'content'  => '<!-- wp:paragraph --><p>Branded header</p><!-- /wp:paragraph -->',
				'inserter' => false,
			)
		);

		pediment_bootstrap_header_template_part();

		$this->assertStringContainsString( 'Branded header', $this->headerPart()->post_content );

		unregister_block_pattern( get_stylesheet() . '/header' );
	}

	public function test_the_generic_header_is_used_when_no_pattern_is_registered(): void {
		pediment_bootstrap_header_template_part();

		$this->assertStringContainsString( 'site-header', $this->headerPart()->post_content );
	}

	public function test_an_existing_header_part_is_never_overwritten_by_the_pattern(): void {
		pediment_bootstrap_header_template_part();
		wp_update_post(
			array(
				'ID'           => $this->headerPart()->ID,
				'post_content' => '<!-- wp:paragraph --><p>Edited</p><!-- /wp:paragraph -->',
			)
		);

		register_block_pattern(
			get_stylesheet() . '/header',
			array(
				'title'    => 'Header',
				'content'  => '<!-- wp:paragraph --><p>Branded header</p><!-- /wp:paragraph -->',
				'inserter' => false,
			)
		);
		pediment_bootstrap_header_template_part();

		$this->assertStringContainsString( 'Edited', $this->headerPart()->post_content );

		unregister_block_pattern( get_stylesheet() . '/header' );
	}

	/**
	 * A direct call to pediment_bootstrap_header_template_part() cannot see this
	 * bug: it always reads whatever happens to already be sitting in the
	 * pattern registry at the moment it's called. The real production trigger
	 * is the `after_switch_theme` hook itself. Core provides no guarantee that
	 * this fires only after the newly active theme's own `patterns/*.php` have
	 * been scanned: that scan is a *separate* mechanism
	 * (`_register_theme_block_patterns()`, `wp-includes/block-patterns.php`,
	 * hooked on `init`) with no ordering tie to `after_switch_theme` beyond
	 * incidental hook-priority numbers that this plugin has no control over.
	 *
	 * This test fires the real hooks -- `switch_theme()` then the
	 * `after_switch_theme` action -- in the order that exposes the gap: the
	 * theme becomes active and `after_switch_theme` runs *before* its own
	 * `<stylesheet>/header` pattern is registered, exactly as a fresh
	 * activation can produce. Only a later `init` (standing in for the next
	 * request's real pattern scan) is where the pattern becomes available.
	 *
	 * The pattern is registered *on* `init` at priority 10 -- core's own
	 * priority for `_register_theme_block_patterns()` -- rather than eagerly
	 * before `do_action( 'init' )`. That is what makes the deferred seed's own
	 * priority load-bearing here: registering eagerly pins only
	 * deferral-to-init, so dropping bootstrap.php's `add_action( 'init', ...,
	 * 100 )` to anything below 10 would leave this test green while every real
	 * site silently fell back to the generic header again. Registered on the
	 * hook, a seed that runs before priority 10 sees an empty registry and the
	 * assertion below fails, which is the whole point.
	 */
	public function test_a_real_theme_switch_seeds_the_header_only_once_its_pattern_is_registered(): void {
		$theme = get_stylesheet();

		// Stand-in for the *next* real request's `init`: core's
		// _register_theme_block_patterns() scans the now-active theme's
		// patterns/*.php on `init` at the default priority 10.
		$register_pattern = static function () use ( $theme ): void {
			register_block_pattern(
				$theme . '/header',
				array(
					'title'    => 'Header',
					'content'  => '<!-- wp:paragraph --><p>Branded via init</p><!-- /wp:paragraph -->',
					'inserter' => false,
				)
			);
		};

		try {
			switch_theme( $theme );
			// The real trigger, fired directly rather than relying on core's
			// own (and, as documented above, unguaranteed) timing to get here.
			// At this exact instant the theme's own pattern has not been
			// registered yet -- exactly like a real deployment.
			do_action( 'after_switch_theme', $theme );

			add_action( 'init', $register_pattern, 10 );
			do_action( 'init' );

			$this->assertStringContainsString(
				'Branded via init',
				$this->headerPart()->post_content,
				'the header must be seeded from the pattern once it is actually registered, not from whatever the registry held at the instant after_switch_theme fired'
			);
		} finally {
			remove_action( 'init', $register_pattern, 10 );
			unregister_block_pattern( $theme . '/header' );
			// Literal option name (rather than the bootstrap.php constant): this
			// test must also compile and run against the pre-fix code, which
			// does not define it.
			delete_option( 'pediment_header_seed_pending' );
		}
	}
}
