import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl } from '@wordpress/components';

// Child block is constrained by `allowedBlocks` in block.json.
const TEMPLATE: Array< [ string, Record< string, unknown > ] > = [
	[
		'pediment/form-field',
		{ label: 'Name', fieldName: 'name', required: true },
	],
	[
		'pediment/form-field',
		{
			fieldType: 'email',
			label: 'Email',
			fieldName: 'email',
			required: true,
		},
	],
	[
		'pediment/form-field',
		{ fieldType: 'textarea', label: 'Message', fieldName: 'message' },
	],
];

type Attrs = {
	destination: string;
	successMessage: string;
	submitLabel: string;
	style?: { spacing?: { blockGap?: string } };
};

// Mirror render.php: resolve a blockGap value (raw or `var:preset|spacing|N`) to
// a CSS value so the editor preview matches the front end.
function gapToCSS( value?: string ): string | undefined {
	if ( ! value ) {
		return undefined;
	}
	const preset = /^var:preset\|spacing\|(.+)$/.exec( value );
	return preset ? `var(--wp--preset--spacing--${ preset[ 1 ] })` : value;
}

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const gap = gapToCSS( attributes.style?.spacing?.blockGap );
	const blockProps = useBlockProps( {
		className: 'pediment-form',
		style: gap
			? ( { '--pediment-form-gap': gap } as React.CSSProperties )
			: undefined,
	} );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form settings', 'pediment' ) }>
					<TextControl
						label={ __( 'Destination id', 'pediment' ) }
						help={ __(
							'Configured in Settings → Forms. Leave empty for the default.',
							'pediment'
						) }
						value={ attributes.destination }
						onChange={ ( v ) =>
							setAttributes( { destination: v } )
						}
					/>
					<TextControl
						label={ __( 'Submit button label', 'pediment' ) }
						value={ attributes.submitLabel }
						onChange={ ( v ) =>
							setAttributes( { submitLabel: v } )
						}
					/>
					<TextareaControl
						label={ __( 'Success message', 'pediment' ) }
						value={ attributes.successMessage }
						onChange={ ( v ) =>
							setAttributes( { successMessage: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<form { ...blockProps } onSubmit={ ( e ) => e.preventDefault() }>
				<InnerBlocks template={ TEMPLATE } />
				<button type="button" className="pediment-form__submit">
					{ attributes.submitLabel || __( 'Send', 'pediment' ) }
				</button>
			</form>
		</>
	);
}
