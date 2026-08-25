import { getAiStatus, aiStatusNotice } from '../aiStatus';

describe( 'getAiStatus', () => {
	afterEach( () => {
		delete ( window as any ).pedimentAiEditor;
	} );

	it( 'defaults to ok when no config is injected', () => {
		expect( getAiStatus() ).toEqual( { status: 'ok', settingsUrl: '' } );
	} );

	it( 'reads the injected status and settings url', () => {
		( window as any ).pedimentAiEditor = {
			aiStatus: 'missing_key',
			settingsUrl: 'https://example.test/wp-admin/options-general.php?page=pediment',
		};
		expect( getAiStatus() ).toEqual( {
			status: 'missing_key',
			settingsUrl:
				'https://example.test/wp-admin/options-general.php?page=pediment',
		} );
	} );

	it( 'falls back to ok on an unknown status value', () => {
		( window as any ).pedimentAiEditor = { aiStatus: 'weird' };
		expect( getAiStatus().status ).toBe( 'ok' );
	} );
} );

describe( 'aiStatusNotice', () => {
	it( 'returns null when the AI is configured', () => {
		expect( aiStatusNotice( 'ok' ) ).toBeNull();
	} );

	it( 'names mock mode so canned replies are not mistaken for the AI', () => {
		expect( aiStatusNotice( 'mock' ) ).toMatch( /mock mode/i );
	} );

	it( 'says the API key is missing', () => {
		expect( aiStatusNotice( 'missing_key' ) ).toMatch( /API key/i );
	} );
} );
