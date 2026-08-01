<?php
/**
 * The single definition of the languages this suite configures.
 *
 * Needed in two places that cannot share a class: bootstrap.php seeds these
 * into a throwaway PLL_Model during `muplugins_loaded`, before WP_UnitTestCase
 * exists (WP core is still mid-boot at that point); PolylangTestCase's
 * wpSetUpBeforeClass() reseeds them after WP_UnitTestCase exists, once per
 * test class, to survive `_delete_all_data()` (see PolylangTestCase.php for
 * why that is necessary). A bare function with no class dependency is
 * loadable at both points; the language array itself lives here exactly once.
 *
 * @return array<int, array{slug: string, name: string, locale: string, flag: string, rtl: int, term_group: int}>
 */
function pediment_test_language_definitions(): array {
	return [
		[ 'slug' => 'en', 'name' => 'English', 'locale' => 'en_US', 'flag' => 'gb', 'rtl' => 0, 'term_group' => 0 ],
		[ 'slug' => 'de', 'name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => 'de', 'rtl' => 0, 'term_group' => 1 ],
	];
}
