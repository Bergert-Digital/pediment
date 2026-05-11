import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import RefineActions from './RefineActions';

const STARTER_BLOCKS = /^starter\//;

const withRefine = createHigherOrderComponent((BlockEdit: any) => (props: any) => {
  if (!STARTER_BLOCKS.test(props.name)) {
    return <BlockEdit {...props} />;
  }
  return (
    <>
      <BlockEdit {...props} />
      <InspectorControls>
        <PanelBody title={__('AI refine', 'starter-ai')} initialOpen={false}>
          <RefineActions
            clientId={props.clientId}
            name={props.name}
            attributes={props.attributes}
            innerBlocks={(props as any).innerBlocks ?? []}
          />
        </PanelBody>
      </InspectorControls>
    </>
  );
}, 'withStarterAiRefine');

let registered = false;
function ensureFilter() {
  if (registered) { return; }
  addFilter('editor.BlockEdit', 'starter-ai/refine-panel', withRefine);
  registered = true;
}

export default function BlockPanel() {
  useEffect(() => { ensureFilter(); }, []);
  return null;
}

// Register immediately at import time so the filter is in place when blocks render.
ensureFilter();
