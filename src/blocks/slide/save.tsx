import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Persist the panel inner blocks. The block is server-rendered (render.php),
 * which wraps the saved content beside the figure. useInnerBlocksProps sits on a
 * nested element, so without an explicit save the editor would serialize a
 * self-closing block and the panel content would vanish on the front end.
 */
export default function save() {
	return <InnerBlocks.Content />;
}
