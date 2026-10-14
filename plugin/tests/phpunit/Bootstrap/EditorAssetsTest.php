<?php

class EditorAssetsTest extends WP_UnitTestCase {

	public function test_no_chat_editor_bundle_is_enqueued(): void {
		do_action( 'enqueue_block_editor_assets' );
		$this->assertFalse( wp_script_is( 'pediment-editor', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'pediment-editor', 'enqueued' ) );
	}

	public function test_core_provides_the_jsx_runtime_blocks_depend_on(): void {
		$this->assertTrue( wp_script_is( 'react-jsx-runtime', 'registered' ) );
		$this->assertStringNotContainsString( 'react-jsx-runtime-shim', (string) wp_scripts()->registered['react-jsx-runtime']->src );
	}

	public function test_plugin_requires_wordpress_6_9(): void {
		$data = get_file_data( PEDIMENT_AI_PLUGIN_FILE, array( 'requires' => 'Requires at least' ) );
		$this->assertSame( '6.9', $data['requires'] );
	}
}
