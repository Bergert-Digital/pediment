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

	public function test_var_fallback_hides_literal_colour(): void {
		$errors = $this->errors( $this->spec( array( 'css' => '&{color:var(--wp--preset--color--accent, #a9621f)}' ) ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'colour', $errors[0] );
	}

	public function test_named_colours_in_css_are_rejected(): void {
		$errors = $this->errors( $this->spec( array( 'css' => '&{color:white}' ) ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'colour', $errors[0] );

		$black = $this->errors( $this->spec( array( 'css' => '&{background:black}' ) ) );
		$this->assertCount( 1, $black );

		$red = $this->errors( $this->spec( array( 'css' => '&{box-shadow:0 1px 2px red}' ) ) );
		$this->assertCount( 1, $red );
	}

	public function test_class_selectors_with_colour_names_are_allowed(): void {
		$this->assertSame( array(), $this->errors( $this->spec( array( 'css' => '& .red{width:26px}' ) ) ) );
	}

	public function test_outline_colour_must_be_token(): void {
		$errors = $this->errors( $this->spec( array( 'outline' => array( 'color' => '#fff' ) ) ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'styles.outline.color', $errors[0] );
	}

	public function test_filter_duotone_must_be_token(): void {
		$errors = $this->errors( $this->spec( array( 'filter' => array( 'duotone' => '#fff' ) ) ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'styles.filter.duotone', $errors[0] );
	}

	public function test_variations_are_recursed(): void {
		$errors = $this->errors(
			$this->spec(
				array(
					'blocks' => array(
						'core/button' => array(
							'variations' => array(
								'big' => array(
									'color' => array( 'text' => '#fff' ),
								),
							),
						),
					),
				)
			)
		);
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'variations.big.color.text', $errors[0] );
	}

	public function test_ref_objects_must_have_only_ref_key(): void {
		$errors = $this->errors( $this->spec( array( 'spacing' => array( 'padding' => array( 'ref' => 'styles.x', 'top' => '78px' ) ) ) ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'styles.spacing.padding.top', $errors[0] );
	}

	public function test_important_with_whitespace(): void {
		$errors = $this->errors( $this->spec( array( 'css' => '&{color:var(--wp--preset--color--accent) ! important}' ) ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( '!important', $errors[0] );
	}

	public function test_slug_with_newline(): void {
		$errors = $this->errors( $this->spec( array(), array( 'slug' => "card\n" ) ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'slug:', $errors[0] );
	}

	public function test_colour_functions_in_css(): void {
		$errors = $this->errors( $this->spec( array( 'css' => '&{color:color(display-p3 1 0 0)}' ) ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'colour', $errors[0] );

		$mix = $this->errors( $this->spec( array( 'css' => '&{color:color-mix(in srgb, red, blue)}' ) ) );
		$this->assertCount( 1, $mix );

		$light = $this->errors( $this->spec( array( 'css' => '&{color:light-dark(white, black)}' ) ) );
		$this->assertCount( 1, $light );
	}

	public function test_at_rule_restrictions(): void {
		$scope = $this->errors( $this->spec( array( 'css' => '@scope (.site-header){display:none}' ) ) );
		$this->assertCount( 1, $scope );
		$this->assertStringContainsString( '@scope', $scope[0] );
		$this->assertStringContainsString( 'not allowed', $scope[0] );

		$import = $this->errors( $this->spec( array( 'css' => '@import url(x.css);' ) ) );
		$this->assertCount( 1, $import );
		$this->assertStringContainsString( '@import', $import[0] );

		$allowed = $this->errors( $this->spec( array( 'css' => '@media (min-width:600px){&:hover{transform:translateY(-4px)}}' ) ) );
		$this->assertEmpty( $allowed );

		$container = $this->errors( $this->spec( array( 'css' => '@container (min-width:600px){&{display:block}}' ) ) );
		$this->assertEmpty( $container );

		$supports = $this->errors( $this->spec( array( 'css' => '@supports (display:grid){&{display:grid}}' ) ) );
		$this->assertEmpty( $supports );

		$layer = $this->errors( $this->spec( array( 'css' => '@layer utilities{&{color:var(--wp--preset--color--accent)}}' ) ) );
		$this->assertEmpty( $layer );
	}
}
