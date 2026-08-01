<?php
namespace Pediment\Tests\Cli;

use Pediment\Cli\SeedCommand;
use Pediment\Seeder\Plan;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Reporter;
use Pediment\Seeder\RunResult;

class SeedCommandTest extends \WP_UnitTestCase {

	private function result(): RunResult {
		$plan = new Plan(
			[
				new PlanItem(
					PlanItem::UPDATE,
					PlanItem::KIND_ENTRY,
					'about',
					'',
					9,
					[ 'slug' => [ 'from' => 'ueber-uns', 'to' => 'about' ] ],
					[ 'title' => [ 'from' => 'About', 'to' => 'About us' ] ],
					'edited in the editor'
				),
			]
		);
		return new RunResult( $plan, false, '/themes/acme/seed/manifest.php' );
	}

	public function test_text_mode_returns_the_reporters_output(): void {
		$result = $this->result();

		$this->assertSame( Reporter::text( $result ), SeedCommand::render( $result, false ) );
	}

	public function test_json_mode_exposes_counts_and_per_item_detail(): void {
		$data = json_decode( SeedCommand::render( $this->result(), true ), true );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'ok', $data );
		$this->assertArrayHasKey( 'counts', $data );
		$this->assertArrayHasKey( 'items', $data );

		$this->assertCount( 1, $data['items'] );
		$item = $data['items'][0];
		$this->assertSame( PlanItem::UPDATE, $item['action'] );
		$this->assertSame( 'about', $item['key'] );
		$this->assertSame( [ 'title' ], $item['protected'] );
	}
}
