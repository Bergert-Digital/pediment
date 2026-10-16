import { test, expect } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

const SLUG = 'icon-block-e2e';

const CONTENT = [
	'<!-- wp:group {"className":"is-style-card","layout":{"type":"constrained"},"metadata":{"name":"Card"}} -->',
	'<div class="wp-block-group is-style-card"><!-- wp:pediment/icon {"icon":"key","className":"is-style-tile"} /-->',
	'',
	'<!-- wp:pediment/icon {"icon":"cube","label":"Blocks"} /--></div>',
	'<!-- /wp:group -->',
].join( '\n' );

test.describe( 'pediment/icon', () => {
	test.afterEach( () => {
		deletePageBySlug( SLUG );
	} );

	test( 'renders the tile centred in a card and names a labelled icon', async ( {
		page,
	} ) => {
		deletePageBySlug( SLUG );
		const url = createPageWithContent( SLUG, 'Icon block E2E', CONTENT );
		await page.goto( url );

		const card = page.locator( '.wp-block-group.is-style-card' ).first();
		const tile = card.locator(
			'.wp-block-pediment-icon.is-style-tile > .wp-block-pediment-icon__glyph'
		);
		await expect( tile ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( tile.locator( 'svg[data-icon="key"]' ) ).toBeVisible();
		const tileBox = await tile.boundingBox();
		const cardBox = await card.boundingBox();
		expect( Math.round( tileBox!.width ) ).toBe( 46 );
		expect( Math.round( tileBox!.height ) ).toBe( 46 );
		expect(
			Math.abs(
				tileBox!.x +
					tileBox!.width / 2 -
					( cardBox!.x + cardBox!.width / 2 )
			)
		).toBeLessThan( 2 );

		await expect(
			page.getByRole( 'img', { name: 'Blocks' } )
		).toBeVisible();
	} );

	test( 'is offered in the inserter and previews in the editor', async ( {
		page,
	} ) => {
		deletePageBySlug( SLUG );
		const url = createPageWithContent( SLUG, 'Icon block E2E', CONTENT );
		const id = url.replace( /[^0-9]/g, '' );
		await login( page );
		await page.goto( `/wp-admin/post.php?post=${ id }&action=edit` );
		await page.waitForFunction(
			() =>
				!! ( window as any ).wp?.blocks?.getBlockType?.(
					'pediment/icon'
				)
		);
		const inserter = await page.evaluate(
			() =>
				( window as any ).wp.blocks.getBlockType( 'pediment/icon' )
					.supports?.inserter
		);
		expect( inserter ).not.toBe( false );

		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await expect(
			canvas.locator( '.wp-block-pediment-icon svg[data-icon="key"]' )
		).toBeVisible( { timeout: 20000 } );

		await page.evaluate( () => {
			const wp = ( window as any ).wp;
			wp.data
				.dispatch( 'core/block-editor' )
				.insertBlocks(
					wp.blocks.createBlock( 'pediment/icon', { icon: 'car' } )
				);
		} );
		await expect(
			canvas.locator( '.wp-block-pediment-icon svg[data-icon="car"]' )
		).toBeVisible();
		const invalid = await page.evaluate(
			() =>
				( window as any ).wp.data
					.select( 'core/block-editor' )
					.getBlocks()
					.flatMap( ( b: any ) => [ b, ...b.innerBlocks ] )
					.filter( ( b: any ) => b.isValid === false ).length
		);
		expect( invalid ).toBe( 0 );
	} );
} );
