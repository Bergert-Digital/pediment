<?php

class AutoLoaderTest extends WP_UnitTestCase {
	public function test_loader_function_exists() {
		$this->assertTrue( function_exists( 'pediment_register_blocks' ) );
	}

	public function test_loader_handles_missing_build_dir_gracefully() {
		pediment_register_blocks( '/nonexistent/path' );
		$this->assertTrue( true );
	}

	public function test_loader_registers_blocks_from_build_dir() {
		$tmp = sys_get_temp_dir() . '/starter-test-blocks-' . uniqid();
		mkdir( $tmp . '/dummy-block', 0777, true );
		file_put_contents(
			$tmp . '/dummy-block/block.json',
			wp_json_encode(
				array(
					'apiVersion' => 3,
					'name'       => 'pediment/dummy',
					'title'      => 'Dummy',
					'category'   => 'design',
					'attributes' => array( 'text' => array( 'type' => 'string', 'default' => '' ) ),
				)
			)
		);

		pediment_register_blocks( $tmp );

		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'pediment/dummy' ) );

		$registry->unregister( 'pediment/dummy' );
	}
}
