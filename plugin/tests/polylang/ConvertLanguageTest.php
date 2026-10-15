<?php

use Pediment\Content\Policy;
use Pediment\Convert\AttachmentMedia;
use Pediment\Convert\Converter;
use Pediment\Convert\PostConverter;

/**
 * The converter finds and converts legacy blocks in every language, not only
 * the request's current one.
 */
class ConvertLanguageTest extends PolylangTestCase {

	private $originalCurlang;

	public function set_up(): void {
		parent::set_up();
		$this->originalCurlang = PLL()->curlang;
	}

	public function tear_down(): void {
		PLL()->curlang = $this->originalCurlang;
		parent::tear_down();
	}

	public function test_translations_are_converted_too(): void {
		$provider = \Pediment\Language\LanguageRegistry::provider();
		$en       = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '<!-- wp:pediment/pull-quote {"quote":"Hello"} /-->' ] );
		$de       = self::factory()->post->create( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '<!-- wp:pediment/pull-quote {"quote":"Hallo"} /-->' ] );
		$provider->setLanguage( $en, 'en' );
		$provider->setLanguage( $de, 'de' );
		$provider->linkTranslations( [ 'en' => $en, 'de' => $de ] );

		// A request scoped to English: a plain page query would hide the German page.
		PLL()->curlang = PLL()->model->get_language( 'en' );

		$service    = new PostConverter( Converter::withDefaults(), new Policy(), new AttachmentMedia() );
		$candidates = $service->candidates();
		$this->assertContains( $en, $candidates );
		$this->assertContains( $de, $candidates );

		$report = $service->runMany( [ $en, $de ], false );
		$this->assertSame( [ 'en', 'de' ], array_column( $report['posts'], 'language' ) );
		$this->assertSame( [ 'converted', 'converted' ], array_column( $report['posts'], 'status' ) );
		$this->assertStringContainsString( '<p>Hallo</p>', get_post_field( 'post_content', $de ) );
		$this->assertSame( 'de', $provider->languageOf( $de ), 'the write keeps the language' );
		$this->assertSame( 'en', $provider->languageOf( $en ) );
	}
}
