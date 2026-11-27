import {
	initInstallGate,
	isGateEnabled,
	VIEWS_KEY,
	VISIBLE_MS_KEY,
} from '../../src/frontend/install-gate';

const fire = (): Event & { prompt: jest.Mock; ktReplayed?: boolean } => {
	const event = Object.assign(
		new Event( 'beforeinstallprompt', { cancelable: true } ),
		{ prompt: jest.fn().mockResolvedValue( {} ) }
	);
	window.dispatchEvent( event );
	return event;
};

describe( 'install gate', () => {
	let stop: () => void;
	let received: Array< Event & { ktReplayed?: boolean } >;
	const listener = ( event: Event ): void => {
		received.push( event as Event & { ktReplayed?: boolean } );
	};

	beforeEach( () => {
		jest.useFakeTimers();
		localStorage.clear();
		document.cookie = 'consent=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
		delete window.wpPwaEarlyPrompt;
		received = [];
		window.addEventListener( 'beforeinstallprompt', listener );
	} );

	afterEach( () => {
		stop?.();
		window.removeEventListener( 'beforeinstallprompt', listener );
		jest.useRealTimers();
	} );

	it( 'is disabled for the default config', () => {
		expect(
			isGateEnabled( {
				min_views: 0,
				min_visible_ms: 0,
				consent_cookie: '',
			} )
		).toBe( false );
		expect( isGateEnabled( undefined ) ).toBe( false );
		expect(
			isGateEnabled( {
				min_views: 0,
				min_visible_ms: 0,
				consent_cookie: 'c',
			} )
		).toBe( true );
	} );

	it( 'holds the prompt until min_views is reached', () => {
		localStorage.setItem( VIEWS_KEY, '1' );
		stop = initInstallGate( {
			min_views: 2,
			min_visible_ms: 0,
			consent_cookie: '',
		} );

		const original = fire();
		jest.advanceTimersByTime( 10 );

		expect( original.defaultPrevented ).toBe( true );
		expect( received ).toHaveLength( 1 );
		expect( received[ 0 ].ktReplayed ).toBe( true );
		expect( localStorage.getItem( VIEWS_KEY ) ).toBe( '2' );
	} );

	it( 'does not replay on the first view until visible time is reached', () => {
		stop = initInstallGate( {
			min_views: 2,
			min_visible_ms: 30000,
			consent_cookie: '',
		} );

		fire();
		jest.advanceTimersByTime( 10000 );
		expect( received ).toHaveLength( 0 );

		jest.advanceTimersByTime( 21000 );
		expect( received ).toHaveLength( 1 );
		expect( received[ 0 ].ktReplayed ).toBe( true );
	} );

	it( 'waits for the consent cookie', () => {
		stop = initInstallGate( {
			min_views: 0,
			min_visible_ms: 0,
			consent_cookie: 'consent',
		} );

		fire();
		jest.advanceTimersByTime( 5000 );
		expect( received ).toHaveLength( 0 );

		document.cookie = 'consent=allow';
		jest.advanceTimersByTime( 1000 );
		expect( received ).toHaveLength( 1 );
	} );

	it( 'replays only once and forwards prompt to the original event', async () => {
		stop = initInstallGate( {
			min_views: 0,
			min_visible_ms: 0,
			consent_cookie: 'consent',
		} );
		document.cookie = 'consent=allow';

		const original = fire();
		jest.advanceTimersByTime( 5000 );
		await received[ 0 ].prompt?.();

		expect( received ).toHaveLength( 1 );
		expect( original.prompt ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'adopts an event captured early by the inline head script', () => {
		const early = Object.assign(
			new Event( 'beforeinstallprompt', { cancelable: true } ),
			{ prompt: jest.fn().mockResolvedValue( {} ) }
		);
		const earlyListener = jest.fn();
		window.wpPwaEarlyPrompt = {
			event: early,
			listener: earlyListener,
		};
		document.cookie = 'consent=allow';

		stop = initInstallGate( {
			min_views: 0,
			min_visible_ms: 0,
			consent_cookie: 'consent',
		} );
		jest.advanceTimersByTime( 10 );

		expect( received ).toHaveLength( 1 );
		expect( received[ 0 ].ktReplayed ).toBe( true );
		expect( window.wpPwaEarlyPrompt?.event ).toBeNull();
	} );

	it( 'persists visible time on pagehide', () => {
		stop = initInstallGate( {
			min_views: 5,
			min_visible_ms: 0,
			consent_cookie: '',
		} );

		jest.advanceTimersByTime( 4000 );
		window.dispatchEvent( new Event( 'pagehide' ) );

		expect( Number( localStorage.getItem( VISIBLE_MS_KEY ) ) ).toBe( 4000 );
	} );
} );
