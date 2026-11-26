<?php

use Pediment\Privacy\Emoji;

/**
 * WordPress's emoji script and images load from s.w.org; Pediment turns them off.
 */
class EmojiTest extends WP_UnitTestCase {

	/** @var array<int,array{0:string,1:string,2:int}> */
	private const HOOKS = array(
		array( 'wp_head', 'print_emoji_detection_script', 7 ),
		array( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles', 10 ),
		array( 'wp_print_styles', 'print_emoji_styles', 10 ),
		array( 'admin_print_scripts', 'print_emoji_detection_script', 10 ),
		array( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles', 10 ),
		array( 'admin_print_styles', 'print_emoji_styles', 10 ),
		array( 'embed_head', 'print_emoji_detection_script', 10 ),
		array( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles', 10 ),
		array( 'the_content_feed', 'wp_staticize_emoji', 10 ),
		array( 'comment_text_rss', 'wp_staticize_emoji', 10 ),
		array( 'wp_mail', 'wp_staticize_emoji_for_email', 10 ),
	);

	public function set_up(): void {
		parent::set_up();
		// Every test starts from core's defaults; the plugin's own init run
		// has already removed them once.
		foreach ( self::HOOKS as list( $hook, $callback, $priority ) ) {
			add_action( $hook, $callback, $priority );
		}
		remove_filter( 'tiny_mce_plugins', array( Emoji::class, 'withoutTinyMcePlugin' ) );
	}

	public function tear_down(): void {
		remove_all_filters( Emoji::FILTER_ENABLED );
		parent::tear_down();
	}

	public function test_the_plugin_runs_it_on_init_and_admin_init(): void {
		$this->assertSame( 10, has_action( 'init', array( Emoji::class, 'apply' ) ) );
		$this->assertSame( 10, has_action( 'admin_init', array( Emoji::class, 'apply' ) ) );
	}

	public function test_it_removes_every_core_emoji_callback(): void {
		Emoji::apply();

		foreach ( self::HOOKS as list( $hook, $callback ) ) {
			$this->assertFalse( has_action( $hook, $callback ), "{$callback} is still on {$hook}" );
		}
		$this->assertSame( array( 'lists', 'wordpress' ), Emoji::withoutTinyMcePlugin( array( 'lists', 'wpemoji', 'wordpress' ) ) );
		$this->assertSame( 10, has_filter( 'tiny_mce_plugins', array( Emoji::class, 'withoutTinyMcePlugin' ) ) );
	}

	public function test_the_front_end_head_loads_nothing_from_s_w_org(): void {
		Emoji::apply();
		$this->go_to( home_url( '/' ) );

		ob_start();
		wp_head();
		$head = (string) ob_get_clean();

		$this->assertStringNotContainsString( 's.w.org', $head );
		$this->assertStringNotContainsString( '_wpemojiSettings', $head );
		$this->assertStringNotContainsString( 'wp-emoji', $head );
	}

	public function test_feeds_and_emails_keep_emoji_as_text(): void {
		Emoji::apply();

		$this->assertSame( '<p>Hi 😀</p>', apply_filters( 'the_content_feed', '<p>Hi 😀</p>', 'rss2' ) );
		$mail = apply_filters(
			'wp_mail',
			array(
				'to'      => 'a@example.org',
				'subject' => 'Hi',
				'message' => '<p>Hi 😀</p>',
				'headers' => array( 'Content-Type: text/html' ),
			)
		);
		$this->assertStringNotContainsString( 's.w.org', $mail['message'] );
	}

	public function test_a_site_can_turn_emoji_back_on(): void {
		add_filter( Emoji::FILTER_ENABLED, '__return_true' );
		Emoji::apply();

		foreach ( self::HOOKS as list( $hook, $callback, $priority ) ) {
			$this->assertSame( $priority, has_action( $hook, $callback ), "{$callback} was removed from {$hook}" );
		}
		$this->assertFalse( has_filter( 'tiny_mce_plugins', array( Emoji::class, 'withoutTinyMcePlugin' ) ) );
	}

	public function test_it_is_off_by_default(): void {
		$this->assertFalse( Emoji::enabled() );
	}
}
