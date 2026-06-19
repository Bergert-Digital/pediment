# Single post template — design

**Date:** 2026-06-19
**Status:** Approved (pending spec review)
**Scope:** Replace the bare `templates/single.html` with a properly designed single blog post template, matching the theme's existing editorial vocabulary (`home.html` index, `kicker`/`lead` classes, `band-surface` style).

## Problem

`templates/single.html` rendered only `post-title` + `post-date` + `post-content`. Because client posts embed their own cover/hero (with the title) in the post content, the title appeared twice on the live page and the date was unwanted. The blunt fix (removing title + date entirely) was wrong: it makes every post depend on hand-built content heroes and loses the title/date for posts that don't.

The right fix is a real single-post template that owns the article header, so authors stop hand-building heroes into post content.

## Design

Top → bottom structure of `templates/single.html`:

1. **Header** template part — unchanged.
2. **Masthead** — a centered group on `is-style-band-surface` (the same tinted band as the `home.html` "INSIGHTS" hero), constrained layout, containing in order:
   - **Category** — `wp:post-terms` (`term: category`) with `className: "kicker"` → uppercase accent eyebrow.
   - **Title** — `wp:post-title` (`level: 1`, `textAlign: center`). The title now lives in the template, not duplicated inside each post's content.
   - **Date** — `wp:post-date` (`format: "F j, Y"`, `fontSize: sm`, `textAlign: center`).
   - *No standfirst/excerpt* — deliberately omitted to avoid WordPress auto-generating an excerpt from the body (which would duplicate the opening) when a post has no manual excerpt.
3. **Featured image** — `wp:post-featured-image`, `align: wide`, `aspectRatio: "16/9"`. An elegant banner, not full-bleed; editable to full in the Site Editor.
4. **Post content** — `wp:post-content` (`align: full`). Default blocks land in the 720px reading column (`theme.json` `contentSize`); wide/full media can break out.
5. **Back to blog** — a single left-aligned link "← Back to blog". Static templates can't resolve the posts-page URL dynamically, so the href is hardcoded (`/blog/`) and adjusted once per fork in the Site Editor.
6. **Footer** template part — unchanged.

## Styling notes

- `is-style-band-surface`, `kicker`, and `wp:post-featured-image` styling hooks already exist in `assets/css/theme.css` / `assets/css/insight-card.css`.
- `.kicker` sets font/letter-spacing/transform/accent color on its element. `wp:post-terms` renders `<a>` links inside; if the category links don't pick up the kicker accent color, add a small `.kicker a { color: inherit; }`-style rule. Keep any CSS additions minimal and scoped.
- No new blocks or PHP required; this is a block-template (HTML) change, optionally plus a tiny CSS rule.

## Out of scope

- Prev/next post navigation, author byline, reading time, related posts, CTA band — considered and deliberately excluded for now.
- `home.html` / `archive.html` / `index.html` — unchanged.

## Deployment caveat

`single.html` is a file-based template. If the Single template was ever customized in the Site Editor on the live site, the DB copy takes precedence and the file change won't show until **Appearance → Editor → Templates → Single → ⋮ → Clear customizations**. Production is wp-admin only.

## Acceptance

- A single post renders: category eyebrow → centered title → date → wide featured image → body in reading column → back-to-blog link.
- The title appears exactly once (from the template); the in-content duplicate hero is removed from posts.
- Posts without a manual excerpt show no auto-generated standfirst.
