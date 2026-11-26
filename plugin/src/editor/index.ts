/**
 * Shared block-editor script. It loads for every block, so editors can use
 * these formats on core headings and paragraphs, not only on Pediment blocks.
 * It also adds the SEO title panel to the document settings sidebar.
 * Built to build/editor/ and enqueued by inc/assets.php.
 */
import './accent-format';
import './seo-title';
