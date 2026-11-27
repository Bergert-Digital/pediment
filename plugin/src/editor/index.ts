/**
 * Shared block-editor script. It loads for every block, so editors can use
 * these formats on core headings and paragraphs, not only on Pediment blocks.
 * It also adds the SEO title panel to the document settings sidebar, and
 * shows the selected panel of a panel set in the canvas (panel-set.tsx).
 * Built to build/editor/ and enqueued by inc/assets.php.
 */
import './accent-format';
import './seo-title';
import './panel-set';
