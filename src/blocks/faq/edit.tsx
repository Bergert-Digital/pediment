import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = [ 'pediment/faq-item' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'pediment/faq-item', { question: '', answer: '' } ],
	[ 'pediment/faq-item', { question: '', answer: '' } ],
	[ 'pediment/faq-item', { question: '', answer: '' } ],
];

export default function Edit() {
	const blockProps = useBlockProps( { className: 'starter-faq' } );
	const innerBlocksProps = useInnerBlocksProps( blockProps, {
		allowedBlocks: ALLOWED,
		template: TEMPLATE,
		templateLock: false,
	} );
	return <section { ...innerBlocksProps } />;
}
