import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl, TextControl } from '@wordpress/components';

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
				</PanelBody>
			</InspectorControls>
			<form { ...blockProps } onSubmit={ ( e ) => e.preventDefault() }>
				<label htmlFor="starter-contact-name">
					{ __( 'Name', 'starter' ) }{ ' ' }
					<input
						id="starter-contact-name"
						type="text"
						disabled
						placeholder={ __( 'Name', 'starter' ) }
					/>
				</label>
				<label htmlFor="starter-contact-email">
					{ __( 'Email', 'starter' ) }{ ' ' }
					<input
						id="starter-contact-email"
						type="email"
						disabled
						placeholder={ __( 'Email', 'starter' ) }
					/>
				</label>
				{ attributes.includePhone && (
					<label htmlFor="starter-contact-phone">
						{ __( 'Phone', 'starter' ) }{ ' ' }
						<input
							id="starter-contact-phone"
							type="tel"
							disabled
							placeholder={ __( 'Phone', 'starter' ) }
						/>
					</label>
				) }
				<label htmlFor="starter-contact-message">
					{ __( 'Message', 'starter' ) }{ ' ' }
					<textarea
						id="starter-contact-message"
						disabled
						placeholder={ __( 'Message', 'starter' ) }
					/>
				</label>
				<button type="button" disabled>
					{ __( 'Send', 'starter' ) }
				</button>
				<RichText
					tagName="p"
					value={ attributes.successMessage }
					onChange={ ( v ) => setAttributes( { successMessage: v } ) }
					placeholder={ __( 'Success message…', 'starter' ) }
				/>
			</form>
		</>
	);
}
