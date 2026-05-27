# Pediment Hero — Stat-Card Variant + Fork-Friendly Variant Filter (Plan 5)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Steps use checkbox (`- [ ]`).

**Goal:** Add a `stat-card` variant to `starter/hero` (mockup split hero: eyebrow chip + headline + lead + primary/secondary pill buttons + trust ticks | photo card with frosted-glass stat overlay). **And** make the parent opinionated-but-fork-friendly: the hero variant list flows through an `apply_filters( 'starter_hero_variants', … )` extension point so a child theme removes a variant (e.g. `stat-card`) with one line of PHP — no block fork. Existing `default`/`centered`/`media-bg` variants stay byte-identical.

**Architecture:** Extend the block — one enum value (`stat-card`) + new attributes. The canonical variant list lives in a tiny parent helper `starter_hero_variants()` (`inc/hero-variants.php`, required from `functions.php`, mirroring the `inc/icons.php`/`inc/block-styles.php` pattern) returning `apply_filters( 'starter_hero_variants', [ 'default','centered','media-bg','stat-card' ] )`. **render.php is authoritative**: it normalizes an unknown/filtered-out variant to `default` (so removing `stat-card` via the filter makes any stat-card hero render the default markup). The editor reflects the filter via a `window.starterHeroVariants` global (parent prints it on `enqueue_block_editor_assets`); `edit.tsx` builds its SelectControl from that global with a hard-coded fallback. `block.json` keeps the full shipped enum (the superset the parent ships; the filter is a runtime opt-out, not a block.json change — so the existing HeroTest enum guard stays valid). render.php's non-stat-card path stays byte-identical so the 3 behavioral tests keep passing.

**Tech Stack:** WordPress FSE block theme, apiVersion 3, `@wordpress/scripts` (TS/SCSS), PHP 8.1, PHPUnit (wp-env).

**Spec:** `docs/superpowers/specs/2026-05-17-pediment-design-system-design.md` (hero photo + frosted-stats overlay). Visual ref: `docs/design/pediment-mockup.html` (`.hero`/`.hero-fig`/`.glass`). Builds on merged Plans 1–4 (tokens, `.chip`, `--r-*`/`--section`, `accent-tint`, the `inc/` + `functions.php`-require convention). Architecture decision (recorded): the parent ships the opinionated Pediment look as default; this plan adds the fork-friendly opt-out hook that decision implies.

**Scope:** `src/blocks/hero/*`, `tests/phpunit/BlockRender/HeroTest.php`, plus the extension-point infra `inc/hero-variants.php` + a one-line `functions.php` require. NOT here: pull-quote→testimonial (Plan 6), blog-index→Insights (Plan 7), child-theme.json reconciliation (separate). No other blocks/parts/theme.json/registration/`mega-*`.

**Pre-existing test contract:** `HeroTest::test_block_json_variant_enum_excludes_split` asserts `enum === ['default','centered','media-bg']` (anti-phantom guard). Adding `stat-card` requires updating it to the new exact enum AND keeping its intent (every shipped variant must render) — done via `test_block_json_variant_enum_is_exact_and_renderable`. `test_block_json_description_does_not_mention_split` must stay green (new description mentions stat-card, never "split"). The 3 behavioral tests must stay green ⇒ non-stat-card render path byte-identical.

**Verification constraint:** Worktree NOT mounted in wp-env. Per task: env-independent gates — `npm run build`, `php -l`, valid `block.json` JSON, SCSS brace-balance, static trace of every HeroTest method against the shipped render.php. Full PHPUnit POST-MERGE in `:8888`/`:8889`. **Definition of done: post-merge PHPUnit green (all HeroTest incl. stat-card + filter cases + the rest); `npm run build` clean.**

---

## File Structure

| File | Action |
|---|---|
| `src/blocks/hero/block.json` | Modify — enum + new attributes + description |
| `tests/phpunit/BlockRender/HeroTest.php` | Modify — enum guard, keep 3 behavioral, add stat-card + filter cases |
| `inc/hero-variants.php` | Create — `starter_hero_variants()` + editor global |
| `functions.php` | Modify — one-line require of `inc/hero-variants.php` |
| `src/blocks/hero/render.php` | Modify — filter-normalize variant; add `stat-card` branch; else byte-identical |
| `src/blocks/hero/edit.tsx` | Modify — variant options from filter global + inspector fields |
| `src/blocks/hero/style.scss` | Modify — append `.is-variant-stat-card` rules |
| `src/blocks/hero/index.tsx` | Unchanged |

Each task commits.

---

### Task 1: block.json attributes + HeroTest contract (incl. filter test)

**Files:** Modify `src/blocks/hero/block.json`; Modify `tests/phpunit/BlockRender/HeroTest.php`.

- [ ] **Step 1: Replace `src/blocks/hero/block.json` with EXACTLY:**
```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "starter/hero",
	"title": "Hero",
	"category": "starter",
	"description": "A page-opening hero with headline, subheadline, and primary CTA. Variants: default, centered, media-bg, stat-card.",
	"textdomain": "starter",
	"supports": { "html": false, "align": [ "wide", "full" ] },
	"attributes": {
		"variant": {
			"type": "string",
			"default": "default",
			"enum": [ "default", "centered", "media-bg", "stat-card" ]
		},
		"headline": { "type": "string", "default": "" },
		"subheadline": { "type": "string", "default": "" },
		"ctaText": { "type": "string", "default": "" },
		"ctaUrl": { "type": "string", "default": "" },
		"secondaryText": { "type": "string", "default": "" },
		"secondaryUrl": { "type": "string", "default": "" },
		"eyebrow": { "type": "string", "default": "" },
		"ticks": { "type": "array", "default": [] },
		"statValue": { "type": "string", "default": "" },
		"statText": { "type": "string", "default": "" },
		"metrics": { "type": "array", "default": [] },
		"mediaId": { "type": "number", "default": 0 }
	},
	"editorScript": "file:./index.js",
	"editorStyle": "file:./style-index.css",
  "style": "file:./style-index.css",
	"render": "file:./render.php"
}
```
(Tabs + the two-space-indented `"style"` line preserved exactly as the original.)

- [ ] **Step 2: Update `tests/phpunit/BlockRender/HeroTest.php`** — keep the `render()` helper and the 3 behavioral tests (`test_renders_headline_and_subheadline`, `test_renders_variant_class`, `test_omits_cta_when_url_is_empty`) EXACTLY as-is. Replace `test_block_json_variant_enum_excludes_split` with the updated guard, KEEP `test_block_json_description_does_not_mention_split`, and ADD the stat-card + filter cases. The full new tail of the class (from `test_block_json_variant_enum_excludes_split` onward) becomes EXACTLY:
```php
	public function test_block_json_variant_enum_is_exact_and_renderable() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/hero/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertSame(
			array( 'default', 'centered', 'media-bg', 'stat-card' ),
			$data['attributes']['variant']['enum'],
			'block.json variant enum must list exactly the variants the renderer ships'
		);
		$html = $this->render( array( 'variant' => 'stat-card', 'headline' => 'X' ) );
		$this->assertStringContainsString( 'is-variant-stat-card', $html );
	}

	public function test_block_json_description_does_not_mention_split() {
		$path = dirname( __DIR__, 3 ) . '/src/blocks/hero/block.json';
		$this->assertFileIsReadable( $path );
		$data = json_decode( file_get_contents( $path ), true );
		$this->assertIsArray( $data );
		$this->assertStringNotContainsStringIgnoringCase( 'split', $data['description'] );
	}

	public function test_stat_card_renders_eyebrow_secondary_and_ticks() {
		$html = $this->render(
			array(
				'variant'       => 'stat-card',
				'headline'      => 'We help leaders',
				'subheadline'   => 'Senior-led work.',
				'eyebrow'       => 'Strategy Consulting',
				'ctaText'       => 'Start',
				'ctaUrl'        => '/start',
				'secondaryText' => 'Our work',
				'secondaryUrl'  => '/work',
				'ticks'         => array( '120+ engagements', 'Global delivery' ),
			)
		);
		$this->assertStringContainsString( 'starter-hero__eyebrow', $html );
		$this->assertStringContainsString( 'Strategy Consulting', $html );
		$this->assertStringContainsString( 'href="/start"', $html );
		$this->assertStringContainsString( 'href="/work"', $html );
		$this->assertStringContainsString( 'Our work', $html );
		$this->assertStringContainsString( 'starter-hero__tick', $html );
		$this->assertStringContainsString( '120+ engagements', $html );
		$this->assertStringContainsString( 'Global delivery', $html );
	}

	public function test_stat_card_renders_glass_stat_and_metrics() {
		$html = $this->render(
			array(
				'variant'   => 'stat-card',
				'headline'  => 'H',
				'statValue' => '+34%',
				'statText'  => 'margin improvement',
				'metrics'   => array(
					array( 'value' => '18', 'label' => 'countries' ),
					array( 'value' => '94%', 'label' => 'repeat clients' ),
				),
			)
		);
		$this->assertStringContainsString( 'starter-hero__glass', $html );
		$this->assertStringContainsString( '+34%', $html );
		$this->assertStringContainsString( 'margin improvement', $html );
		$this->assertStringContainsString( 'starter-hero__metric', $html );
		$this->assertStringContainsString( '18', $html );
		$this->assertStringContainsString( 'countries', $html );
		$this->assertStringContainsString( '94%', $html );
		$this->assertStringContainsString( 'repeat clients', $html );
	}

	public function test_stat_card_omits_secondary_when_url_missing() {
		$html = $this->render(
			array(
				'variant'       => 'stat-card',
				'headline'      => 'H',
				'secondaryText' => 'Our work',
				'secondaryUrl'  => '',
			)
		);
		$this->assertStringNotContainsString( 'starter-hero__cta--secondary', $html );
	}

	public function test_default_variant_markup_unchanged() {
		$html = $this->render(
			array( 'variant' => 'default', 'headline' => 'D', 'subheadline' => 'S' )
		);
		$this->assertStringContainsString( 'is-variant-default', $html );
		$this->assertStringContainsString( 'starter-hero__headline', $html );
		$this->assertStringNotContainsString( 'starter-hero__glass', $html );
		$this->assertStringNotContainsString( 'starter-hero__eyebrow', $html );
	}

	public function test_starter_hero_variants_filter_is_default_superset() {
		$this->assertTrue( function_exists( 'starter_hero_variants' ) );
		$this->assertSame(
			array( 'default', 'centered', 'media-bg', 'stat-card' ),
			starter_hero_variants()
		);
	}

	public function test_filter_removing_stat_card_falls_back_to_default() {
		$cb = static function ( $variants ) {
			return array_values( array_diff( $variants, array( 'stat-card' ) ) );
		};
		add_filter( 'starter_hero_variants', $cb );
		try {
			$html = $this->render(
				array(
					'variant'   => 'stat-card',
					'headline'  => 'H',
					'statValue' => '+34%',
				)
			);
			$this->assertStringContainsString( 'is-variant-default', $html );
			$this->assertStringNotContainsString( 'is-variant-stat-card', $html );
			$this->assertStringNotContainsString( 'starter-hero__glass', $html );
		} finally {
			remove_filter( 'starter_hero_variants', $cb );
		}
	}
```

- [ ] **Step 3: Verify (env-independent)** — `python3 -c "import json;json.load(open('src/blocks/hero/block.json'));print('JSON-OK')"`; enum is exactly the 4 values, description has "stat-card" and not "split"; `php -l tests/phpunit/BlockRender/HeroTest.php`; `npm run build` compiles. (Two new tests reference `starter_hero_variants()` / the filter behavior — they go green once Tasks 2 & 3 land; that's expected TDD. The 3 behavioral + description tests stay green now.) Only those 2 files changed.

- [ ] **Step 4: Commit**
```bash
git add src/blocks/hero/block.json tests/phpunit/BlockRender/HeroTest.php
git commit -m "feat(hero): block.json stat-card variant + HeroTest (incl. filter)"
```

---

### Task 2: `starter_hero_variants()` extension point

**Files:** Create `inc/hero-variants.php`; Modify `functions.php`.

- [ ] **Step 1: Create `inc/hero-variants.php` with EXACTLY:**
```php
<?php
/**
 * Hero variant registry — the fork-friendly extension point.
 *
 * The parent ships an opinionated set of hero variants. A child theme can
 * remove one with a single line, e.g.:
 *
 *   add_filter( 'starter_hero_variants', fn( $v ) => array_diff( $v, [ 'stat-card' ] ) );
 *
 * render.php normalizes any variant not in this list to "default", and the
 * block editor only offers the filtered list.
 *
 * @package Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The allowed hero variants (filterable).
 *
 * @return string[] Re-indexed list of variant slugs.
 */
function starter_hero_variants() {
	$defaults = array( 'default', 'centered', 'media-bg', 'stat-card' );
	$variants = apply_filters( 'starter_hero_variants', $defaults );
	$variants = is_array( $variants ) ? array_values( array_filter( array_map( 'strval', $variants ) ) ) : $defaults;
	if ( ! in_array( 'default', $variants, true ) ) {
		array_unshift( $variants, 'default' );
	}
	return $variants;
}

/**
 * Expose the filtered variant list to the block editor so the Hero
 * inspector only offers variants the site actually ships.
 */
add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_add_inline_script(
			'wp-blocks',
			'window.starterHeroVariants = ' . wp_json_encode( starter_hero_variants() ) . ';',
			'after'
		);
	}
);
```

- [ ] **Step 2: Modify `functions.php`** — add `require_once __DIR__ . '/inc/hero-variants.php';` directly after the existing `require_once __DIR__ . '/inc/block-styles.php';` line (match that exact idiom/placement; read the file first). Change nothing else.

- [ ] **Step 3: Verify** — `php -l inc/hero-variants.php && php -l functions.php`; `npm run build` unaffected. `git diff functions.php` shows ONLY the one added require line (addition-only). Static-trace `test_starter_hero_variants_filter_is_default_superset`: with no filter, `starter_hero_variants()` returns exactly `['default','centered','media-bg','stat-card']` (defaults, array_values, 'default' already present) ⇒ passes. `wp-blocks` is a core editor script handle always present in the block editor; `wp_add_inline_script(...,'after')` runs before block edit components ⇒ `window.starterHeroVariants` defined for edit.tsx. Only the 2 files changed.

- [ ] **Step 4: Commit**
```bash
git add inc/hero-variants.php functions.php
git commit -m "feat(hero): starter_hero_variants() filter + editor global"
```

---

### Task 3: render.php — filter-normalize + stat-card branch

**Files:** Modify `src/blocks/hero/render.php`.

Normalize the variant against `starter_hero_variants()` BEFORE building wrapper attributes/branching. The existing default/centered/media-bg markup stays byte-identical (wrapped in `else`). Add the `stat-card` branch.

- [ ] **Step 1: Replace `src/blocks/hero/render.php` with EXACTLY:**
```php
<?php
/**
 * Server-side render for starter/hero.
 *
 * @var array $attributes
 */

$variant     = isset( $attributes['variant'] ) ? (string) $attributes['variant'] : 'default';
$headline    = isset( $attributes['headline'] ) ? (string) $attributes['headline'] : '';
$subheadline = isset( $attributes['subheadline'] ) ? (string) $attributes['subheadline'] : '';
$cta_text    = isset( $attributes['ctaText'] ) ? (string) $attributes['ctaText'] : '';
$cta_url     = isset( $attributes['ctaUrl'] ) ? (string) $attributes['ctaUrl'] : '';
$media_id    = isset( $attributes['mediaId'] ) ? (int) $attributes['mediaId'] : 0;

$allowed = function_exists( 'starter_hero_variants' )
	? starter_hero_variants()
	: array( 'default', 'centered', 'media-bg', 'stat-card' );
if ( ! in_array( $variant, $allowed, true ) ) {
	$variant = 'default';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'starter-hero is-variant-' . sanitize_html_class( $variant ),
	)
);

if ( 'stat-card' === $variant ) {
	$eyebrow    = isset( $attributes['eyebrow'] ) ? (string) $attributes['eyebrow'] : '';
	$sec_text   = isset( $attributes['secondaryText'] ) ? (string) $attributes['secondaryText'] : '';
	$sec_url    = isset( $attributes['secondaryUrl'] ) ? (string) $attributes['secondaryUrl'] : '';
	$stat_value = isset( $attributes['statValue'] ) ? (string) $attributes['statValue'] : '';
	$stat_text  = isset( $attributes['statText'] ) ? (string) $attributes['statText'] : '';
	$ticks      = ( isset( $attributes['ticks'] ) && is_array( $attributes['ticks'] ) ) ? $attributes['ticks'] : array();
	$metrics    = ( isset( $attributes['metrics'] ) && is_array( $attributes['metrics'] ) ) ? $attributes['metrics'] : array();

	ob_start();
	?>
	<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<div class="starter-hero__col">
			<?php if ( '' !== $eyebrow ) : ?>
				<span class="starter-hero__eyebrow"><?php echo wp_kses_post( $eyebrow ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $headline ) : ?>
				<h1 class="starter-hero__headline"><?php echo wp_kses_post( $headline ); ?></h1>
			<?php endif; ?>
			<?php if ( '' !== $subheadline ) : ?>
				<p class="starter-hero__subheadline"><?php echo wp_kses_post( $subheadline ); ?></p>
			<?php endif; ?>
			<div class="starter-hero__actions">
				<?php if ( '' !== $cta_text && '' !== $cta_url ) : ?>
					<a class="starter-hero__cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo wp_kses_post( $cta_text ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $sec_text && '' !== $sec_url ) : ?>
					<a class="starter-hero__cta starter-hero__cta--secondary" href="<?php echo esc_url( $sec_url ); ?>"><?php echo wp_kses_post( $sec_text ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $ticks ) ) : ?>
				<ul class="starter-hero__ticks">
					<?php foreach ( $ticks as $tick ) : ?>
						<li class="starter-hero__tick"><?php echo wp_kses_post( (string) $tick ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<figure class="starter-hero__fig">
			<?php
			if ( $media_id ) {
				echo wp_get_attachment_image( $media_id, 'large', false, array( 'class' => 'starter-hero__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
			<?php if ( '' !== $stat_value || '' !== $stat_text || ! empty( $metrics ) ) : ?>
				<div class="starter-hero__glass">
					<?php if ( '' !== $stat_value ) : ?>
						<div class="starter-hero__stat-value"><?php echo wp_kses_post( $stat_value ); ?></div>
					<?php endif; ?>
					<?php if ( '' !== $stat_text ) : ?>
						<div class="starter-hero__stat-text"><?php echo wp_kses_post( $stat_text ); ?></div>
					<?php endif; ?>
					<?php if ( ! empty( $metrics ) ) : ?>
						<div class="starter-hero__metrics">
							<?php foreach ( $metrics as $m ) : ?>
								<?php
								$mv = is_array( $m ) && isset( $m['value'] ) ? (string) $m['value'] : '';
								$ml = is_array( $m ) && isset( $m['label'] ) ? (string) $m['label'] : '';
								?>
								<div class="starter-hero__metric">
									<b><?php echo wp_kses_post( $mv ); ?></b>
									<span><?php echo wp_kses_post( $ml ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</figure>
	</section>
	<?php
	echo ob_get_clean();
	return;
}

$bg_style = '';
if ( 'media-bg' === $variant && $media_id ) {
	$url = wp_get_attachment_image_url( $media_id, 'full' );
	if ( $url ) {
		$bg_style = ' style="background-image:url(' . esc_url( $url ) . ');"';
	}
}

ob_start();
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<?php if ( $headline ) : ?>
		<h1 class="starter-hero__headline"><?php echo wp_kses_post( $headline ); ?></h1>
	<?php endif; ?>
	<?php if ( $subheadline ) : ?>
		<p class="starter-hero__subheadline"><?php echo wp_kses_post( $subheadline ); ?></p>
	<?php endif; ?>
	<?php if ( $cta_text && $cta_url ) : ?>
		<a class="starter-hero__cta" href="<?php echo esc_url( $cta_url ); ?>">
			<?php echo wp_kses_post( $cta_text ); ?>
		</a>
	<?php endif; ?>
</section>
<?php
echo ob_get_clean();
```
(Everything from `$bg_style = '';` onward is the original render verbatim. Net changes vs. original: the `$allowed`/normalize block, and the inserted `if ( 'stat-card' === $variant ) { … return; }` branch.)

- [ ] **Step 2: Verify** — `php -l src/blocks/hero/render.php`; `npm run build`. Static-trace ALL HeroTest methods: 3 behavioral + `test_default_variant_markup_unchanged` → variant in default allowed list → unchanged else-branch markup (no `__glass`/`__eyebrow`) ⇒ pass; `test_block_json_variant_enum_is_exact_and_renderable` + the 3 stat-card tests → default filter includes `stat-card` → branch runs ⇒ pass; `test_filter_removing_stat_card_falls_back_to_default` → filter drops `stat-card` → not in `$allowed` → `$variant='default'` → else branch → `is-variant-default`, no `__glass` ⇒ pass; `test_starter_hero_variants_filter_is_default_superset` covered by Task 2. Only `render.php` changed.

- [ ] **Step 3: Commit**
```bash
git add src/blocks/hero/render.php
git commit -m "feat(hero): filter-normalized variant + stat-card render branch"
```

---

### Task 4: edit.tsx — variant options from filter global + inspector fields

**Files:** Modify `src/blocks/hero/edit.tsx`.

- [ ] **Step 1: Replace `src/blocks/hero/edit.tsx` with EXACTLY:**
```tsx
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
	MediaUpload,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	Button,
} from '@wordpress/components';

type Metric = { value: string; label: string };
type Attrs = {
	variant: 'default' | 'centered' | 'media-bg' | 'stat-card';
	headline: string;
	subheadline: string;
	ctaText: string;
	ctaUrl: string;
	secondaryText: string;
	secondaryUrl: string;
	eyebrow: string;
	ticks: string[];
	statValue: string;
	statText: string;
	metrics: Metric[];
	mediaId: number;
};

const ALL_VARIANTS = [
	'default',
	'centered',
	'media-bg',
	'stat-card',
] as const;
const LABELS: Record< string, string > = {
	default: 'Default',
	centered: 'Centered',
	'media-bg': 'Media BG',
	'stat-card': 'Stat card',
};

function allowedVariants(): string[] {
	const w = ( window as unknown as { starterHeroVariants?: unknown } )
		.starterHeroVariants;
	if ( Array.isArray( w ) && w.length ) {
		return w.map( String );
	}
	return [ ...ALL_VARIANTS ];
}

export default function Edit( {
	attributes,
	setAttributes,
}: {
	attributes: Attrs;
	setAttributes: ( a: Partial< Attrs > ) => void;
} ) {
	const blockProps = useBlockProps( {
		className: `starter-hero is-variant-${ attributes.variant }`,
	} );
	const isStatCard = attributes.variant === 'stat-card';
	const options = allowedVariants().map( ( v ) => ( {
		label: LABELS[ v ] ?? v,
		value: v,
	} ) );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Hero settings', 'starter' ) }>
					<SelectControl
						label={ __( 'Variant', 'starter' ) }
						value={ attributes.variant }
						options={ options }
						onChange={ ( v ) =>
							setAttributes( {
								variant: v as Attrs[ 'variant' ],
							} )
						}
					/>
					<TextControl
						label={ __( 'CTA URL', 'starter' ) }
						value={ attributes.ctaUrl }
						onChange={ ( v ) => setAttributes( { ctaUrl: v } ) }
					/>
					{ isStatCard && (
						<>
							<TextControl
								label={ __( 'Eyebrow', 'starter' ) }
								value={ attributes.eyebrow }
								onChange={ ( v ) =>
									setAttributes( { eyebrow: v } )
								}
							/>
							<TextControl
								label={ __( 'Secondary CTA text', 'starter' ) }
								value={ attributes.secondaryText }
								onChange={ ( v ) =>
									setAttributes( { secondaryText: v } )
								}
							/>
							<TextControl
								label={ __( 'Secondary CTA URL', 'starter' ) }
								value={ attributes.secondaryUrl }
								onChange={ ( v ) =>
									setAttributes( { secondaryUrl: v } )
								}
							/>
							<TextareaControl
								label={ __(
									'Trust ticks (one per line)',
									'starter'
								) }
								value={ ( attributes.ticks || [] ).join(
									'\n'
								) }
								onChange={ ( v ) =>
									setAttributes( {
										ticks: v
											.split( '\n' )
											.map( ( s ) => s.trim() )
											.filter( Boolean ),
									} )
								}
							/>
							<TextControl
								label={ __( 'Stat value', 'starter' ) }
								value={ attributes.statValue }
								onChange={ ( v ) =>
									setAttributes( { statValue: v } )
								}
							/>
							<TextControl
								label={ __( 'Stat text', 'starter' ) }
								value={ attributes.statText }
								onChange={ ( v ) =>
									setAttributes( { statText: v } )
								}
							/>
							<TextareaControl
								label={ __(
									'Metrics — “value | label” per line',
									'starter'
								) }
								value={ ( attributes.metrics || [] )
									.map(
										( m ) => `${ m.value } | ${ m.label }`
									)
									.join( '\n' ) }
								onChange={ ( v ) =>
									setAttributes( {
										metrics: v
											.split( '\n' )
											.map( ( line ) => {
												const [ value, label ] =
													line.split( '|' );
												return {
													value: ( value || '' ).trim(),
													label: ( label || '' ).trim(),
												};
											} )
											.filter(
												( m ) =>
													m.value !== '' ||
													m.label !== ''
											),
									} )
								}
							/>
						</>
					) }
					{ ( attributes.variant === 'media-bg' ||
						isStatCard ) && (
						<MediaUpload
							allowedTypes={ [ 'image' ] }
							onSelect={ ( media: any ) =>
								setAttributes( { mediaId: media.id } )
							}
							render={ ( { open }: { open: () => void } ) => (
								<Button variant="secondary" onClick={ open }>
									{ attributes.mediaId
										? __( 'Replace image', 'starter' )
										: __( 'Pick image', 'starter' ) }
								</Button>
							) }
						/>
					) }
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				{ isStatCard && (
					<RichText
						tagName="span"
						className="starter-hero__eyebrow"
						value={ attributes.eyebrow }
						onChange={ ( v ) =>
							setAttributes( { eyebrow: v } )
						}
						placeholder={ __( 'Eyebrow…', 'starter' ) }
					/>
				) }
				<RichText
					tagName="h1"
					className="starter-hero__headline"
					value={ attributes.headline }
					onChange={ ( v ) => setAttributes( { headline: v } ) }
					placeholder={ __( 'Headline…', 'starter' ) }
				/>
				<RichText
					tagName="p"
					className="starter-hero__subheadline"
					value={ attributes.subheadline }
					onChange={ ( v ) =>
						setAttributes( { subheadline: v } )
					}
					placeholder={ __( 'Subheadline…', 'starter' ) }
				/>
				<RichText
					tagName="span"
					className="starter-hero__cta"
					value={ attributes.ctaText }
					onChange={ ( v ) => setAttributes( { ctaText: v } ) }
					placeholder={ __( 'CTA text…', 'starter' ) }
				/>
			</div>
		</>
	);
}
```

- [ ] **Step 2: Verify** — `npm run build` compiles (authoritative TS gate via ts-loader; do NOT rely on standalone `npx tsc`, which mis-fires without the project tsconfig — only flag a TS issue if `npm run build` fails). Only `edit.tsx` changed.

- [ ] **Step 3: Commit**
```bash
git add src/blocks/hero/edit.tsx
git commit -m "feat(hero): editor variant options from starter_hero_variants + stat-card controls"
```

---

### Task 5: style.scss — `.is-variant-stat-card`

**Files:** Modify `src/blocks/hero/style.scss` (APPEND only; existing rules untouched).

- [ ] **Step 1: Append to the END of `src/blocks/hero/style.scss`** (leading blank line, then EXACTLY):
```scss

/* stat-card variant: split text + photo-with-glass-stats */
.starter-hero.is-variant-stat-card {
  display: grid;
  grid-template-columns: 1.05fr .95fr;
  gap: clamp(32px, 5vw, 60px);
  align-items: center;
  padding-block: clamp(56px, 7vw, 96px);

  .starter-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    background: var(--wp--preset--color--accent-tint);
    color: var(--wp--preset--color--accent-hover);
    font-size: 13px;
    font-weight: 600;
    padding: 8px 16px;
    border-radius: var(--r-pill, 999px);
    margin-bottom: var(--wp--preset--spacing--30);
  }

  .starter-hero__subheadline { max-width: 38ch; }

  .starter-hero__actions {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: var(--wp--preset--spacing--40);
  }

  .starter-hero__cta--secondary {
    background: var(--wp--preset--color--surface);
    color: var(--wp--preset--color--text);
    border: 1.5px solid var(--wp--preset--color--border);
  }
  .starter-hero__cta--secondary:hover {
    background: var(--wp--preset--color--surface);
    color: var(--wp--preset--color--accent);
    border-color: var(--wp--preset--color--accent);
  }

  .starter-hero__ticks {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 14px 30px;
  }
  .starter-hero__tick {
    color: var(--wp--preset--color--text-muted);
    font-size: .92rem;
    font-weight: 600;
  }

  .starter-hero__fig {
    position: relative;
    margin: 0;
    border-radius: var(--r-lg, 20px);
    overflow: hidden;
    box-shadow: var(--wp--preset--shadow--medium);
    aspect-ratio: 5 / 6;
    background: var(--wp--preset--color--primary);
  }
  .starter-hero__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .starter-hero__glass {
    position: absolute;
    left: 18px;
    right: 18px;
    bottom: 18px;
    background: color-mix(in srgb, var(--wp--preset--color--primary) 60%, transparent);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, .16);
    border-radius: 16px;
    padding: 22px 24px;
    color: #fff;
  }
  .starter-hero__stat-value {
    font-size: 2.4rem;
    font-weight: 800;
    letter-spacing: -.02em;
    line-height: 1;
  }
  .starter-hero__stat-text {
    color: #c9d6ec;
    font-size: .88rem;
    margin-top: 6px;
  }
  .starter-hero__metrics {
    display: flex;
    gap: 22px;
    margin-top: 18px;
    border-top: 1px solid rgba(255, 255, 255, .16);
    padding-top: 16px;
  }
  .starter-hero__metric b {
    display: block;
    font-size: 1.15rem;
    font-weight: 800;
  }
  .starter-hero__metric span {
    font-size: .74rem;
    color: #c9d6ec;
  }

  @media (max-width: 781px) {
    grid-template-columns: 1fr;
  }
}
```

- [ ] **Step 2: Verify** — `npm run build` compiles; SCSS brace-balanced; `git diff` append-only to `style.scss` (zero `-` lines; default/centered/media-bg rules byte-unchanged). Only `style.scss` changed.

- [ ] **Step 3: Commit**
```bash
git add src/blocks/hero/style.scss
git commit -m "style(hero): stat-card variant (split + frosted glass stats)"
```

---

### Task 6: Build + cumulative guard

**Files:** none (verification only).

- [ ] **Step 1:** `npm run build` — compiles; `build/blocks/hero/{block.json,index.js,style-index.css,render.php}` present.
- [ ] **Step 2:** `git diff <branch-base>..HEAD --name-only` — ONLY `src/blocks/hero/{block.json,render.php,edit.tsx,style.scss}`, `tests/phpunit/BlockRender/HeroTest.php`, `inc/hero-variants.php`, `functions.php`. NO other blocks/parts/theme.json/registration/`mega-*`.
- [ ] **Step 3:** `php -l` on `render.php` + `inc/hero-variants.php` + `functions.php`; block.json valid JSON (enum exactly 4, "stat-card" in description, no "split"); SCSS brace-balanced; `functions.php` diff is the single added require; render.php's post-`stat-card` section (from `$bg_style = '';`) byte-identical to the pre-Plan-5 original. `git status --porcelain` clean (besides pre-existing untracked `docs/images/`).

**Post-merge (main checkout `:8888`/`:8889`, controller — NOT a worktree step):** `npm run build` → `npx wp-env run cli wp theme activate wp-starter-theme` → full `vendor/bin/phpunit` (expect: all HeroTest green incl. the updated enum guard, 4 stat-card cases, `test_default_variant_markup_unchanged`, `test_starter_hero_variants_filter_is_default_superset`, `test_filter_removing_stat_card_falls_back_to_default`; rest of suite unchanged). Playwright unaffected (no e2e changes; unrelated mega-menu failures stay out of scope).

---

## Self-Review

**Spec coverage:** hero photo + frosted-stats overlay as `stat-card` variant (Tasks 1,3,4,5); fork-friendly opt-out via `starter_hero_variants` filter authoritative in render + reflected in editor (Tasks 2,3,4); HeroTest contract incl. filter-removal fallback (Task 1); guard (Task 6). pull-quote→testimonial and blog-index→Insights are Plans 6 & 7 — not gaps. Architecture decision (opinionated parent + fork-friendly) directly realized by the filter.

**Placeholder scan:** none — all code complete.

**Type/name consistency:** Filter name `starter_hero_variants` identical across `inc/hero-variants.php` (definition + `apply_filters`), render.php (`function_exists`/call), HeroTest (`function_exists`/`add_filter`/`assertSame`), and the editor global `window.starterHeroVariants` (PHP `wp_json_encode` ⇄ edit.tsx `allowedVariants()`), and the docblock one-liner example. New attrs match block.json ⇄ render.php ⇄ edit.tsx `Attrs`. Render BEM (`__eyebrow/__col/__actions/__cta--secondary/__ticks/__tick/__fig/__img/__glass/__stat-value/__stat-text/__metrics/__metric`) match appended SCSS + HeroTest assertions. Enum `['default','centered','media-bg','stat-card']` identical in block.json and `test_block_json_variant_enum_is_exact_and_renderable`; the filter (not block.json) is the runtime opt-out, so that guard stays valid. Non-stat-card render path (from `$bg_style`) verbatim original ⇒ 3 behavioral + `test_default_variant_markup_unchanged` pass; `test_filter_removing_stat_card_falls_back_to_default` exercises the new normalize block (filtered-out → 'default' → else branch). `require_once` placement mirrors the Plan-1/2 `inc/` convention.

**Regression safety:** Changed set = hero block files + HeroTest + `inc/hero-variants.php` + one functions.php require (Task 6 Step 2 asserts) ⇒ other blocks' PHPUnit + all e2e untouched. SCSS append-only. The variant-normalize defaults to the full shipped set, so default behavior is unchanged; the filter only ever *narrows*, and render always falls back to `default` (guaranteed present via `starter_hero_variants()`'s `array_unshift` safeguard) so a mis-filter can't yield an unrenderable variant.
