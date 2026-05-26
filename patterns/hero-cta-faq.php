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
<!-- wp:pediment/hero {"variant":"centered","headline":"Welcome to <?php echo esc_html( get_bloginfo( 'name' ) ); ?>","subheadline":"A short, benefit-led promise.","ctaText":"Get in touch","ctaUrl":"/contact"} /-->

<!-- wp:pediment/cta {"title":"Ready to start?","body":"Tell us about your project. We respond within one business day.","primaryText":"Contact us","primaryUrl":"/contact","secondaryText":"Learn more","secondaryUrl":"/about"} /-->

<!-- wp:pediment/faq -->
    <!-- wp:pediment/faq-item {"question":"How long does a typical project take?","answer":"Most engagements run 4–8 weeks."} /-->
    <!-- wp:pediment/faq-item {"question":"Do you work with my industry?","answer":"We've shipped for SaaS, e-commerce, and editorial clients."} /-->
    <!-- wp:pediment/faq-item {"question":"What's your pricing model?","answer":"Fixed-scope sprints or monthly retainer — we'll recommend the better fit."} /-->
<!-- /wp:pediment/faq -->
