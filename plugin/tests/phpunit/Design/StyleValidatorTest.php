<?php

use Pediment\Design\StyleValidator;

class StyleValidatorTest extends WP_UnitTestCase {

	private function spec( array $styles, array $overrides = array() ): array {
		return array_merge(
			array(
				'slug'       => 'card',
				'label'      => 'Card',
				'blockTypes' => array( 'core/group' ),
				'styles'     => $styles,
			),
			$overrides
		);
	}

	private function errors( array $spec ): array {
		return ( new StyleValidator() )->validate( $spec );
	}

	public function test_token_values_are_valid(): void {
		$this->assertSame(
			array(),
			$this->errors(
				$this->spec(
					array(
						'color'      => array( 'background' => 'var:preset|color|surface', 'text' => 'currentColor' ),
						'spacing'    => array( 'padding' => array( 'top' => 'var:preset|spacing|50', 'left' => '0' ) ),
						'border'     => array( 'radius' => 'var(--wp--custom--radius--md)', 'width' => '1px', 'style' => 'solid', 'color' => 'var:preset|color|border' ),
						'shadow'     => 'var:preset|shadow|subtle',
						'typography' => array( 'fontSize' => 'var:preset|font-size|lg', 'letterSpacing' => '0.14em' ),
						'dimensions' => array( 'minHeight' => '50%' ),
					)
				)
			)
		);
	}

	public function test_ref_objects_are_valid(): void {
		$this->assertSame( array(), $this->errors( $this->spec( array( 'color' => array( 'text' => array( 'ref' => 'styles.color.text' ) ) ) ) ) );
	}

	public function test_literal_padding_is_rejected_with_its_path(): void {
		$errors = $this->errors( $this->spec( array( 'spacing' => array( 'padding' => array( 'top' => '78px' ) ) ) ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'styles.spacing.padding.top', $errors[0] );
		$this->assertStringContainsString( '78px', $errors[0] );
	}

	public function test_literal_colour_radius_shadow_and_font_size_are_rejected(): void {
		$errors = $this->errors(
			$this->spec(
				array(
					'color'      => array( 'background' => '#ffffff' ),
					'border'     => array( 'radius' => '22px' ),
					'shadow'     => '0 2px 4px rgba(0,0,0,.2)',
					'typography' => array( 'fontSize' => '1.2rem' ),
				)
			)
		);
		$this->assertCount( 4, $errors );
	}

	public function test_nested_element_and_pseudo_state_values_are_checked(): void {
		$errors = $this->errors(
			$this->spec(
				array(
					'elements' => array(
						'link' => array(
							'color'  => array( 'text' => '#a9621f' ),
							':hover' => array( 'color' => array( 'text' => 'red' ) ),
						),
					),
				)
			)
		);
		$this->assertCount( 2, $errors );
		$this->assertStringContainsString( 'styles.elements.link.color.text', $errors[0] );
		$this->assertStringContainsString( 'styles.elements.link.:hover.color.text', $errors[1] );
	}

	public function test_css_rules(): void {
		$this->assertSame( array(), $this->errors( $this->spec( array( 'css' => '& .x::before{content:"";width:26px;background:var(--wp--preset--color--accent)} @media (min-width:600px){&:hover{transform:translateY(-4px)}} &:is(.a, .b){opacity:.9}' ) ) ) );

		$important = $this->errors( $this->spec( array( 'css' => '&{color:var(--wp--preset--color--accent) !important}' ) ) );
		$this->assertStringContainsString( '!important', $important[0] );

		$colour = $this->errors( $this->spec( array( 'css' => '&:hover{background:#fff}' ) ) );
		$this->assertStringContainsString( 'colour', $colour[0] );

		$rgb = $this->errors( $this->spec( array( 'css' => '&{box-shadow:0 1px 2px rgba(0,0,0,.1)}' ) ) );
		$this->assertCount( 1, $rgb );

		$scope = $this->errors( $this->spec( array( 'css' => '.site-header{display:none}' ) ) );
		$this->assertStringContainsString( 'must start with &', $scope[0] );
	}

	public function test_spec_shape(): void {
		$this->assertNotEmpty( $this->errors( $this->spec( array(), array( 'slug' => 'Card' ) ) ) );
		$this->assertNotEmpty( $this->errors( $this->spec( array(), array( 'label' => '' ) ) ) );
		$this->assertNotEmpty( $this->errors( $this->spec( array(), array( 'blockTypes' => array() ) ) ) );
		$unknown = $this->errors( $this->spec( array(), array( 'blockTypes' => array( 'acme/nope' ) ) ) );
		$this->assertStringContainsString( 'not registered', $unknown[0] );
		$this->assertNotEmpty( $this->errors( array( 'slug' => 'card', 'label' => 'Card', 'blockTypes' => array( 'core/group' ) ) ) );
	}
}
