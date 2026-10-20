import { test, expect } from '@playwright/test';
import { createPageWithContent, deletePageBySlug } from './utils';

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
