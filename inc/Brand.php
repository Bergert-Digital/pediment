<?php
/**
 * Brand settings storage and accessor.
 *
 * @package Starter
 */

namespace Starter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Brand {
	public const OPTION = 'starter_theme_brand';

	private const DEFAULTS = array(
		'brand_name'    => '',
		'brand_tagline' => '',
		'voice_tone'    => '',
		'logo_id'       => 0,
		'og_image_id'   => 0,
		'contact_email' => '',
		'phone'         => '',
		'address'       => '',
		'social_links'  => array(),
	);

	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::DEFAULTS, $stored );
	}

	/**
	 * @param string $key     Setting key.
	 * @param mixed  $default Returned when the key is missing or empty.
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$all = self::all();
		if ( ! array_key_exists( $key, $all ) ) {
			return $default;
		}
		$value = $all[ $key ];
		if ( '' === $value || ( is_array( $value ) && array() === $value ) ) {
			return $default ?? $value;
		}
		return $value;
	}

	/**
	 * @param string $key   Setting key.
	 * @param mixed  $value Value to persist.
	 */
	public static function set( string $key, $value ): void {
		$all         = self::all();
		$all[ $key ] = $value;
		update_option( self::OPTION, $all );
	}
}
