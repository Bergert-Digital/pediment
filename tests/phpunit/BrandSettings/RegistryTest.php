<?php

class RegistryTest extends WP_UnitTestCase {
    public function test_fields_returns_all_parent_fields_with_expected_shape() {
        $fields = \Starter\BrandRegistry::fields();

        $expected_keys = array(
            'brand_name', 'brand_tagline', 'voice_tone', 'logo_id',
            'contact_email', 'phone', 'address',
            'social_links',
            'og_image_id',
        );
        foreach ( $expected_keys as $key ) {
            $this->assertArrayHasKey( $key, $fields, "Field {$key} should be in the registry" );
            $this->assertArrayHasKey( 'label', $fields[ $key ] );
            $this->assertArrayHasKey( 'section', $fields[ $key ] );
            $this->assertArrayHasKey( 'type', $fields[ $key ] );
            $this->assertArrayHasKey( 'default', $fields[ $key ] );
        }

        $this->assertSame( 'identity', $fields['brand_name']['section'] );
        $this->assertSame( 'text',     $fields['brand_name']['type'] );
        $this->assertSame( '',         $fields['brand_name']['default'] );

        $this->assertSame( 'image',    $fields['logo_id']['type'] );
        $this->assertSame( 0,          $fields['logo_id']['default'] );

        $this->assertSame( 'social',   $fields['social_links']['type'] );
        $this->assertSame( array(),    $fields['social_links']['default'] );
    }

    public function test_sections_returns_all_parent_sections() {
        $sections = \Starter\BrandRegistry::sections();

        foreach ( array( 'identity', 'contact', 'social', 'og' ) as $slug ) {
            $this->assertArrayHasKey( $slug, $sections );
            $this->assertArrayHasKey( 'title', $sections[ $slug ] );
        }
    }

    public function test_fields_have_null_sanitize_and_renderer_keys_by_default() {
        $fields = \Starter\BrandRegistry::fields();

        foreach ( $fields as $key => $def ) {
            $this->assertArrayHasKey( 'sanitize', $def, "Field {$key} should have a sanitize key" );
            $this->assertArrayHasKey( 'renderer', $def, "Field {$key} should have a renderer key" );
            $this->assertNull( $def['sanitize'], "Field {$key} sanitize should default to null" );
            $this->assertNull( $def['renderer'], "Field {$key} renderer should default to null" );
        }
    }

    public function test_filter_supplied_sanitize_survives_null_merge() {
        $cb = static function ( $fields ) {
            $fields['custom_field'] = array(
                'label'    => 'Custom',
                'section'  => 'contact',
                'type'     => 'integer',
                'default'  => 0,
                'sanitize' => 'absint',
            );
            return $fields;
        };
        add_filter( 'starter_brand_fields', $cb );

        $fields = \Starter\BrandRegistry::fields();

        $this->assertArrayHasKey( 'custom_field', $fields );
        $this->assertSame( 'absint', $fields['custom_field']['sanitize'], 'Pre-set sanitize must survive the null merge.' );
        $this->assertNull( $fields['custom_field']['renderer'], 'Unset renderer should fill with null.' );

        remove_filter( 'starter_brand_fields', $cb );
    }
}
