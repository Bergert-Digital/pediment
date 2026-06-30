import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	TextareaControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

type Option = { label: string; value: string };
type Attrs = {
	fieldType: string;
	label: string;
	fieldName: string;
	required: boolean;
	placeholder: string;
	helpText: string;
	options: Option[];
};

const TYPES = [
	{ label: __( 'Text', 'pediment' ), value: 'text' },
	{ label: __( 'Email', 'pediment' ), value: 'email' },
	{ label: __( 'Phone', 'pediment' ), value: 'tel' },
	{ label: __( 'Long text', 'pediment' ), value: 'textarea' },
	{ label: __( 'Dropdown', 'pediment' ), value: 'select' },
	{ label: __( 'Checkbox', 'pediment' ), value: 'checkbox' },
	{ label: __( 'Radio', 'pediment' ), value: 'radio' },
	{ label: __( 'Number', 'pediment' ), value: 'number' },
	{ label: __( 'Date', 'pediment' ), value: 'date' },
];

function slug( s: string ): string {
	return (
		s
			.toLowerCase()
			.trim()
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' ) || 'field'
	);
}

export default function Edit( {
	clientId,
	attributes,
	setAttributes,
}: {
	clientId: string;
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'pediment-form__field' } );
	const {
		fieldType,
		label,
		fieldName,
		required,
		placeholder,
		helpText,
		options,
	} = attributes;

	const siblingNames = useSelect(
		( select: any ) => {
			const { getBlockRootClientId, getBlocks } =
				select( blockEditorStore );
			const root = getBlockRootClientId( clientId );
			return getBlocks( root )
				.filter(
					( b: any ) =>
						b.clientId !== clientId &&
						b.name === 'pediment/form-field'
				)
				.map( ( b: any ) => b.attributes.fieldName );
		},
		[ clientId ]
	) as string[];

	useEffect( () => {
		if ( fieldName !== '' || label === '' ) {
			return;
		}
		const base = slug( label );
		let candidate = base;
		let n = 2;
		while ( siblingNames.includes( candidate ) ) {
			candidate = base + '_' + n;
			n++;
		}
		setAttributes( { fieldName: candidate } );
	}, [ label ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const needsOptions = fieldType === 'select' || fieldType === 'radio';
	// `options` comes from block attributes, which AI-authored markup can shape
	// loosely — a string, an array of strings, or objects with missing keys. Normalize
	// to a clean Option[] so the editor preview never crashes on malformed input.
	const rawOptions: unknown = options;
	const safeOptions: Option[] = Array.isArray( rawOptions )
		? rawOptions.map( ( o: unknown ) => {
				if ( typeof o === 'string' ) {
					return { label: o, value: o };
				}
				const obj = ( o ?? {} ) as {
					label?: unknown;
					value?: unknown;
				};
				return {
					label: String( obj.label ?? obj.value ?? '' ),
					value: String( obj.value ?? obj.label ?? '' ),
				};
		  } )
		: [];
	const optionsText = safeOptions
		.map( ( o ) =>
			o.label === o.value ? o.label : `${ o.label }|${ o.value }`
		)
		.join( '\n' );
	const parseOptions = ( text: string ): Option[] =>
		text
			.split( '\n' )
			.map( ( l ) => l.trim() )
			.filter( Boolean )
			.map( ( l ) => {
				const [ lab, val ] = l.split( '|' );
				return { label: lab.trim(), value: ( val ?? lab ).trim() };
			} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field', 'pediment' ) }>
					<SelectControl
						label={ __( 'Type', 'pediment' ) }
						value={ fieldType }
						options={ TYPES }
						onChange={ ( v ) => setAttributes( { fieldType: v } ) }
					/>
					<TextControl
						label={ __( 'Label', 'pediment' ) }
						value={ label }
						onChange={ ( v ) => setAttributes( { label: v } ) }
					/>
					<TextControl
						label={ __( 'Field name', 'pediment' ) }
						help={ __(
							'Data key. Auto-filled from the label.',
							'pediment'
						) }
						value={ fieldName }
						onChange={ ( v ) =>
							setAttributes( { fieldName: slug( v ) } )
						}
					/>
					<ToggleControl
						label={ __( 'Required', 'pediment' ) }
						checked={ required }
						onChange={ ( v ) => setAttributes( { required: v } ) }
					/>
					<TextControl
						label={ __( 'Placeholder', 'pediment' ) }
						value={ placeholder }
						onChange={ ( v ) =>
							setAttributes( { placeholder: v } )
						}
					/>
					<TextControl
						label={ __( 'Help text', 'pediment' ) }
						value={ helpText }
						onChange={ ( v ) => setAttributes( { helpText: v } ) }
					/>
					{ needsOptions && (
						<TextareaControl
							label={ __(
								'Options (one per line, "Label|value" optional)',
								'pediment'
							) }
							value={ optionsText }
							onChange={ ( v ) =>
								setAttributes( { options: parseOptions( v ) } )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			{ /* eslint-disable-next-line jsx-a11y/label-has-associated-control */ }
			<label { ...blockProps }>
				<span className="pediment-form__label">
					{ label || __( 'Untitled field', 'pediment' ) }
					{ required ? ' *' : '' }
				</span>
				{ renderPreview( fieldType, placeholder, safeOptions ) }
				{ helpText && (
					<small className="pediment-form__help">{ helpText }</small>
				) }
			</label>
		</>
	);
}

function renderPreview( type: string, placeholder: string, options: Option[] ) {
	if ( type === 'textarea' ) {
		return <textarea rows={ 4 } placeholder={ placeholder } readOnly />;
	}
	if ( type === 'select' ) {
		return (
			<select disabled>
				{ options.map( ( o ) => (
					<option key={ o.value }>{ o.label }</option>
				) ) }
			</select>
		);
	}
	if ( type === 'checkbox' ) {
		return <input type="checkbox" disabled />;
	}
	if ( type === 'radio' ) {
		return (
			<span>
				{ options.map( ( o ) => (
					// eslint-disable-next-line jsx-a11y/label-has-associated-control
					<label key={ o.value }>
						<input type="radio" disabled /> { o.label }
					</label>
				) ) }
			</span>
		);
	}
	return <input type={ type } placeholder={ placeholder } readOnly />;
}
