<?php
// plugin/tests/phpunit/Seeder/FormSeederTest.php

use Pediment\Seeder\Manifest;
use Pediment\Seeder\ManifestError;
use Pediment\Seeder\PlanItem;
use Pediment\Seeder\Reporter;
use Pediment\Seeder\Runner;

class FormSeederTest extends WP_UnitTestCase {

	private array $manifest;

	public function set_up(): void {
		parent::set_up();
		$this->manifest = [
			'pages' => [ 'contact' => [ 'title' => 'Contact', 'content' => '' ] ],
			'forms' => [
				'destinations'        => [
					'contact-mail' => [
						'label'          => 'Contact requests',
						'to'             => 'sales@example.com, office@example.com',
						'subject'        => 'Request from {{ field:name }}',
						'reply_to_field' => 'email',
					],
				],
				'default_destination' => 'contact-mail',
			],
		];
		add_filter( 'pediment_seed_manifest', fn() => $this->manifest );
	}

	public function tear_down(): void {
		remove_all_filters( 'pediment_seed_manifest' );
		Manifest::resetCache();
		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION );
		parent::tear_down();
	}

	private function stored(): array {
		return array_values( (array) get_option( PEDIMENT_FORM_DESTINATIONS_OPTION, [] ) );
	}

	public function test_a_run_creates_the_destination_and_sets_the_default() {
		$result = ( new Runner() )->run();

		$this->assertTrue( $result->ok(), implode( "\n", array_merge( $result->errors, $result->problems ) ) );
		$stored = $this->stored();
		$this->assertCount( 1, $stored );
		$this->assertSame( 'email', $stored[0]['type'] );
		$this->assertSame( 'contact-mail', $stored[0]['seed_key'] );
		$this->assertSame( [ 'sales@example.com', 'office@example.com' ], $stored[0]['to'] );
		$this->assertSame( 'Request from {{ field:name }}', $stored[0]['subject'] );
		$this->assertSame( $stored[0]['id'], get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION ) );
		$this->assertStringContainsString( 'FORMS', Reporter::text( $result ) );
	}

	public function test_dry_run_writes_nothing() {
		$result = ( new Runner() )->run( [ 'dry_run' => true ] );

		$this->assertCount( 2, $result->plan->byKind( PlanItem::KIND_FORM ) );
		$this->assertSame( [], $this->stored() );
		$this->assertSame( '', (string) get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '' ) );
	}

	public function test_a_second_run_plans_no_writes_even_after_the_owner_edits_the_recipients() {
		( new Runner() )->run();

		$id     = $this->stored()[0]['id'];
		$edited = pediment_form_sanitize_destination(
			[
				'id'    => $id,
				'type'  => 'email',
				'label' => 'Vertrieb',
				'to'    => 'vertrieb@example.com',
			]
		);
		pediment_form_save_destination( $edited['dest'] );

		$second = ( new Runner() )->run();
		$this->assertTrue( $second->plan->isEmpty(), Reporter::text( $second ) );
		$this->assertTrue( $second->ok() );
		$this->assertStringContainsString( '0 to write,', Reporter::summaryLine( $second ) );
		$this->assertCount( 1, $this->stored() );
		$this->assertSame( [ 'vertrieb@example.com' ], $this->stored()[0]['to'] );
		$this->assertSame( 'contact-mail', $this->stored()[0]['seed_key'] );
	}

	public function test_a_default_the_owner_chose_is_kept() {
		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			[
				[
					'id'   => '7',
					'type' => 'email',
					'to'   => [ 'owner@example.com' ],
				],
			]
		);
		update_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION, '7' );

		( new Runner() )->run();

		$this->assertSame( '7', get_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION ) );
		$this->assertCount( 2, $this->stored() );
		$this->assertSame( '8', $this->stored()[1]['id'] );
	}

	public function test_without_to_the_admin_email_is_the_placeholder() {
		unset( $this->manifest['forms']['destinations']['contact-mail']['to'] );
		update_option( 'admin_email', 'admin@example.org' );

		( new Runner() )->run();

		$this->assertSame( [ 'admin@example.org' ], $this->stored()[0]['to'] );
	}

	public function test_attach_files_seeds_and_defaults_to_true() {
		( new Runner() )->run();
		$this->assertTrue( $this->stored()[0]['attach_files'] );

		delete_option( PEDIMENT_FORM_DESTINATIONS_OPTION );
		delete_option( PEDIMENT_FORM_DEFAULT_DEST_OPTION );
		Manifest::resetCache();
		$this->manifest['forms']['destinations']['contact-mail']['attach_files'] = false;
		( new Runner() )->run();
		$this->assertFalse( $this->stored()[0]['attach_files'] );
	}

	public function test_attach_files_must_be_a_boolean() {
		$this->manifest['forms']['destinations']['contact-mail']['attach_files'] = 'no';
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'attach_files' );
		Manifest::fromArray( [ 'forms' => $this->manifest['forms'] ], '/tmp' );
	}

	public function test_a_deleted_destination_is_created_again() {
		( new Runner() )->run();
		pediment_form_delete_destination( $this->stored()[0]['id'] );

		( new Runner() )->run();

		$this->assertCount( 1, $this->stored() );
	}

	public function test_duplicate_seed_keys_block_the_run() {
		update_option(
			PEDIMENT_FORM_DESTINATIONS_OPTION,
			[
				[ 'id' => '1', 'type' => 'email', 'to' => [ 'a@example.com' ], 'seed_key' => 'contact-mail' ],
				[ 'id' => '2', 'type' => 'email', 'to' => [ 'b@example.com' ], 'seed_key' => 'contact-mail' ],
			]
		);

		$result = ( new Runner() )->run();

		$this->assertFalse( $result->applied );
		$this->assertStringContainsString( 'forms.destinations.contact-mail', implode( "\n", $result->errors ) );
	}

	/** @dataProvider invalid_forms_sections */
	public function test_invalid_forms_sections_are_validation_errors( array $forms, string $message ) {
		try {
			Manifest::fromArray( [ 'forms' => $forms ], '/tmp' );
			$this->fail( 'expected a ManifestError' );
		} catch ( ManifestError $e ) {
			$this->assertStringContainsString( $message, $e->getMessage() );
		}
	}

	public function invalid_forms_sections(): array {
		return [
			'unknown section key'     => [ [ 'default' => 'x' ], "unknown key 'default'" ],
			'unknown destination key' => [ [ 'destinations' => [ 'a' => [ 'url' => 'https://x.test' ] ] ], "unknown key 'url'" ],
			'http type'               => [ [ 'destinations' => [ 'a' => [ 'type' => 'http' ] ] ], 'only e-mail destinations' ],
			'bad address'             => [ [ 'destinations' => [ 'a' => [ 'to' => 'nope' ] ] ], 'forms.destinations.a.to' ],
			'secret in body'          => [ [ 'destinations' => [ 'a' => [ 'body_template' => '{{ secret:key }}' ] ] ], 'forms.destinations.a.body_template' ],
			'undeclared default'      => [ [ 'default_destination' => 'missing' ], "'missing' is not a declared form destination" ],
		];
	}
}
