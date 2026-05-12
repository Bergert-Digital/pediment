<?php
namespace StarterAi\Tests\Chat;

use StarterAi\Chat\ConversationStore;

class ConversationStoreTest extends \WP_UnitTestCase {
	private ConversationStore $store;

	public function setUp(): void {
		parent::setUp();
		\starter_ai_install_tables();
		global $wpdb;
		$wpdb->query( "TRUNCATE {$wpdb->prefix}starter_ai_chat_conversations" );
		$wpdb->query( "TRUNCATE {$wpdb->prefix}starter_ai_chat_messages" );
		$this->store = new ConversationStore();
	}

	public function test_get_or_create_creates_when_missing(): void {
		$conv = $this->store->getOrCreate( 42, 7 );
		$this->assertSame( 42, $conv['post_id'] );
		$this->assertSame( 7,  $conv['user_id'] );
		$this->assertGreaterThan( 0, $conv['id'] );
		$this->assertSame( [], $conv['messages'] );
	}

	public function test_get_or_create_returns_existing(): void {
		$first  = $this->store->getOrCreate( 42, 7 );
		$second = $this->store->getOrCreate( 42, 7 );
		$this->assertSame( $first['id'], $second['id'] );
	}

	public function test_get_or_create_scopes_per_user(): void {
		$a = $this->store->getOrCreate( 42, 7 );
		$b = $this->store->getOrCreate( 42, 8 );
		$this->assertNotSame( $a['id'], $b['id'] );
	}

	public function test_find_by_id_returns_conversation_when_present(): void {
		$created = $this->store->getOrCreate( 10, 5 );
		$found   = $this->store->findById( $created['id'] );
		$this->assertNotNull( $found );
		$this->assertSame( $created['id'], $found['id'] );
		$this->assertSame( 10, $found['post_id'] );
		$this->assertSame( 5,  $found['user_id'] );
	}

	public function test_find_by_id_returns_null_for_unknown_id(): void {
		$this->assertNull( $this->store->findById( 999_999 ) );
	}
}
