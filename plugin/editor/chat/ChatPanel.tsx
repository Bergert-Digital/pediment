import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { Button, Modal, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useConversation from '../hooks/useConversation';
import useChatTurn from '../hooks/useChatTurn';
import { getAiStatus, aiStatusNotice } from './aiStatus';
import useSelectedBlockContext from '../hooks/useSelectedBlockContext';
import MessageList from './MessageList';
import Composer from './Composer';
import SelectionChip from './SelectionChip';
import QuickActions from './QuickActions';
import { STORE_NAME, type ChatMessage } from './store';

type Props = {
	/** When mounted from the Block inspector, the selected block is implicit; suppress the chip. */
	hideSelectionChip?: boolean;
};

export default function ChatPanel( { hideSelectionChip = false }: Props ) {
	const postId = useSelect(
		( s ) => ( s( 'core/editor' ) as any ).getCurrentPostId(),
		[]
	) as number | null;
	const { conv, clear } = useConversation( postId );
	const { streaming, error, start, stop } = useChatTurn();
	const pendingUserMessage = useSelect(
		( s ) =>
			(
				s( STORE_NAME ) as any
			 ).getPendingUserMessage() as ChatMessage | null,
		[]
	);
	const selected = useSelectedBlockContext();
	const [ confirmDelete, setConfirmDelete ] = useState( false );
	const { status: aiStatus, settingsUrl } = getAiStatus();
	const statusNotice = aiStatusNotice( aiStatus );

	const messages = pendingUserMessage
		? [ ...( conv?.messages ?? [] ), pendingUserMessage ]
		: conv?.messages ?? [];

	const send = (
		text: string,
		images: import('./images').ChatImage[] = []
	) => {
		if ( ! conv || ! postId ) {
			return;
		}
		start( {
			conversationId: conv.id,
			postId,
			message: text,
			images,
			selectedBlock: selected,
		} );
	};

	return (
		<div className="pediment-chat">
			<div className="pediment-chat__header">
				<span className="pediment-chat__title">
					{ __( 'AI Chat', 'pediment' ) }
				</span>
			</div>
			{ statusNotice && (
				<Notice
					className="pediment-chat__status-notice"
					status="warning"
					isDismissible={ false }
				>
					{ statusNotice }
					{ settingsUrl && (
						<>
							{ ' ' }
							<a href={ settingsUrl }>
								{ __( 'Open settings', 'pediment' ) }
							</a>
						</>
					) }
				</Notice>
			) }
			<MessageList messages={ messages } streaming={ streaming } />
			{ error && <div className="pediment-chat__error">{ error }</div> }
			{ selected && ! hideSelectionChip && (
				<SelectionChip block={ selected } />
			) }
			{ selected && (
				<QuickActions
					block={ selected }
					onAction={ send }
					busy={ !! streaming || ! conv || ! postId }
				/>
			) }
			{ messages.length > 0 && (
				<div className="pediment-chat__history-actions">
					<Button
						variant="tertiary"
						size="small"
						isDestructive
						disabled={ !! streaming }
						onClick={ () => setConfirmDelete( true ) }
					>
						{ __( 'Delete history', 'pediment' ) }
					</Button>
				</div>
			) }
			<Composer
				onSubmit={ send }
				onStop={ stop }
				busy={ !! streaming }
				ready={ !! conv && !! postId }
			/>
			{ confirmDelete && (
				<Modal
					title={ __( 'Delete history', 'pediment' ) }
					onRequestClose={ () => setConfirmDelete( false ) }
					size="small"
				>
					<p>
						{ __(
							"Delete this chat's history? This can't be undone.",
							'pediment'
						) }
					</p>
					<div className="pediment-chat__confirm-actions">
						<Button
							variant="tertiary"
							onClick={ () => setConfirmDelete( false ) }
						>
							{ __( 'Cancel', 'pediment' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ () => {
								setConfirmDelete( false );
								clear();
							} }
						>
							{ __( 'Delete', 'pediment' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
