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
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( { className: 'starter-contact-form' } );
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Form settings', 'starter' ) }>
					<ToggleControl
						label={ __( 'Include phone field', 'starter' ) }
						checked={ attributes.includePhone }
						onChange={ ( v ) =>
							setAttributes( { includePhone: v } )
						}
					/>
					<TextControl
						label={ __(
							'Recipient email (override Brand default)',
							'starter'
						) }
						value={ attributes.recipientOverride }
						onChange={ ( v ) =>
							setAttributes( { recipientOverride: v } )
						}
					/>
					<TextareaControl
						label={ __( 'Success message', 'starter' ) }
						help={ __(
							'Shown to visitors after the form is submitted.',
							'starter'
						) }
						value={ attributes.successMessage }
						onChange={ ( v ) =>
							setAttributes( { successMessage: v } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<form { ...blockProps } onSubmit={ ( e ) => e.preventDefault() }>
				<label className="starter-contact-form__field">
					<span>{ __( 'Name', 'starter' ) }</span>
					<input type="text" name="name" readOnly />
				</label>
				<label className="starter-contact-form__field">
					<span>{ __( 'Email', 'starter' ) }</span>
					<input type="email" name="email" readOnly />
				</label>
				{ attributes.includePhone && (
					<label className="starter-contact-form__field">
						<span>{ __( 'Phone', 'starter' ) }</span>
						<input type="tel" name="phone" readOnly />
					</label>
				) }
				<label className="starter-contact-form__field">
					<span>{ __( 'Message', 'starter' ) }</span>
					<textarea name="message" rows={ 5 } readOnly />
				</label>

				<button type="button" className="starter-contact-form__submit">
					{ __( 'Send', 'starter' ) }
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
