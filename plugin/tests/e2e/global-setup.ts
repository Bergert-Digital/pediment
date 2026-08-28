import { execSync } from 'node:child_process';
const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();

/**
 * Bring a WPML wp-env up to the same seeded, deterministic state the Polylang
 * branch produces — with one extra step that has no Polylang analogue.
 *
 * The critical difference is `WPML_Config::load_config_run()`. Pediment ships
 * `inc/wpml-compat.php`, whose `wpml_config_array` filter declares
 * `wp_navigation` translatable (and `wp_template_part` shared). WPML does NOT
 * consume that filter continuously: `WPML_Config::load_config()` parses it and
 * persists the result to `custom_posts_sync_option` only on an is_admin()
 * request that lands on a whitelisted admin page (plugins.php, themes.php, the
 * WPML languages screen) or when the setup-wizard's FinishStep endpoint runs
 * (both call `WPML_Config::load_config_run()`, see the installed source at
 * classes/xml-config/class-wpml-config.php and classes/setup/endpoints/FinishStep.php).
 * `wp pediment languages` (WpmlSetup) activates the languages but does NOT
 * trigger that parse, and a WP-CLI request is not is_admin().
 *
 * So on a headless deploy, `wp_navigation` stays non-translatable until an
 * admin visits wp-admin, and until it is translatable WPML's `wpml_object_id`
 * returns the default-language nav for EVERY language — the seeded German menu
 * is never linked as a translation, and both languages render the English
 * header. `WpmlSetup::configure()` (which `wp pediment languages` routes to) now
 * fires `WPML_Config::load_config_run()` itself after activating the languages,
 * so the parse runs through WPML's real config path (it reads
 * `inc/wpml-compat.php`'s filter — nothing is hand-set) as part of the headless
 * deploy. This proves the real CLI path end-to-end: no manual load_config_run
 * here. The parse MUST have run before the seed: NavSeeder builds the nav
 * translation group during the seed, and that only holds once `wp_navigation`
 * is translatable — `wp pediment languages` runs before `wp pediment seed`
 * below, so that ordering is preserved.
 *
 * @param wp Runs a wp-cli command inside the wp-env `cli` container.
 */
function setupWpml( wp: ( cmd: string ) => string ): void {
	// WPML is supplied by the env's plugin list; make sure it is on, then
	// configure en+de through `wp pediment languages` (routes to WpmlSetup,
	// which drives WPML's own installation API — a raw icl_sitepress_settings
	// write does not activate languages — and then triggers WPML's config parse
	// so `wp_navigation` becomes translatable via the real headless CLI path,
	// before the nav translation group is built).
	wp( `plugin activate sitepress-multilingual-cms` );
	wp( `pediment languages` );

	wp( `pediment seed` );
}

/**
 * Prepare the wp-env site so the e2e suite is deterministic:
 * active fixture theme, pretty permalinks, manifest-seeded demo content (pages/posts/nav/logo),
 * static front page, dismissed editor welcome guides (otherwise the Site/Post
 * Editor never attaches its canvas iframe on a fresh wp-env, hanging every
 * editor-canvas test). Idempotent — safe to run repeatedly.
 */
export default async function globalSetup(): Promise< void > {
	const wp = ( cmd: string ) =>
		execSync( `npx wp-env run cli wp ${ cmd }`, {
			cwd: WP_ENV_CWD,
			stdio: 'pipe',
		} )
			.toString()
			.trim();

	// The fixture supplies the standalone client-theme shell; Pediment supplies
	// templates, tokens, blocks, patterns, and the header bootstrap.
	const active = wp( `option get stylesheet` );
	if ( active !== 'pediment-fixture' ) {
		wp( `theme activate pediment-fixture` );
	}
	wp( `plugin activate pediment-ai` );

	wp( `rewrite structure '/%postname%/' --hard` );

	// One global-setup serves two mutually-exclusive envs: the default Polylang
	// wp-env (.wp-env.json) and the WPML wp-env (.wp-env.override.json /
	// .wp-env.wpml.json). They cannot both be active — the WPML env has no
	// Polylang installed and vice-versa — so branch on which multilingual
	// plugin the running env actually carries rather than hardcoding one.
	const usesWpml = wp( `plugin list --field=name --status=active` ).includes(
		'sitepress-multilingual-cms'
	);

	if ( usesWpml ) {
		setupWpml( wp );
	} else {
		// Languages first, always: content written before the languages exist
		// carries no language, is invisible to every translation lookup, and has
		// previously removed a live site's header outright. `wp pediment seed`
		// refuses to run when the two disagree, so this is also what unblocks it.
		wp( `plugin activate polylang` );
		wp( `pediment languages` );

		// Content comes from the fixture theme's seed manifest, applied by the
		// real engine — the suite exercises `wp pediment seed` on every run.
		wp( `pediment seed` );
	}

	wp( `rewrite flush --hard` );

	// Dismiss the Site Editor + Post Editor welcome guides for every existing
	// user. WP stores these as `wp_persisted_preferences` user meta; the modal
	// overlays the canvas on first visit and blocks the iframe from mounting.
	const prefs = JSON.stringify( {
		'core/edit-site': {
			welcomeGuide: false,
			welcomeGuideStyles: false,
			welcomeGuidePage: false,
			welcomeGuideTemplate: false,
		},
		'core/edit-post': { welcomeGuide: false, welcomeGuideTemplate: false },
		'core/editor': { welcomeGuide: false, welcomeGuideTemplate: false },
		'core/nux': { areTipsEnabled: false },
	} );
	const userIds = wp( `user list --field=ID` )
		.split( /\s+/ )
		.filter( Boolean );
	for ( const uid of userIds ) {
		execSync(
			`npx wp-env run cli wp user meta update ${ uid } wp_persisted_preferences '${ prefs }' --format=json`,
			{ cwd: WP_ENV_CWD, stdio: 'pipe' }
		);

		// E2E runs share the persistent wp-env database. Reset the per-user
		// transient counters so repeated local runs do not exhaust production
		// rate limits before the mock-provider assertions execute.
		for ( const kind of [ 'compose', 'edit', 'refine' ] ) {
			wp( `transient delete pediment_ai_rl_${ uid }_${ kind }` );
		}
	}
}
