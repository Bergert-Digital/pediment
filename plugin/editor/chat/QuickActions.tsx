import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { SelectedBlock } from '../hooks/useSelectedBlockContext';

const PRESETS: Record< string, { label: string; instruction: string }[] > = {
	'core/paragraph': [
		{
			label: __( 'Shorten', 'pediment' ),
			instruction: 'Shorten the selected paragraph.',
		},
		{
			label: __( 'Expand', 'pediment' ),
			instruction: 'Expand the selected paragraph with more detail.',
		},
		{
			label: __( 'Rewrite', 'pediment' ),
			instruction: 'Rewrite the selected paragraph in different words.',
		},
		{
			label: __( 'Fix grammar', 'pediment' ),
			instruction: 'Fix any grammar or typos in the selected paragraph.',
		},
	],
	'core/heading': [
		{
			label: __( 'Shorten', 'pediment' ),
			instruction: 'Shorten the selected heading.',
		},
		{
			label: __( 'Rewrite', 'pediment' ),
			instruction: 'Rewrite the selected heading with a different angle.',
		},
	],
	'core/list': [
		{
			label: __( 'Add item', 'pediment' ),
			instruction: 'Add another item to the selected list.',
		},
		{
			label: __( 'Reorder', 'pediment' ),
			instruction:
				'Reorder the items in the selected list more logically.',
		},
	],
	'core/image': [
		{
			label: __( 'Alt text', 'pediment' ),
			instruction: 'Generate alt text for the selected image.',
		},
		{
			label: __( 'Caption', 'pediment' ),
			instruction: 'Write a short caption for the selected image.',
		},
	],
};
const FALLBACK = [
	{
		label: __( 'Improve', 'pediment' ),
		instruction: 'Improve the selected block.',
	},
	{
		label: __( 'Rewrite', 'pediment' ),
		instruction: 'Rewrite the selected block in different words.',
	},
];

export default function QuickActions( {
	block,
	onAction,
	busy,
}: {
	block: SelectedBlock;
	onAction: ( instruction: string ) => void;
	busy: boolean;
} ) {
	const actions = PRESETS[ block.name ] ?? FALLBACK;
	return (
		<div className="pediment-chat__quick">
			{ actions.map( ( a ) => (
				<Button
					key={ a.label }
					variant="secondary"
					size="small"
					onClick={ () => onAction( a.instruction ) }
					disabled={ busy }
				>
					{ a.label }
				</Button>
			) ) }
		</div>
	);
}
