import { Button, TextareaControl, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { postRefine } from './hooks/useApiClient';

const QUICK_ACTIONS: Record<string, { label: string; instruction: string }[]> = {
  'starter/hero': [
    { label: 'Punchier',         instruction: 'Make it punchier and more benefit-led.' },
    { label: 'Different angle',  instruction: 'Try a completely different angle.' },
  ],
  'starter/cta': [
    { label: 'More urgent',      instruction: 'Make it more urgent.' },
    { label: 'Shorter',          instruction: 'Make it shorter.' },
  ],
  'starter/faq-item': [
    { label: 'Tighter answer',   instruction: 'Make the answer tighter.' },
  ],
};

export default function RefineActions({ clientId, name, attributes, innerBlocks }: {
  clientId: string;
  name: string;
  attributes: Record<string, any>;
  innerBlocks: any[];
}) {
  const [custom,  setCustom]  = useState('');
  const [loading, setLoading] = useState(false);
  const [err,     setErr]     = useState<string | null>(null);

  const trigger = async (instruction: string) => {
    setErr(null); setLoading(true);
    try {
      const res = await postRefine({ blockName: name, attributes, innerBlocks: innerBlocksToTree(innerBlocks), instruction });
      (dispatch('core/block-editor') as any).updateBlockAttributes(clientId, res.attributes);
      if (Array.isArray(res.innerBlocks)) {
        const parsed = treeToInnerBlocks(res.innerBlocks);
        (dispatch('core/block-editor') as any).replaceInnerBlocks(clientId, parsed);
      }
    } catch (e: any) {
      setErr(e?.message ?? 'Refine failed');
    } finally {
      setLoading(false);
    }
  };

  const quick = QUICK_ACTIONS[name] ?? [];

  return (
    <div>
      {quick.map((qa) => (
        <Button key={qa.label} variant="secondary" onClick={() => trigger(qa.instruction)} disabled={loading} style={{ marginRight: 6, marginBottom: 6 }}>
          {qa.label}
        </Button>
      ))}
      <TextareaControl label={__('Custom instruction', 'starter-ai')} value={custom} onChange={setCustom} rows={2} />
      <Button variant="primary" onClick={() => trigger(custom)} disabled={loading || !custom.trim()}>
        {__('Refine', 'starter-ai')}
      </Button>
      {loading && <Spinner />}
      {err && <p className="starter-ai__error">{err}</p>}
    </div>
  );
}

function innerBlocksToTree(blocks: any[]): any[] {
  return blocks.map((b) => ({ name: b.name, attributes: b.attributes ?? {}, innerBlocks: innerBlocksToTree(b.innerBlocks ?? []) }));
}
function treeToInnerBlocks(tree: any[]): any[] {
  const { createBlock } = (window as any).wp.blocks;
  return tree.map((node: any) => createBlock(node.name, node.attributes ?? {}, treeToInnerBlocks(node.innerBlocks ?? [])));
}
