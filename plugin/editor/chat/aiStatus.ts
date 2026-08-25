import { __ } from '@wordpress/i18n';

/**
 * Provider status injected by Bootstrap as window.pedimentAiEditor, so the
 * sidebar can say up front when replies are canned fixtures (mock mode) or
 * when no Anthropic key is configured — instead of leaving the user to
 * puzzle over nonsense answers or a cryptic 401.
 */
export type AiStatus = 'ok' | 'mock' | 'missing_key';

type AiEditorConfig = {
	aiStatus?: string;
	settingsUrl?: string;
};

export function getAiStatus(): { status: AiStatus; settingsUrl: string } {
	const cfg = ( window as any ).pedimentAiEditor as
		| AiEditorConfig
		| undefined;
	const raw = cfg?.aiStatus;
	const status: AiStatus =
		raw === 'mock' || raw === 'missing_key' ? raw : 'ok';
	return { status, settingsUrl: cfg?.settingsUrl ?? '' };
}

export function aiStatusNotice( status: AiStatus ): string | null {
	if ( status === 'mock' ) {
		return __(
			'Mock mode is on. Replies are canned test fixtures, not real AI output.',
			'pediment'
		);
	}
	if ( status === 'missing_key' ) {
		return __(
			'No Anthropic API key is set. Add one in the Pediment settings to enable AI chat.',
			'pediment'
		);
	}
	return null;
}
