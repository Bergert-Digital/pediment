/**
 * Filter a list of icon slugs by a search query (case-insensitive substring).
 *
 * @param slugs Full list of icon slugs.
 * @param query Raw search input.
 * @return The matching slugs, in original order; the full list when query is blank.
 */
export function filterIcons( slugs: string[], query: string ): string[] {
	const q = query.trim().toLowerCase();
	if ( ! q ) {
		return slugs;
	}
	return slugs.filter( ( slug ) => slug.includes( q ) );
}
