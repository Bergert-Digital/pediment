import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = [ 'starter/feature' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'starter/feature', { icon: 'trend-up' } ],
	[ 'starter/feature', { icon: 'gear' } ],
	[ 'starter/feature', { icon: 'stack' } ],
];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'starter-feature-grid' } );
	const innerProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
	} );
	return <section { ...innerProps } />;
}
