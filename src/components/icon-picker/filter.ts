import type { IconMeta } from './catalog';

/**
 * Filter icon slugs by category then query.
 *
 * - Category narrows first: '' or 'all' keeps everything; otherwise keep slugs
 *   whose meta categories include the selected one. Skipped when meta is null.
 * - Query then matches (case-insensitive substring) against the slug and, when
 *   meta is present, the slug's tags. Falls back to slug-only when meta is null.
 *
 * @param slugs    Full list of icon slugs.
 * @param query    Raw search input.
 * @param category Selected category ('' / 'all' = no category filter).
 * @param meta     Slug → { categories, tags }, or null when unavailable.
 * @return Matching slugs, in original order.
 */
export function filterIcons(
	slugs: string[],
	query: string,
	category = '',
	meta: Record< string, IconMeta > | null = null
): string[] {
	const cat = category.trim().toLowerCase();
	const q = query.trim().toLowerCase();
	let result = slugs;

	if ( cat && cat !== 'all' && meta ) {
		result = result.filter( ( slug ) => meta[ slug ]?.c.includes( cat ) );
	}

	if ( q ) {
		result = result.filter( ( slug ) => {
			if ( slug.includes( q ) ) {
				return true;
			}
			const tags = meta?.[ slug ]?.t;
			return tags ? tags.some( ( t ) => t.includes( q ) ) : false;
		} );
	}

	return result;
}
