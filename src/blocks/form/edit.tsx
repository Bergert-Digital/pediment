import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

type Destination = { id: string; label: string };

// Destinations are injected by the plugin's inc/forms-destinations.php via an
// inline script on `enqueue_block_editor_assets`.
declare global {
	interface Window {
		pedimentFormDestinations?: Destination[];
	}
}

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
	const destinations = window.pedimentFormDestinations ?? [];
	const destinationOptions = [
		{ label: __( 'Save to submissions only', 'pediment' ), value: '' },
		...destinations.map( ( d ) => ( { label: d.label, value: d.id } ) ),
	];
	// Preserve a saved id that no longer matches a configured destination so it
	// is not silently dropped to the default.
	if (
		attributes.destination &&
		! destinations.some( ( d ) => d.id === attributes.destination )
	) {
		destinationOptions.push( {
			label: sprintf(
				/* translators: %s: destination id */
				__( 'Unknown (%s)', 'pediment' ),
				attributes.destination
			),
			value: attributes.destination,
		} );
	}
	const gap = gapToCSS( attributes.style?.spacing?.blockGap );
	const blockProps = useBlockProps( {
		className: 'pediment-form',
		style: gap
			? ( { '--pediment-form-gap': gap } as React.CSSProperties )
			: undefined,
	} );
	// Spread inner blocks directly onto the <form> (like the theme's other
	// container blocks) so each field is a direct grid child and the block gap
	// spaces the fields — not just the trailing button. A bare <InnerBlocks />
	// nests them in a wrapper the grid gap can't reach.
	const { children, ...innerBlocksProps } = useInnerBlocksProps( blockProps, {
		template: TEMPLATE,
	} );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form settings', 'pediment' ) }>
					<SelectControl
						label={ __( 'Destination', 'pediment' ) }
						help={ __(
							'Submissions are always saved. Pick a destination to also forward them (configured in Settings → Pediment Theme → Forms).',
							'pediment'
						) }
						value={ attributes.destination }
						options={ destinationOptions }
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
			<form
				{ ...innerBlocksProps }
				onSubmit={ ( e ) => e.preventDefault() }
			>
				{ children }
				<button type="button" className="pediment-form__submit">
					{ attributes.submitLabel || __( 'Send', 'pediment' ) }
				</button>
			</form>
		</>
	);
}
