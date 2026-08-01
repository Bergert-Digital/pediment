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
	'version' => 1,
	'site'    => array( 'logo' => 'logo' ),
	'media'   => array(
		'logo' => array( 'file' => 'seed/media/logo.svg', 'title' => 'Pediment e2e logo' ),
	),
	'pages'   => array(
		'home'      => array( 'title' => 'Home', 'pattern' => 'pediment/pediment-landing', 'front_page' => true ),
		'about'     => array( 'title' => 'About', 'pattern' => 'pediment-fixture/about' ),
		'contact'   => array( 'title' => 'Contact', 'content' => '' ),
		'blog'      => array( 'title' => 'Blog', 'content' => '', 'posts_page' => true ),
		'mega-demo' => array( 'title' => 'Mega Menu Demo', 'pattern' => 'pediment/mega-menu-header' ),
	),
	'posts'   => array(
		'sample-insight-one'  => array( 'title' => 'A practical insight on getting started', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'insights' ) ) ),
		'sample-insight-two'  => array( 'title' => 'What good looks like, in plain terms', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'insights' ) ) ),
		'sample-briefing-one' => array( 'title' => 'A short briefing on a common decision', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'briefings' ) ) ),
		'sample-briefing-two' => array( 'title' => 'Trade-offs worth weighing early', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'briefings' ) ) ),
		'sample-note-one'     => array( 'title' => 'A quick note on process', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'notes' ) ) ),
		'sample-note-two'     => array( 'title' => 'A quick note on outcomes', 'pattern' => 'pediment-fixture/sample-post', 'terms' => array( 'category' => array( 'notes' ) ) ),
	),
	'navs'    => array(
		'primary' => array(
			'title' => 'Header Navigation',
			'items' => array(
				array( 'entry' => 'about', 'label' => 'About' ),
				array( 'entry' => 'blog', 'label' => 'Blog' ),
				array( 'entry' => 'contact', 'label' => 'Contact' ),
			),
		),
	),
);
