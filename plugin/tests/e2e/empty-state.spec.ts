import { test, expect } from '@playwright/test';
import { createPageWithContent, deletePageBySlug, login } from './utils';

// Every Pediment block with no attributes at all, the way a person inserts it.
// The PHPUnit EmptyStateTest checks the markup; this checks that the editor
// accepts each block and that a visitor sees no PHP notice. Form fields sit
// in a form and the mega menu in a navigation, where the editor allows them.
const EMPTY_BLOCKS = [
	'<!-- wp:pediment/blog-index /-->',
	'<!-- wp:pediment/form --><!-- wp:pediment/form-field /--><!-- /wp:pediment/form -->',
	'<!-- wp:pediment/icon /-->',
	'<!-- wp:navigation --><!-- wp:pediment/mega-menu /--><!-- /wp:navigation -->',
	'<!-- wp:pediment/slider /-->',
	'<!-- wp:pediment/social-links /-->',
].join( '\n\n' );

const SLUG = 'e2e-empty-state';

test.describe( 'empty blocks', () => {
	let url = '';

	test.beforeAll( () => {
		deletePageBySlug( SLUG );
		url = createPageWithContent( SLUG, 'Empty state', EMPTY_BLOCKS );
	} );

	test.afterAll( () => deletePageBySlug( SLUG ) );

	test( 'a visitor sees no PHP notice and no empty link', async ( {
		page,
	} ) => {
		const response = await page.goto( url );
		expect( response?.status() ).toBe( 200 );

		const html = await page.content();
		expect( html ).not.toMatch(
			/<b>(Warning|Notice|Deprecated|Fatal error)<\/b>:/
		);
		expect( html ).not.toContain( 'There has been a critical error' );
		await expect( page.locator( 'main a[href=""]' ) ).toHaveCount( 0 );
	} );

	test( 'the editor accepts every block without a warning', async ( {
		page,
	} ) => {
		const errors: string[] = [];
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );

		await login( page );
		const postId = new URL( url, 'http://localhost' ).searchParams.get(
			'page_id'
		);
		await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
		await page.waitForFunction(
			() =>
				( window as any ).wp?.data
					?.select( 'core/block-editor' )
					?.getBlocks().length > 0,
			null,
			{ timeout: 30_000 }
		);

		const names = await page.evaluate( () => {
			const select = ( window as any ).wp.data.select( 'core/block-editor' );
			const all: string[] = [];
			const walk = ( blocks: any[] ) =>
				blocks.forEach( ( block ) => {
					all.push( block.name );
					walk( block.innerBlocks );
				} );
			walk( select.getBlocks() );
			return all;
		} );
		expect( names ).toEqual(
			expect.arrayContaining( [
				'pediment/blog-index',
				'pediment/form',
				'pediment/form-field',
				'pediment/icon',
				'pediment/mega-menu',
				'pediment/slider',
				'pediment/social-links',
			] )
		);

		const invalid = await page.evaluate( () => {
			const select = ( window as any ).wp.data.select( 'core/block-editor' );
			const found: string[] = [];
			const walk = ( blocks: any[] ) =>
				blocks.forEach( ( block ) => {
					if ( ! block.isValid ) {
						found.push( block.name );
					}
					walk( block.innerBlocks );
				} );
			walk( select.getBlocks() );
			return found;
		} );
		expect( invalid ).toEqual( [] );

		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect(
			canvas.locator( '.wp-block-pediment-slider, .starter-slider' ).first()
		).toBeVisible();
		// A block whose edit() throws is replaced by this warning.
		await expect(
			canvas.getByText( 'This block has encountered an error' )
		).toHaveCount( 0 );
		expect( errors ).toEqual( [] );
	} );
} );
