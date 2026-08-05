import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		return (
			<p { ...useBlockProps() }>
				<RichText
					tagName="span"
					value={ attributes.message }
					onChange={ ( message ) => setAttributes( { message } ) }
					placeholder="Notice text…"
				/>
			</p>
		);
	},
	save() {
		return null;
	},
} );
