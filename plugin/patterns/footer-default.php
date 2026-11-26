<?php
/**
 * Title: Footer
 * Slug: pediment/footer-default
 * Categories: pediment
 * Description: Pediment's default site footer: logo, tagline, social links, the primary menu, the site title and links to the Impressum and privacy policy. Seeds the footer template part when the theme has no footer pattern of its own.
 * Keywords: footer, impressum, privacy
 * Block Types: core/template-part/footer
 * Inserter: yes
 */
// phpcs:ignoreFile -- block pattern content
?>
<!-- wp:group {"className":"site-footer","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|40"}},"border":{"top":{"color":"var:preset|color|border","width":"1px"}}},"backgroundColor":"surface-elevated","layout":{"type":"constrained","contentSize":"1200px"}} -->
<div class="wp-block-group site-footer has-border-color has-surface-elevated-background-color has-background" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--40)"><!-- wp:columns {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|50"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%"><!-- wp:group {"className":"brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group brand"><!-- wp:site-logo {"width":150} /--></div>
<!-- /wp:group -->

<!-- wp:site-tagline {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}},"textColor":"foreground-muted","fontSize":"xs"} /-->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|30"}}},"layout":{"type":"flex"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--30)"><!-- wp:pediment/social-links /--></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:navigation {"overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"fontSize":"xs"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"},"padding":{"top":"var:preset|spacing|30"}}},"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--50);padding-top:var(--wp--preset--spacing--30)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"textColor":"foreground-muted","fontSize":"xs"} -->
<p class="has-foreground-muted-color has-text-color has-xs-font-size">©</p>
<!-- /wp:paragraph -->

<!-- wp:site-title {"level":0,"isLink":false,"style":{"typography":{"fontWeight":"400"}},"textColor":"foreground-muted","fontSize":"xs"} /--></div>
<!-- /wp:group -->

<!-- wp:pediment/legal-links {"textColor":"foreground-muted","fontSize":"xs"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
