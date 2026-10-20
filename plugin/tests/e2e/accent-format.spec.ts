import { test, expect } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

const SLUG = 'accent-format-e2e';

test.afterEach( () => {
	deletePageBySlug( SLUG );
} );

test( 'the accent format is registered without a hero block', async ( {
	page,
} ) => {
	deletePageBySlug( SLUG );
	const url = createPageWithContent(
		SLUG,
		'Accent Format E2E',
		'<!-- wp:paragraph --><p>Only a core paragraph.</p><!-- /wp:paragraph -->'
	);
	const id = new URL( url, 'http://localhost' ).searchParams.get( 'page_id' );
	await login( page );
	await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );

	await page.waitForFunction(
		() =>
			!! ( window as any ).wp?.data
				?.select( 'core/block-editor' )
				?.getBlocks?.().length,
		null,
		{ timeout: 20_000 }
	);

	const result = await page.evaluate( () => {
		const { select } = ( window as any ).wp.data;
		return {
			names: select( 'core/block-editor' )
				.getBlocks()
				.map( ( b: { name: string } ) => b.name ),
			format: select( 'core/rich-text' ).getFormatType(
				'pediment/accent'
			),
		};
	} );

	// The shared editor script registers the format. While the hero block
	// exists, its editor script loads too, so check the shared one is there.
	await expect(
		page.locator( 'script#pediment-editor-formats-js' )
	).toHaveCount( 1 );
	expect( result.names ).toEqual( [ 'core/paragraph' ] );
	expect( result.format ).toBeDefined();
	expect( result.format.tagName ).toBe( 'span' );
	expect( result.format.className ).toBe( 'accent' );
} );
