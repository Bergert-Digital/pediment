export type BlockLike = { name: string; attributes?: { className?: string } };

export type SectionPlan =
  | { kind: 'keep'; index: number }
  | { kind: 'wrap'; indices: number[] };

const SECTION_CLASS = 'starter-section';

function isSectionGroup(b: BlockLike): boolean {
  return (
    b.name === 'core/group' &&
    typeof b.attributes?.className === 'string' &&
    b.attributes.className.split(/\s+/).includes(SECTION_CLASS)
  );
}

/**
 * Partition a flat top-level block list into a deterministic section plan.
 * Runs split on core/separator (separators are dropped). A segment that is
 * exactly one already-correct section group is kept as-is (idempotent).
 */
export function planSections(blocks: BlockLike[]): SectionPlan[] {
  const out: SectionPlan[] = [];
  let segment: number[] = [];

  const flush = () => {
    if (segment.length === 0) return;
    if (segment.every((i) => isSectionGroup(blocks[i]))) {
      segment.forEach((i) => out.push({ kind: 'keep', index: i }));
    } else {
      out.push({ kind: 'wrap', indices: segment.slice() });
    }
    segment = [];
  };

  blocks.forEach((blk, i) => {
    if (blk.name === 'core/separator') {
      flush();
    } else {
      segment.push(i);
    }
  });
  flush();
  return out;
}

type RootBlock = { clientId: string; name: string; attributes: any; innerBlocks: any[] };

export type NormalizeDeps = {
  getBlocks: () => RootBlock[];
  replaceBlocks: (clientIds: string[], blocks: any[]) => void;
};

export type CreateBlock = (name: string, attributes: any, innerBlocks: any[]) => any;

/**
 * Recursively rebuild a block via `create` so every node (and child) gets a
 * FRESH clientId. Reusing live block objects keeps their original clientIds,
 * which are also in replaceBlocks' removal list — producing a cyclic
 * parent/order map and an infinite-recursion editor crash.
 */
function cloneBlock(b: RootBlock, create: CreateBlock): any {
  return create(b.name, { ...b.attributes }, (b.innerBlocks ?? []).map((c) => cloneBlock(c, create)));
}

/**
 * Deterministically rewrite the editor root into section groups.
 * Idempotent: already-correct section groups are reused unchanged.
 */
export function normalizeSections(deps: NormalizeDeps, create: CreateBlock): void {
  const root = deps.getBlocks();
  if (root.length === 0) return;

  const plan = planSections(root);

  const next = plan.map((p) =>
    p.kind === 'keep'
      ? cloneBlock(root[p.index], create)
      : create(
          'core/group',
          { tagName: 'section', className: SECTION_CLASS, layout: { type: 'default' } },
          p.indices.map((i) => cloneBlock(root[i], create))
        )
  );

  deps.replaceBlocks(
    root.map((b) => b.clientId),
    next
  );
}
