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
}
