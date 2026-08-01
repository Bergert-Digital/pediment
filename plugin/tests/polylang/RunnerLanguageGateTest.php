<?php

use Pediment\Seeder\Runner;

class RunnerLanguageGateTest extends PolylangTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		\Pediment\Seeder\Manifest::resetCache();
		parent::tear_down();
	}

	/** @param array<string,mixed> $manifest */
	private function withManifest( array $manifest ): void {
		add_filter( 'pediment_seed_manifest', static fn() => $manifest );
		\Pediment\Seeder\Manifest::resetCache();
	}

	public function test_a_manifest_language_polylang_does_not_have_blocks_the_run() {
		$this->withManifest(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
					'fr' => [ 'name' => 'Français', 'locale' => 'fr_FR' ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			]
		);

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertNotEmpty( $result->errors );
		$this->assertStringContainsString( 'fr', $result->errors[0] );
		$this->assertStringContainsString( 'wp pediment languages', $result->errors[0] );
	}

	/**
	 * Sorting the two language lists before comparing erases which one is
	 * default — a manifest declaring `de` as default against a site
	 * configured with `en` default has an identical SET (`en`, `de` either
	 * way) and used to pass the gate outright. Everything downstream reads
	 * $this->lang->defaultLanguage(), never the manifest's, so a mismatch
	 * here means Manifest::defaultLanguage() is silently inert until someone
	 * happens to re-run `wp pediment languages`.
	 */
	public function test_a_default_language_mismatch_blocks_the_run() {
		$this->withManifest(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US' ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE', 'default' => true ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			]
		);

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertNotEmpty( $result->errors );
		$this->assertStringContainsString( 'de', $result->errors[0] );
		$this->assertStringContainsString( 'en', $result->errors[0] );
		$this->assertStringContainsString( 'default', $result->errors[0] );
		$this->assertStringContainsString( 'wp pediment languages', $result->errors[0] );
	}

	public function test_a_matching_set_runs() {
		$this->withManifest(
			[
				'languages' => [
					'en' => [ 'name' => 'English', 'locale' => 'en_US', 'default' => true ],
					'de' => [ 'name' => 'Deutsch', 'locale' => 'de_DE' ],
				],
				'pages'     => [ 'home' => [ 'title' => 'Home', 'content' => '' ] ],
			]
		);

		$result = ( new Runner() )->run( [ 'dry_run' => true ] );

		$this->assertSame( [], $result->errors );
	}
}
