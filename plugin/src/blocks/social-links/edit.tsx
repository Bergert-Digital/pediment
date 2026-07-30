import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import {
	PanelBody,
	SelectControl,
	TextControl,
	Button,
	Placeholder,
} from '@wordpress/components';

type Link = { platform: string; url: string };
type Attrs = { links: Link[] };

const PLATFORMS = [
	{ label: __( 'X (Twitter)', 'pediment' ), value: 'x' },
	{ label: __( 'Twitter', 'pediment' ), value: 'twitter' },
	{ label: __( 'GitHub', 'pediment' ), value: 'github' },
	{ label: __( 'LinkedIn', 'pediment' ), value: 'linkedin' },
	{ label: __( 'Instagram', 'pediment' ), value: 'instagram' },
	{ label: __( 'Facebook', 'pediment' ), value: 'facebook' },
	{ label: __( 'YouTube', 'pediment' ), value: 'youtube' },
	{ label: __( 'Mastodon', 'pediment' ), value: 'mastodon' },
	{ label: __( 'RSS', 'pediment' ), value: 'rss' },
];

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps();
	const links = attributes.links ?? [];

	const update = ( index: number, patch: Partial< Link > ): void => {
		setAttributes( {
			links: links.map( ( link, i ) =>
				i === index ? { ...link, ...patch } : link
			),
		} );
	};
	const add = (): void => {
		setAttributes( { links: [ ...links, { platform: 'x', url: '' } ] } );
	};
	const remove = ( index: number ): void => {
		setAttributes( { links: links.filter( ( _, i ) => i !== index ) } );
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Social links', 'pediment' ) }>
					{ links.map( ( link, i ) => (
						<div
							key={ i }
							className="pediment-social-links__control"
							style={ {
								marginBottom: '1rem',
								paddingBottom: '1rem',
								borderBottom: '1px solid #e0e0e0',
							} }
						>
							<SelectControl
								label={ __( 'Platform', 'pediment' ) }
								value={ link.platform }
								options={ PLATFORMS }
								onChange={ ( platform: string ) =>
									update( i, { platform } )
								}
							/>
							<TextControl
								label={ __( 'URL', 'pediment' ) }
								type="url"
								value={ link.url }
								onChange={ ( url: string ) =>
									update( i, { url } )
								}
							/>
							<Button
								isDestructive
								variant="secondary"
								onClick={ () => remove( i ) }
							>
								{ __( 'Remove', 'pediment' ) }
							</Button>
						</div>
					) ) }
					<Button variant="primary" onClick={ add }>
						{ __( 'Add link', 'pediment' ) }
					</Button>
				</PanelBody>
			</InspectorControls>
			{ links.length === 0 ? (
				<Placeholder
					label={ __( 'Social links', 'pediment' ) }
					instructions={ __(
						'Add social links in the block settings sidebar.',
						'pediment'
					) }
				/>
			) : (
				<ServerSideRender
					block="pediment/social-links"
					attributes={ attributes }
				/>
			) }
		</div>
	);
}
