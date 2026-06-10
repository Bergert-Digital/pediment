import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = [ 'pediment/testimonial' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'pediment/testimonial', {} ],
	[ 'pediment/testimonial', {} ],
];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'starter-testimonial-grid' } );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
	} );
	return <section { ...innerProps } />;
}
