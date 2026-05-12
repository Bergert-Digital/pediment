import { PluginDocumentSettingPanel as PluginDocumentSettingPanelFromEditor } from '@wordpress/editor';
import { PluginDocumentSettingPanel as PluginDocumentSettingPanelFromEditPost } from '@wordpress/edit-post';
import { Button } from '@wordpress/components';
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

const PluginDocumentSettingPanel =
  PluginDocumentSettingPanelFromEditor ?? PluginDocumentSettingPanelFromEditPost;

export default function DocumentPanel() {
  const open = () => {
    const d = dispatch('core/editor') as any;
    if (typeof d.openGeneralSidebar === 'function') {
      d.openGeneralSidebar('starter-ai/chat');
    } else {
      (dispatch('core/edit-post') as any).openGeneralSidebar('starter-ai/chat');
    }
  };
  return (
    <PluginDocumentSettingPanel name="starter-ai" title="AI" className="starter-ai__panel">
      <Button variant="primary" onClick={open}>{__('Open AI Chat', 'starter-ai')}</Button>
    </PluginDocumentSettingPanel>
  );
}
