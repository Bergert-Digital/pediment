import { execSync } from 'node:child_process';
const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();

/**
 * Prepare the wp-env site so the e2e suite is deterministic:
 * active fixture theme, pretty permalinks, inline demo fixtures (pages/posts/nav/logo),
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

	// Build the minimal demo content the suite depends on. The standalone
	// `wp pediment seed` command was removed when seeding moved to the child
	// theme; these fixtures source canonical page markup from the registered
	// patterns. eval-file runs inside the container against the mapped plugin dir.
	wp( `eval-file wp-content/plugins/pediment-ai/tests/e2e/fixtures.php` );

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
