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

	public function test_inline_php_that_would_be_lost_fails_the_file(): void {
		$text = self::HEADER . '<!-- wp:pediment/image-caption {"mediaId":<?php echo (int) acme_media_id( \'lake.jpg\' ); ?>,"caption":"Lake"} /-->' . "\n";
		$out  = $this->files()->convertText( $text );
		$this->assertSame( 'failed', $out['status'] );
		$this->assertStringContainsString( 'would not survive the conversion', $out['errors'][0] );
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
}
