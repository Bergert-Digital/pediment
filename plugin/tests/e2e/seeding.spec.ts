import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();
const wp = ( cmd: string ) =>
	execSync( `npx wp-env run cli wp ${ cmd }`, { cwd: WP_ENV_CWD, stdio: 'pipe' } )
		.toString()
		.trim();

test.describe( 'seeding engine', () => {
	test( 'a re-seed plans no writes', async () => {
		const plan = wp( `pediment seed --dry-run` );
		expect( plan ).toContain( '0 to write' );
	} );

	test( 'an editor change survives the next seed', async () => {
		const id = wp( `post list --post_type=page --name=about --field=ID` );
		wp( `post update ${ id } --post_content='<!-- wp:paragraph --><p>client edit</p><!-- /wp:paragraph -->'` );

		const plan = wp( `pediment seed --dry-run` );
		expect( plan ).toContain( 'protected' );

		wp( `pediment seed` );
		expect( wp( `post get ${ id } --field=post_content` ) ).toContain(
			'client edit'
		);

		// Restore: the brief's own suggestion — delete the stored hash and
		// re-seed — does not cleanly come back. Differ treats a missing/foreign
		// stored hash as "edited" unconditionally (Differ.php's rule 2), so once
		// _pediment_seed_hash is gone the entry stays PROTECTED forever; a plain
		// `wp pediment seed` never re-hashes a PROTECTED entry (Applier skips it
		// outright), so the page would never rejoin seed management on its own.
		//
		// `wp pediment adopt` looks like the fix, but it writes the live markup
		// back into the theme's patterns/about.php ON DISK — a fine tool for a
		// developer's own client edits, but not something an automated suite
		// should do to a tracked pattern file on every run.
		//
		// Hand-restoring the original bytes via a plain `wp post update` is also
		// unsafe: the Applier suspends KSES before every seed write (see
		// Applier.php's docblock) specifically because un-suspended KSES mangles
		// block-comment JSON on save. A raw CLI update goes through the normal,
		// KSES-active path, so it is not guaranteed to reproduce the exact bytes
		// the engine itself would write.
		//
		// The clean fix: delete the row and let `wp pediment seed` recreate it.
		// A missing row takes the ordinary CREATE path — the same, KSES-suspended
		// path used to seed the site the first time — so the restored page is
		// byte-identical to a fresh seed, with a correctly recomputed hash, not a
		// hand-patched one.
		wp( `post delete ${ id } --force` );
		wp( `pediment seed` );

		const restoredId = wp( `post list --post_type=page --name=about --field=ID` );
		expect( restoredId ).toBeTruthy();
		expect( wp( `post get ${ restoredId } --field=post_content` ) ).not.toContain(
			'client edit'
		);

		const restoredPlan = wp( `pediment seed --dry-run` );
		expect( restoredPlan ).toContain( '0 to write' );
	} );

	test( 'a slug change is reverted', async () => {
		const id = wp( `post list --post_type=page --name=contact --field=ID` );
		wp( `post update ${ id } --post_name=kontakt` );

		wp( `pediment seed` );

		expect( wp( `post get ${ id } --field=post_name` ) ).toBe( 'contact' );
	} );
} );
