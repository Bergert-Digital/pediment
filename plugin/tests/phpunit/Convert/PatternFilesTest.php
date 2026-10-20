<?php

use Pediment\Content\Policy;
use Pediment\Convert\AttachmentMedia;
use Pediment\Convert\Converter;
use Pediment\Convert\PatternFiles;

class PatternFilesTest extends WP_UnitTestCase {

	private string $dir;

	private const HEADER = "<?php\n/**\n * Title: Home\n * Slug: acme/home\n */\n// phpcs:ignoreFile -- block pattern content\n?>\n";

	public function set_up(): void {
		parent::set_up();
		$this->dir = get_temp_dir() . 'pediment-patterns-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->dir );
	}

	public function tear_down(): void {
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->dir );
		parent::tear_down();
	}

	private function files(): PatternFiles {
		return new PatternFiles( Converter::withDefaults(), new Policy(), new AttachmentMedia() );
	}

	public function test_header_inline_php_and_media_placeholders_survive(): void {
		$body = '<!-- wp:pediment/media-text {"mediaId":{{media_id:team}}} -->' . "\n"
			. '<!-- wp:paragraph -->' . "\n" . '<p>Team</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n"
			. '<!-- /wp:pediment/media-text -->' . "\n\n"
			. '<!-- wp:pediment/hero {"variant":"centered","headline":"Welcome to <?php echo esc_html( get_bloginfo( \'name\' ) ); ?>","ctaText":"Go","ctaUrl":"<?php echo esc_url( home_url( \'/contact/\' ) ); ?>"} /-->' . "\n";
		$out  = $this->files()->convertText( self::HEADER . $body );

		$this->assertSame( 'preview', $out['status'], implode( "\n", $out['errors'] ) );
		$this->assertStringStartsWith( self::HEADER . '<!-- wp:media-text ', $out['text'] );
		$this->assertStringContainsString( '"mediaId":{{media_id:team}}', $out['text'] );
		$this->assertStringContainsString( 'src="{{media_url:team}}"', $out['text'] );
		$this->assertStringContainsString( 'class="wp-image-{{media_id:team}} size-large"', $out['text'] );
		$this->assertStringContainsString( '<h1 class="wp-block-heading has-text-align-center">Welcome to <?php echo esc_html( get_bloginfo( \'name\' ) ); ?></h1>', $out['text'] );
		$this->assertStringContainsString( 'href="<?php echo esc_url( home_url( \'/contact/\' ) ); ?>"', $out['text'] );
		$this->assertStringNotContainsString( 'pediment.invalid', $out['text'] );
		$this->assertStringNotContainsString( '__PEDIMENT_PHP_', $out['text'] );
	}

	public function test_the_policy_error_for_a_legacy_block_does_not_stop_a_file_conversion(): void {
		$body = '<!-- wp:pediment/pull-quote {"quote":"Q"} /-->' . "\n";
		$this->assertNotSame( array(), ( new Policy() )->check( $body )['errors'], 'the Policy rejects the legacy block' );

		$out = $this->files()->convertText( self::HEADER . $body );

		$this->assertSame( 'preview', $out['status'], implode( "\n", $out['errors'] ) );
		$this->assertSame( array(), $out['errors'] );
		$this->assertStringContainsString( 'is-style-pull-quote', $out['text'] );
	}

	public function test_inline_php_that_would_be_lost_fails_the_file(): void {
		$text = self::HEADER . '<!-- wp:pediment/image-caption {"mediaId":<?php echo (int) acme_media_id( \'lake.jpg\' ); ?>,"caption":"Lake"} /-->' . "\n";
		$out  = $this->files()->convertText( $text );
		$this->assertSame( 'failed', $out['status'] );
		$this->assertStringContainsString( 'would not survive the conversion', $out['errors'][0] );
		$this->assertSame( $text, $out['text'] );
	}

	public function test_raw_html_in_a_legacy_container_fails_the_file(): void {
		$text = self::HEADER . "<!-- wp:pediment/prose -->\n<p>Raw <?php echo esc_html( acme_name() ); ?></p>\n<!-- /wp:pediment/prose -->\n";
		$out  = $this->files()->convertText( $text );
		$this->assertSame( 'failed', $out['status'] );
		$this->assertSame( array( 'pediment/prose holds HTML outside blocks; edit it into blocks first ("<p>Raw <?php echo esc_html( acme_name() ); ?></p>")' ), $out['errors'] );
		$this->assertSame( $text, $out['text'] );
		$this->assertSame( '', $out['diff'] );
		foreach ( $out['notes'] as $note ) {
			$this->assertStringNotContainsString( 'rendered nothing', $note );
		}
	}

	public function test_a_block_comment_whose_attributes_are_php_fails_the_file(): void {
		$text = self::HEADER . '<!-- wp:pediment/pull-quote {"quote":"Q"} /-->' . "\n" . '<!-- wp:pediment/hero <?php echo acme_hero_attrs(); ?> /-->' . "\n";
		$out  = $this->files()->convertText( $text );
		$this->assertSame( 'failed', $out['status'] );
		$this->assertSame( array( 'pediment/hero is still in the markup after conversion (its block comment does not parse); convert it by hand' ), $out['errors'] );
		$this->assertSame( $text, $out['text'] );
	}

	public function test_dry_run_writes_nothing_and_a_real_run_rewrites_only_changed_files(): void {
		$legacy = self::HEADER . '<!-- wp:pediment/pull-quote {"quote":"Q"} /-->' . "\n";
		$plain  = self::HEADER . '<!-- wp:paragraph -->' . "\n" . '<p>Plain</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n";
		file_put_contents( "{$this->dir}/a.php", $legacy );
		file_put_contents( "{$this->dir}/b.php", $plain );

		$dry = $this->files()->run( $this->dir, true );
		$this->assertSame( array( 'preview', 'unchanged' ), array_column( $dry, 'status' ) );
		$this->assertStringContainsString( '+<!-- wp:quote', $dry[0]['diff'] );
		$this->assertSame( $legacy, file_get_contents( "{$this->dir}/a.php" ) );

		$real = $this->files()->run( $this->dir, false );
		$this->assertSame( array( 'converted', 'unchanged' ), array_column( $real, 'status' ) );
		$this->assertStringContainsString( 'is-style-pull-quote', (string) file_get_contents( "{$this->dir}/a.php" ) );
		$this->assertSame( $plain, file_get_contents( "{$this->dir}/b.php" ) );
	}

	private const MEDIA_TEXT = '<!-- wp:pediment/media-text {"mediaId":{{media_id:team}}} -->' . "\n"
		. '<!-- wp:paragraph -->' . "\n" . '<p>Team</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n"
		. '<!-- /wp:pediment/media-text -->' . "\n";

	private const LEGACY = '<!-- wp:pediment/pull-quote {"quote":"Q"} /-->' . "\n";

	private function outside_dir(): string {
		$dir = get_temp_dir() . 'pediment-outside-' . wp_generate_password( 6, false );
		wp_mkdir_p( $dir );
		return $dir;
	}

	public function test_unwritable_file_fails_and_is_left_alone(): void {
		$file = "{$this->dir}/a.php";
		file_put_contents( $file, self::HEADER . self::LEGACY );
		chmod( $file, 0444 );
		clearstatcache();
		if ( is_writable( $file ) ) {
			chmod( $file, 0644 );
			$this->markTestSkipped( 'Running as a user that can write read-only files.' );
		}

		$rows = $this->files()->run( $this->dir, false );
		chmod( $file, 0644 );

		$this->assertSame( 'failed', $rows[0]['status'] );
		$this->assertStringContainsString( 'not writable', $rows[0]['errors'][0] );
		$this->assertSame( self::HEADER . self::LEGACY, file_get_contents( $file ) );
	}

	public function test_unreadable_file_fails(): void {
		$file = "{$this->dir}/a.php";
		file_put_contents( $file, self::HEADER . self::LEGACY );
		chmod( $file, 0000 );
		clearstatcache();
		if ( is_readable( $file ) ) {
			chmod( $file, 0644 );
			$this->markTestSkipped( 'Running as a user that can read any file.' );
		}

		$rows = $this->files()->run( $this->dir, false );
		chmod( $file, 0644 );

		$this->assertSame( 'failed', $rows[0]['status'] );
		$this->assertStringContainsString( 'cannot read', $rows[0]['errors'][0] );
	}

	public function test_a_directory_named_like_a_pattern_is_never_converted(): void {
		mkdir( "{$this->dir}/x.php" );
		$rows = $this->files()->run( $this->dir, false );
		rmdir( "{$this->dir}/x.php" );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'skipped', $rows[0]['status'] );
		$this->assertStringContainsString( 'not a regular file', $rows[0]['notes'][0] );
	}

	public function test_a_symlink_that_leaves_the_directory_is_not_touched(): void {
		$outside = $this->outside_dir();
		file_put_contents( "{$outside}/real.php", self::HEADER . self::LEGACY );
		symlink( "{$outside}/real.php", "{$this->dir}/link.php" );

		$rows = $this->files()->run( $this->dir, false );
		unlink( "{$this->dir}/link.php" );
		$kept = file_get_contents( "{$outside}/real.php" );
		unlink( "{$outside}/real.php" );
		rmdir( $outside );

		$this->assertSame( 'skipped', $rows[0]['status'] );
		$this->assertStringContainsString( 'outside', $rows[0]['notes'][0] );
		$this->assertSame( self::HEADER . self::LEGACY, $kept );
	}

	public function test_a_write_leaves_no_temporary_file_and_keeps_the_mode(): void {
		$file = "{$this->dir}/a.php";
		file_put_contents( $file, self::HEADER . self::LEGACY );
		chmod( $file, 0640 );

		$rows = $this->files()->run( $this->dir, false );
		clearstatcache();

		$this->assertSame( 'converted', $rows[0]['status'] );
		$this->assertSame( array( $file ), glob( $this->dir . '/*' ) );
		$this->assertSame( 0640, fileperms( $file ) & 0777 );
	}

	public function test_a_php_file_without_a_pattern_header_is_skipped(): void {
		$text = "<?php\n\$legacy = '<!-- wp:pediment/pull-quote {\"quote\":\"Q\"} /-->';\n";
		file_put_contents( "{$this->dir}/functions.php", $text );

		$rows = $this->files()->run( $this->dir, false );

		$this->assertSame( 'skipped', $rows[0]['status'] );
		$this->assertStringContainsString( 'no pattern header', $rows[0]['notes'][0] );
		$this->assertSame( $text, file_get_contents( "{$this->dir}/functions.php" ) );
		$this->assertSame( 'skipped', $this->files()->convertText( $text )['status'] );
	}

	public function test_a_literal_scheme_before_inline_php_is_kept(): void {
		$body = '<!-- wp:pediment/hero {"variant":"centered","headline":"Hi","ctaText":"Go","ctaUrl":"http://<?php echo esc_attr( $host ); ?>/contact/"} /-->' . "\n";
		$out  = $this->files()->convertText( self::HEADER . $body );

		$this->assertSame( 'preview', $out['status'], implode( "\n", $out['errors'] ) );
		$this->assertStringContainsString( 'href="http://<?php echo esc_attr( $host ); ?>/contact/"', $out['text'] );
	}

	public function test_a_number_that_equals_a_stand_in_id_fails_the_file(): void {
		$text = self::HEADER . self::MEDIA_TEXT . '<!-- wp:paragraph -->' . "\n" . '<p>Order 9100001</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n";
		$out  = $this->files()->convertText( $text );

		$this->assertSame( 'failed', $out['status'] );
		$this->assertStringContainsString( 'clashes with a media stand-in', $out['errors'][0] );
		$this->assertSame( $text, $out['text'] );
	}

	public function test_a_stand_in_url_in_the_file_fails_the_file(): void {
		$text = self::HEADER . self::MEDIA_TEXT . '<!-- wp:paragraph -->' . "\n" . '<p>https://media.pediment.invalid/x</p>' . "\n" . '<!-- /wp:paragraph -->' . "\n";
		$out  = $this->files()->convertText( $text );

		$this->assertSame( 'failed', $out['status'] );
		$this->assertStringContainsString( 'clashes with a media stand-in', $out['errors'][0] );
	}

	public function test_a_number_inside_inline_php_is_not_rewritten(): void {
		$text = self::HEADER . self::MEDIA_TEXT . '<!-- wp:paragraph -->' . "\n" . '<p><?php echo 9100001; ?></p>' . "\n" . '<!-- /wp:paragraph -->' . "\n";
		$out  = $this->files()->convertText( $text );

		$this->assertSame( 'preview', $out['status'], implode( "\n", $out['errors'] ) );
		$this->assertStringContainsString( '<?php echo 9100001; ?>', $out['text'] );
	}

	public function test_an_unresolvable_alt_for_a_placeholder_is_noted(): void {
		$out = $this->files()->convertText( self::HEADER . self::MEDIA_TEXT );

		$this->assertSame( 'preview', $out['status'], implode( "\n", $out['errors'] ) );
		$this->assertContains( 'alt text not resolvable for {{media_id:team}}; set it by hand', $out['notes'] );
	}
}
