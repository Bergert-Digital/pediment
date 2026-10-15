<?php

/**
 * Resets the Abilities API registries so the plugin's load-time hooks
 * re-register its abilities through the real init actions.
 */
trait AbilityRegistryReset {

	protected function reset_registries(): void {
		foreach ( array( 'WP_Abilities_Registry', 'WP_Ability_Categories_Registry' ) as $class ) {
			$prop = new ReflectionProperty( $class, 'instance' );
			$prop->setAccessible( true );
			$prop->setValue( null, null );
		}
	}
}
