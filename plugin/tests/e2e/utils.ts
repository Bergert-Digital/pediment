import { type FrameLocator, type Page } from '@playwright/test';
import { execSync } from 'node:child_process';

const WP_ENV_CWD = process.env.WP_ENV_CWD || process.cwd();

/**
 * Returns a locator scope for the editor canvas — the iframe in WP 6.5+ block themes,
 * or the page itself in classic / non-iframed setups.
 */
export async function canvas( page: Page ): Promise< FrameLocator | Page > {
	return ( await page.locator( 'iframe[name="editor-canvas"]' ).count() )
		? page.frameLocator( 'iframe[name="editor-canvas"]' )
		: page;
}

export async function login( page: Page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( 'input#user_login', 'admin' );
	await page.fill( 'input#user_pass', 'password' );
	await page.click( 'input#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

export async function openNewPage( page: Page, title: string ) {
	// Use a persisted page with a valid block tree so editor flows have a stable
	// post ID and do not depend on WordPress's auto-draft lifecycle.
	const slug = `e2e-${ Date.now() }`;
	const frontUrl = createPageWithContent(
		slug,
		title,
		'<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->'
	);
	const postId = new URL( frontUrl, 'http://localhost' ).searchParams.get(
		'page_id'
	);
	await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
	// Give the editor a beat to mount its iframe before we look for the canvas.
	await page
		.locator( 'iframe[name="editor-canvas"], .editor-post-title__input' )
		.first()
		.waitFor( { timeout: 20_000 } );
	// The "Welcome to the editor" guide mounts asynchronously and its modal overlay
	// intercepts pointer events. Disable it via the preferences store (reactive —
	// closes it if already open) rather than racing to click its Close button.
	await disableWelcomeGuide( page );
	// WP 6.9 + pattern-providing themes open a "Choose a pattern" dialog *after* the editor mounts.
	await dismissEditorOverlays( page );
	const scope = await canvas( page );
	const titleField = scope
		.locator( '.editor-post-title__input, [aria-label*="Add title" i]' )
		.first();
	await titleField.waitFor( { state: 'visible', timeout: 20_000 } );
	await titleField.fill( title );
}

/**
 * Turns off the editor welcome guide via the preferences store. This is reactive,
 * so it dismisses the guide whether it has already mounted or is about to. Covers
 * both the modern `core/preferences` store and the legacy edit-post feature flag.
 */
async function disableWelcomeGuide( page: Page ) {
	await page
		.waitForFunction(
			() =>
				!! ( window as any ).wp?.data?.dispatch?.( 'core/preferences' ),
			null,
			{ timeout: 20_000 }
		)
		.catch( () => {} );
	await page.evaluate( () => {
		const wp = ( window as any ).wp;
		wp?.data
			?.dispatch?.( 'core/preferences' )
			?.set?.( 'core', 'welcomeGuide', false );
		const editPost = wp?.data?.select?.( 'core/edit-post' );
		if ( editPost?.isFeatureActive?.( 'welcomeGuide' ) ) {
			wp.data
				.dispatch( 'core/edit-post' )
				.toggleFeature( 'welcomeGuide' );
		}
	} );
}

/**
 * Dismisses any modal/dialog that the post editor opens on a fresh page.
 * Handles WP's welcome guide, fullscreen prompt, and (WP 6.9+) the
 * "Choose a pattern" picker that appears when the active theme provides patterns.
 */
async function dismissEditorOverlays( page: Page ) {
	const patterns = [
		/^Choose a pattern$/i,
		/^Welcome to/i,
		/Welcome to the block editor/i,
	];
	for ( const name of patterns ) {
		const dialog = page.getByRole( 'dialog', { name } );
		if (
			await dialog
				.first()
				.isVisible()
				.catch( () => false )
		) {
			await dialog
				.first()
				.getByRole( 'button', { name: /^close/i } )
				.click( { timeout: 2_000 } )
				.catch( () => {} );
		}
	}
	// Generic fallback: any visible "Close dialog" button (covers older WP modal labels).
	const closeDialog = page.getByRole( 'button', { name: /^close dialog$/i } );
	if (
		await closeDialog
			.first()
			.isVisible()
			.catch( () => false )
	) {
		await closeDialog
			.first()
			.click( { timeout: 2_000 } )
			.catch( () => {} );
	}
}

/**
 * Opens the editor's general sidebar to a specific tab via the WordPress data API.
 * More reliable than clicking around UI affordances that move between WP versions.
 *   tab = 'edit-post/document' | 'edit-post/block'
 */
async function openSidebarTab(
	page: Page,
	tab: 'edit-post/document' | 'edit-post/block'
) {
	await page.evaluate( ( target ) => {
		const wp = ( window as any ).wp;
		const dispatch =
			wp?.data?.dispatch?.( 'core/edit-post' ) ??
			wp?.data?.dispatch?.( 'core/editor' );
		dispatch?.openGeneralSidebar?.( target );
	}, tab );
}

/**
 * Opens the Document sidebar, ensures the "Pediment" PluginDocumentSettingPanel is expanded,
 * and returns the chat panel locator (`.pediment-chat`) for further interactions.
 */
export async function openAIChatPanel( page: Page ) {
	await openSidebarTab( page, 'edit-post/document' );
	const toggle = page.getByRole( 'button', { name: /^Pediment$/i } ).first();
	await toggle.waitFor( { state: 'visible', timeout: 10_000 } );
	if ( ( await toggle.getAttribute( 'aria-expanded' ) ) === 'false' ) {
		await toggle.click();
	}
	const panel = page.locator( '.pediment-chat:visible' ).first();
	await panel.waitFor( { state: 'visible', timeout: 10_000 } );
	return panel;
}

/**
 * Variant for the block-inspector Pediment PanelBody (rendered by BlockChatPanel.tsx
 * when a block is selected). Use this after selecting a block in the canvas.
 */
export async function openBlockAIChatPanel( page: Page ) {
	await openSidebarTab( page, 'edit-post/block' );
	const toggle = page.getByRole( 'button', { name: /^Pediment$/i } ).first();
	await toggle.waitFor( { state: 'visible', timeout: 10_000 } );
	if ( ( await toggle.getAttribute( 'aria-expanded' ) ) === 'false' ) {
		await toggle.click();
	}
	const panel = page.locator( '.pediment-chat:visible' ).first();
	await panel.waitFor( { state: 'visible', timeout: 10_000 } );
	return panel;
}

/** Create a page through wp-cli and return its front-end URL. */
export function createPageWithContent(
	slug: string,
	title: string,
	content: string
): string {
	const escapedContent = content.replace( /'/g, "'\\''" );
	const escapedTitle = title.replace( /'/g, "'\\''" );
	execSync(
		`npx wp-env run cli wp post create --post_type=page --post_status=publish --post_title='${ escapedTitle }' --post_name='${ slug }' --post_content='${ escapedContent }'`,
		{ stdio: 'ignore', cwd: WP_ENV_CWD }
	);
	const out = execSync(
		`bash -c "npx wp-env run cli wp post list --post_type=page --name='${ slug }' --field=ID --format=ids 2>/dev/null | grep -E '^[0-9]+$' | head -n 1"`,
		{ encoding: 'utf8', cwd: WP_ENV_CWD }
	);
	const id = parseInt( out.trim(), 10 );
	if ( ! id ) {
		throw new Error(
			`Failed to create/resolve page for slug '${ slug }'; output: ${ out }`
		);
	}
	return `/?page_id=${ id }`;
}

export function deletePageBySlug( slug: string ): void {
	try {
		execSync(
			`bash -c "ids=\\$(npx wp-env run cli wp post list --post_type=page --name=${ slug } --format=ids 2>/dev/null | tail -n 1); [ -n \\\"\\$ids\\\" ] && npx wp-env run cli wp post delete \\$ids --force >/dev/null 2>&1"`,
			{ stdio: 'ignore', cwd: WP_ENV_CWD }
		);
	} catch {
		// The page may not have been created because a preceding test failed.
	}
}

export function createNavigationEntityWithContent(
	slug: string,
	title: string,
	content: string
): number {
	const escapedContent = content.replace( /'/g, "'\\''" );
	const escapedTitle = title.replace( /'/g, "'\\''" );
	execSync(
		`npx wp-env run cli wp post create --post_type=wp_navigation --post_status=publish --post_title='${ escapedTitle }' --post_name='${ slug }' --post_content='${ escapedContent }'`,
		{ stdio: 'ignore', cwd: WP_ENV_CWD }
	);
	const out = execSync(
		`bash -c "npx wp-env run cli wp post list --post_type=wp_navigation --name='${ slug }' --field=ID --format=ids 2>/dev/null | grep -E '^[0-9]+$' | head -n 1"`,
		{ encoding: 'utf8', cwd: WP_ENV_CWD }
	);
	const id = parseInt( out.trim(), 10 );
	if ( ! id ) {
		throw new Error(
			`Failed to create/resolve wp_navigation '${ slug }'; output: ${ out }`
		);
	}
	return id;
}

export function deleteNavigationEntityById( id: number ): void {
	if ( ! id ) return;
	try {
		execSync(
			`npx wp-env run cli wp post delete ${ id } --force >/dev/null 2>&1`,
			{
				stdio: 'ignore',
				cwd: WP_ENV_CWD,
			}
		);
	} catch {
		// The navigation entity may already have been cleaned up.
	}
}
