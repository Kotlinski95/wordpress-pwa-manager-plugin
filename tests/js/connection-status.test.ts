import {
	createManager,
	mockServiceWorker,
	releaseListeners,
	trackListeners,
} from './helpers';

describe( 'connection status', () => {
	beforeEach( async () => {
		trackListeners();
		jest.useFakeTimers();
		jest.spyOn( console, 'log' ).mockImplementation( () => undefined );
		document.body.innerHTML = '';
		mockServiceWorker();
		await createManager().init();
	} );

	afterEach( () => {
		releaseListeners();
		jest.useRealTimers();
		jest.restoreAllMocks();
		Reflect.deleteProperty( navigator, 'serviceWorker' );
	} );

	it( 'shows the offline message and keeps it visible', () => {
		window.dispatchEvent( new Event( 'offline' ) );
		jest.advanceTimersByTime( 10000 );

		const status = document.querySelector( '.pwa-connection-status' );
		expect( status?.classList.contains( 'pwa-status-offline' ) ).toBe(
			true
		);
		expect( status?.textContent ).toContain( 'You are offline' );
	} );

	it( 'shows the online message and hides it after three seconds', () => {
		window.dispatchEvent( new Event( 'online' ) );
		jest.advanceTimersByTime( 200 );
		expect(
			document.querySelector( '.pwa-status-online.show' )?.textContent
		).toContain( 'Back online' );

		jest.advanceTimersByTime( 3500 );
		expect( document.querySelector( '.pwa-connection-status' ) ).toBeNull();
	} );

	it( 'replaces a previous status instead of stacking them', () => {
		window.dispatchEvent( new Event( 'offline' ) );
		window.dispatchEvent( new Event( 'online' ) );

		expect(
			document.querySelectorAll( '.pwa-connection-status' )
		).toHaveLength( 1 );
	} );
} );
