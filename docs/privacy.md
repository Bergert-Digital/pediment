# Privacy defaults

A Pediment site must make no request to a third party before the visitor
consents (GDPR; launch checklist item PRIV-1). The plugin turns off the parts
of WordPress core that make such requests on their own.

## Emoji

Core prints an emoji detection script on every page. In a browser that cannot
draw an emoji, the script loads the emoji as an image from `s.w.org`. Core also
replaces emoji with `s.w.org` images in feeds and in HTML emails.

Pediment turns all of this off (`Pediment\Privacy\Emoji`): the detection
script and the emoji styles on the front end, in embeds, in wp-admin and in the
block editor, the image replacement in feeds and emails, and the `wpemoji`
plugin of the classic editor. Emoji stay in the content as Unicode characters,
and every current browser draws them.

To turn WordPress's emoji support back on, add this to the client theme's
`functions.php`:

```php
add_filter( 'pediment_emoji_enabled', '__return_true' );
```

Then name `s.w.org` in the privacy policy, or load it only after consent.
