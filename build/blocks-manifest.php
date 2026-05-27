<?php
// This file is generated. Do not modify it manually.
return array(
	'blog-index' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/blog-index',
		'title' => 'Blog Index',
		'category' => 'pediment',
		'description' => 'Recent posts as Insight cards with featured image, category badge, and an optional presentational category filter.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide'
			)
		),
		'attributes' => array(
			'count' => array(
				'type' => 'number',
				'default' => 6
			),
			'categorySlug' => array(
				'type' => 'string',
				'default' => ''
			),
			'showFilter' => array(
				'type' => 'boolean',
				'default' => true
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js',
		'render' => 'file:./render.php'
	),
	'contact-form' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/contact-form',
		'title' => 'Contact Form',
		'category' => 'pediment',
		'description' => 'A contact form with name, email, message (and optional phone). Submissions are stored privately and emailed to the configured recipient.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide'
			)
		),
		'attributes' => array(
			'includePhone' => array(
				'type' => 'boolean',
				'default' => false
			),
			'recipientOverride' => array(
				'type' => 'string',
				'default' => ''
			),
			'successMessage' => array(
				'type' => 'string',
				'default' => 'Thanks — we\'ll be in touch shortly.'
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'viewScript' => 'file:./view.js',
		'render' => 'file:./render.php'
	),
	'cta' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/cta',
		'title' => 'Call to Action',
		'category' => 'pediment',
		'description' => 'Inline call-to-action with title, body, and one or two buttons.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide'
			)
		),
		'attributes' => array(
			'title' => array(
				'type' => 'string',
				'default' => ''
			),
			'body' => array(
				'type' => 'string',
				'default' => ''
			),
			'primaryText' => array(
				'type' => 'string',
				'default' => ''
			),
			'primaryUrl' => array(
				'type' => 'string',
				'default' => ''
			),
			'secondaryText' => array(
				'type' => 'string',
				'default' => ''
			),
			'secondaryUrl' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'faq' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/faq',
		'title' => 'FAQ',
		'category' => 'pediment',
		'description' => 'A list of frequently asked questions. Contains FAQ Item child blocks.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide'
			)
		),
		'attributes' => array(
			
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'faq-item' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/faq-item',
		'title' => 'FAQ Item',
		'category' => 'pediment',
		'description' => 'A single question and answer in an FAQ list.',
		'parent' => array(
			'pediment/faq'
		),
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'inserter' => false
		),
		'attributes' => array(
			'question' => array(
				'type' => 'string',
				'default' => ''
			),
			'answer' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'feature' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/feature',
		'title' => 'Feature',
		'category' => 'pediment',
		'description' => 'A single icon + title + text + optional link card.',
		'parent' => array(
			'pediment/feature-grid'
		),
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'inserter' => false
		),
		'attributes' => array(
			'icon' => array(
				'type' => 'string',
				'default' => 'trend-up'
			),
			'title' => array(
				'type' => 'string',
				'default' => ''
			),
			'text' => array(
				'type' => 'string',
				'default' => ''
			),
			'linkText' => array(
				'type' => 'string',
				'default' => ''
			),
			'linkUrl' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'feature-grid' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/feature-grid',
		'title' => 'Feature Grid',
		'category' => 'pediment',
		'description' => 'A responsive grid of feature cards. Contains Feature child blocks.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'attributes' => array(
			
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'hero' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/hero',
		'title' => 'Hero',
		'category' => 'pediment',
		'description' => 'A page-opening hero with headline, subheadline, and primary CTA. Variants: default, centered, media-bg, stat-card.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'attributes' => array(
			'variant' => array(
				'type' => 'string',
				'default' => 'default',
				'enum' => array(
					'default',
					'centered',
					'media-bg',
					'stat-card'
				)
			),
			'headline' => array(
				'type' => 'string',
				'default' => ''
			),
			'subheadline' => array(
				'type' => 'string',
				'default' => ''
			),
			'ctaText' => array(
				'type' => 'string',
				'default' => ''
			),
			'ctaUrl' => array(
				'type' => 'string',
				'default' => ''
			),
			'secondaryText' => array(
				'type' => 'string',
				'default' => ''
			),
			'secondaryUrl' => array(
				'type' => 'string',
				'default' => ''
			),
			'eyebrow' => array(
				'type' => 'string',
				'default' => ''
			),
			'ticks' => array(
				'type' => 'array',
				'default' => array(
					
				)
			),
			'statValue' => array(
				'type' => 'string',
				'default' => ''
			),
			'statText' => array(
				'type' => 'string',
				'default' => ''
			),
			'metrics' => array(
				'type' => 'array',
				'default' => array(
					
				)
			),
			'mediaId' => array(
				'type' => 'number',
				'default' => 0
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'image-caption' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/image-caption',
		'title' => 'Image with caption',
		'category' => 'pediment',
		'description' => 'A figure block with image, optional caption, and optional alt-text override.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'attributes' => array(
			'mediaId' => array(
				'type' => 'number',
				'default' => 0
			),
			'caption' => array(
				'type' => 'string',
				'default' => ''
			),
			'altOverride' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'logo-cloud' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/logo-cloud',
		'title' => 'Logo Cloud',
		'category' => 'pediment',
		'description' => 'A “trusted by” strip of client or partner logos.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'attributes' => array(
			'caption' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'mega-menu' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/mega-menu',
		'title' => 'Mega Menu',
		'category' => 'pediment',
		'description' => 'A mega-menu dropdown for the navigation: columns of icon links.',
		'parent' => array(
			'core/navigation'
		),
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'reusable' => false
		),
		'attributes' => array(
			'label' => array(
				'type' => 'string',
				'default' => ''
			),
			'columns' => array(
				'type' => 'array',
				'default' => array(
					
				)
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'viewScriptModule' => 'file:./view.js',
		'render' => 'file:./render.php'
	),
	'prose' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/prose',
		'title' => 'Prose',
		'category' => 'pediment',
		'description' => 'Long-form prose with constrained content width and typographic defaults. Contains paragraphs, headings, and lists.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide'
			),
			'layout' => array(
				'allowEditing' => false,
				'default' => array(
					'type' => 'constrained'
				)
			)
		),
		'attributes' => array(
			
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'pull-quote' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/pull-quote',
		'title' => 'Pull Quote',
		'category' => 'pediment',
		'description' => 'An emphasized quotation, optionally rendered as a testimonial with avatar, author name, and role.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide'
			)
		),
		'attributes' => array(
			'variant' => array(
				'type' => 'string',
				'enum' => array(
					'default',
					'testimonial'
				),
				'default' => 'default'
			),
			'quote' => array(
				'type' => 'string',
				'default' => ''
			),
			'citation' => array(
				'type' => 'string',
				'default' => ''
			),
			'authorName' => array(
				'type' => 'string',
				'default' => ''
			),
			'authorRole' => array(
				'type' => 'string',
				'default' => ''
			),
			'avatarId' => array(
				'type' => 'integer',
				'default' => 0
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'section-head' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/section-head',
		'title' => 'Section Head',
		'category' => 'pediment',
		'description' => 'Eyebrow + headline + lead intro for a section/band. Use at align:wide above content blocks.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide'
			)
		),
		'attributes' => array(
			'eyebrow' => array(
				'type' => 'string',
				'default' => ''
			),
			'headline' => array(
				'type' => 'string',
				'default' => ''
			),
			'lead' => array(
				'type' => 'string',
				'default' => ''
			),
			'alignment' => array(
				'type' => 'string',
				'default' => 'start',
				'enum' => array(
					'start',
					'center'
				)
			),
			'level' => array(
				'type' => 'number',
				'default' => 2,
				'enum' => array(
					2,
					3
				)
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'social-links' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/social-links',
		'title' => 'Social Links',
		'category' => 'pediment',
		'description' => 'Renders the social links configured in Settings → Brand Settings. Hides itself when none are configured.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide'
			)
		),
		'attributes' => array(
			
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'stat' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/stat',
		'title' => 'Stat',
		'category' => 'pediment',
		'description' => 'A prominent number or short value with a label and optional context line.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false
		),
		'attributes' => array(
			'value' => array(
				'type' => 'string',
				'default' => ''
			),
			'label' => array(
				'type' => 'string',
				'default' => ''
			),
			'context' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'step' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/step',
		'title' => 'Step',
		'category' => 'pediment',
		'description' => 'A single numbered step (number auto-generated).',
		'parent' => array(
			'pediment/steps'
		),
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'inserter' => false
		),
		'attributes' => array(
			'title' => array(
				'type' => 'string',
				'default' => ''
			),
			'text' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'editorScript' => 'file:./index.js',
		'render' => 'file:./render.php'
	),
	'steps' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/steps',
		'title' => 'Steps',
		'category' => 'pediment',
		'description' => 'An ordered list of numbered steps. Contains Step child blocks.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide'
			)
		),
		'attributes' => array(
			
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	)
);
