<?php
// plugin/tests/phpunit/Seeder/MediaSeederTest.php

use Pediment\Seeder\Manifest;
use Pediment\Seeder\MediaSeeder;
use Pediment\Seeder\Meta;
use Pediment\Seeder\PlanItem;

class MediaSeederTest extends WP_UnitTestCase {

	private string $dir;

	public function set_up(): void {
		parent::set_up();
		$this->dir = get_temp_dir() . 'pediment-media-test';
		wp_mkdir_p( $this->dir . '/seed/media' );
		file_put_contents( $this->dir . '/seed/media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>' );
		copy( DIR_TESTDATA . '/images/canola.jpg', $this->dir . '/seed/media/hero.jpg' );
	}

	private function manifest(): Manifest {
		return Manifest::fromArray(
			[
				'media' => [
					'logo' => [ 'file' => 'seed/media/logo.svg', 'title' => 'Logo' ],
					'hero' => [ 'file' => 'seed/media/hero.jpg', 'title' => 'Hero' ],
				],
				'site'  => [ 'logo' => 'logo' ],
			],
			$this->dir
		);
	}

	public function test_plan_lists_every_missing_file_as_a_create() {
		$plan = ( new MediaSeeder() )->plan( $this->manifest() );

		$this->assertCount( 2, $plan->byAction( PlanItem::CREATE ) );
		$this->assertSame( PlanItem::KIND_MEDIA, $plan->items()[0]->kind );
	}

	public function test_apply_sideloads_and_keys_the_attachments() {
		$seeder = new MediaSeeder();
		$m      = $this->manifest();

		$map = $seeder->apply( $seeder->plan( $m ), $m );

		$this->assertGreaterThan( 0, $map->id( 'logo' ) );
		$this->assertSame( 'image/svg+xml', get_post_mime_type( $map->id( 'logo' ) ) );
		$this->assertSame( 'logo', get_post_meta( $map->id( 'logo' ), Meta::KEY, true ) );
		$this->assertNotEmpty( wp_get_attachment_metadata( $map->id( 'hero' ) ), 'raster media needs sizes' );
		$this->assertStringContainsString( 'hero', $map->url( 'hero' ) );
	}

	public function test_apply_sets_the_site_logo() {
		$seeder = new MediaSeeder();
		$m      = $this->manifest();

		$map = $seeder->apply( $seeder->plan( $m ), $m );

		$this->assertSame( $map->id( 'logo' ), (int) get_theme_mod( 'custom_logo' ) );
	}

	public function test_reapplying_is_idempotent() {
		$seeder = new MediaSeeder();
		$m      = $this->manifest();
		$first  = $seeder->apply( $seeder->plan( $m ), $m );

		$plan   = $seeder->plan( $m );
		$second = $seeder->apply( $plan, $m );

		$this->assertSame( $first->id( 'logo' ), $second->id( 'logo' ) );
		$this->assertCount( 2, $plan->byAction( PlanItem::UNCHANGED ) );
		$this->assertSame( [], $plan->byAction( PlanItem::CREATE ) );
	}

	public function test_map_sees_only_what_is_already_seeded() {
		$this->assertSame( 0, ( new MediaSeeder() )->map( $this->manifest() )->id( 'logo' ) );
	}
}
