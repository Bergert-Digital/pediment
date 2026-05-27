<?php
/**
 * Brand Settings admin page.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PEDIMENT_BRAND_OPTION_GROUP = 'pediment_brand_group';
const PEDIMENT_BRAND_PAGE         = 'pediment-brand';

add_action(
	'admin_menu',
	function () {
		add_options_page(
			__( 'Brand Settings', 'pediment' ),
			__( 'Brand Settings', 'pediment' ),
			'manage_options',
			PEDIMENT_BRAND_PAGE,
			'pediment_brand_render_page'
		);
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			PEDIMENT_BRAND_OPTION_GROUP,
			\Pediment\Brand::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'pediment_brand_sanitize',
				'default'           => array(),
			)
		);

		foreach ( \Pediment\BrandRegistry::sections() as $slug => $section ) {
			add_settings_section( $slug, $section['title'], '__return_false', PEDIMENT_BRAND_PAGE );
		}

		$renderers = array(
			'text'     => 'pediment_brand_field_text',
			'textarea' => 'pediment_brand_field_textarea',
			'email'    => 'pediment_brand_field_text',
			'image'    => 'pediment_brand_field_image',
			'social'   => 'pediment_brand_field_social',
			'integer'  => 'pediment_brand_field_text',
		);

		foreach ( \Pediment\BrandRegistry::fields() as $key => $field ) {
			$type     = $field['type'];
			$renderer = $field['renderer'] ?? $renderers[ $type ] ?? 'pediment_brand_field_text';

			$args = array( 'key' => $key );
			if ( 'email' === $type ) {
				$args['type'] = 'email';
			} elseif ( 'integer' === $type ) {
				$args['type'] = 'number';
			}

			add_settings_field(
				$key,
				$field['label'],
				$renderer,
				PEDIMENT_BRAND_PAGE,
				$field['section'],
				$args
			);
		}
	}
);

function pediment_brand_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Brand Settings', 'pediment' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( PEDIMENT_BRAND_OPTION_GROUP );
			do_settings_sections( PEDIMENT_BRAND_PAGE );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

function pediment_brand_field_text( array $args ): void {
	$key   = $args['key'];
	$type  = $args['type'] ?? 'text';
	$value = (string) \Pediment\Brand::get( $key, '' );
	printf(
		'<input type="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" />',
		esc_attr( $type ),
		esc_attr( \Pediment\Brand::OPTION ),
		esc_attr( $key ),
		esc_attr( $value )
	);
}

function pediment_brand_field_textarea( array $args ): void {
	$key   = $args['key'];
	$value = (string) \Pediment\Brand::get( $key, '' );
	printf(
		'<textarea name="%1$s[%2$s]" rows="3" class="large-text">%3$s</textarea>',
		esc_attr( \Pediment\Brand::OPTION ),
		esc_attr( $key ),
		esc_textarea( $value )
	);
}

function pediment_brand_field_image( array $args ): void {
	$key      = $args['key'];
	$media_id = (int) \Pediment\Brand::get( $key, 0 );
	$url      = $media_id ? wp_get_attachment_image_url( $media_id, 'medium' ) : '';
	printf(
		'<div class="starter-brand-image" data-key="%1$s">
			<input type="hidden" name="%2$s[%1$s]" value="%3$d" class="starter-brand-image__id" />
			<img src="%4$s" alt="" class="starter-brand-image__preview" %5$s style="max-width:200px;height:auto;display:block;margin-bottom:8px;" />
			<button type="button" class="button starter-brand-image__pick">%6$s</button>
			<button type="button" class="button starter-brand-image__clear" %7$s>%8$s</button>
		</div>',
		esc_attr( $key ),
		esc_attr( \Pediment\Brand::OPTION ),
		(int) $media_id,
		esc_url( $url ),
		$url ? '' : 'hidden',
		esc_html__( 'Pick image', 'pediment' ),
		$media_id ? '' : 'hidden',
		esc_html__( 'Clear', 'pediment' )
	);
}

function pediment_brand_field_social( array $args ): void {
	$key   = $args['key'];
	$links = (array) \Pediment\Brand::get( $key, array() );
	?>
	<div class="starter-brand-social" data-key="<?php echo esc_attr( $key ); ?>">
		<template class="starter-brand-social__template">
			<div class="starter-brand-social__row">
				<input type="text" name="<?php echo esc_attr( \Pediment\Brand::OPTION ); ?>[social_links][__INDEX__][platform]" placeholder="<?php esc_attr_e( 'Platform', 'pediment' ); ?>" />
				<input type="url"  name="<?php echo esc_attr( \Pediment\Brand::OPTION ); ?>[social_links][__INDEX__][url]"      placeholder="https://…" />
				<button type="button" class="button-link starter-brand-social__remove"><?php esc_html_e( 'Remove', 'pediment' ); ?></button>
			</div>
		</template>
		<div class="starter-brand-social__rows">
			<?php foreach ( $links as $i => $link ) : ?>
				<div class="starter-brand-social__row">
					<input type="text" name="<?php echo esc_attr( \Pediment\Brand::OPTION ); ?>[social_links][<?php echo (int) $i; ?>][platform]" value="<?php echo esc_attr( $link['platform'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Platform', 'pediment' ); ?>" />
					<input type="url"  name="<?php echo esc_attr( \Pediment\Brand::OPTION ); ?>[social_links][<?php echo (int) $i; ?>][url]"      value="<?php echo esc_attr( $link['url'] ?? '' ); ?>"      placeholder="https://…" />
					<button type="button" class="button-link starter-brand-social__remove"><?php esc_html_e( 'Remove', 'pediment' ); ?></button>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button starter-brand-social__add"><?php esc_html_e( 'Add link', 'pediment' ); ?></button>
	</div>
	<?php
}

function pediment_brand_sanitize( $input ): array {
	if ( ! is_array( $input ) ) {
		return array();
	}
	$clean = array();

	$type_sanitizers = array(
		'text'     => 'sanitize_text_field',
		'textarea' => 'sanitize_textarea_field',
		'integer'  => 'absint',
		'image'    => 'absint',
	);

	foreach ( \Pediment\BrandRegistry::fields() as $key => $field ) {
		$type   = $field['type'];
		$custom = $field['sanitize'] ?? null;
		$raw    = $input[ $key ] ?? null;

		if ( is_callable( $custom ) ) {
			// Callable receives the raw value or null if the field is absent from $input.
			$clean[ $key ] = call_user_func( $custom, $raw );
			continue;
		}

		if ( 'email' === $type ) {
			$value = isset( $raw ) ? sanitize_text_field( wp_unslash( (string) $raw ) ) : '';
			if ( '' !== $value && ! is_email( $value ) ) {
				add_settings_error(
					\Pediment\Brand::OPTION,
					'invalid_' . $key,
					sprintf(
						/* translators: %s: field label */
						__( '%s is invalid.', 'pediment' ),
						$field['label']
					)
				);
				$value = '';
			}
			$clean[ $key ] = $value;
			continue;
		}

		if ( 'social' === $type ) {
			$clean[ $key ] = array();
			if ( is_array( $raw ) ) {
				foreach ( $raw as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$platform  = isset( $row['platform'] ) ? sanitize_key( $row['platform'] ) : '';
					$raw_url   = isset( $row['url'] ) ? (string) $row['url'] : '';
					$url       = $raw_url ? esc_url_raw( $raw_url ) : '';
					$valid_url = $url && wp_http_validate_url( $url );
					if ( '' !== $platform && $valid_url ) {
						$clean[ $key ][] = array(
							'platform' => $platform,
							'url'      => $url,
						);
					}
				}
			}
			continue;
		}

		$sanitizer = $type_sanitizers[ $type ] ?? 'sanitize_text_field';
		if ( 'absint' === $sanitizer ) {
			$clean[ $key ] = isset( $raw ) ? absint( $raw ) : 0;
		} else {
			$clean[ $key ] = isset( $raw ) ? call_user_func( $sanitizer, wp_unslash( (string) $raw ) ) : '';
		}
	}

	return $clean;
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook_suffix ) {
		if ( 'settings_page_' . PEDIMENT_BRAND_PAGE !== $hook_suffix ) {
			return;
		}
		wp_enqueue_media();
		$rel = 'assets/js/admin-brand-settings.js';
		wp_enqueue_script(
			'pediment-brand-settings',
			get_theme_file_uri( $rel ),
			array(),
			(string) filemtime( get_theme_file_path( $rel ) ),
			true
		);
	}
);
