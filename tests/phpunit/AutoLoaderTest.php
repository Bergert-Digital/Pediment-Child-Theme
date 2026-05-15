<?php

class AutoLoaderTest extends WP_UnitTestCase {
	public function test_loader_function_exists() {
		$this->assertTrue( function_exists( 'starter_child_register_blocks' ) );
	}

	public function test_loader_handles_missing_build_dir_gracefully() {
		starter_child_register_blocks( '/nonexistent/path' );
		$this->assertTrue( true );
	}

	public function test_loader_registers_blocks_from_build_dir() {
		$tmp = sys_get_temp_dir() . '/starter-child-test-blocks-' . uniqid();
		mkdir( $tmp . '/dummy-block', 0777, true );
		file_put_contents(
			$tmp . '/dummy-block/block.json',
			wp_json_encode(
				array(
					'apiVersion' => 3,
					'name'       => 'starter-child/dummy',
					'title'      => 'Dummy',
					'category'   => 'design',
					'attributes' => array( 'text' => array( 'type' => 'string', 'default' => '' ) ),
				)
			)
		);

		starter_child_register_blocks( $tmp );

		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'starter-child/dummy' ) );

		$registry->unregister( 'starter-child/dummy' );
	}
}
