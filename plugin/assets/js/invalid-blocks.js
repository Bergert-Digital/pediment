/**
 * Reports blocks the editor could not validate on this page (or post of a
 * type the MCP tools serve, listed in window.pedimentInvalidBlocks) to
 * POST /pediment/v1/invalid-blocks/<id>, once per editor load, so AI agents
 * see them through the pediment/check-page ability. An empty list is sent
 * too: it replaces an earlier report once the page is fixed.
 *
 * @param {Object} wp The WordPress globals (window.wp).
 */
( function ( wp ) {
	if ( ! wp || ! wp.data || ! wp.apiFetch ) {
		return;
	}
	const types = ( window.pedimentInvalidBlocks || {} ).postTypes || [
		'page',
	];
	let done = false;
	const unsubscribe = wp.data.subscribe( function () {
		if ( done ) {
			return;
		}
		const editor = wp.data.select( 'core/editor' );
		if (
			! editor ||
			! editor.__unstableIsEditorReady ||
			! editor.__unstableIsEditorReady()
		) {
			return;
		}
		const id = editor.getCurrentPostId();
		if ( ! id || -1 === types.indexOf( editor.getCurrentPostType() ) ) {
			done = true;
			unsubscribe();
			return;
		}
		// The ready flag turns true before the editor parses the content, so
		// wait for blocks unless the page has no content at all.
		const blocks = wp.data.select( 'core/block-editor' ).getBlocks();
		const post = editor.getCurrentPost();
		const empty = post && post.content && '' === post.content.raw;
		if ( ! blocks.length && ! empty ) {
			return;
		}
		done = true;
		unsubscribe();
		const invalid = [];
		( function walk( list ) {
			list.forEach( function ( block ) {
				if ( false === block.isValid ) {
					invalid.push( block.name );
				}
				walk( block.innerBlocks || [] );
			} );
		} )( blocks );
		wp.apiFetch( {
			path: '/pediment/v1/invalid-blocks/' + id,
			method: 'POST',
			data: { blocks: invalid },
		} ).catch( function () {} );
	} );
} )( window.wp );
