import { Modal, Button, TextareaControl, Spinner, Notice } from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { select, dispatch } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { postEdit } from './hooks/useApiClient';
import useJobPolling from './hooks/useJobPolling';
import SourcePills from './SourcePills';

export default function EditModal({ onClose }: { onClose: () => void }) {
  const [instruction, setInstruction] = useState('');
  const [jobId,       setJobId]       = useState<number | null>(null);
  const [submitErr,   setSubmitErr]   = useState<string | null>(null);

  const { status, urls, progressNote, result, error } = useJobPolling(jobId);
  const appliedRef = useRef(false);

  useEffect(() => {
    if (!result || appliedRef.current) return;
    appliedRef.current = true;
    (dispatch('core/block-editor') as any).resetBlocks(treeToBlocks(result.blocks));
    onClose();
  }, [result, onClose]);

  const submit = async () => {
    setSubmitErr(null);
    const currentBlocks = (select('core/block-editor') as any).getBlocks();
    const tree = blocksToTree(currentBlocks);
    try {
      const { job_id } = await postEdit({ instruction, tree });
      setJobId(job_id);
    } catch (e: any) {
      setSubmitErr(e?.message ?? 'Request failed');
    }
  };

  if (result) return null;

  return (
    <Modal title={__('Edit with AI', 'starter-ai')} onRequestClose={onClose}>
      <Notice status="warning" isDismissible={false}>
        {__('This will replace your current page. Use Undo (Cmd/Ctrl+Z) to revert.', 'starter-ai')}
      </Notice>

      {jobId === null && (
        <>
          <TextareaControl
            label={__('Instruction', 'starter-ai')}
            value={instruction}
            onChange={setInstruction}
            rows={4}
            placeholder={__('Add an FAQ section, shorten the hero, change CTA to…', 'starter-ai')}
          />
          {submitErr && <p className="starter-ai__error">{submitErr}</p>}
          <Button variant="primary" onClick={submit} disabled={!instruction.trim()}>
            {__('Edit', 'starter-ai')}
          </Button>
        </>
      )}

      {jobId !== null && status !== 'complete' && status !== 'error' && (
        <div className="starter-ai__progress"><Spinner /><span>{progressNote ?? 'Working…'}</span></div>
      )}
      {urls.length > 0 && <SourcePills urls={urls} />}
      {status === 'error' && <p className="starter-ai__error">{error ?? __('Edit failed.', 'starter-ai')}</p>}
    </Modal>
  );
}

function blocksToTree(blocks: any[]): any[] {
  return blocks.map((b) => ({
    name: b.name,
    attributes: b.attributes ?? {},
    innerBlocks: blocksToTree(b.innerBlocks ?? []),
  }));
}
// Build a block tree directly via createBlock() — see ComposeModal for rationale.
function treeToBlocks(tree: any[]): any[] {
  return tree.map((node: any) => createBlock(node.name, node.attributes ?? {}, treeToBlocks(node.innerBlocks ?? [])));
}
