# Per-section visual acceptance gate — fidelity-to-original

The mandatory quality gate for a port. A section is NOT done until it passes here.
Run this as the final acceptance step per section, looping until every section passes.

## Primary judgment: fidelity to the original

Each rendered section is judged against **its matching source section** — not against a
generic Pediment pattern or an ideal "what good looks like" reference. The question is:
*does this rendered section faithfully reproduce what the source section showed a visitor?*

The secondary categories (spacing, image sizing, etc.) exist to name *why* a section
diverges from the original; they are not independent goals.

## Inputs per section

1. **Rendered section** — scroll the built page to the section, **wait out the entrance
   animation** (Pediment fades sections in on scroll; capture too early and it reads as
   blank/grey — wait ≥1.5 s after it enters the viewport), screenshot the full section.
2. **Source section** — the matching screenshot/region from the source page for this
   section. This is the ground truth.

## Rubric — score each section 1–5 per category

| Category | What FAILs it |
|---|---|
| **Fidelity to original** | The rendered section diverges visibly from the source section — different layout rhythm, missing content blocks, wrong visual weight, or the overall impression does not match. **Also fails if the section looks like a recolored stock Pediment block rather than the source** (e.g. Pediment's bordered white feature cards with peach icon-squares, the stat-card hero, the default band rhythm) — recognisable Pediment defaults are a fidelity FAIL even when colors/fonts are on-brand. This is the primary category. |
| **Spacing & padding** | Content touching edges; no vertical breathing room; cramped or collapsed band. (e.g. `hero media-bg` has no `padding-block` — must be added.) |
| **Image sizing & aspect** | Images rendered far larger or smaller than the source; portrait images ballooning a column; no max-width/height constraint. |
| **Block-usage correctness** | Wrong block for the job — e.g. a `pediment/cta` band (title+body+buttons) used as a lone button. A single link → `core/button`. A conversion band → `cta`. |
| **Contrast & readability** | Text low-contrast on its band (`is-style-band-navy` only lightens `stat`/`pull-quote`/`social-links`; never put `section-head`/`prose`/`feature` on navy). |
| **Alignment & hierarchy** | Misaligned columns; heading/lead/body hierarchy unclear; orphaned/empty containers. |
| **Content fidelity** | Source copy or imagery for this section is missing or wrong. |

## Pass bar

**Every section must score ≥ 4/5 in every category AND have zero high-severity issues
AND the holistic judgment "matches the original closely enough" must be true.**

Anything below the pass bar loops: apply the fix, re-render, re-judge ONLY the changed
sections.

## Output format

Per run, record a row per section per round:

```
| Round | Section label | spacing | imageSizing | blockUsage | contrast | alignment | contentFidelity | fidelityToOriginal | pass | Issues |
```

Keep the latest round's verdict at the top. Append previous rounds below a `---` divider.

## The judge

The judge is a vision-capable agent applying this rubric against the two inputs (rendered
section + source section). The judge is independent — it is not told what the builder
intended, and it is not asked to confirm a section is fine. It evaluates blind against the
source.

Do not pass a section on structure alone ("the block rendered") — judge whether it
looks like the source page. Be demanding: "acceptable" means a client reviewing both
side-by-side would not notice a meaningful difference.
