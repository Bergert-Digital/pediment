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
}
