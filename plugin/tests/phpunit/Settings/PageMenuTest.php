<?php
namespace Pediment\Tests\Settings;

use Pediment\Settings\Page;

/**
 * Covers where the settings UI is mounted: as a tab inside Pediment's shared
 * settings hub when the parent theme exposes pediment_settings_register_tab(),
 * or as a standalone Settings > Pediment page otherwise. Also pins the
 * split between the tab body (no page chrome) and the standalone shell.
 */
class PageMenuTest extends \WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	private function capture( callable $fn ): string {
		ob_start();
		$fn();
		return (string) ob_get_clean();
	}

	// --- Standalone fallback (no hub) -------------------------------------

	public function test_addMenu_registers_standalone_page_without_hub(): void {
		if ( function_exists( 'pediment_settings_register_tab' ) ) {
			$this->markTestSkipped( 'Hub API is defined in this process; fallback path cannot be exercised.' );
		}

		unset( $GLOBALS['submenu']['options-general.php'] );
		( new Page() )->addMenu();

		$slugs = array_column( $GLOBALS['submenu']['options-general.php'] ?? [], 2 );
		$this->assertContains( Page::SLUG, $slugs, 'Standalone options page must be registered when the hub is absent.' );
	}

	// --- Body / shell split ------------------------------------------------

	public function test_renderTabBody_emits_form_without_page_chrome(): void {
		$body = $this->capture( [ new Page(), 'renderTabBody' ] );

		$this->assertStringContainsString( 'action="options.php"', $body, 'Tab body must still submit through the Settings API.' );
		$this->assertStringContainsString( 'pediment_ai_group', $body, 'Tab body must emit settings_fields for the option group.' );
		$this->assertStringNotContainsString( 'class="wrap"', $body, 'The hub owns the .wrap; the tab body must not emit it.' );
		$this->assertStringNotContainsString( '<h1', $body, 'The hub owns the page <h1>; the tab body must not emit one.' );
	}

	public function test_render_wraps_body_in_standalone_shell(): void {
		$html = $this->capture( [ new Page(), 'render' ] );

		$this->assertStringContainsString( 'class="wrap"', $html, 'Standalone page must provide its own .wrap shell.' );
		$this->assertStringContainsString( '<h1', $html, 'Standalone page must provide its own <h1>.' );
		$this->assertStringContainsString( 'action="options.php"', $html, 'Standalone page must include the settings form body.' );
	}

	public function test_renderTabBody_bails_without_capability(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$body = $this->capture( [ new Page(), 'renderTabBody' ] );

		$this->assertSame( '', $body, 'Tab body must re-check manage_options and emit nothing for unprivileged users.' );
	}

	// --- Hub tab registration ---------------------------------------------
	// Since Task 4 (settings hub + forms engine moved into this plugin),
	// pediment_settings_register_tab() is always defined in this process —
	// it ships in the same artifact as Page, so the "hub absent" branch in
	// addMenu() can no longer be reached; it's covered only by the
	// self-skipping fallback test above. This test now asserts against the
	// real hub registry (inc/settings-page.php) instead of an eval() stub.

	public function test_addMenu_registers_ai_tab_when_hub_present(): void {
		$GLOBALS['pediment_settings_tabs'] = [];
		unset( $GLOBALS['submenu']['options-general.php'] );

		$page = new Page();
		$page->addMenu();

		$tabs = pediment_settings_get_tabs();
		$this->assertCount( 1, $tabs, 'Exactly one tab should be registered with the hub.' );
		$this->assertSame( 'ai', array_key_first( $tabs ) );
		$this->assertSame( 100, $tabs['ai']['priority'] );
		$this->assertSame( [ $page, 'renderTabBody' ], $tabs['ai']['render'] );

		$slugs = array_column( $GLOBALS['submenu']['options-general.php'] ?? [], 2 );
		$this->assertNotContains( Page::SLUG, $slugs, 'No standalone menu when mounting as a hub tab.' );

		unset( $GLOBALS['pediment_settings_tabs'] );
	}
}
