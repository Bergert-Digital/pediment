# Session Log

Rolling log. /dev-cycle keeps only the most recent prior session entry plus the current one.

---

## Session 2026-06-22 — single post polish

[14:47] ✅ Tightened the single-post template after screenshot review: the masthead keeps top padding but uses a smaller article-specific bottom pad, the featured image is capped below wide-size, and post content now uses a constrained layout so normal prose aligns to the reading column.

### Planned next
_(none)_

### Need a decision on
_(none)_

## Session 2026-06-23 — block translation config

[17:05] ✅ Added root `wpml-config.xml` for Polylang Pro/WPML block translation support. It declares translatable text/link attributes for Pediment's custom content blocks, including wildcard paths for hero metrics/ticks, slider slides, and mega-menu columns/links.
[17:05] 🔍 Root XML is not excluded by `.distignore`, so it should be included in parent theme release zips.

### Planned next
- Validate on staging with Polylang Pro + DeepL that parent-theme `wpml-config.xml` is discovered while a child theme is active, especially for wildcard array attributes in `hero`, `slider`, and `mega-menu`.

### Need a decision on
_(none)_
