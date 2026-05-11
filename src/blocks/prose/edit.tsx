import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

const ALLOWED = ['core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/separator'];

export default function Edit() {
  const blockProps = useBlockProps({ className: 'starter-prose' });
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ALLOWED,
    template: [['core/paragraph', { placeholder: 'Start writing…' }]],
    templateLock: false,
  });
  return <div {...innerBlocksProps} />;
}
