# Privacy / GDPR disclosure

The Pediment plugin's AI chat sends data to Anthropic when an editor uses it. Nothing is
sent unless someone opens the chat sidebar and submits a turn.

## What is transmitted

- The editor's chat message text.
- Prior messages in the same conversation — up to the last 20 turns.
- The block tree of the post being edited, including all of its editorial copy, and which
  block is currently selected.
- Any images the editor attaches to a message (PNG, JPEG, GIF or WebP).
- When the request names a URL, the page content Pediment fetched server-side from it.
- URLs the model chooses to retrieve through Anthropic's hosted `web_fetch` and
  `web_search` tools. Anthropic performs those retrievals from its own infrastructure.

## What is not transmitted

No form submissions, no site visitor data, no WordPress user accounts or credentials, no
commerce data, and no content from posts other than the one being edited.

## Recommended privacy-policy paragraph (German)

> Unsere Website nutzt zur Inhaltserstellung im Backend einen KI-Dienst der Anthropic, PBC
> (548 Market St, San Francisco, CA 94104, USA). Bei Nutzung der Funktion werden die
> Eingabe der Redakteurin oder des Redakteurs, der bisherige Gesprächsverlauf, der Inhalt
> des bearbeiteten Beitrags sowie gegebenenfalls hochgeladene Bilder an Anthropic
> übertragen. Die Verarbeitung erfolgt auf Grundlage unseres berechtigten Interesses gemäß
> Art. 6 Abs. 1 lit. f DSGVO. Die Datenübermittlung in die USA erfolgt auf Basis der
> EU-Standardvertragsklauseln.

## Recommended privacy-policy paragraph (English)

> This website uses an AI service from Anthropic, PBC (548 Market St, San Francisco, CA
> 94104, USA) for internal content drafting. When the feature is used, the editor's input,
> the conversation history so far, the content of the post being edited and any uploaded
> images are transmitted to Anthropic. Processing is based on our legitimate interest under
> Art. 6(1)(f) GDPR. Transfers to the US rely on the EU Standard Contractual Clauses.

## Turning it off

Either enable **Mock mode** under Settings → Pediment, or define
`PEDIMENT_AI_MOCK` as `true` in `wp-config.php`. With mock mode active the chat UI stays
visible but the plugin never contacts Anthropic. Clearing the API key has the same
practical effect: without a key no request can be made.
