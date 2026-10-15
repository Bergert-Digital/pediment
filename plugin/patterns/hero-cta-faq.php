<?php
/**
 * Title: Hero + CTA + FAQ
 * Slug: pediment/hero-cta-faq
 * Categories: pediment
 * Description: Minimal landing composition: centered hero, CTA band, and three-item FAQ.
 * Keywords: hero, cta, faq, landing, simple page
 * Viewport Width: 1200
 */
// phpcs:ignoreFile -- block pattern content
?>
<!-- wp:group {"tagName":"section","layout":{"type":"constrained"},"metadata":{"name":"Hero"}} -->
<section class="wp-block-group"><!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">Welcome to <?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","className":"is-style-lead"} -->
<p class="has-text-align-center is-style-lead">A short, benefit-led promise.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-pill"} -->
<div class="wp-block-button is-style-pill"><a class="wp-block-button__link wp-element-button" href="/contact">Get in touch</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","className":"is-style-panel","layout":{"type":"constrained"},"metadata":{"name":"Call to action"}} -->
<section class="wp-block-group is-style-panel"><!-- wp:heading -->
<h2 class="wp-block-heading">Ready to start?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Tell us about your project. We respond within one business day.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-pill"} -->
<div class="wp-block-button is-style-pill"><a class="wp-block-button__link wp-element-button" href="/contact">Contact us</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-pill-ghost"} -->
<div class="wp-block-button is-style-pill-ghost"><a class="wp-block-button__link wp-element-button" href="/about">Learn more</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"},"metadata":{"name":"FAQ"}} -->
<section class="wp-block-group"><!-- wp:details {"className":"is-style-accordion"} -->
<details class="wp-block-details is-style-accordion"><summary>How long does a typical project take?</summary><!-- wp:paragraph -->
<p>Most engagements run 4–8 weeks.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"is-style-accordion"} -->
<details class="wp-block-details is-style-accordion"><summary>Do you work with my industry?</summary><!-- wp:paragraph -->
<p>We've shipped for SaaS, e-commerce, and editorial clients.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details {"className":"is-style-accordion"} -->
<details class="wp-block-details is-style-accordion"><summary>What's your pricing model?</summary><!-- wp:paragraph -->
<p>Fixed-scope sprints or monthly retainer — we'll recommend the better fit.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details --></section>
<!-- /wp:group -->
