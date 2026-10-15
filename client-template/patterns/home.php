<?php
/**
 * Title: Home
 * Slug: __PEDIMENT_SLUG__/home
 * Categories: pediment
 * Description: Landing page for __PEDIMENT_NAME__.
 * Inserter: no
 */
// phpcs:ignoreFile -- block pattern content
?>
<!-- wp:group {"tagName":"section","layout":{"type":"constrained"},"metadata":{"name":"Hero"}} -->
<section class="wp-block-group"><!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">__PEDIMENT_NAME__</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","className":"is-style-lead"} -->
<p class="has-text-align-center is-style-lead">__PEDIMENT_DESCRIPTION__</p>
<!-- /wp:paragraph --></section>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"},"metadata":{"name":"Section head"}} -->
<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--60)"><!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">What we do</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-prose","layout":{"type":"constrained"},"metadata":{"name":"Prose"}} -->
<div class="wp-block-group is-style-prose"><!-- wp:paragraph --><p>Replace this with a short description of the service, in the client's own words. Build the rest of the page in the editor, then run <code>npm run adopt -- home</code> to bring it back into this file.</p><!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"is-style-panel","layout":{"type":"constrained"},"metadata":{"name":"Call to action"}} -->
<section class="wp-block-group is-style-panel"><!-- wp:heading -->
<h2 class="wp-block-heading">Get in touch</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Tell us what you need and we will come back to you within one working day.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-pill"} -->
<div class="wp-block-button is-style-pill"><a class="wp-block-button__link wp-element-button" href="/contact/">Contact us</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></section>
<!-- /wp:group -->
