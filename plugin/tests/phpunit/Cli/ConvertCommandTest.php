<?php
namespace Pediment\Tests\Cli;

use Pediment\Cli\ConvertCommand;

class ConvertCommandTest extends \WP_UnitTestCase {

	private function report(): array {
		$post = array(
			'id'              => 12,
			'type'            => 'page',
			'title'           => 'About',
			'language'        => 'de',
			'status'          => 'converted',
			'converted'       => array( 'pediment/hero' ),
			'blocks'          => 1,
			'unmapped'        => array(),
			'notes'           => array( 'pediment/feature: icon dropped, core blocks cannot show the icon set' ),
			'errors'          => array(),
			'existingErrors'  => array(),
			'diff'            => '',
			'revision'        => 31,
			'restoreRevision' => 30,
		);
		return array(
			'dryRun'  => false,
			'posts'   => array( $post ),
			'summary' => array(
				'posts'     => 1,
				'blocks'    => 1,
				'converted' => 1,
				'preview'   => 0,
				'unchanged' => 0,
				'failed'    => 0,
				'skipped'   => 0,
			),
		);
	}

	public function test_text_output_names_post_blocks_notes_and_the_old_revision(): void {
		$text = ConvertCommand::render( $this->report(), false );
		$this->assertStringContainsString( 'converted page #12 About [de]', $text );
		$this->assertStringContainsString( '  block: pediment/hero', $text );
		$this->assertStringContainsString( '  note: pediment/feature: icon dropped', $text );
		$this->assertStringContainsString( '  old content: revision 30', $text );
		$this->assertStringEndsWith( '1 posts, 1 legacy blocks: 1 converted, 0 to convert, 0 unchanged, 0 failed, 0 skipped.', $text );
	}

	public function test_failed_posts_show_their_reasons_and_count(): void {
		$report                          = $this->report();
		$report['posts'][0]['status']    = 'failed';
		$report['posts'][0]['errors']    = array( 'revisions are disabled or limited to fewer than 2 for this post (WP_POST_REVISIONS); conversion needs a backup revision' );
		$report['posts'][0]['restoreRevision'] = 0;
		$report['summary']['converted']  = 0;
		$report['summary']['failed']     = 1;
		$text = ConvertCommand::render( $report, false );
		$this->assertStringContainsString( 'failed    page #12 About [de]', $text );
		$this->assertStringContainsString( '  error: revisions are disabled', $text );
		$this->assertStringNotContainsString( 'old content', $text );
		$this->assertStringEndsWith( '0 converted, 0 to convert, 0 unchanged, 1 failed, 0 skipped.', $text );
	}

	public function test_json_output_is_the_report(): void {
		$this->assertSame( $this->report(), json_decode( ConvertCommand::render( $this->report(), true ), true ) );
	}

	public function test_pattern_output_lists_each_file(): void {
		$text = ConvertCommand::renderPatterns(
			array(
				array(
					'file'      => '/t/patterns/home.php',
					'status'    => 'failed',
					'converted' => array(),
					'blocks'    => 0,
					'unmapped'  => array(),
					'notes'     => array(),
					'errors'    => array( 'inline PHP would not survive' ),
					'diff'      => '',
				),
			),
			false
		);
		$this->assertSame( "failed    /t/patterns/home.php\n  error: inline PHP would not survive", $text );
	}
}
