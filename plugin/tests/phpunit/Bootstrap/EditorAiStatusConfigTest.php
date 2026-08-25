<?php
namespace Pediment\Tests\Bootstrap;

use Pediment\Mock\MockProvider;

class EditorAiStatusConfigTest extends \WP_UnitTestCase {
	public function test_editor_bundle_receives_ai_status_config(): void {
		// Pin the provider so the asserted status is deterministic in every env.
		add_filter(
			'pediment_ai_provider',
			static fn() => new MockProvider( PEDIMENT_AI_PLUGIN_DIR . '/src/Mock/fixtures' )
		);

		do_action( 'enqueue_block_editor_assets' );

		$this->assertTrue( wp_script_is( 'pediment-editor', 'enqueued' ), 'editor bundle must be enqueued' );

		$before = wp_scripts()->get_data( 'pediment-editor', 'before' );
		$blob   = is_array( $before ) ? implode( "\n", $before ) : (string) $before;
		$this->assertStringContainsString( 'pedimentAiEditor', $blob );
		$this->assertStringContainsString( '"aiStatus":"mock"', $blob );
	}

	public function test_admins_get_a_link_to_the_ai_settings_tab(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		do_action( 'enqueue_block_editor_assets' );

		$before = wp_scripts()->get_data( 'pediment-editor', 'before' );
		$blob   = is_array( $before ) ? implode( "\n", $before ) : (string) $before;
		// The plugin ships its own settings hub; the notice must deep-link to
		// the AI tab there, not to the legacy standalone page.
		$this->assertStringContainsString( 'page=pediment-theme', $blob );
		$this->assertStringContainsString( 'tab=ai', $blob );
	}

	public function test_non_admins_get_no_settings_link(): void {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );

		do_action( 'enqueue_block_editor_assets' );

		$before = wp_scripts()->get_data( 'pediment-editor', 'before' );
		$blob   = is_array( $before ) ? implode( "\n", $before ) : (string) $before;
		$this->assertStringContainsString( '"settingsUrl":""', $blob );
	}
}
