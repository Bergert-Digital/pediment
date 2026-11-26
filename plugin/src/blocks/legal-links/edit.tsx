import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { PanelBody, TextControl, Placeholder } from '@wordpress/components';

type Attrs = { keys: string[] };

const EmptyLinks = () => (
	<Placeholder
		label={ __( 'Legal links', 'pediment' ) }
		instructions={ __(
			'No page with these seed keys exists yet. Run the seed, or set the keys in the block settings sidebar.',
			'pediment'
		) }
	/>
);

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps();
	const keys = attributes.keys ?? [];

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Legal links', 'pediment' ) }>
					<TextControl
						label={ __( 'Seed keys', 'pediment' ) }
						help={ __(
							'The seed keys of the pages to link, in order, separated by commas.',
							'pediment'
						) }
						value={ keys.join( ', ' ) }
						onChange={ ( value: string ) =>
							setAttributes( {
								keys: value
									.split( ',' )
									.map( ( key ) => key.trim() )
									.filter( Boolean ),
							} )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="pediment/legal-links"
				attributes={ attributes }
				EmptyResponsePlaceholder={ EmptyLinks }
			/>
		</div>
	);
}
