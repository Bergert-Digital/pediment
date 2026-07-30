<?php
namespace Pediment\Tests;

use Pediment\Updater;
use ReflectionClass;

class UpdaterTest extends \WP_UnitTestCase {
	public function test_repo_url_points_at_the_monorepo(): void {
		$reflection = new ReflectionClass( Updater::class );
		$constant   = $reflection->getReflectionConstant( 'REPO_URL' );

		$this->assertNotFalse( $constant, 'Updater::REPO_URL constant should exist.' );
		$this->assertSame( 'https://github.com/Bergert-Digital/pediment/', $constant->getValue() );
	}

	public function test_register_method_exists(): void {
		$reflection = new ReflectionClass( Updater::class );

		$this->assertTrue( $reflection->hasMethod( 'register' ) );
	}

	/**
	 * The PUC slug and release-asset regex are inline literals inside
	 * register(), not class constants, so reflection can't read their
	 * values directly. Parse the source instead.
	 */
	public function test_register_wires_the_pediment_slug_and_asset_regex(): void {
		$reflection = new ReflectionClass( Updater::class );
		$source     = (string) file_get_contents( (string) $reflection->getFileName() );

		$this->assertStringContainsString(
			"buildUpdateChecker( self::REPO_URL, \$plugin_file, 'pediment' )",
			$source,
			'PUC slug should be pediment.'
		);
		$this->assertStringContainsString(
			"enableReleaseAssets( '/pediment-plugin\\.zip\$/' )",
			$source,
			'Release asset regex should match pediment-plugin.zip.'
		);
	}
}
