import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = [ 'pediment/feature' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'pediment/feature', { icon: 'trend-up' } ],
	[ 'pediment/feature', { icon: 'gear' } ],
	[ 'pediment/feature', { icon: 'stack' } ],
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
