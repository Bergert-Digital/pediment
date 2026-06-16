import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Persist only the inner blocks (the text column). The block is server-rendered
 * (render.php), which rebuilds the figure from mediaId and wraps the saved inner
 * content in the two-column layout. Without an explicit save the inner blocks are
 * NOT written to post_content — they live on the block root only for prose, but
 * here useInnerBlocksProps sits on a nested element, so the editor would serialize
 * a self-closing block and the text would vanish on the front end.
 */
export default function save() {
	return <InnerBlocks.Content />;
}
