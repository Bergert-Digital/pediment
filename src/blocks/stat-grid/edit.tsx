import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = [ 'pediment/stat' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'pediment/stat', {} ],
	[ 'pediment/stat', {} ],
	[ 'pediment/stat', {} ],
];

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'starter-stat-grid',
	} );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
	} );
	return <div { ...innerProps } />;
}
