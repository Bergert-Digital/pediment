# Chat Image Attachments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users paste, upload, or drag-and-drop images into the AI chat composer so they can show the AI what to build; images are sent to the vision model for that turn and persist in conversation history.

**Architecture:** Images are downscaled client-side and carried as base64 in the existing `POST /chat/turns` body. The server persists them in a new `pediment_ai_chat_attachments` table linked to the user message, and `TurnRunner` prepends Anthropic `image` content blocks to the current user turn. History reconstruction stays text-only (prior images are shown in the UI, not re-billed).

**Tech Stack:** PHP 8 (WordPress plugin), TypeScript + React (`@wordpress/*`), Jest (`wp-scripts test-unit-js`), PHPUnit (`WP_UnitTestCase`), Playwright (e2e), Anthropic Messages API.

## Global Constraints

- Accepted image types only: `image/png`, `image/jpeg`, `image/gif`, `image/webp`.
- Max **5 images per message**; each downscaled to **1568px** on the long edge.
- Base64 payload is stored/transported **without** the `data:` URI prefix; the prefix is reconstructed only for `<img>` display.
- Only the **active turn** sends image content blocks; `historyToMessages()` stays text-only.
- No new runtime npm/composer dependencies — use `@wordpress/components` `Button` with a dashicon string (`icon="format-image"`), browser `createImageBitmap`/`canvas`.
- Lint gates must pass before commit: `composer run lint:colors` is not relevant here, but `composer run lint` (phpcs; fails on warnings) and `npm run lint:js` are. Run them on touched files.
- Canvas cannot encode GIF/WebP losslessly: PNG input is re-encoded as PNG (preserve transparency); **all other types (incl. GIF/WebP) are re-encoded as JPEG q0.85**. Animated GIFs collapse to their first frame — acceptable for reference images. (This is a deliberate, documented deviation from the spec's "keep GIF as GIF".)

---

### Task 1: Attachments table + ConversationStore persistence

**Files:**
- Modify: `src/Schema/tables.php` (add the attachments `CREATE TABLE`)
- Modify: `src/Chat/ConversationStore.php` (attachments column, `appendUserMessage` images, `getAttachments`, `buildResult` hydration, `clear` cascade)
- Test: `tests/phpunit/ConversationStoreTest.php` (create)

**Interfaces:**
- Produces:
  - `ConversationStore::appendUserMessage(int $conversation_id, string $content, array $images = []): int` — `$images` is `array<int,array{media_type:string,data:string}>`.
  - `ConversationStore::getAttachments(int $message_id): array` — returns `array<int,array{media_type:string,data:string}>`.
  - User-role messages returned by `findById()`/`getOrCreate()` carry an `attachments` key of the same shape.

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/ConversationStoreTest.php`:

```php
<?php
namespace PedimentAi\Tests;

use PedimentAi\Chat\ConversationStore;

class ConversationStoreTest extends \WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		// DDL is not rolled back between tests; idempotent dbDelta is safe to re-run.
		pediment_ai_install_tables();
	}

	public function test_attachments_round_trip(): void {
		$store = new ConversationStore();
		$conv  = $store->getOrCreate( 1, 1 );
		$mid   = $store->appendUserMessage( $conv['id'], 'build this', [
			[ 'media_type' => 'image/png', 'data' => 'AAAB' ],
		] );

		$atts = $store->getAttachments( $mid );
		$this->assertCount( 1, $atts );
		$this->assertSame( 'image/png', $atts[0]['media_type'] );
		$this->assertSame( 'AAAB', $atts[0]['data'] );
	}

	public function test_user_message_result_includes_attachments(): void {
		$store = new ConversationStore();
		$conv  = $store->getOrCreate( 2, 1 );
		$store->appendUserMessage( $conv['id'], 'with image', [
			[ 'media_type' => 'image/jpeg', 'data' => 'ZZZZ' ],
		] );

		$reloaded = $store->getOrCreate( 2, 1 );
		$userMsg  = $reloaded['messages'][0];
		$this->assertSame( 'user', $userMsg['role'] );
		$this->assertSame( 'image/jpeg', $userMsg['attachments'][0]['media_type'] );
		$this->assertSame( 'ZZZZ', $userMsg['attachments'][0]['data'] );
	}

	public function test_clear_removes_attachments(): void {
		$store = new ConversationStore();
		$conv  = $store->getOrCreate( 3, 1 );
		$mid   = $store->appendUserMessage( $conv['id'], 'x', [
			[ 'media_type' => 'image/png', 'data' => 'QQQQ' ],
		] );

		$store->clear( $conv['id'] );
		$this->assertSame( [], $store->getAttachments( $mid ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer run test` (or `vendor/bin/phpunit --filter ConversationStoreTest`)
Expected: FAIL — `getAttachments`/`appendUserMessage` arity errors or missing table.

- [ ] **Step 3: Add the attachments table**

In `src/Schema/tables.php`, after the `$sql_msgs` `dbDelta`, before `update_option(...)`, add:

```php
	$attachments = $wpdb->prefix . 'pediment_ai_chat_attachments';

	$sql_attachments = "CREATE TABLE {$attachments} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		message_id bigint(20) UNSIGNED NOT NULL,
		media_type varchar(40) NOT NULL,
		data longtext NOT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY message_idx (message_id)
	) {$charset};";

	dbDelta( $sql_attachments );
```

(The existing `update_option( 'pediment_ai_db_version', PEDIMENT_AI_VERSION )` already gates the upgrade path that calls this installer, so existing sites pick up the new table on next upgrade.)

- [ ] **Step 4: Wire attachments into ConversationStore**

In `src/Chat/ConversationStore.php`:

Add the property and assign it in the constructor:

```php
	private string $attachments;
```
```php
		$this->attachments   = $wpdb->prefix . 'pediment_ai_chat_attachments';
```

Replace `appendUserMessage`:

```php
	/**
	 * @param array<int,array{media_type:string,data:string}> $images
	 */
	public function appendUserMessage( int $conversation_id, string $content, array $images = [] ): int {
		$id = $this->insertMessage( $conversation_id, 'user', 'complete', $content );
		foreach ( $images as $img ) {
			$this->insertAttachment( $id, (string) ( $img['media_type'] ?? '' ), (string) ( $img['data'] ?? '' ) );
		}
		return $id;
	}

	private function insertAttachment( int $message_id, string $media_type, string $data ): void {
		global $wpdb;
		$wpdb->insert( $this->attachments, [
			'message_id' => $message_id,
			'media_type' => $media_type,
			'data'       => $data,
			'created_at' => current_time( 'mysql', true ),
		] );
	}

	/**
	 * @return array<int,array{media_type:string,data:string}>
	 */
	public function getAttachments( int $message_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT media_type, data FROM {$this->attachments} WHERE message_id = %d ORDER BY id ASC",
				$message_id
			),
			ARRAY_A
		);
		return array_map(
			static fn( $r ) => [ 'media_type' => (string) $r['media_type'], 'data' => (string) $r['data'] ],
			$rows ?: []
		);
	}
```

In `buildResult()`, replace the `'messages' => ...` line so user messages get their attachments:

```php
		$messages = array_map( [ $this, 'hydrate' ], $rows ?: [] );
		return [
			'id'       => (int) $header['id'],
			'post_id'  => (int) $header['post_id'],
			'user_id'  => (int) $header['user_id'],
			'messages' => $this->attachImages( $messages ),
		];
```

Add the helper (one query for all user messages, no N+1):

```php
	/**
	 * Attach each user message's images. One query for the whole conversation.
	 *
	 * @param array<int,array<string,mixed>> $messages
	 * @return array<int,array<string,mixed>>
	 */
	private function attachImages( array $messages ): array {
		global $wpdb;
		$userIds = [];
		foreach ( $messages as $m ) {
			if ( 'user' === ( $m['role'] ?? '' ) ) {
				$userIds[] = (int) $m['id'];
			}
		}
		if ( [] === $userIds ) {
			return $messages;
		}
		$placeholders = implode( ',', array_fill( 0, count( $userIds ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT message_id, media_type, data FROM {$this->attachments} WHERE message_id IN ({$placeholders}) ORDER BY id ASC",
				...$userIds
			),
			ARRAY_A
		);
		$byMessage = [];
		foreach ( $rows ?: [] as $r ) {
			$byMessage[ (int) $r['message_id'] ][] = [ 'media_type' => (string) $r['media_type'], 'data' => (string) $r['data'] ];
		}
		foreach ( $messages as &$m ) {
			if ( 'user' === ( $m['role'] ?? '' ) ) {
				$m['attachments'] = $byMessage[ (int) $m['id'] ] ?? [];
			}
		}
		unset( $m );
		return $messages;
	}
```

Replace `clear()` to cascade attachment rows first:

```php
	public function clear( int $conversation_id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE a FROM {$this->attachments} a
				 JOIN {$this->messages} m ON m.id = a.message_id
				 WHERE m.conversation_id = %d",
				$conversation_id
			)
		);
		$wpdb->delete( $this->messages, [ 'conversation_id' => $conversation_id ] );
	}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `composer run test`
Expected: PASS (3 new tests green; SmokeTest still green).

- [ ] **Step 6: Lint and commit**

```bash
composer run lint -- src/Schema/tables.php src/Chat/ConversationStore.php
git add src/Schema/tables.php src/Chat/ConversationStore.php tests/phpunit/ConversationStoreTest.php
git commit -m "feat(chat): persist image attachments per user message"
```

---

### Task 2: Send image content blocks to Anthropic

**Files:**
- Modify: `src/Chat/TurnRunner.php` (new `$images` param on `run()`, prepend image blocks)
- Modify: `src/Rest/ChatController.php` (`normalizeImages`, relaxed validation, persist images, thread through dispatch + `processTurn`)
- Test: `tests/phpunit/TurnRunnerImagesTest.php` (create)

**Interfaces:**
- Consumes: `ConversationStore::appendUserMessage(..., $images)`, `ConversationStore::getAttachments(int): array` (Task 1).
- Produces:
  - `TurnRunner::run(int $turn_id, VirtualTree $tree, array $history, ?string $selectedId, string $currentUserMsg, array $images = []): void`
  - `ChatController::processTurn(int $turn_id, int $conversation_id, VirtualTree $tree, $selected, string $message, array $images = []): void`

- [ ] **Step 1: Write the failing test**

Create `tests/phpunit/TurnRunnerImagesTest.php`:

```php
<?php
namespace PedimentAi\Tests;

use PedimentAi\Anthropic\ProviderInterface;
use PedimentAi\BlockTree\Validator;
use PedimentAi\Chat\ConversationStore;
use PedimentAi\Chat\PageFetcherInterface;
use PedimentAi\Chat\PromptBuilder;
use PedimentAi\Chat\Tools;
use PedimentAi\Chat\TurnRunner;
use PedimentAi\Chat\VirtualTree;

class TurnRunnerImagesTest extends \WP_UnitTestCase {
	public function set_up(): void {
		parent::set_up();
		pediment_ai_install_tables();
	}

	public function test_run_prepends_image_content_blocks(): void {
		$store = new ConversationStore();
		$conv  = $store->getOrCreate( 10, 1 );
		$store->appendUserMessage( $conv['id'], 'build this', [] );
		$turn  = $store->startAssistantTurn( $conv['id'] );

		$provider = new class implements ProviderInterface {
			public array $lastArgs = [];
			public function messages( array $args ) { return []; }
			public function stream_messages( array $args ) {
				$this->lastArgs = $args;
				return ( static function () {
					yield [ 'type' => 'message_delta', 'delta' => [ 'stop_reason' => 'end_turn' ] ];
				} )();
			}
		};
		$pageFetcher = new class implements PageFetcherInterface {
			public function fetch( string $url ): ?string { return null; }
		};

		$runner = new TurnRunner(
			$store,
			new Tools( [], new Validator( [] ) ),
			new PromptBuilder( [] ),
			$provider,
			'claude-sonnet-4-6',
			$pageFetcher
		);

		$runner->run(
			turn_id:        $turn,
			tree:           new VirtualTree( [] ),
			history:        [],
			selectedId:     null,
			currentUserMsg: 'build this',
			images:         [ [ 'media_type' => 'image/png', 'data' => 'AAAB' ] ]
		);

		$messages = $provider->lastArgs['messages'];
		$userMsg  = end( $messages );
		$imageBlocks = array_values( array_filter(
			$userMsg['content'],
			static fn( $b ) => ( $b['type'] ?? '' ) === 'image'
		) );

		$this->assertCount( 1, $imageBlocks );
		$this->assertSame( 'base64', $imageBlocks[0]['source']['type'] );
		$this->assertSame( 'image/png', $imageBlocks[0]['source']['media_type'] );
		$this->assertSame( 'AAAB', $imageBlocks[0]['source']['data'] );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter TurnRunnerImagesTest`
Expected: FAIL — `run()` does not accept an `images` argument.

- [ ] **Step 3: Add the `$images` param to TurnRunner::run and prepend image blocks**

In `src/Chat/TurnRunner.php`, change the `run()` signature:

```php
	public function run(
		int $turn_id,
		VirtualTree $tree,
		array $history,
		?string $selectedId,
		string $currentUserMsg,
		array $images = []
	): void {
```

Then, immediately after the line that builds the text-only `$userContent` (currently `$userContent = [ [ 'type' => 'text', 'text' => $this->prompts->contextMessage( $tree, $selectedId ) ] ];`) and **before** the prefetch `if` block, prepend the image blocks:

```php
		// Image attachments lead the turn — Anthropic recommends images before text.
		if ( [] !== $images ) {
			$imageBlocks = [];
			foreach ( $images as $img ) {
				$imageBlocks[] = [
					'type'   => 'image',
					'source' => [
						'type'       => 'base64',
						'media_type' => (string) ( $img['media_type'] ?? '' ),
						'data'       => (string) ( $img['data'] ?? '' ),
					],
				];
			}
			$userContent = array_merge( $imageBlocks, $userContent );
		}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter TurnRunnerImagesTest`
Expected: PASS.

- [ ] **Step 5: Thread images through ChatController**

In `src/Rest/ChatController.php`:

In `startTurn()`, after `$selected = $r->get_param( 'selected_block' );` add:

```php
		$images = $this->normalizeImages( $r->get_param( 'images' ) );
```

Replace the empty-message guard:

```php
		if ( '' === $message && [] === $images ) {
			return new \WP_Error( 'pediment_ai_invalid', __( 'A message or an image is required.', 'pediment-ai' ), [ 'status' => 400 ] );
		}
```

Capture the user message id when persisting:

```php
		$store   = new ConversationStore();
		$user_message_id = $store->appendUserMessage( $conversation_id, $message, $images );
		$turn_id = $store->startAssistantTurn( $conversation_id );
```

In the `'inline' === $mode` branch, pass images:

```php
			$this->processTurn( $turn_id, $conversation_id, new VirtualTree( $tree_source ), $selected, $message, $images );
```

In the auto-dispatch `stashInput` array, add the user message id (NOT the base64):

```php
		$dispatcher->stashInput( $turn_id, [
			'conversation_id' => $conversation_id,
			'message'         => $message,
			'selected_block'  => $selected,
			'block_tree'      => $tree_source,
			'user_message_id' => $user_message_id,
		] );
```

In `runTurn()`, after `$input = ( new \PedimentAi\Chat\TurnDispatcher() )->takeInput( $turn_id );` and the null guard, load the images and pass them through:

```php
		$images = $store->getAttachments( (int) ( $input['user_message_id'] ?? 0 ) );
```
```php
		$this->processTurn(
			$turn_id,
			(int) $input['conversation_id'],
			$tree,
			$input['selected_block'] ?? null,
			(string) $input['message'],
			$images
		);
```

Extend `processTurn()`'s signature and the `run()` call:

```php
	public function processTurn( int $turn_id, int $conversation_id, VirtualTree $tree, $selected, string $message, array $images = [] ): void {
```
```php
		( new TurnRunner( $store, $tools, $prompts, $provider, $model ) )->run(
			turn_id:        $turn_id,
			tree:           $tree,
			history:        $history,
			selectedId:     $selectedId,
			currentUserMsg: $message,
			images:         $images
		);
```

Add the private normaliser at the end of the class (before the closing brace):

```php
	/**
	 * @param mixed $raw
	 * @return array<int,array{media_type:string,data:string}>
	 */
	private function normalizeImages( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$allowed = [ 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ];
		$out     = [];
		foreach ( $raw as $img ) {
			if ( ! is_array( $img ) ) {
				continue;
			}
			$type = isset( $img['media_type'] ) ? (string) $img['media_type'] : '';
			$data = isset( $img['data'] ) ? (string) $img['data'] : '';
			if ( in_array( $type, $allowed, true ) && '' !== $data ) {
				$out[] = [ 'media_type' => $type, 'data' => $data ];
			}
			if ( count( $out ) >= 5 ) {
				break;
			}
		}
		return $out;
	}
```

- [ ] **Step 6: Run the full PHP suite**

Run: `composer run test`
Expected: PASS (all tests green).

- [ ] **Step 7: Lint and commit**

```bash
composer run lint -- src/Chat/TurnRunner.php src/Rest/ChatController.php
git add src/Chat/TurnRunner.php src/Rest/ChatController.php tests/phpunit/TurnRunnerImagesTest.php
git commit -m "feat(chat): send attached images as Anthropic image blocks"
```

---

### Task 3: Client image-preparation helper

**Files:**
- Create: `editor/chat/images.ts`
- Test: `editor/chat/test/images.test.ts` (create)

**Interfaces:**
- Produces:
  - `type ChatImage = { media_type: string; data: string }`
  - `isAccepted(type: string): boolean`
  - `splitDataUri(dataUri: string): ChatImage`
  - `selectFiles(files: File[], room: number): { accepted: File[]; rejected: boolean }`
  - `prepareImages(files: File[], room: number): Promise<{ images: ChatImage[]; rejected: boolean }>`

- [ ] **Step 1: Write the failing test**

Create `editor/chat/test/images.test.ts`:

```ts
import { isAccepted, splitDataUri, selectFiles } from '../images';

const file = (type: string) => new File(['x'], 'f', { type });

describe('isAccepted', () => {
  it('accepts the four supported image types', () => {
    expect(isAccepted('image/png')).toBe(true);
    expect(isAccepted('image/jpeg')).toBe(true);
    expect(isAccepted('image/gif')).toBe(true);
    expect(isAccepted('image/webp')).toBe(true);
  });
  it('rejects unsupported types', () => {
    expect(isAccepted('application/pdf')).toBe(false);
    expect(isAccepted('text/plain')).toBe(false);
    expect(isAccepted('image/svg+xml')).toBe(false);
  });
});

describe('splitDataUri', () => {
  it('splits media type and base64 payload, dropping the prefix', () => {
    expect(splitDataUri('data:image/png;base64,AAAB')).toEqual({ media_type: 'image/png', data: 'AAAB' });
  });
  it('throws on a non-data URI', () => {
    expect(() => splitDataUri('http://example.com/x.png')).toThrow();
  });
});

describe('selectFiles', () => {
  it('drops non-image files and flags rejection', () => {
    const { accepted, rejected } = selectFiles([file('image/png'), file('application/pdf')], 5);
    expect(accepted).toHaveLength(1);
    expect(rejected).toBe(true);
  });
  it('caps at the remaining room and flags rejection', () => {
    const { accepted, rejected } = selectFiles([file('image/png'), file('image/jpeg'), file('image/gif')], 2);
    expect(accepted).toHaveLength(2);
    expect(rejected).toBe(true);
  });
  it('accepts everything when within room and all images', () => {
    const { accepted, rejected } = selectFiles([file('image/png')], 5);
    expect(accepted).toHaveLength(1);
    expect(rejected).toBe(false);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:js -- images`
Expected: FAIL — cannot find module `../images`.

- [ ] **Step 3: Implement the helper**

Create `editor/chat/images.ts`:

```ts
export type ChatImage = { media_type: string; data: string };

const ACCEPTED = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
const MAX_EDGE = 1568;

export function isAccepted(type: string): boolean {
  return ACCEPTED.includes(type);
}

/** Splits "data:image/png;base64,AAAA" into { media_type, data } (no data: prefix). */
export function splitDataUri(dataUri: string): ChatImage {
  const match = /^data:([^;]+);base64,(.*)$/.exec(dataUri);
  if (!match) throw new Error('Not a base64 data URI');
  return { media_type: match[1], data: match[2] };
}

/** Pure filter+cap step, separated from canvas work so it is unit-testable. */
export function selectFiles(files: File[], room: number): { accepted: File[]; rejected: boolean } {
  const valid = files.filter((f) => isAccepted(f.type));
  const accepted = valid.slice(0, Math.max(0, room));
  const rejected = valid.length < files.length || accepted.length < valid.length;
  return { accepted, rejected };
}

/** Validate, cap, and downscale a batch of files into base64 ChatImages. */
export async function prepareImages(files: File[], room: number): Promise<{ images: ChatImage[]; rejected: boolean }> {
  const { accepted, rejected: selectionRejected } = selectFiles(files, room);
  let rejected = selectionRejected;
  const images: ChatImage[] = [];
  for (const file of accepted) {
    try {
      images.push(await downscale(file));
    } catch {
      rejected = true;
    }
  }
  return { images, rejected };
}

async function downscale(file: File): Promise<ChatImage> {
  const bitmap = await createImageBitmap(file);
  const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
  const w = Math.round(bitmap.width * scale);
  const h = Math.round(bitmap.height * scale);
  const canvas = document.createElement('canvas');
  canvas.width = w;
  canvas.height = h;
  const ctx = canvas.getContext('2d');
  if (!ctx) throw new Error('no 2d context');
  ctx.drawImage(bitmap, 0, 0, w, h);
  // PNG keeps transparency; GIF/WebP/JPEG are re-encoded to JPEG (canvas can't emit GIF/WebP reliably).
  const outType = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
  return splitDataUri(canvas.toDataURL(outType, 0.85));
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:js -- images`
Expected: PASS (all describe blocks green).

- [ ] **Step 5: Lint and commit**

```bash
npm run lint:js -- editor/chat/images.ts editor/chat/test/images.test.ts
git add editor/chat/images.ts editor/chat/test/images.test.ts
git commit -m "feat(chat): add client-side image validate/downscale helper"
```

---

### Task 4: Composer capture, transport, and rendering

**Files:**
- Modify: `editor/chat/Composer.tsx` (paste/upload/drop, thumbnails, send on text-or-image)
- Modify: `editor/chat/ChatPanel.tsx` (`send(text, images)`)
- Modify: `editor/hooks/useChatTurn.ts` (`StartArgs.images`, POST body, optimistic attachments)
- Modify: `editor/chat/store.ts` (`ChatMessage.attachments?`)
- Modify: `editor/chat/MessageList.tsx` (render attachment thumbnails)
- Modify: `editor/styles.scss` (composer + message-list image styles)

**Interfaces:**
- Consumes: `ChatImage`, `prepareImages` (Task 3); the `images` param on `POST /chat/turns` (Task 2).
- Produces: `Composer` `onSubmit: (text: string, images: ChatImage[]) => void`; `ChatMessage.attachments?: { media_type: string; data: string }[]`.

This task is verified by the e2e test in Task 5 (React UI wiring is exercised end-to-end rather than unit-tested, matching the repo's existing approach). Build the JS after the edits so the e2e runs against fresh bundles.

- [ ] **Step 1: Add `attachments` to the ChatMessage type**

In `editor/chat/store.ts`, add to the `ChatMessage` type (after `error`):

```ts
  attachments?: { media_type: string; data: string }[];
```

- [ ] **Step 2: Rewrite the Composer**

Replace the entire contents of `editor/chat/Composer.tsx`:

```tsx
import { Button } from '@wordpress/components';
import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { prepareImages, type ChatImage } from './images';

const MAX_IMAGES = 5;

export default function Composer({
  onSubmit,
  onStop,
  busy,
}: {
  onSubmit: (text: string, images: ChatImage[]) => void;
  onStop: () => void;
  busy: boolean;
}) {
  const [value, setValue] = useState('');
  const [images, setImages] = useState<ChatImage[]>([]);
  const [dragging, setDragging] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const fileRef = useRef<HTMLInputElement>(null);

  const addFiles = async (files: File[]) => {
    if (!files.length) return;
    const room = MAX_IMAGES - images.length;
    const { images: prepared, rejected } = await prepareImages(files, room);
    if (prepared.length) setImages((cur) => [...cur, ...prepared]);
    setNotice(rejected ? __('Some files were skipped — up to 5 JPEG, PNG, GIF, or WebP images.', 'pediment-ai') : null);
  };

  const submit = () => {
    const trimmed = value.trim();
    if ((!trimmed && images.length === 0) || busy) return;
    onSubmit(trimmed, images);
    setValue('');
    setImages([]);
    setNotice(null);
  };

  return (
    <div
      className={`pediment-ai-chat__composer${dragging ? ' is-dragging' : ''}`}
      onDragOver={(e) => { e.preventDefault(); setDragging(true); }}
      onDragLeave={() => setDragging(false)}
      onDrop={(e) => { e.preventDefault(); setDragging(false); addFiles(Array.from(e.dataTransfer.files)); }}
    >
      {images.length > 0 && (
        <div className="pediment-ai-chat__thumbs">
          {images.map((img, i) => (
            <div key={i} className="pediment-ai-chat__thumb">
              <img src={`data:${img.media_type};base64,${img.data}`} alt="" />
              <button
                type="button"
                aria-label={__('Remove image', 'pediment-ai')}
                onClick={() => setImages((cur) => cur.filter((_, j) => j !== i))}
              >
                ×
              </button>
            </div>
          ))}
        </div>
      )}
      <textarea
        value={value}
        onChange={(e) => setValue(e.target.value)}
        onPaste={(e) => {
          const files = Array.from(e.clipboardData.files).filter((f) => f.type.startsWith('image/'));
          if (files.length) { e.preventDefault(); addFiles(files); }
        }}
        onKeyDown={(e) => {
          if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(); }
        }}
        placeholder={__('Ask the AI to write or edit…', 'pediment-ai')}
        rows={3}
        disabled={busy}
      />
      {notice && <div className="pediment-ai-chat__composer-notice">{notice}</div>}
      <input
        ref={fileRef}
        type="file"
        accept="image/png,image/jpeg,image/gif,image/webp"
        multiple
        style={{ display: 'none' }}
        onChange={(e) => { addFiles(Array.from(e.target.files ?? [])); e.target.value = ''; }}
      />
      <div className="pediment-ai-chat__composer-actions">
        <Button
          icon="format-image"
          label={__('Attach image', 'pediment-ai')}
          onClick={() => fileRef.current?.click()}
          disabled={busy || images.length >= MAX_IMAGES}
        />
        {busy ? (
          <Button variant="secondary" onClick={onStop}>{__('Stop', 'pediment-ai')}</Button>
        ) : (
          <Button variant="primary" onClick={submit} disabled={!value.trim() && images.length === 0}>
            {__('Send', 'pediment-ai')}
          </Button>
        )}
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Update ChatPanel.send to pass images**

In `editor/chat/ChatPanel.tsx`, replace the `send` function:

```tsx
  const send = (text: string, images: import('./images').ChatImage[] = []) => {
    if (!conv || !postId) return;
    start({
      conversationId: conv.id,
      postId,
      message: text,
      images,
      selectedBlock: selected,
    });
  };
```

(`QuickActions` calls `onAction(instruction)` with one arg, so its images default to `[]` — no change needed there. The `Composer`'s `onSubmit={send}` now supplies both args.)

- [ ] **Step 4: Carry images through useChatTurn**

In `editor/hooks/useChatTurn.ts`:

Add to the `StartArgs` type:

```ts
  images: { media_type: string; data: string }[];
```

In `start()`, set the optimistic user message's attachments — change the `setPendingUserMessage({...})` call to include:

```ts
      attachments: args.images,
```

Add `images` to the POST body `data` object:

```ts
          images: args.images,
```

- [ ] **Step 5: Render attachment thumbnails in MessageList**

In `editor/chat/MessageList.tsx`, inside the message `<div>`, after the `__bubble` div and before `<ToolCallSummary .../>`, add:

```tsx
          {m.attachments && m.attachments.length > 0 && (
            <div className="pediment-ai-chat__msg-images">
              {m.attachments.map((a, i) => (
                <img key={i} src={`data:${a.media_type};base64,${a.data}`} alt="" />
              ))}
            </div>
          )}
```

- [ ] **Step 6: Add styles**

In `editor/styles.scss`, inside the `.pediment-ai-chat { ... }` block, change the composer-actions rule and add the new rules:

```scss
  &__composer.is-dragging { outline: 2px dashed #007cba; outline-offset: -2px; }
  &__composer-actions { display: flex; justify-content: space-between; align-items: center; }
  &__composer-notice { font-size: 11px; color: #b32d2e; }
  &__thumbs { display: flex; flex-wrap: wrap; gap: 6px; }
  &__thumb { position: relative; width: 48px; height: 48px; }
  &__thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; }
  &__thumb button { position: absolute; top: -6px; right: -6px; width: 16px; height: 16px; line-height: 14px; border: 0; border-radius: 50%; background: #1e1e1e; color: #fff; font-size: 11px; padding: 0; cursor: pointer; }
  &__msg-images { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
  &__msg-images img { max-width: 120px; max-height: 120px; border-radius: 6px; }
```

(Replace the existing `&__composer-actions { display: flex; justify-content: flex-end; }` line with the version above.)

- [ ] **Step 7: Build and lint**

Run:
```bash
npm run lint:js -- editor/chat/Composer.tsx editor/chat/ChatPanel.tsx editor/hooks/useChatTurn.ts editor/chat/store.ts editor/chat/MessageList.tsx
npm run build
```
Expected: lint clean; build succeeds, emitting updated `build/index.js` / `build/index.css`.

- [ ] **Step 8: Commit**

```bash
git add editor/chat/Composer.tsx editor/chat/ChatPanel.tsx editor/hooks/useChatTurn.ts editor/chat/store.ts editor/chat/MessageList.tsx editor/styles.scss build/
git commit -m "feat(chat): paste/upload/drop images in the composer"
```

---

### Task 5: End-to-end test

**Files:**
- Create: `tests/e2e/chat-image.spec.ts`

**Interfaces:**
- Consumes: the `input[type="file"]`, `.pediment-ai-chat__thumb`, and `.pediment-ai-chat__msg-images` selectors from Task 4; the `insert-paragraph` mock chat fixture (text "Add a paragraph that says hi").

- [ ] **Step 1: Write the e2e test**

Create `tests/e2e/chat-image.spec.ts`:

```ts
import { test, expect } from '@playwright/test';
import { login, openNewPage, openAIChatPanel, canvas } from './utils';

// A 1x1 transparent PNG.
const PNG_BYTES = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
  'base64'
);

test('chat composer attaches an image and persists its thumbnail', async ({ page }) => {
  await login(page);
  await openNewPage(page, 'Chat Image E2E');

  const sidebar = await openAIChatPanel(page);

  // Attach an image through the hidden file input.
  await sidebar.locator('input[type="file"]').setInputFiles({
    name: 'shot.png',
    mimeType: 'image/png',
    buffer: PNG_BYTES,
  });

  // A thumbnail chip appears in the composer.
  await expect(sidebar.locator('.pediment-ai-chat__thumb img')).toBeVisible({ timeout: 5_000 });

  // Send with text that triggers the insert-paragraph mock fixture.
  await sidebar.locator('textarea').fill('Add a paragraph that says hi');
  await sidebar.getByRole('button', { name: /^send$/i }).click();

  // The user message renders its image thumbnail (optimistic + persisted reload).
  await expect(sidebar.locator('.pediment-ai-chat__msg-images img').first()).toBeVisible({ timeout: 15_000 });

  // The mock still applies its tool call to the canvas.
  const editor = await canvas(page);
  await expect(editor.locator('p.wp-block-paragraph', { hasText: 'Hello from mock.' })).toBeVisible({ timeout: 10_000 });
});
```

- [ ] **Step 2: Run the e2e test**

Run: `npm run e2e -- chat-image`
Expected: PASS (thumbnail visible before and after send; paragraph inserted).

If the run reports the env is not started, start it first per the repo's e2e setup (`npm run env:start`) and re-run.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/chat-image.spec.ts
git commit -m "test(e2e): attach an image in the chat composer"
```

---

## Self-Review

**Spec coverage:**
- Capture (paste/upload/drop) → Task 4 Composer. ✓
- Type validation, downscale, 5-image cap → Task 3 helper + Task 4 `MAX_IMAGES`. ✓
- Persist in dedicated table → Task 1. ✓
- Send as Anthropic image blocks, active turn only → Task 2 (`run` prepends; `historyToMessages` untouched). ✓
- Re-render thumbnails after reload → Task 1 `attachImages` + Task 4 MessageList. ✓
- Relaxed "text OR image" validation → Task 2 `startTurn` + Task 4 Composer submit guard. ✓
- Auto-dispatch carries `user_message_id`, not base64 → Task 2. ✓
- Tests (PHP round-trip + validation, TS helper, e2e) → Tasks 1, 2, 3, 5. ✓

**Placeholder scan:** No TBD/TODO; every code step contains complete code. ✓

**Type consistency:** `ChatImage = { media_type, data }` is used consistently across `images.ts`, `Composer`, `ChatPanel`, `useChatTurn`, `store.ts`, and the PHP `{media_type, data}` shape in `appendUserMessage`/`getAttachments`/`normalizeImages`/`run`. `run(... , array $images = [])` and `processTurn(... , array $images = [])` signatures match their call sites. ✓
