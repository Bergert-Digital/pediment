<?php
namespace PedimentAi\Tests;

use PedimentAi\Updater;
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
}
