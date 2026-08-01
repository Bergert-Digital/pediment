import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();
const wp = ( cmd: string ) =>
	execSync( `npx wp-env run cli wp ${ cmd }`, { cwd: WP_ENV_CWD, stdio: 'pipe' } )
		.toString()
		.trim();

test.describe( 'multilingual seeding', () => {
	test( 'both languages exist and are configured', () => {
		// Not `pediment seed --dry-run --json`: WP-CLI 2.12 rewrites ANY
		// `--json` assoc-arg to `--format=json` before a command's own
		// synopsis is validated (wp-cli core, the `--json -> --format=json`
		// shorthand in Runner::run_command()) — confirmed by `wp post list
		// --json` and `wp post list --format=json` producing identical output.
		// SeedCommand declares its own `--json` flag but no `--format`, so the
		// rewritten arg always fails validation with "unknown --format
		// parameter". This is a pre-existing bug in wp-cli/SeedCommand.php
		// (predates step 4 — see commit e7ea6ce), reported rather than fixed
		// here per this task's constraints. `wp eval` calls the exact same
		// render() path the CLI flag would, without going through WP-CLI's
		// own arg-rewriting layer where the collision lives.
		const json = JSON.parse(
			wp(
				`eval '$r = ( new \\Pediment\\Seeder\\Runner() )->run( [ "dry_run" => true ] ); echo \\Pediment\\Cli\\SeedCommand::render( $r, true );'`
			)
		);
		const languages = new Set(
			json.items.filter( ( i ) => i.kind === 'entry' ).map( ( i ) => i.language )
		);

		expect( [ ...languages ].sort() ).toEqual( [ 'de', 'en' ] );
	} );

	test( 'a re-seed plans no writes', () => {
		expect( wp( `pediment seed --dry-run` ) ).toContain( '0 to write' );
	} );

	test( 'the untranslated posts are reported, not failed', () => {
		const plan = wp( `pediment seed --dry-run` );

		expect( plan ).toContain( 'TRANSLATIONS' );
		expect( plan ).toContain( 'sample-insight-one' );
	} );

	test( 'the German page is reachable at its own slug', async ( { page } ) => {
		const response = await page.goto( '/de/ueber-uns/' );

		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( 'body' ) ).toContainText( 'Deutschsprachige Fassung' );
	} );

	test( 'the German language root serves the front page, not a redirect chain', async ( { page } ) => {
		await page.goto( '/de/' );

		expect( new URL( page.url() ).pathname ).toBe( '/de/' );
	} );

	test( 'the header navigation links to German pages on a German page', async ( { page } ) => {
		await page.goto( '/de/ueber-uns/' );

		const header = page.locator( 'header.site-header' );
		await expect( header.getByRole( 'link', { name: 'Kontakt' } ) ).toHaveAttribute(
			'href',
			/\/de\/kontakt\/?$/
		);
	} );

	test( 'the two About pages are one translation group', () => {
		const en = wp( `post list --post_type=page --name=about --field=ID` );
		const de = wp( `post list --post_type=page --name=ueber-uns --field=ID` );

		const linked = wp( `eval 'echo (int) pll_get_post( ${ en }, "de" );'` );

		expect( linked ).toBe( de );
	} );
} );
