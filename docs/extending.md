# Extending wp-starter-ai from a child theme

The plugin exposes filters so a child theme can register blocks under its own namespace and customise the system prompt without forking the plugin.

## Block discovery namespaces

By default the plugin discovers blocks under the `starter/` and `client/` namespaces. To allow an additional namespace, hook `starter_ai_block_namespaces`:

```php
add_filter( 'starter_ai_block_namespaces', function ( $namespaces ) {
    $namespaces[] = 'acme';
    return $namespaces;
} );
```

Blocks under `acme/*` registered with a non-empty `description` will appear in the AI's block schema. The schema is cached in a transient, so if you add the filter after the plugin has already built the schema, call `\StarterAi\Anthropic\SchemaBuilder::invalidate()` (e.g. in a one-shot WP-CLI command or in test setup) to force re-discovery.

## System prompt

Wrap the prompt to inject brand voice, domain examples, or extra constraints. The filter runs on every chat turn — there is no caching to invalidate.

```php
add_filter( 'starter_ai_system_prompt', function ( $prompt, $schema ) {
    return $prompt . "\n\nBrand voice: confident, concise, no marketing fluff.";
}, 10, 2 );
```

The filter receives the composed prompt string and the block schema array (so you can inspect what blocks are available before appending guidance). Return a string.

## Provider

The AI provider object can be swapped wholesale — useful for testing or pointing at a self-hosted compatible endpoint:

```php
add_filter( 'starter_ai_provider', function ( $provider ) {
    return new MyCustomProvider();
} );
```

The default provider is `\StarterAi\Anthropic\Client`. The built-in `STARTER_AI_MOCK=true` constant (or the mock-mode admin setting) already hooks this filter to swap in `\StarterAi\Mock\MockProvider`; don't add your own hook while that's active.

## Model selection

Use `starter_ai_model_compose` to change the model used for page composition:

```php
add_filter( 'starter_ai_model_compose', function ( $model ) {
    return 'claude-opus-4-5';
} );
```

The filters `starter_ai_model_edit` and `starter_ai_model_refine` follow the same signature and will gate edit and refine flows as those are wired up in future releases. All three are already hooked internally to respect the model settings configured in the admin Settings page, so child-theme overrides will take precedence only when the admin fields are left blank.
