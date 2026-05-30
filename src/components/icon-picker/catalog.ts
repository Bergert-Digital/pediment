export type Catalog = Record< string, string >;

let catalogCache: Catalog | null = null;
let catalogPromise: Promise< Catalog > | null = null;

function getCatalogUrl(): string | undefined {
	return (
		window as unknown as {
			pedimentIcons?: { catalogUrl?: string };
		}
	 ).pedimentIcons?.catalogUrl;
}

export function getCatalog(): Promise< Catalog > {
	if ( catalogCache ) {
		return Promise.resolve( catalogCache );
	}
	if ( catalogPromise ) {
		return catalogPromise;
	}
	const url = getCatalogUrl();
	if ( ! url ) {
		return Promise.reject(
			new Error( 'Icon catalog URL is unavailable.' )
		);
	}
	catalogPromise = fetch( url )
		.then( ( res ) => {
			if ( ! res.ok ) {
				throw new Error( `Failed to load icons (${ res.status }).` );
			}
			return res.json();
		} )
		.then( ( data: Catalog ) => {
			catalogCache = data;
			return data;
		} )
		.catch( ( err ) => {
			catalogPromise = null;
			throw err;
		} );
	return catalogPromise;
}
