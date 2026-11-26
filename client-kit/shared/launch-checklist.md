# Launch checklist — __PEDIMENT_NAME__

Work through this list before the site goes live: before the first production deploy, a
domain switch, or removing a "coming soon" page. Run `/pediment:launch` — it checks the
`[auto]` items against the site and asks you about the `[manual]` ones, then updates this file.

The defaults assume a German business under German and EU law. This list is a reminder, not
legal advice: take legal texts from a generator (for example eRecht24) or from a lawyer.

How to mark an item:

- `[x]` with a short note: `— done 2026-11-26, checked on staging`.
- `[x]` with `n/a` and the reason: `— n/a: no forms on this site`.

The site is ready to launch when no item is `[ ]`.

Planned launch date: _not set_
Last check: _never_

## Legal pages

- [ ] **LEGAL-1** `[auto]` An Impressum (§ 5 DDG) exists, with real content, and every page
  links to it (usually the footer).
- [ ] **LEGAL-2** `[auto]` A privacy policy (Datenschutzerklärung) exists, with real content,
  and every page links to it.
- [ ] **LEGAL-3** `[manual]` The privacy policy covers everything the site does: hosting and
  server logs, the forms (and how many days submissions are kept — Settings → Pediment →
  Forms, 90 by default), fonts, embeds, analytics, newsletter, and every other third party.
- [ ] **LEGAL-4** `[manual]` Each language has its own Impressum and privacy policy, or a
  translated version of them.
- [ ] **LEGAL-5** `[manual]` A data processing agreement (AVV, Art. 28 GDPR) is signed with the
  host and with every mail, analytics or other provider that processes visitor data.
- [ ] **LEGAL-6** `[manual]` Accessibility law (BFSG, since June 2025): decide if it applies.
  It covers services to consumers such as online booking or shops; micro-enterprises that
  offer services are exempt. If it applies, add the accessibility statement.
- [ ] **LEGAL-7** `[manual]` The client has the rights to every image, font, text and video
  on the site (stock licences, photographer credits where required).

## Privacy and consent

- [ ] **PRIV-1** `[auto]` No request goes to a third party before the visitor consents. The
  check lists every external host in the HTML; you decide for each one. Confirm in a browser
  with a fresh profile: DevTools → Network, load the page, accept nothing.
- [ ] **PRIV-2** `[auto]` Fonts load from the site itself, never from Google Fonts or another
  font CDN.
- [ ] **PRIV-3** `[manual]` YouTube, Vimeo and map embeds load only after a click
  (two-click solution), or use the privacy-enhanced mode and are named in the privacy policy.
- [ ] **PRIV-4** `[manual]` Cookie banner: the site needs one only if it sets non-essential
  cookies or uses trackers (analytics, ads, marketing pixels). Write the decision here. If it
  needs one, "Reject" is as easy to reach as "Accept", and nothing loads before consent.
- [ ] **PRIV-5** `[manual]` Analytics, if any: cookieless (for example a self-hosted Matomo
  without cookies), or loaded only after consent.

## Forms

- [ ] **FORM-1** `[manual]` Every form links to the privacy policy next to the submit button.
- [ ] **FORM-2** `[manual]` A test submission from the live domain arrives at the configured
  destination (email inbox, webhook) and does not land in spam.
- [ ] **FORM-3** `[manual]` The retention period (Settings → Pediment → Forms) matches what
  the privacy policy says.
- [ ] **FORM-4** `[manual]` The sending domain has SPF, DKIM and DMARC records, so form emails
  reach the inbox.

## SEO

- [ ] **SEO-1** `[auto]` Search engines may index the site: Settings → Reading → "Discourage
  search engines" is off, so no page has `noindex`.
- [ ] **SEO-2** `[auto]` `robots.txt` does not block the site.
- [ ] **SEO-3** `[auto]` The sitemap (`/wp-sitemap.xml`) loads and lists the real pages.
- [ ] **SEO-4** `[auto]` Every page has its own `<title>` and a meta description (the page
  excerpt; see Pediment's `docs/seo.md`).
- [ ] **SEO-5** `[auto]` A share image exists (featured image, or the site default from the
  manifest's `site.share_image`, 1200 × 630).
- [ ] **SEO-6** `[auto]` The site has a site icon (favicon): Site Editor → Styles, or
  Settings → General → Site Icon.
- [ ] **SEO-7** `[auto]` An unknown URL returns status 404 and shows the 404 page.
- [ ] **SEO-8** `[manual]` Ported site only: every old URL redirects (301) to its new page.
  Take the list from the old site's sitemap and test each one.
- [ ] **SEO-9** `[manual]` Multilingual site only: each language version links to the others
  with `hreflang`, and the language switcher works on every page.
- [ ] **SEO-10** `[manual]` After launch: the domain is verified in Google Search Console (and
  Bing Webmaster Tools), and the sitemap is submitted.

## Domain and operations

- [ ] **OPS-1** `[auto]` The site loads over HTTPS, and `http://` redirects to `https://`.
- [ ] **OPS-2** `[manual]` Only one host name serves the site: `www` redirects to the bare
  domain, or the other way around.
- [ ] **OPS-3** `[manual]` Backups run on a schedule, and one restore was tested.
- [ ] **OPS-4** `[manual]` `WP_DEBUG` and `WP_DEBUG_DISPLAY` are off on production (Tools →
  Site Health shows no debug warning).
- [ ] **OPS-5** `[manual]` Users: no test accounts, no shared admin logins, and no application
  passwords that are no longer needed (for example from MCP work during the build).
- [ ] **OPS-6** `[manual]` Plugin and core updates have an owner, and an uptime monitor watches
  the site.

## Content and quality

- [ ] **QA-1** `[auto]` No placeholder text remains: "Lorem ipsum", `example.com`, `[TODO`,
  the copy of Pediment's default footer, and WordPress's "Sample Page" and "Hello world!" post
  (delete them).
- [ ] **QA-2** `[manual]` The footer shows the client's real details. Pediment's default
  footer (`pediment/footer`) is placeholder copy: replace it with a client
  `patterns/footer.php` that has `Slug: pediment/footer` and links the legal pages. A theme
  pattern registers before the plugin's, so it takes its place on every template. WordPress
  caches the list of theme pattern files per theme version: the next theme release picks the
  new file up; locally, run `npx wp-env run cli wp eval 'wp_get_theme()->delete_pattern_cache();'`.
- [ ] **QA-3** `[manual]` Every image has meaningful alt text, or empty alt text when it is
  decoration only.
- [ ] **QA-4** `[manual]` Every page looks right at 375, 768 and 1440 px, and no link is
  broken.
- [ ] **QA-5** `[manual]` Lighthouse on the home page and one content page: no red score for
  performance, accessibility or SEO.
- [ ] **QA-6** `[auto]` No `localhost`, `.local` or staging URL remains in the content. The
  check finds the first two; search for the staging host name by hand.

## Handover

- [ ] **HAND-1** `[manual]` The client has their own admin account, and knows how to edit a
  page.
- [ ] **HAND-2** `[manual]` Hosting, domain and email logins are stored where the client and
  you can find them.
