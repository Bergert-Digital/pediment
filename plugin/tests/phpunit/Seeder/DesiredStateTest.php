<?php
// plugin/tests/phpunit/Seeder/DesiredStateTest.php

use Pediment\Language\NullProvider;
use Pediment\Seeder\ContentResolver;
use Pediment\Seeder\DesiredState;
use Pediment\Seeder\Manifest;
use Pediment\Seeder\ManifestError;
use Pediment\Seeder\MediaMap;

class DesiredStateTest extends WP_UnitTestCase {

	private function state(): DesiredState {
		return new DesiredState( new NullProvider(), new ContentResolver( new MediaMap( [] ) ) );
	}

	private function manifest( array $pages ): Manifest {
		return Manifest::fromArray( [ 'pages' => $pages ], '/tmp/theme' );
	}

	public function test_an_existing_entry_does_not_need_its_pattern() {
		// The theme dropped the pattern file of a page that now lives only on
		// the site. The seeder never writes that page again, so the missing
		// pattern must not block the run.
		$desired = $this->state()->build(
			$this->manifest( [ 'about' => [ 'title' => 'About', 'pattern' => 'acme/gone' ] ] ),
			[ 'about|' => true ]
		);

		$this->assertArrayHasKey( 'about|', $desired );
		$this->assertSame( '', $desired['about|']->content );
	}

	public function test_a_new_entry_still_needs_its_pattern() {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'acme/gone' );

		$this->state()->build( $this->manifest( [ 'about' => [ 'title' => 'About', 'pattern' => 'acme/gone' ] ] ) );
	}

	public function test_an_undeclared_media_key_is_reported_only_for_a_new_entry() {
		$manifest = $this->manifest(
			[
				'home'  => [ 'title' => 'Home', 'content' => '<img src="{{media_url:hreo}}" />' ],
				'about' => [ 'title' => 'About', 'content' => '<img src="{{media_url:hreo}}" />' ],
			]
		);
		$state = $this->state();

		$state->build( $manifest, [ 'home|' => true ] );

		$this->assertSame( [ 'about|' => [ 'hreo' ] ], $state->undeclaredMediaKeys() );
	}
}
