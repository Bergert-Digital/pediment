<?php
/**
 * Seed manifest for the e2e fixture client theme.
 *
 * This is the reference example of the format AND the content the Playwright
 * suite asserts against — it replaced tests/e2e/fixtures.php so every CI run
 * exercises the real seeding engine.
 *
 * @package Pediment
 */

return array(
	'version'   => 1,
	'languages' => array(
		'en' => array( 'name' => 'English', 'locale' => 'en_US', 'flag' => 'gb', 'default' => true ),
		'de' => array( 'name' => 'Deutsch', 'locale' => 'de_DE', 'flag' => 'de' ),
	),
	'site'      => array( 'logo' => 'logo' ),
	'media'     => array(
		'logo' => array( 'file' => 'seed/media/logo.svg', 'title' => 'Pediment e2e logo' ),
	),
	'pages'     => array(
		'home'      => array(
			'title'      => 'Home',
			'pattern'    => 'pediment/pediment-landing',
			'front_page' => true,
			'languages'  => array( 'de' => array( 'title' => 'Startseite', 'slug' => 'startseite' ) ),
		),
		'about'     => array(
			'title'     => 'About',
			'pattern'   => 'pediment-fixture/about',
			'languages' => array( 'de' => array( 'title' => 'Über uns', 'slug' => 'ueber-uns' ) ),
		),
		'contact'   => array(
			'title'     => 'Contact',
			'content'   => '',
			'languages' => array( 'de' => array( 'title' => 'Kontakt', 'slug' => 'kontakt' ) ),
		),
		'blog'      => array(
			'title'      => 'Blog',
			'content'    => '',
			'posts_page' => true,
			'languages'  => array( 'de' => array( 'title' => 'Journal', 'slug' => 'journal' ) ),
		),
		'mega-demo' => array(
			'title'     => 'Mega Menu Demo',
			'pattern'   => 'pediment/mega-menu-header',
			'languages' => array( 'de' => array( 'title' => 'Mega-Menü Demo', 'slug' => 'mega-menue-demo' ) ),
		),
	),
	'posts'     => array(
		'sample-insight-one'  => array( 'title' => 'A practical insight on getting started', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'insights' ) ) ),
		'sample-insight-two'  => array( 'title' => 'What good looks like, in plain terms', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'insights' ) ) ),
		'sample-briefing-one' => array( 'title' => 'A short briefing on a common decision', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'briefings' ) ) ),
		'sample-briefing-two' => array( 'title' => 'Trade-offs worth weighing early', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'briefings' ) ) ),
		'sample-note-one'     => array( 'title' => 'A quick note on process', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'notes' ) ) ),
		'sample-note-two'     => array( 'title' => 'A quick note on outcomes', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'notes' ) ) ),
	),
	'navs'      => array(
		'primary' => array(
			'title' => 'Header Navigation',
			// No hardcoded label: NavSeeder::serialize() falls back to the linked
			// entry's own post_title when a nav item omits 'label', and that title
			// is already per-language (About/Blog/Contact in en, Über
			// uns/Journal/Kontakt in de) — the same English text these items used
			// to hardcode. A fixed label would render the SAME text in every
			// language, defeating the point of a bilingual header nav.
			'items' => array(
				array( 'entry' => 'about' ),
				array( 'entry' => 'blog' ),
				array( 'entry' => 'contact' ),
			),
		),
	),
);
