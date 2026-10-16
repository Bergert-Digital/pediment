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

	// A post no menu links to, so the edit cannot ripple into a nav. Title and
	// slug only: they restore exactly with `wp post update`, while restoring
	// block markup through WP-CLI would run it through KSES. PHPUnit covers
	// content (RunnerTest::test_a_reseed_never_updates_existing_content).
	test( 'a re-seed never changes a post a person edited', async () => {
		const id = wp( `post list --post_type=post --name=sample-note-two --field=ID` );
		const title = wp( `post get ${ id } --field=post_title` );
		wp( `post update ${ id } --post_title='Edited live' --post_name=edited-live` );

		try {
			const plan = wp( `pediment seed --dry-run` );
			expect( plan ).toContain( '0 to write' );
			expect( plan ).not.toContain( 'protected' );

			wp( `pediment seed` );
			expect( wp( `post get ${ id } --field=post_title` ) ).toBe( 'Edited live' );
			expect( wp( `post get ${ id } --field=post_name` ) ).toBe( 'edited-live' );
		} finally {
			wp( `post update ${ id } --post_title='${ title }' --post_name=sample-note-two` );
		}
	} );
} );
