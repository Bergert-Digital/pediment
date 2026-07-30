import { registerPlugin } from '@wordpress/plugins';
import DocumentPanel from './DocumentPanel';
import BlockChatPanel from './BlockChatPanel';
import './styles.scss';

registerPlugin('pediment-document-panel', { render: DocumentPanel });
registerPlugin('pediment-block-chat', { render: BlockChatPanel });
