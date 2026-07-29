import { test, expect } from '@playwright/test';
import { login, openNewPage, openAIChatPanel, canvas } from './utils';

// A 1x1 red RGB PNG (color-type 2, 8-bit; broader createImageBitmap support than grayscale+alpha).
const PNG_BYTES = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR42mP4z8AAAAMBAQD3A0FDAAAAAElFTkSuQmCC',
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
