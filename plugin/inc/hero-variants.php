<?php
/**
 * Hero variant registry, kept for the legacy-block converter.
 *
 * The converter's HeroMapper (plugin/src/Convert/Mappers/HeroMapper.php)
 * reads pediment_hero_variants() to decide how a legacy pediment/hero block
 * converts: a variant the filter removed converts as "default", and a variant
 * a site added without a converter mapping is noted and converts as "default".
 * A client theme can still remove a variant with one line, e.g.:
 *
 *   add_filter( 'pediment_hero_variants', fn( $v ) => array_diff( $v, [ 'stat-card' ] ) );
 *
 * The block editor no longer reads this list. The function and the filter
 * go in 6.0.0, together with the converter.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The allowed hero variants (filterable).
 *
 * @return string[] Re-indexed list of variant slugs.
 */
function pediment_hero_variants() {
	$defaults = array( 'default', 'centered', 'media-bg', 'stat-card' );
	$variants = apply_filters( 'pediment_hero_variants', $defaults );
	$variants = is_array( $variants ) ? array_values( array_filter( array_map( 'strval', $variants ) ) ) : $defaults;
	if ( ! in_array( 'default', $variants, true ) ) {
		array_unshift( $variants, 'default' );
	}
	return $variants;
}
