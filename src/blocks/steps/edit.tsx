import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = [ 'starter/step' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'starter/step', {} ],
	[ 'starter/step', {} ],
	[ 'starter/step', {} ],
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
