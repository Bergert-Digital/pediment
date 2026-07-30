import { Button } from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import type { SelectedBlock } from '../hooks/useSelectedBlockContext';

export default function SelectionChip({ block }: { block: SelectedBlock }) {
  const preview = (block.attributes.content ?? block.attributes.text ?? '').toString().slice(0, 60);
  const clear   = () => (dispatch('core/block-editor') as any).clearSelectedBlock();
  return (
    <div className="pediment-chat__chip">
      <span className="pediment-chat__chip-type">{block.name.replace(/^core\//, '')}</span>
      <span className="pediment-chat__chip-preview">{preview}</span>
      <Button size="small" variant="tertiary" onClick={clear} aria-label={__('Clear selection', 'pediment')}>×</Button>
    </div>
  );
}
