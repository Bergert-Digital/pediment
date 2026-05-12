import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = [ 'starter/faq-item' ];
const TEMPLATE: [ string, Record< string, unknown > ][] = [
	[ 'starter/faq-item', { question: '', answer: '' } ],
	[ 'starter/faq-item', { question: '', answer: '' } ],
	[ 'starter/faq-item', { question: '', answer: '' } ],
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
