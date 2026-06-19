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
		'example' => array(
			'attributes' => array(
				'count' => 3,
				'showFilter' => false
			),
			'viewportWidth' => 1280
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
		'example' => array(
			'attributes' => array(
				'includePhone' => true,
				'successMessage' => 'Danke — wir melden uns in Kürze.'
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
		'example' => array(
			'attributes' => array(
				'title' => 'Bereit, Ihr Projekt zu starten?',
				'body' => 'Lassen Sie uns gemeinsam die nächsten Schritte besprechen.',
				'primaryText' => 'Kontakt aufnehmen',
				'primaryUrl' => '#',
				'secondaryText' => 'Mehr erfahren',
				'secondaryUrl' => '#'
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
		'example' => array(
			'innerBlocks' => array(
				array(
					'name' => 'pediment/faq-item',
					'attributes' => array(
						'question' => 'Wie läuft die Zusammenarbeit ab?',
						'answer' => 'Wir starten mit einem unverbindlichen Erstgespräch und entwickeln daraus einen konkreten Fahrplan.'
					)
				),
				array(
					'name' => 'pediment/faq-item',
					'attributes' => array(
						'question' => 'Was kostet ein Projekt?',
						'answer' => 'Die Kosten richten sich nach Umfang und Zielen — Sie erhalten vorab ein transparentes Angebot.'
					)
				)
			)
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
		'example' => array(
			'innerBlocks' => array(
				array(
					'name' => 'pediment/feature',
					'attributes' => array(
						'icon' => 'trend-up',
						'title' => 'Wachstum',
						'text' => 'Messbare Ergebnisse durch datengetriebene Strategien.'
					)
				),
				array(
					'name' => 'pediment/feature',
					'attributes' => array(
						'icon' => 'gear',
						'title' => 'Effizienz',
						'text' => 'Optimierte Prozesse sparen Zeit und Kosten.'
					)
				),
				array(
					'name' => 'pediment/feature',
					'attributes' => array(
						'icon' => 'stack',
						'title' => 'Skalierbarkeit',
						'text' => 'Lösungen, die mit Ihrem Unternehmen mitwachsen.'
					)
				)
			),
			'viewportWidth' => 1280
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
				'default' => '',
				'required' => true
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
		'example' => array(
			'attributes' => array(
				'variant' => 'default',
				'headline' => 'Strategische Beratung für nachhaltiges Wachstum',
				'subheadline' => 'Wir begleiten Unternehmen von der ersten Idee bis zur erfolgreichen Umsetzung.',
				'ctaText' => 'Jetzt Termin vereinbaren',
				'ctaUrl' => '#'
			),
			'viewportWidth' => 1280
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
		'example' => array(
			'attributes' => array(
				'caption' => 'Bildunterschrift zur Veranschaulichung'
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
		'example' => array(
			'attributes' => array(
				'caption' => 'Vertraut von führenden Unternehmen'
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'media-text' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/media-text',
		'title' => 'Media & Text',
		'category' => 'pediment',
		'description' => 'A section that pairs a column of copy with an image side by side. Put the heading, paragraphs and lists in innerBlocks; set mediaPosition to left or right.',
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
			'altOverride' => array(
				'type' => 'string',
				'default' => ''
			),
			'mediaPosition' => array(
				'type' => 'string',
				'default' => 'right'
			)
		),
		'variations' => array(
			array(
				'name' => 'media-right',
				'title' => 'Media & Text (image right)',
				'description' => 'Copy on the left, image on the right.',
				'isDefault' => true,
				'attributes' => array(
					'mediaPosition' => 'right'
				),
				'isActive' => array(
					'mediaPosition'
				),
				'scope' => array(
					'inserter',
					'transform'
				)
			),
			array(
				'name' => 'media-left',
				'title' => 'Media & Text (image left)',
				'description' => 'Image on the left, copy on the right.',
				'attributes' => array(
					'mediaPosition' => 'left'
				),
				'isActive' => array(
					'mediaPosition'
				),
				'scope' => array(
					'inserter',
					'transform'
				)
			)
		),
		'example' => array(
			'attributes' => array(
				'mediaPosition' => 'right'
			),
			'innerBlocks' => array(
				array(
					'name' => 'core/heading',
					'attributes' => array(
						'level' => 2,
						'content' => 'Wachstumskrisen bewältigen'
					)
				),
				array(
					'name' => 'core/paragraph',
					'attributes' => array(
						'content' => 'Dein Geschäft stagniert? Hol dir starke Partner an deine Seite.'
					)
				),
				array(
					'name' => 'core/list',
					'innerBlocks' => array(
						array(
							'name' => 'core/list-item',
							'attributes' => array(
								'content' => 'Prozesse und Abläufe analysieren'
							)
						),
						array(
							'name' => 'core/list-item',
							'attributes' => array(
								'content' => 'Wachstumsschwellen überwinden'
							)
						)
					)
				)
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
		'example' => array(
			'innerBlocks' => array(
				array(
					'name' => 'core/heading',
					'attributes' => array(
						'level' => 2,
						'content' => 'Über uns'
					)
				),
				array(
					'name' => 'core/paragraph',
					'attributes' => array(
						'content' => 'Wir sind ein Team aus Strateginnen und Machern, das Unternehmen bei nachhaltigem Wachstum begleitet.'
					)
				),
				array(
					'name' => 'core/paragraph',
					'attributes' => array(
						'content' => 'Unser Anspruch: klare Konzepte, saubere Umsetzung und messbare Ergebnisse.'
					)
				)
			)
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
		'description' => 'An emphasized quotation with optional citation.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide'
			)
		),
		'attributes' => array(
			'quote' => array(
				'type' => 'string',
				'default' => ''
			),
			'citation' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'example' => array(
			'attributes' => array(
				'quote' => 'Die Zusammenarbeit hat unser Geschäft grundlegend verändert.',
				'citation' => 'Maria Schmidt, Geschäftsführerin'
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
				'default' => '',
				'required' => true
			),
			'lead' => array(
				'type' => 'string',
				'default' => ''
			),
			'alignment' => array(
				'type' => 'string',
				'default' => 'center',
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
			),
			'maxWidth' => array(
				'type' => 'string',
				'default' => ''
			)
		),
		'example' => array(
			'attributes' => array(
				'eyebrow' => 'Unsere Leistungen',
				'headline' => 'Alles aus einer Hand',
				'lead' => 'Von der Strategie bis zur Umsetzung begleiten wir Sie auf dem gesamten Weg.',
				'alignment' => 'center',
				'level' => 2
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'slide' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/slide',
		'title' => 'Slide',
		'category' => 'pediment',
		'description' => 'A single slide: a full-bleed image beside a colored content panel. Used inside a Slider.',
		'parent' => array(
			'pediment/slider'
		),
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'inserter' => false,
			'reusable' => false
		),
		'attributes' => array(
			'mediaId' => array(
				'type' => 'number',
				'default' => 0
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
	'slider' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/slider',
		'title' => 'Slider',
		'category' => 'pediment',
		'description' => 'An image/content slider: one slide at a time, each pairing a full-bleed image with a colored content panel. Contains Slide child blocks.',
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'align' => array(
				'wide',
				'full'
			)
		),
		'attributes' => array(
			'mediaPosition' => array(
				'type' => 'string',
				'default' => 'left'
			),
			'panelColor' => array(
				'type' => 'string',
				'default' => '#0A1B33'
			)
		),
		'example' => array(
			'innerBlocks' => array(
				array(
					'name' => 'pediment/slide',
					'innerBlocks' => array(
						array(
							'name' => 'core/heading',
							'attributes' => array(
								'level' => 2,
								'content' => 'Lebenslanges Lernen'
							)
						),
						array(
							'name' => 'core/paragraph',
							'attributes' => array(
								'content' => 'Wir bringen unser eigenes Organismus immer auf den neuesten Stand.'
							)
						)
					)
				),
				array(
					'name' => 'pediment/slide',
					'innerBlocks' => array(
						array(
							'name' => 'core/heading',
							'attributes' => array(
								'level' => 2,
								'content' => 'Gemeinsam wachsen'
							)
						),
						array(
							'name' => 'core/paragraph',
							'attributes' => array(
								'content' => 'Tägliche Team-Updates und ein intensiver Austausch.'
							)
						)
					)
				)
			),
			'viewportWidth' => 1280
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'viewScriptModule' => 'file:./view.js',
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
		'example' => array(
			
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
		'parent' => array(
			'pediment/stat-grid'
		),
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'inserter' => false
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
	'stat-grid' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/stat-grid',
		'title' => 'Stat Grid',
		'category' => 'pediment',
		'description' => 'A responsive row of key figures. Use for a \'numbers & facts\' / Zahlen & Fakten / stats section. Contains Stat child blocks laid out side by side.',
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
		'example' => array(
			'innerBlocks' => array(
				array(
					'name' => 'pediment/stat',
					'attributes' => array(
						'value' => '98%',
						'label' => 'Kundenzufriedenheit',
						'context' => 'über alle Projekte'
					)
				),
				array(
					'name' => 'pediment/stat',
					'attributes' => array(
						'value' => '150+',
						'label' => 'Projekte',
						'context' => 'erfolgreich umgesetzt'
					)
				),
				array(
					'name' => 'pediment/stat',
					'attributes' => array(
						'value' => '12',
						'label' => 'Jahre Erfahrung'
					)
				)
			),
			'viewportWidth' => 1280
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
		'example' => array(
			'innerBlocks' => array(
				array(
					'name' => 'pediment/step',
					'attributes' => array(
						'title' => 'Analyse',
						'text' => 'Wir verstehen Ihre Ziele und Rahmenbedingungen.'
					)
				),
				array(
					'name' => 'pediment/step',
					'attributes' => array(
						'title' => 'Konzept',
						'text' => 'Gemeinsam entwickeln wir die passende Strategie.'
					)
				),
				array(
					'name' => 'pediment/step',
					'attributes' => array(
						'title' => 'Umsetzung',
						'text' => 'Wir bringen die Lösung zuverlässig auf die Straße.'
					)
				)
			)
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	),
	'testimonial' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/testimonial',
		'title' => 'Testimonial',
		'category' => 'pediment',
		'description' => 'A single customer testimonial card: quote, author name, role, and optional avatar.',
		'parent' => array(
			'pediment/testimonial-grid'
		),
		'textdomain' => 'pediment',
		'supports' => array(
			'html' => false,
			'inserter' => false
		),
		'attributes' => array(
			'quote' => array(
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
	'testimonial-grid' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'pediment/testimonial-grid',
		'title' => 'Testimonial Grid',
		'category' => 'pediment',
		'description' => 'A responsive grid of customer testimonial cards. Use for \'what our clients say\' / Kundenstimmen sections. Contains Testimonial child blocks.',
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
		'example' => array(
			'innerBlocks' => array(
				array(
					'name' => 'pediment/testimonial',
					'attributes' => array(
						'quote' => 'Ein Partner, der mitdenkt und zuverlässig liefert.',
						'authorName' => 'Maria Schmidt',
						'authorRole' => 'Geschäftsführerin, Muster GmbH'
					)
				),
				array(
					'name' => 'pediment/testimonial',
					'attributes' => array(
						'quote' => 'Professionell, schnell und immer auf Augenhöhe.',
						'authorName' => 'Thomas Krause',
						'authorRole' => 'Marketingleiter, Beispiel AG'
					)
				)
			),
			'viewportWidth' => 1280
		),
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./style-index.css',
		'style' => 'file:./style-index.css',
		'render' => 'file:./render.php'
	)
);
