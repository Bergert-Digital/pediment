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
<!-- wp:pediment/hero {"variant":"centered","headline":"Contact","subheadline":"Tell us about your project. We respond within one business day."} /-->

<!-- wp:pediment/prose -->
    <!-- wp:paragraph --><p>Prefer email? Reach us at <a href="mailto:hello@example.com">hello@example.com</a>.</p><!-- /wp:paragraph -->
<!-- /wp:pediment/prose -->

<!-- wp:pediment/form {"successMessage":"Thanks — we'll be in touch shortly."} -->
<!-- wp:pediment/form-field {"label":"Name","fieldName":"name","required":true} /-->
<!-- wp:pediment/form-field {"fieldType":"email","label":"Email","fieldName":"email","required":true} /-->
<!-- wp:pediment/form-field {"fieldType":"tel","label":"Phone","fieldName":"phone"} /-->
<!-- wp:pediment/form-field {"fieldType":"textarea","label":"Message","fieldName":"message","required":true} /-->
<!-- /wp:pediment/form -->
