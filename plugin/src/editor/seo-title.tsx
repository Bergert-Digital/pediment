/**
 * "SEO" panel in the document settings sidebar: the SEO title, the whole
 * <title> of the page (post meta `_pediment_seo_title`, see
 * plugin/src/Seo/SeoTitle.php). Hidden while an SEO plugin prints the head
 * tags, and on post types that are not public or do not expose the meta.
 */
import { __, sprintf } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl } from '@wordpress/components';

const META_KEY = '_pediment_seo_title';

declare global {
	interface Window {
		pedimentSeo?: { titleEnabled?: boolean };
	}
}

type Meta = Record< string, unknown >;

function SeoTitlePanel() {
	const { meta, viewable } = useSelect( ( select ) => {
		const editor = select( editorStore ) as unknown as {
			getCurrentPostType: () => string;
			getEditedPostAttribute: ( key: string ) => unknown;
		};
		const core = select( coreStore ) as unknown as {
			getPostType: ( slug: string ) => { viewable?: boolean } | undefined;
		};
		return {
			meta: editor.getEditedPostAttribute( 'meta' ) as Meta | undefined,
			viewable: !! core.getPostType( editor.getCurrentPostType() )
				?.viewable,
		};
	}, [] );
	const { editPost } = useDispatch( editorStore ) as unknown as {
		editPost: ( edits: { meta: Meta } ) => void;
	};

	if ( ! viewable || typeof meta?.[ META_KEY ] !== 'string' ) {
		return null;
	}
	const value = meta[ META_KEY ] as string;

	return (
		<PluginDocumentSettingPanel
			name="pediment-seo"
			title={ __( 'SEO', 'pediment' ) }
		>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'SEO title', 'pediment' ) }
				value={ value }
				onChange={ ( next: string ) =>
					editPost( { meta: { [ META_KEY ]: next } } )
				}
				help={ sprintf(
					/* translators: %d: number of characters in the SEO title. */
					__(
						'The title in the browser tab and in search results, exactly as written. Empty: the page title and the site name. Aim for 50–60 characters (now %d).',
						'pediment'
					),
					value.length
				) }
			/>
		</PluginDocumentSettingPanel>
	);
}

if ( window.pedimentSeo?.titleEnabled ) {
	registerPlugin( 'pediment-seo-title', { render: SeoTitlePanel } );
}
