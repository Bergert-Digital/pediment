import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import './style.scss';

// Dynamic block (rendered by render.php), but it holds inner form-field blocks.
// `save` must emit InnerBlocks.Content so those children are serialized into the
// post; without it the editor drops every field on save and render.php receives
// an empty form.
registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );
