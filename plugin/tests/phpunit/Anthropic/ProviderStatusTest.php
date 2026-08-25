<?php
namespace Pediment\Tests\Anthropic;

use Pediment\Anthropic\ProviderStatus;
use Pediment\Mock\MockProvider;
use Pediment\Settings\OptionsStore;

class ProviderStatusTest extends \WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();
		// Neutralize Bootstrap's mock filter (wp-env defines PEDIMENT_AI_MOCK=true)
		// so each test controls the provider path itself. WP_UnitTestCase restores
		// the hook table after each test.
		remove_all_filters( 'pediment_ai_provider' );
		delete_option( OptionsStore::OPTION );
	}

	public function tearDown(): void {
		delete_option( OptionsStore::OPTION );
		parent::tearDown();
	}

	public function test_reports_missing_key_when_no_key_is_configured(): void {
		if ( defined( 'ANTHROPIC_API_KEY' ) ) {
			$this->markTestSkipped( 'ANTHROPIC_API_KEY constant is defined in this environment.' );
		}
		$this->assertSame( ProviderStatus::MISSING_KEY, ProviderStatus::current() );
	}

	public function test_reports_ok_when_a_key_is_stored(): void {
		( new OptionsStore() )->setApiKey( 'sk-ant-test-123' );
		$this->assertSame( ProviderStatus::OK, ProviderStatus::current() );
	}

	public function test_reports_mock_when_the_mock_provider_is_active(): void {
		add_filter(
			'pediment_ai_provider',
			static fn() => new MockProvider( PEDIMENT_AI_PLUGIN_DIR . '/src/Mock/fixtures' )
		);
		$this->assertSame( ProviderStatus::MOCK, ProviderStatus::current() );
	}

	public function test_reports_ok_for_a_custom_provider_without_a_key(): void {
		// A third party replacing the provider brings its own credentials; the
		// missing-key warning must not fire for it.
		$custom = new class() implements \Pediment\Anthropic\ProviderInterface {
			public function messages( array $args ) {
				return [];
			}
			public function stream_messages( array $args ) {
				return [];
			}
		};
		add_filter( 'pediment_ai_provider', static fn() => $custom );
		$this->assertSame( ProviderStatus::OK, ProviderStatus::current() );
	}
}
