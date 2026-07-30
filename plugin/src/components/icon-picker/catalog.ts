export type IconMeta = { c: string[]; t: string[] };
export type IconSet = { viewBox: string; svgAttrs: Record< string, string > };
export type IconData = {
	markup: Record< string, string >;
	meta: Record< string, IconMeta > | null;
	set: IconSet;
};

const DEFAULT_SET: IconSet = {
	viewBox: '0 0 256 256',
	svgAttrs: { fill: 'currentColor' },
};

let cache: IconData | null = null;
let promise: Promise< IconData > | null = null;

function urls(): {
	markupUrl?: string;
	metaUrl?: string;
	setUrl?: string;
} {
	return (
		(
			window as unknown as {
				pedimentIcons?: {
					markupUrl?: string;
					metaUrl?: string;
					setUrl?: string;
				};
			}
		 ).pedimentIcons ?? {}
	);
}

async function fetchJson( url?: string ): Promise< unknown > {
	if ( ! url ) {
		return null;
	}
	const res = await fetch( url );
	if ( ! res.ok ) {
		throw new Error( `Failed to load icons (${ res.status }).` );
	}
	return res.json();
}

export function getCatalog(): Promise< IconData > {
	if ( cache ) {
		return Promise.resolve( cache );
	}
	if ( promise ) {
		return promise;
	}

	const { markupUrl, metaUrl, setUrl } = urls();
	if ( ! markupUrl ) {
		return Promise.reject(
			new Error( 'Icon catalog URL is unavailable.' )
		);
	}

	promise = ( async () => {
		const markup = ( await fetchJson( markupUrl ) ) as Record<
			string,
			string
		> | null;
		if ( ! markup ) {
			throw new Error( 'Icon catalog URL is unavailable.' );
		}
		// Meta and the manifest are optional: failures degrade gracefully.
		const meta = ( await fetchJson( metaUrl ).catch(
			() => null
		) ) as Record< string, IconMeta > | null;
		const setRaw = ( await fetchJson( setUrl ).catch(
			() => null
		) ) as Partial< IconSet > | null;
		const set: IconSet =
			setRaw && typeof setRaw.viewBox === 'string'
				? {
						viewBox: setRaw.viewBox,
						svgAttrs: setRaw.svgAttrs ?? {},
				  }
				: DEFAULT_SET;

		cache = { markup, meta, set };
		return cache;
	} )().catch( ( err ) => {
		promise = null;
		throw err;
	} );

	return promise;
}

// Test-only: clear the module-level cache between cases.
export function __resetCatalogForTests(): void {
	cache = null;
	promise = null;
}
