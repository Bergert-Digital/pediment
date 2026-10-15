/**
 * Reports blocks the editor could not validate on this page to
 * POST /pediment/v1/invalid-blocks/<id>, once per editor load, so AI agents
 * see them through the pediment/check-page ability. An empty list is sent
 * too: it replaces an earlier report once the page is fixed.
 */
( function ( wp ) {
	if ( ! wp || ! wp.data || ! wp.apiFetch ) {
		return;
	}
	var done = false;
	var unsubscribe = wp.data.subscribe( function () {
		if ( done ) {
			return;
		}
		var editor = wp.data.select( 'core/editor' );
		if ( ! editor || ! editor.__unstableIsEditorReady || ! editor.__unstableIsEditorReady() ) {
			return;
		}
		var id = editor.getCurrentPostId();
		if ( ! id || 'page' !== editor.getCurrentPostType() ) {
			done = true;
			unsubscribe();
			return;
		}
		// The ready flag turns true before the editor parses the content, so
		// wait for blocks unless the page has no content at all.
		var blocks = wp.data.select( 'core/block-editor' ).getBlocks();
		var post = editor.getCurrentPost();
		var empty = post && post.content && '' === post.content.raw;
		if ( ! blocks.length && ! empty ) {
			return;
		}
		done = true;
		unsubscribe();
		var invalid = [];
		( function walk( blocks ) {
			blocks.forEach( function ( block ) {
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
