<?php

use Pediment\Design\Exporter;
use Pediment\Design\SiteStyles;
use Pediment\Design\StyleValidator;

class ExporterTest extends WP_UnitTestCase {

	private SiteStyles $store;

	public function set_up(): void {
		parent::set_up();
		$this->store = new SiteStyles( new StyleValidator() );
	}

	private function save( string $slug ): void {
		$this->store->save(
			array(
				'slug'       => $slug,
				'label'      => ucfirst( $slug ),
				'blockTypes' => array( 'core/group' ),
				'styles'     => array(
					'color' => array( 'background' => 'var:preset|color|accent' ),
					'css'   => '& > .x::after{content:"→"}',
				),
			)
		);
	}

	public function test_files_are_theme_partials_for_active_styles_only(): void {
		$this->save( 'promo' );
		$this->save( 'old' );
		$this->store->setState( 'old', SiteStyles::STATE_PROMOTED );

		$files = ( new Exporter( $this->store ) )->files();

		$this->assertSame( array( 'promo.json' ), array_keys( $files ) );
		$data = json_decode( $files['promo.json'], true );
		$this->assertSame( 3, $data['version'] );
		$this->assertSame( 'promo', $data['slug'] );
		$this->assertSame( 'Promo', $data['title'] );
		$this->assertSame( array( 'core/group' ), $data['blockTypes'] );
		$this->assertSame( '& > .x::after{content:"→"}', $data['styles']['css'] );
	}

	public function test_export_keeps_theme_properties_of_the_same_slug(): void {
		$this->store->save(
			array(
				'slug'       => 'panel',
				'label'      => 'Site panel',
				'blockTypes' => array( 'core/group' ),
				'styles'     => array( 'color' => array( 'background' => 'var:preset|color|accent' ) ),
			)
		);
		$theme = array(
			'panel' => array(
				'blockTypes' => array( 'core/column' ),
				'styles'     => array(
					'color'   => array( 'background' => 'var:preset|color|surface' ),
					'spacing' => array( 'padding' => array( 'top' => 'var:preset|spacing|50' ) ),
				),
			),
		);

		$data = json_decode( ( new Exporter( $this->store, $theme ) )->files()['panel.json'], true );

		$this->assertSame( 'Site panel', $data['title'] );
		$this->assertSame( array( 'core/column', 'core/group' ), $data['blockTypes'] );
		$this->assertSame( 'var:preset|color|accent', $data['styles']['color']['background'] );
		$this->assertSame( 'var:preset|spacing|50', $data['styles']['spacing']['padding']['top'] );
	}

	public function test_no_active_styles_exports_nothing_without_error(): void {
		$exporter = new Exporter( $this->store );
		$this->assertSame( array(), $exporter->files() );
		$this->assertSame( array(), $exporter->writeTo( get_temp_dir() . 'pediment-export-empty-' . wp_rand() ) );
	}

	public function test_write_to_and_zip(): void {
		$this->save( 'promo' );
		$exporter = new Exporter( $this->store );

		$dir   = get_temp_dir() . 'pediment-export-' . wp_rand();
		$paths = $exporter->writeTo( $dir );
		$this->assertSame( array( $dir . '/promo.json' ), $paths );
		$this->assertFileExists( $dir . '/promo.json' );

		$zip = $exporter->zip();
		$this->assertIsString( $zip );
		$archive = new ZipArchive();
		$this->assertTrue( $archive->open( $zip ) );
		$this->assertNotFalse( $archive->locateName( 'styles/blocks/promo.json' ) );
		$archive->close();
	}

	public function test_zip_without_active_styles_returns_an_error(): void {
		$zip = ( new Exporter( $this->store ) )->zip();
		$this->assertWPError( $zip );
		$this->assertSame( 'pediment_nothing_to_export', $zip->get_error_code() );
	}

	public function test_write_to_an_unwritable_directory_returns_an_error(): void {
		$this->save( 'promo' );
		$file = wp_tempnam( 'pediment-export-file' );

		$result = ( new Exporter( $this->store ) )->writeTo( $file . '/sub' );

		$this->assertWPError( $result );
		$this->assertSame( 'pediment_export_write_failed', $result->get_error_code() );
		wp_delete_file( $file );
	}
}
