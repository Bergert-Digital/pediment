import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import './accent-format';
import './style.scss';

registerBlockType( metadata.name, { edit: Edit } );
