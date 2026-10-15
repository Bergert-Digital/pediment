<?php

require_once __DIR__ . '/AbilityRegistryReset.php';

/**
 * Base for ability tests: resets the Abilities API registries around each test.
 */
abstract class AbilityTestCase extends WP_UnitTestCase {

	use AbilityRegistryReset;

	public function set_up(): void {
		parent::set_up();
		$this->reset_registries();
	}

	public function tear_down(): void {
		$this->reset_registries();
		parent::tear_down();
	}

	protected function user( string $role ): int {
		return self::factory()->user->create( array( 'role' => $role ) );
	}

	/**
	 * Runs an ability as a user.
	 *
	 * @param int    $user  User ID.
	 * @param string $name  Ability name.
	 * @param mixed  $input Input.
	 * @return mixed|WP_Error
	 */
	protected function run_as( int $user, string $name, $input = null ) {
		wp_set_current_user( $user );
		return wp_get_ability( $name )->execute( $input );
	}
}
