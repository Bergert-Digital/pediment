import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = [ 'pediment/step' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'pediment/step', {} ],
	[ 'pediment/step', {} ],
	[ 'pediment/step', {} ],
];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'starter-steps' } );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
	} );
	return <div { ...innerProps } />;
}
