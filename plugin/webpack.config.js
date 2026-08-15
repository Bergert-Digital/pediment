/**
 * Extends @wordpress/scripts' default webpack config for one reason: stop the
 * editor build from wiping the blocks build.
 *
 * There are two wp-scripts compilations here:
 *   - `build:editor` / `start`  → editor bundle, output dir `build/`
 *   - `build:blocks`            → per-block bundles, output dir `build/blocks/`
 *                                 (+ `build/blocks-manifest.php`)
 *
 * The editor build enables webpack `output.clean` (keep: fonts|images), so on
 * every compile it deletes everything under `build/` except those — including
 * `build/blocks/` and `build/blocks-manifest.php`. In a one-shot `npm run build`
 * that is harmless because build:editor runs first, then build:blocks repopulates
 * it. But the `start` editor watch runs alone and clobbers the blocks output on
 * its initial compile (and every recompile), which unregisters every Pediment
 * block in the editor. Widen the keep list so the editor build preserves the
 * blocks output that lives alongside it.
 *
 * The blocks build sets WP_EXPERIMENTAL_MODULES, which disables `output.clean`
 * entirely, so it has no `clean` to patch — we leave those configs untouched.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const preserveBlocksOutput = ( config ) => {
	if ( ! config.output || ! config.output.clean || ! config.output.clean.keep ) {
		return config;
	}
	return {
		...config,
		output: {
			...config.output,
			// `keep` is matched against paths relative to the output dir. The
			// `blocks` prefix covers both `blocks/…` and `blocks-manifest.php`.
			clean: { ...config.output.clean, keep: /^(fonts|images|blocks)/ },
		},
	};
};

module.exports = Array.isArray( defaultConfig )
	? defaultConfig.map( preserveBlocksOutput )
	: preserveBlocksOutput( defaultConfig );
