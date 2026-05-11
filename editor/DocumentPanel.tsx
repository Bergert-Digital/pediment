import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ComposeModal from './ComposeModal';
import EditModal from './EditModal';

type Mode = 'idle' | 'compose' | 'edit';

export default function DocumentPanel() {
  const [mode, setMode] = useState<Mode>('idle');

  return (
    <>
      <PluginDocumentSettingPanel name="starter-ai" title="AI" className="starter-ai__panel">
        <Button variant="primary"   onClick={() => setMode('compose')} style={{ marginRight: 8 }}>
          {__('Compose with AI', 'starter-ai')}
        </Button>
        <Button variant="secondary" onClick={() => setMode('edit')}>
          {__('Edit with AI', 'starter-ai')}
        </Button>
      </PluginDocumentSettingPanel>

      {mode === 'compose' && <ComposeModal onClose={() => setMode('idle')} />}
      {mode === 'edit'    && <EditModal    onClose={() => setMode('idle')} />}
    </>
  );
}
