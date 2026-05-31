import type { IconMeta } from './catalog';

/**
 * Derive the sorted, de-duplicated list of categories present in the metadata.
 *
 * @param meta Slug → { categories, tags }, or null.
 * @return Sorted unique category keys; [] when meta is null.
 */
export function categoriesFromMeta(
	meta: Record< string, IconMeta > | null
): string[] {
	if ( ! meta ) {
		return [];
	}
	const set = new Set< string >();
	for ( const slug in meta ) {
		for ( const category of meta[ slug ].c ) {
			set.add( category );
		}
	}
	return [ ...set ].sort();
}

/**
 * Human label for a category key (Phosphor keys are lower-case, e.g.
 * "maps & travel"). Capitalises the first letter only — full title-casing
 * mangles the ampersand phrases.
 *
 * @param category Category key.
 * @return Display label.
 */
export function categoryLabel( category: string ): string {
	return category.charAt( 0 ).toUpperCase() + category.slice( 1 );
}
