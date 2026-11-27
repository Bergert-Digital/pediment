import { test, expect } from '@playwright/test';
import { login, createPageWithContent, deletePageBySlug } from './utils';

/*
 * A panel set (`pediment-panels`) shows one panel at a time on the front end.
 * In the editor the shared editor script (src/editor/panel-set.tsx) mirrors
 * the selection into the set's `data-state`, so the panel a person selects,
 * or the panel of the tab they select, shows and its text can be edited.
 */
const SLUG = 'panel-set-e2e';

const p = ( text: string, className = '' ) =>
	className
		? `<!-- wp:paragraph {"className":"${ className }"} --><p class="${ className }">${ text }</p><!-- /wp:paragraph -->`
		: `<!-- wp:paragraph --><p>${ text }</p><!-- /wp:paragraph -->`;
const group = ( className: string, inner: string ) =>
	`<!-- wp:group {"className":"${ className }"} --><div class="wp-block-group ${ className }">${ inner }</div><!-- /wp:group -->`;

// The paragraph outside the set comes after it: the floating toolbar of a
// selected block sits above that block, outside the canvas iframe, and would
// catch a click on a paragraph just above the set.
const CONTENT =
	group(
		'pediment-panels',
		group(
			'e2e-tabs',
			p( 'Tab one', 'pediment-panel-tab' ) +
				p( 'Tab two', 'pediment-panel-tab' )
		) +
			group(
				'e2e-stack',
				group( 'pediment-panel', p( 'Voice one' ) ) +
					group( 'pediment-panel', p( 'Voice two' ) )
			)
	) + p( 'After the set.' );

// The kind of rule a client theme writes for its front end: one panel at a
// time, chosen by data-state, the first one while there is none.
const THEME_CSS = `
.pediment-panels .pediment-panel { display: none; }
.pediment-panels:not([data-state]) .pediment-panel:nth-child(1),
.pediment-panels[data-state="0"] .pediment-panel:nth-child(1),
.pediment-panels[data-state="1"] .pediment-panel:nth-child(2) { display: block; }
`;

test.afterEach( () => {
	deletePageBySlug( SLUG );
} );

test( 'the editor shows the panel whose block or tab is selected', async ( {
	page,
} ) => {
	deletePageBySlug( SLUG );
	const url = createPageWithContent( SLUG, 'Panel Set E2E', CONTENT );
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
	// The welcome guide's overlay would catch the clicks below.
	await page.evaluate( () =>
		( window as any ).wp.data
			.dispatch( 'core/preferences' )
			.set( 'core', 'welcomeGuide', false )
	);

	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	const set = canvas.locator( '.pediment-panels' );
	const voiceOne = canvas.getByText( 'Voice one', { exact: true } );
	const voiceTwo = canvas.getByText( 'Voice two', { exact: true } );
	await expect( set ).toBeVisible( { timeout: 20_000 } );
	await canvas.locator( 'body' ).evaluate( ( body, css ) => {
		const style = body.ownerDocument.createElement( 'style' );
		style.textContent = css;
		body.ownerDocument.head.appendChild( style );
	}, THEME_CSS );

	// Nothing selected: the theme's default panel.
	await expect( set ).not.toHaveAttribute( 'data-state' );
	await expect( voiceOne ).toBeVisible();
	await expect( voiceTwo ).toBeHidden();

	// Selecting tab 2 shows panel 2, so its text can be clicked and edited.
	await canvas.getByText( 'Tab two', { exact: true } ).click();
	await expect( set ).toHaveAttribute( 'data-state', '1' );
	await expect( voiceTwo ).toBeVisible();
	await expect( voiceOne ).toBeHidden();

	await voiceTwo.click();
	await page.keyboard.press( 'End' );
	await page.keyboard.type( ' edited' );
	await expect( set ).toHaveAttribute( 'data-state', '1' );
	await expect(
		canvas.getByText( 'Voice two edited', { exact: true } )
	).toBeVisible();

	// Selecting tab 1 switches back.
	await canvas.getByText( 'Tab one', { exact: true } ).click();
	await expect( set ).toHaveAttribute( 'data-state', '0' );
	await expect( voiceOne ).toBeVisible();

	// A selection outside the set clears the state.
	await canvas.getByText( 'After the set.', { exact: true } ).click();
	await expect( set ).not.toHaveAttribute( 'data-state' );

	// No selection at all clears it too. Tab 2 is selected through the store
	// first: a click could hit the toolbar of the paragraph below the set.
	const tabTwo = await canvas
		.getByText( 'Tab two', { exact: true } )
		.getAttribute( 'data-block' );
	await page.evaluate(
		( clientId ) =>
			( window as any ).wp.data
				.dispatch( 'core/block-editor' )
				.selectBlock( clientId ),
		tabTwo
	);
	await expect( set ).toHaveAttribute( 'data-state', '1' );
	await page.evaluate( () =>
		( window as any ).wp.data
			.dispatch( 'core/block-editor' )
			.clearSelectedBlock()
	);
	await expect( set ).not.toHaveAttribute( 'data-state' );
	await expect( voiceOne ).toBeVisible();

	// The attribute exists only in the editor; it is never saved.
	const content: string = await page.evaluate( () =>
		( window as any ).wp.data.select( 'core/editor' ).getEditedPostContent()
	);
	expect( content ).toContain( 'Voice two edited' );
	expect( content ).not.toContain( 'data-state' );
} );
