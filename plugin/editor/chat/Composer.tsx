import { Button } from '@wordpress/components';
import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { prepareImages, type ChatImage } from './images';

const MAX_IMAGES = 5;

export default function Composer( {
	onSubmit,
	onStop,
	busy,
	ready,
}: {
	onSubmit: ( text: string, images: ChatImage[] ) => void;
	onStop: () => void;
	busy: boolean;
	ready: boolean;
} ) {
	const [ value, setValue ] = useState( '' );
	const [ images, setImages ] = useState< ChatImage[] >( [] );
	const [ dragging, setDragging ] = useState( false );
	const [ notice, setNotice ] = useState< string | null >( null );
	const fileRef = useRef< HTMLInputElement >( null );

	const addFiles = async ( files: File[] ) => {
		if ( ! files.length ) {
			return;
		}
		const room = MAX_IMAGES - images.length;
		const { images: prepared, rejected } = await prepareImages(
			files,
			room
		);
		if ( prepared.length ) {
			setImages( ( cur ) => [ ...cur, ...prepared ] );
		}
		setNotice(
			rejected
				? __(
						'Some files were skipped — up to 5 JPEG, PNG, GIF, or WebP images.',
						'pediment'
				  )
				: null
		);
	};

	const submit = () => {
		const trimmed = value.trim();
		if ( ( ! trimmed && images.length === 0 ) || busy || ! ready ) {
			return;
		}
		onSubmit( trimmed, images );
		setValue( '' );
		setImages( [] );
		setNotice( null );
	};

	return (
		<div
			className={ `pediment-chat__composer${
				dragging ? ' is-dragging' : ''
			}` }
			onDragOver={ ( e ) => {
				e.preventDefault();
				setDragging( true );
			} }
			onDragLeave={ () => setDragging( false ) }
			onDrop={ ( e ) => {
				e.preventDefault();
				setDragging( false );
				addFiles( Array.from( e.dataTransfer.files ) );
			} }
		>
			{ images.length > 0 && (
				<div className="pediment-chat__thumbs">
					{ images.map( ( img, i ) => (
						<div key={ i } className="pediment-chat__thumb">
							<img
								src={ `data:${ img.media_type };base64,${ img.data }` }
								alt=""
							/>
							<button
								type="button"
								aria-label={ __( 'Remove image', 'pediment' ) }
								onClick={ () =>
									setImages( ( cur ) =>
										cur.filter( ( _, j ) => j !== i )
									)
								}
							>
								×
							</button>
						</div>
					) ) }
				</div>
			) }
			<textarea
				value={ value }
				onChange={ ( e ) => setValue( e.target.value ) }
				onPaste={ ( e ) => {
					const files = Array.from( e.clipboardData.files ).filter(
						( f ) => f.type.startsWith( 'image/' )
					);
					if ( files.length ) {
						e.preventDefault();
						addFiles( files );
					}
				} }
				onKeyDown={ ( e ) => {
					if ( e.key === 'Enter' && ! e.shiftKey ) {
						e.preventDefault();
						submit();
					}
				} }
				placeholder={ __( 'Ask the AI to write or edit…', 'pediment' ) }
				rows={ 3 }
				disabled={ busy }
			/>
			{ notice && (
				<div className="pediment-chat__composer-notice">{ notice }</div>
			) }
			<input
				ref={ fileRef }
				type="file"
				accept="image/png,image/jpeg,image/gif,image/webp"
				multiple
				style={ { display: 'none' } }
				onChange={ ( e ) => {
					addFiles( Array.from( e.target.files ?? [] ) );
					e.target.value = '';
				} }
			/>
			<div className="pediment-chat__composer-actions">
				<Button
					icon="format-image"
					label={ __( 'Attach image', 'pediment' ) }
					onClick={ () => fileRef.current?.click() }
					disabled={ busy || images.length >= MAX_IMAGES }
				/>
				{ busy ? (
					<Button variant="secondary" onClick={ onStop }>
						{ __( 'Stop', 'pediment' ) }
					</Button>
				) : (
					<Button
						variant="primary"
						onClick={ submit }
						disabled={
							! ready || ( ! value.trim() && images.length === 0 )
						}
					>
						{ __( 'Send', 'pediment' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
