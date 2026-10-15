<?php
/**
 * Title: Contact page
 * Slug: pediment/contact-page
 * Categories: pediment
 * Description: Centered contact hero + form with name, email, phone, and message.
 * Keywords: contact, form, get in touch
 * Viewport Width: 1200
 */
// phpcs:ignoreFile -- block pattern content
?>
<!-- wp:group {"tagName":"section","layout":{"type":"constrained"},"metadata":{"name":"Hero"}} -->
<section class="wp-block-group"><!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">Contact</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","className":"is-style-lead"} -->
<p class="has-text-align-center is-style-lead">Tell us about your project. We respond within one business day.</p>
<!-- /wp:paragraph --></section>
<!-- /wp:group -->

<!-- wp:group {"className":"is-style-prose","layout":{"type":"constrained"},"metadata":{"name":"Prose"}} -->
<div class="wp-block-group is-style-prose"><!-- wp:paragraph --><p>Prefer email? Reach us at <a href="mailto:hello@example.com">hello@example.com</a>.</p><!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:pediment/form {"successMessage":"Thanks — we'll be in touch shortly."} -->
<!-- wp:pediment/form-field {"label":"Name","fieldName":"name","required":true} /-->
<!-- wp:pediment/form-field {"fieldType":"email","label":"Email","fieldName":"email","required":true} /-->
<!-- wp:pediment/form-field {"fieldType":"tel","label":"Phone","fieldName":"phone"} /-->
<!-- wp:pediment/form-field {"fieldType":"textarea","label":"Message","fieldName":"message","required":true} /-->
<!-- /wp:pediment/form -->
