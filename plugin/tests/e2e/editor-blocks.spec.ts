import { test, expect } from '@playwright/test';
import { createPageWithContent, deletePageBySlug, login } from './utils';

const BLOCKS_TO_VERIFY: Array< { name: string; cls: string; markup: string } > =
	[
		{
			name: 'blog-index',
			cls: 'starter-blog-index',
			markup: '<!-- wp:pediment/blog-index {"count":3} /-->',
		},
		{
			name: 'slider',
			cls: 'starter-slider',
			markup: '<!-- wp:pediment/slider {"slides":[{"heading":"x"}]} /-->',
		},
	];

const SLUG = 'e2e-block-kitchen-sink';

test( 'every pediment block renders on the front end', async ( { page } ) => {
	deletePageBySlug( SLUG );
	const content = BLOCKS_TO_VERIFY.map( ( b ) => b.markup ).join( '\n\n' );
	const url = createPageWithContent( SLUG, 'Block kitchen sink', content );

	await page.goto( url );

	for ( const { cls } of BLOCKS_TO_VERIFY ) {
		await expect( page.locator( `.${ cls }` ).first() ).toBeVisible();
	}

	deletePageBySlug( SLUG );
} );

// Pediment 5 removed the 18 legacy blocks; the editor must not register them.
test( 'a legacy block is not registered', async ( { page } ) => {
	await login( page );
	await page.goto( '/wp-admin/post-new.php?post_type=page' );
	// Wait on a block that stays registered, never on the removed one.
	await page.waitForFunction(
		() => !! ( window as any ).wp?.blocks?.getBlockType?.( 'pediment/form' )
	);
	const unregistered = await page.evaluate( () =>
		[ 'pediment/hero', 'pediment/faq', 'pediment/prose' ].map(
			( name ) =>
				( window as any ).wp.blocks.getBlockType( name ) === undefined
		)
	);
	expect( unregistered ).toEqual( [ true, true, true ] );
} );
