<?php
/**
 * Brand Settings admin page.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const STARTER_BRAND_OPTION_GROUP = 'starter_brand_group';
const STARTER_BRAND_PAGE         = 'starter-brand';

add_action(
	'admin_menu',
	function () {
		add_options_page(
			__( 'Brand Settings', 'starter' ),
			__( 'Brand Settings', 'starter' ),
			'manage_options',
			STARTER_BRAND_PAGE,
			'starter_brand_render_page'
		);
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			STARTER_BRAND_OPTION_GROUP,
			\Starter\Brand::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'starter_brand_sanitize',
				'default'           => array(),
			)
		);

		add_settings_section( 'identity', __( 'Identity', 'starter' ), '__return_false', STARTER_BRAND_PAGE );
		add_settings_section( 'contact',  __( 'Contact', 'starter' ),  '__return_false', STARTER_BRAND_PAGE );
		add_settings_section( 'social',   __( 'Social', 'starter' ),   '__return_false', STARTER_BRAND_PAGE );
		add_settings_section( 'og',       __( 'OG / SEO', 'starter' ), '__return_false', STARTER_BRAND_PAGE );

		add_settings_field( 'brand_name',    __( 'Brand name', 'starter' ),    'starter_brand_field_text',     STARTER_BRAND_PAGE, 'identity', array( 'key' => 'brand_name' ) );
		add_settings_field( 'brand_tagline', __( 'Tagline', 'starter' ),       'starter_brand_field_text',     STARTER_BRAND_PAGE, 'identity', array( 'key' => 'brand_tagline' ) );
		add_settings_field( 'voice_tone',    __( 'Voice / tone', 'starter' ),  'starter_brand_field_textarea', STARTER_BRAND_PAGE, 'identity', array( 'key' => 'voice_tone' ) );
		add_settings_field( 'logo_id',       __( 'Logo', 'starter' ),          'starter_brand_field_image',    STARTER_BRAND_PAGE, 'identity', array( 'key' => 'logo_id' ) );

		add_settings_field( 'contact_email', __( 'Contact email', 'starter' ), 'starter_brand_field_text',     STARTER_BRAND_PAGE, 'contact',  array( 'key' => 'contact_email', 'type' => 'email' ) );
		add_settings_field( 'phone',         __( 'Phone', 'starter' ),         'starter_brand_field_text',     STARTER_BRAND_PAGE, 'contact',  array( 'key' => 'phone' ) );
		add_settings_field( 'address',       __( 'Address', 'starter' ),       'starter_brand_field_textarea', STARTER_BRAND_PAGE, 'contact',  array( 'key' => 'address' ) );

		add_settings_field( 'social_links',  __( 'Social links', 'starter' ),  'starter_brand_field_social',   STARTER_BRAND_PAGE, 'social',   array( 'key' => 'social_links' ) );

		add_settings_field( 'og_image_id',   __( 'Default OG image', 'starter' ), 'starter_brand_field_image', STARTER_BRAND_PAGE, 'og',       array( 'key' => 'og_image_id' ) );
	}
);

function starter_brand_render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Brand Settings', 'starter' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( STARTER_BRAND_OPTION_GROUP );
			do_settings_sections( STARTER_BRAND_PAGE );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

function starter_brand_field_text( array $args ): void {
	$key   = $args['key'];
	$type  = $args['type'] ?? 'text';
	$value = (string) \Starter\Brand::get( $key, '' );
	printf(
		'<input type="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" />',
		esc_attr( $type ),
		esc_attr( \Starter\Brand::OPTION ),
		esc_attr( $key ),
		esc_attr( $value )
	);
}

function starter_brand_field_textarea( array $args ): void {
	$key   = $args['key'];
	$value = (string) \Starter\Brand::get( $key, '' );
	printf(
		'<textarea name="%1$s[%2$s]" rows="3" class="large-text">%3$s</textarea>',
		esc_attr( \Starter\Brand::OPTION ),
		esc_attr( $key ),
		esc_textarea( $value )
	);
}

function starter_brand_field_image( array $args ): void {
	$key      = $args['key'];
	$media_id = (int) \Starter\Brand::get( $key, 0 );
	$url      = $media_id ? wp_get_attachment_image_url( $media_id, 'medium' ) : '';
	printf(
		'<div class="starter-brand-image" data-key="%1$s">
			<input type="hidden" name="%2$s[%1$s]" value="%3$d" class="starter-brand-image__id" />
			<img src="%4$s" alt="" class="starter-brand-image__preview" %5$s style="max-width:200px;height:auto;display:block;margin-bottom:8px;" />
			<button type="button" class="button starter-brand-image__pick">%6$s</button>
			<button type="button" class="button starter-brand-image__clear" %7$s>%8$s</button>
		</div>',
		esc_attr( $key ),
		esc_attr( \Starter\Brand::OPTION ),
		(int) $media_id,
		esc_url( $url ),
		$url ? '' : 'hidden',
		esc_html__( 'Pick image', 'starter' ),
		$media_id ? '' : 'hidden',
		esc_html__( 'Clear', 'starter' )
	);
}

function starter_brand_field_social( array $args ): void {
	$key   = $args['key'];
	$links = (array) \Starter\Brand::get( $key, array() );
	?>
	<div class="starter-brand-social" data-key="<?php echo esc_attr( $key ); ?>">
		<template class="starter-brand-social__template">
			<div class="starter-brand-social__row">
				<input type="text" name="<?php echo esc_attr( \Starter\Brand::OPTION ); ?>[social_links][__INDEX__][platform]" placeholder="<?php esc_attr_e( 'Platform', 'starter' ); ?>" />
				<input type="url"  name="<?php echo esc_attr( \Starter\Brand::OPTION ); ?>[social_links][__INDEX__][url]"      placeholder="https://…" />
				<button type="button" class="button-link starter-brand-social__remove"><?php esc_html_e( 'Remove', 'starter' ); ?></button>
			</div>
		</template>
		<div class="starter-brand-social__rows">
			<?php foreach ( $links as $i => $link ) : ?>
				<div class="starter-brand-social__row">
					<input type="text" name="<?php echo esc_attr( \Starter\Brand::OPTION ); ?>[social_links][<?php echo (int) $i; ?>][platform]" value="<?php echo esc_attr( $link['platform'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Platform', 'starter' ); ?>" />
					<input type="url"  name="<?php echo esc_attr( \Starter\Brand::OPTION ); ?>[social_links][<?php echo (int) $i; ?>][url]"      value="<?php echo esc_attr( $link['url'] ?? '' ); ?>"      placeholder="https://…" />
					<button type="button" class="button-link starter-brand-social__remove"><?php esc_html_e( 'Remove', 'starter' ); ?></button>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button starter-brand-social__add"><?php esc_html_e( 'Add link', 'starter' ); ?></button>
	</div>
	<?php
}

function starter_brand_sanitize( $input ): array {
	if ( ! is_array( $input ) ) {
		return array();
	}
	$clean = array();

	foreach ( array( 'brand_name', 'brand_tagline', 'voice_tone', 'contact_email', 'phone', 'address' ) as $k ) {
		$clean[ $k ] = isset( $input[ $k ] ) ? sanitize_text_field( wp_unslash( (string) $input[ $k ] ) ) : '';
	}
	if ( '' !== $clean['contact_email'] && ! is_email( $clean['contact_email'] ) ) {
		add_settings_error( \Starter\Brand::OPTION, 'invalid_email', __( 'Contact email is invalid.', 'starter' ) );
		$clean['contact_email'] = '';
	}

	$clean['logo_id']     = isset( $input['logo_id'] )     ? (int) $input['logo_id']     : 0;
	$clean['og_image_id'] = isset( $input['og_image_id'] ) ? (int) $input['og_image_id'] : 0;

	$clean['social_links'] = array();
	if ( isset( $input['social_links'] ) && is_array( $input['social_links'] ) ) {
		foreach ( $input['social_links'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$platform  = isset( $row['platform'] ) ? sanitize_key( $row['platform'] ) : '';
			$raw_url   = isset( $row['url'] ) ? (string) $row['url'] : '';
			$url       = $raw_url ? esc_url_raw( $raw_url ) : '';
			$valid_url = $url && wp_http_validate_url( $url );
			if ( '' !== $platform && $valid_url ) {
				$clean['social_links'][] = array( 'platform' => $platform, 'url' => $url );
			}
		}
	}

	return $clean;
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook_suffix ) {
		if ( 'settings_page_' . STARTER_BRAND_PAGE !== $hook_suffix ) {
			return;
		}
		wp_enqueue_media();
		$rel = 'assets/js/admin-brand-settings.js';
		wp_enqueue_script(
			'starter-brand-settings',
			get_theme_file_uri( $rel ),
			array(),
			(string) filemtime( get_theme_file_path( $rel ) ),
			true
		);
	}
);
