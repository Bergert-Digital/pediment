import { useEffect, useRef } from '@wordpress/element';
import ToolCallSummary from './ToolCallSummary';
import type { ChatMessage } from '../hooks/useConversation';

export default function MessageList( {
	messages,
	streaming,
}: {
	messages: ChatMessage[];
	streaming: ChatMessage | null;
} ) {
	const ref = useRef< HTMLDivElement >( null );
	useEffect( () => {
		ref.current?.scrollTo( {
			top: ref.current.scrollHeight,
			behavior: 'smooth',
		} );
	}, [ messages.length, streaming?.content ] );

	const display = streaming ? [ ...messages, streaming ] : messages;

	return (
		<div className="pediment-chat__messages" ref={ ref }>
			{ display.map( ( m ) => (
				<div
					key={ m.id }
					className={ `pediment-chat__message pediment-chat__message--${ m.role }` }
				>
					<div className="pediment-chat__bubble">
						{ m.content }
						{ m.status === 'streaming' && (
							<span className="pediment-chat__caret" />
						) }
					</div>
					{ m.attachments && m.attachments.length > 0 && (
						<div className="pediment-chat__msg-images">
							{ m.attachments.map( ( a, i ) => (
								<img
									key={ i }
									src={ `data:${ a.media_type };base64,${ a.data }` }
									alt=""
								/>
							) ) }
						</div>
					) }
					<ToolCallSummary calls={ m.tool_calls } />
					{ m.error && (
						<div className="pediment-chat__error">
							{ m.error.message }
						</div>
					) }
				</div>
			) ) }
		</div>
	);
}
