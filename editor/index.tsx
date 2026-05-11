import { registerPlugin } from '@wordpress/plugins';
import DocumentPanel from './DocumentPanel';
import BlockPanel from './BlockPanel';
import './styles.scss';

registerPlugin('starter-ai-document-panel', {
  render: DocumentPanel,
});

registerPlugin('starter-ai-block-panel', {
  render: BlockPanel,
});
