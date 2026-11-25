# Search and share tags

Pediment prints the basic search and share tags in `<head>` of every public
view (`Pediment\Seo\HeadTags`, on `wp_head`). Every client site gets them
without theme code and without an SEO plugin.

## What is printed

| Tag | Source |
| --- | --- |
| `<meta name="description">` | The post excerpt. Blog front page: the tagline. Term archive: the term description. Post type archive: the post type description. No tag when the source is empty. Nothing is cut from the content: a search engine writes a better snippet than a cut sentence. |
| `og:title` | The post title. Front page: the site name, as in `<title>`. Archives: the term or post type name. |
| `og:description` | The meta description. When it is empty: the start of the post's text, cut at a word to about 155 characters. |
| `og:url` | The canonical URL of the post, the home URL on the front page, the archive link on an archive. |
| `og:type` | `article` for a single `post`, `website` for everything else. |
| `og:site_name` | The site name. |
| `og:locale` | The request's locale as `language_TERRITORY` (`de_DE` from `de_DE_formal`). Polylang and WPML switch it per language. |
| `og:image`, `og:image:width`, `og:image:height`, `og:image:alt` | The featured image (full size). Without one: the site's default share image. SVG is skipped. |
| `twitter:card` | `summary_large_image` with an image, `summary` without. |

A password-protected post shows no text. 404 pages, feeds and embeds get no
tags.

## Setting the description

The description is the post excerpt. Pediment turns on excerpt support for
pages (core leaves it off). Other post types need `excerpt` in their
`supports`; the seeder's manifest `post_types` have it by default.

- **Block editor:** the "Excerpt" panel in the page's settings sidebar.
- **AI:** `excerpt` in `pediment-write-page` ([mcp.md](mcp.md)).
- **Seed manifest:** `excerpt` per entry, and per language under
  `languages` ([seeding.md](seeding.md)). Written only when the seeder creates
  the page.

Write one or two sentences of about 150 characters, in plain text. The
excerpt belongs to the post, so each translation keeps its own.

## The default share image

The option `pediment_share_image` holds the attachment ID of the image a
page without a featured image shares. The manifest sets it while it is unset:

```php
'site'  => array( 'share_image' => 'share' ),
'media' => array(
	'share' => array( 'file' => 'seed/media/share.jpg', 'title' => 'Share image' ),
),
```

Use a 1200 × 630 JPEG or PNG.

## Filters

| Filter | Value | Use |
| --- | --- | --- |
| `pediment_seo_enabled` | `bool` | Turn all tags off (or back on). Default: on, off while Yoast, Rank Math, The SEO Framework, SEOPress or All in One SEO is active. |
| `pediment_meta_description` | `string`, queried object | Change the description, for example for a custom post type. An empty string prints no description tag. |
| `pediment_share_image` | attachment ID or image URL, queried object | Change the share image. A URL names a file, for example one in the client theme. `0` prints no image. |
| `pediment_og_tags` | `array` tag name ⇒ content, queried object | Change, add or remove `og:*` and `twitter:*` tags. A `twitter:` key prints as `name=`, every other key as `property=`. An empty value prints nothing. |

Example: a client theme that shares a theme file and adds its X handle.

```php
add_filter(
	'pediment_share_image',
	static fn( $image ) => $image ?: get_theme_file_uri( 'assets/share.jpg' )
);
add_filter(
	'pediment_og_tags',
	static function ( array $tags ): array {
		$tags['twitter:site'] = '@example';
		return $tags;
	}
);
```

## Not covered

Structured data (JSON-LD), `robots` rules, sitemaps (core ships
`wp-sitemap.xml`) and `hreflang` (the language plugin prints it) are not
part of these tags. A client theme adds structured data itself.
