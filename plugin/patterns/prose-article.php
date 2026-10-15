<?php
/**
 * Title: Prose article
 * Slug: pediment/prose-article
 * Categories: pediment
 * Description: Article-style long-form layout: heading, paragraph, subheading.
 * Keywords: article, prose, blog post, long form, writing
 * Viewport Width: 720
 */
// phpcs:ignoreFile -- block pattern content
?>
<!-- wp:group {"className":"is-style-prose","layout":{"type":"constrained"},"metadata":{"name":"Prose"}} -->
<div class="wp-block-group is-style-prose"><!-- wp:heading --><h2 class="wp-block-heading">Section heading</h2><!-- /wp:heading -->

<!-- wp:paragraph --><p>Opening paragraph that frames the piece. One or two sentences that tell the reader why this matters and what they'll learn.</p><!-- /wp:paragraph -->

<!-- wp:paragraph --><p>Continue the thread. Concrete examples beat abstractions. Cite numbers when you have them.</p><!-- /wp:paragraph -->

<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">A subsection</h3><!-- /wp:heading -->

<!-- wp:paragraph --><p>Detail. Keep paragraphs short on mobile readers.</p><!-- /wp:paragraph -->

<!-- wp:list --><ul class="wp-block-list">
        <!-- wp:list-item --><li>One point.</li><!-- /wp:list-item -->
        <!-- wp:list-item --><li>Another point.</li><!-- /wp:list-item -->
        <!-- wp:list-item --><li>One more.</li><!-- /wp:list-item -->
    </ul><!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:quote {"className":"is-style-pull-quote"} -->
<blockquote class="wp-block-quote is-style-pull-quote"><!-- wp:paragraph -->
<p>A memorable line from the piece — short, specific, and quotable.</p>
<!-- /wp:paragraph --><cite>Author</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:group {"className":"is-style-prose","layout":{"type":"constrained"},"metadata":{"name":"Prose"}} -->
<div class="wp-block-group is-style-prose"><!-- wp:paragraph --><p>Closing thought. Reaffirm the why, and point the reader at the next step.</p><!-- /wp:paragraph --></div>
<!-- /wp:group -->
