import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

type Attrs = {
	count: number;
	categorySlug: string;
	showFilter: boolean;
};

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps();
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Blog index', 'starter' ) }>
					<RangeControl
						label={ __( 'Posts to show', 'starter' ) }
						value={ attributes.count }
						min={ 1 }
						max={ 20 }
						onChange={ ( v ) => setAttributes( { count: v ?? 6 } ) }
					/>
					<TextControl
						label={ __( 'Category slug (optional)', 'starter' ) }
						value={ attributes.categorySlug }
						onChange={ ( v ) =>
							setAttributes( { categorySlug: v } )
						}
					/>
					<ToggleControl
						label={ __( 'Show category filter', 'starter' ) }
						checked={ attributes.showFilter }
						onChange={ ( v ) => setAttributes( { showFilter: v } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="starter/blog-index"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
