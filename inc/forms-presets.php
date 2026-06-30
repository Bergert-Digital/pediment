<?php
/**
 * Curated provider presets — starter templates for new destinations.
 *
 * @package Pediment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the preset map (id => partial destination), filterable.
 *
 * @return array<string,array<string,mixed>>
 */
function pediment_form_presets(): array {
	$presets = array(
		'brevo'   => array(
			'label'         => __( 'Brevo (transactional email)', 'pediment' ),
			'method'        => 'POST',
			'url'           => 'https://api.brevo.com/v3/smtp/email',
			'headers'       => array( 'api-key' => '{{ secret:brevo_api_key }}' ),
			'content_type'  => 'application/json',
			'body_template' => '{"sender":{"email":"noreply@example.com"},"to":[{"email":"you@example.com"}],"subject":"New form submission","textContent":"{{ field:message }}","params":"{{ all_fields }}"}',
			'secret_refs'   => array( 'brevo_api_key' ),
		),
		'resend'  => array(
			'label'         => __( 'Resend', 'pediment' ),
			'method'        => 'POST',
			'url'           => 'https://api.resend.com/emails',
			'headers'       => array( 'Authorization' => 'Bearer {{ secret:resend_api_key }}' ),
			'content_type'  => 'application/json',
			'body_template' => '{"from":"noreply@example.com","to":"you@example.com","subject":"New form submission","text":"{{ field:message }}"}',
			'secret_refs'   => array( 'resend_api_key' ),
		),
		'mailgun' => array(
			'label'         => __( 'Mailgun', 'pediment' ),
			'method'        => 'POST',
			'url'           => 'https://api.mailgun.net/v3/YOUR_DOMAIN/messages',
			'headers'       => array( 'Authorization' => 'Basic {{ secret:mailgun_basic_auth }}' ),
			'content_type'  => 'application/x-www-form-urlencoded',
			'body_template' => 'from=noreply@example.com&to=you@example.com&subject=New form submission&text={{ field:message }}',
			'secret_refs'   => array( 'mailgun_basic_auth' ),
		),
		'n8n'     => array(
			'label'         => __( 'n8n webhook', 'pediment' ),
			'method'        => 'POST',
			'url'           => 'https://n8n.example.com/webhook/your-id',
			'headers'       => array(),
			'content_type'  => 'application/json',
			'body_template' => '{"fields":"{{ all_fields }}","page":"{{ meta:page_url }}","at":"{{ meta:submitted_at }}"}',
			'secret_refs'   => array(),
		),
		'slack'   => array(
			'label'         => __( 'Slack incoming webhook', 'pediment' ),
			'method'        => 'POST',
			'url'           => 'https://hooks.slack.com/services/{{ secret:slack_webhook_path }}',
			'headers'       => array(),
			'content_type'  => 'application/json',
			'body_template' => '{"text":"New form submission from {{ meta:page_url }}"}',
			'secret_refs'   => array( 'slack_webhook_path' ),
		),
		'custom'  => array(
			'label'         => __( 'Custom HTTP request', 'pediment' ),
			'method'        => 'POST',
			'url'           => '',
			'headers'       => array(),
			'content_type'  => 'application/json',
			'body_template' => '{"fields":"{{ all_fields }}"}',
			'secret_refs'   => array(),
		),
	);

	$filtered = apply_filters( 'pediment_form_presets', $presets );
	return is_array( $filtered ) ? $filtered : $presets;
}
