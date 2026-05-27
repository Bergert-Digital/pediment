import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

type Attrs = {
	includePhone: boolean;
	recipientOverride: string;
	successMessage: string;
};

export default function Edit( {
	clientId,
	attributes,
	setAttributes,
}: {
	clientId: string;
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-contact-form' } );
	const fieldId = ( name: string ) =>
		`pediment-contact-${ clientId }-${ name }`;
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form settings', 'pediment' ) }>
					<ToggleControl
						label={ __( 'Include phone field', 'pediment' ) }
						checked={ attributes.includePhone }
						onChange={ ( v ) =>
							setAttributes( { includePhone: v } )
						}
					/>
					<TextControl
						label={ __(
							'Recipient email (override Brand default)',
							'pediment'
						) }
						value={ attributes.recipientOverride }
						onChange={ ( v ) =>
							setAttributes( { recipientOverride: v } )
						}
					/>
					<TextareaControl
						label={ __( 'Success message', 'pediment' ) }
						help={ __(
							'Shown to visitors after the form is submitted.',
							'pediment'
						) }
						value={ attributes.successMessage }
						onChange={ ( v ) =>
							setAttributes( { successMessage: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<form { ...blockProps } onSubmit={ ( e ) => e.preventDefault() }>
				<label
					className="starter-contact-form__field"
					htmlFor={ fieldId( 'name' ) }
				>
					<span>{ __( 'Name', 'pediment' ) }</span>
					<input
						id={ fieldId( 'name' ) }
						type="text"
						name="name"
						readOnly
					/>
				</label>
				<label
					className="starter-contact-form__field"
					htmlFor={ fieldId( 'email' ) }
				>
					<span>{ __( 'Email', 'pediment' ) }</span>
					<input
						id={ fieldId( 'email' ) }
						type="email"
						name="email"
						readOnly
					/>
				</label>
				{ attributes.includePhone && (
					<label
						className="starter-contact-form__field"
						htmlFor={ fieldId( 'phone' ) }
					>
						<span>{ __( 'Phone', 'pediment' ) }</span>
						<input
							id={ fieldId( 'phone' ) }
							type="tel"
							name="phone"
							readOnly
						/>
					</label>
				) }
				<label
					className="starter-contact-form__field"
					htmlFor={ fieldId( 'message' ) }
				>
					<span>{ __( 'Message', 'pediment' ) }</span>
					<textarea
						id={ fieldId( 'message' ) }
						name="message"
						rows={ 5 }
						readOnly
					/>
				</label>

				<button type="button" className="starter-contact-form__submit">
					{ __( 'Send', 'pediment' ) }
				</button>

				<p
					className="starter-contact-form__status"
					role="status"
					hidden
				/>
			</form>
		</>
	);
}
