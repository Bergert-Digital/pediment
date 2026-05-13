import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { Placeholder } from '@wordpress/components';

export default function Edit() {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<ServerSideRender
				block="starter/social-links"
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __( 'Social links', 'starter' ) }
						instructions={ __(
							'No social links configured. Add them under Settings → Brand Settings → Social.',
							'starter'
						) }
					/>
				) }
			/>
		</div>
	);
}
