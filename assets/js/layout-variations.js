/**
 * Default the Row and Grid variations of core/group to wide alignment.
 *
 * Row and Grid are not standalone blocks — they are variations of core/group.
 * We clone each variation, add `align: 'wide'` to its default attributes, then
 * re-register it so newly inserted Rows/Grids land at the theme's wide width
 * (1200px) instead of the 720px content width. Plain Group is untouched.
 */
( function ( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.domReady ) {
		return;
	}

	wp.domReady( function () {
		var variations = wp.blocks.getBlockVariations( 'core/group' ) || [];

		[ 'group-row', 'group-grid' ].forEach( function ( slug ) {
			var variation = variations.find( function ( v ) {
				return v.name === slug;
			} );
			if ( ! variation ) {
				return;
			}

			wp.blocks.unregisterBlockVariation( 'core/group', slug );
			wp.blocks.registerBlockVariation(
				'core/group',
				Object.assign( {}, variation, {
					attributes: Object.assign( {}, variation.attributes, {
						align: 'wide',
					} ),
				} )
			);
		} );
	} );
} )( window.wp );
