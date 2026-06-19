import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

type Attrs = {
	mediaPosition: string;
	panelColor: string;
};

const ALLOWED = [ 'pediment/slide' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'pediment/slide', {} ],
	[ 'pediment/slide', {} ],
];

export default function Edit( {
	attributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const position = attributes.mediaPosition === 'right' ? 'right' : 'left';
	const blockProps = useBlockProps( {
		className: `starter-slider is-editor is-media-${ position }`,
		style: {
			[ '--slide-panel-bg' as string ]:
				attributes.panelColor || undefined,
		},
	} );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
		orientation: 'vertical',
	} );
	return <section { ...innerProps } />;
}
