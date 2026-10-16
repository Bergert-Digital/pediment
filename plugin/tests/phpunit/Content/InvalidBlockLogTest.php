<?php

use Pediment\Content\InvalidBlockLog;

class InvalidBlockLogTest extends WP_UnitTestCase {

	private function post( int $page_id, array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/pediment/v1/invalid-blocks/' . $page_id );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return rest_do_request( $request );
	}

	public function test_editor_can_record_and_get_reads_it(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$response = $this->post( $page, array( 'blocks' => array( 'core/group', 'core/group', 'core/cover' ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'core/group', 'core/cover' ), InvalidBlockLog::get( $page )['blocks'] );
		$this->assertNotEmpty( InvalidBlockLog::get( $page )['checkedAt'] );
	}

	public function test_empty_list_is_recorded_and_clear_removes_it(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		InvalidBlockLog::record( $page, array() );
		$this->assertSame( array(), InvalidBlockLog::get( $page )['blocks'] );
		InvalidBlockLog::clear( $page );
		$this->assertNull( InvalidBlockLog::get( $page ) );
	}

	public function test_subscriber_and_bad_input_are_refused(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, $this->post( $page, array( 'blocks' => array() ) )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( 400, $this->post( $page, array( 'blocks' => array( 'Not A Block' ) ) )->get_status() );

		$post = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$this->assertSame( 404, $this->post( $post, array( 'blocks' => array() ) )->get_status() );
	}

	public function test_editor_script_is_enqueued(): void {
		do_action( 'enqueue_block_editor_assets' );
		$this->assertTrue( wp_script_is( 'pediment-invalid-blocks', 'enqueued' ) );
	}
}
