import { __ } from '@wordpress/i18n';
import { registerFormatType, toggleFormat } from '@wordpress/rich-text';
import { RichTextToolbarButton } from '@wordpress/block-editor';

type EditProps = {
	isActive: boolean;
	value: { start: number; end: number };
	onChange: ( value: unknown ) => void;
};

const FORMAT_NAME = 'starter/accent';

registerFormatType( FORMAT_NAME, {
	title: __( 'Accent', 'starter' ),
	tagName: 'span',
	className: 'accent',
	edit( { isActive, value, onChange }: EditProps ) {
		return (
			<RichTextToolbarButton
				icon="art"
				title={ __( 'Accent', 'starter' ) }
				onClick={ () =>
					onChange(
						toggleFormat( value as never, { type: FORMAT_NAME } )
					)
				}
				isActive={ isActive }
			/>
		);
	},
} );
