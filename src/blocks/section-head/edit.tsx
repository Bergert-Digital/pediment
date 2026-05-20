import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps( { className: 'starter-section-head' } );
	return <div { ...blockProps }>Section Head (placeholder)</div>;
}
