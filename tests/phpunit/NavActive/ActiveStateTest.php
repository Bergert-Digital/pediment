<?php

class ActiveStateTest extends WP_UnitTestCase {
	public function test_matches_identical_paths() {
		$this->assertTrue( pediment_nav_path_is_current( '/about', '/about' ) );
	}

	public function test_ignores_surrounding_slashes() {
		$this->assertTrue( pediment_nav_path_is_current( '/about', '/about/' ) );
		$this->assertTrue( pediment_nav_path_is_current( '/blog/', '/blog' ) );
	}

	public function test_ignores_query_string() {
		$this->assertTrue( pediment_nav_path_is_current( '/contact', '/contact?foo=bar' ) );
	}

	public function test_non_matching_paths_are_not_current() {
		$this->assertFalse( pediment_nav_path_is_current( '/about', '/blog' ) );
		$this->assertFalse( pediment_nav_path_is_current( '/about', '/about/team' ) );
	}

	public function test_empty_or_root_link_is_never_current() {
		$this->assertFalse( pediment_nav_path_is_current( '', '/' ) );
		$this->assertFalse( pediment_nav_path_is_current( '/', '/' ) );
	}
}
