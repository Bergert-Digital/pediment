<?php
/**
 * The shared editor script (build/editor) loads for every block, so the
 * pediment/accent RichText format works on core headings and paragraphs.
 *
 * @package Pediment
 */

class EditorFormatsTest extends WP_UnitTestCase {

	public function test_the_shared_editor_script_is_enqueued(): void {
		do_action( 'enqueue_block_editor_assets' );
		$this->assertTrue( wp_script_is( 'pediment-editor-formats', 'enqueued' ) );
	}

	public function test_the_script_uses_the_generated_dependencies(): void {
		$this->assertFileExists( PEDIMENT_AI_PLUGIN_DIR . '/build/editor/index.asset.php', 'Run `npm run build` in plugin/ first.' );
		$asset = require PEDIMENT_AI_PLUGIN_DIR . '/build/editor/index.asset.php';
		do_action( 'enqueue_block_editor_assets' );
		$script = wp_scripts()->registered['pediment-editor-formats'];
		$this->assertSame( $asset['dependencies'], $script->deps );
		$this->assertSame( $asset['version'], $script->ver );
		$this->assertContains( 'wp-rich-text', $script->deps );
	}
}
