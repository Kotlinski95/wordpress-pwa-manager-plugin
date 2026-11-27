import {
	createManager,
	mockServiceWorker,
	releaseListeners,
	trackListeners,
} from './helpers';

const fireInstallPrompt = (): Event & { prompt: jest.Mock } => {
	const event = Object.assign(
		new Event( 'beforeinstallprompt', { cancelable: true } ),
		{ prompt: jest.fn().mockResolvedValue( {} ) }
	);
	window.dispatchEvent( event );
	return event;
};

describe( 'install prompt', () => {
	beforeEach( async () => {
		trackListeners();
		jest.useFakeTimers();
		jest.spyOn( console, 'log' ).mockImplementation( () => undefined );
		document.body.innerHTML = '';
		localStorage.clear();
		mockServiceWorker();
	} );

	afterEach( () => {
		releaseListeners();
		jest.useRealTimers();
		jest.restoreAllMocks();
		Reflect.deleteProperty( navigator, 'serviceWorker' );
	} );

	const start = async (
		overrides: Record< string, unknown > = {}
	): Promise< void > => {
		await createManager( overrides ).init();
	};

	it( 'shows the prompt three seconds after beforeinstallprompt and prevents the native one', async () => {
		await start();

		const event = fireInstallPrompt();
		expect( event.defaultPrevented ).toBe( true );
		expect( document.querySelector( '.pwa-install-prompt' ) ).toBeNull();

		jest.advanceTimersByTime( 3200 );
		expect(
			document.querySelector( '.pwa-install-prompt.show' )
		).not.toBeNull();
		expect(
			document.querySelector( '.pwa-install-button' )
		).not.toBeNull();
	} );

	it( 'does nothing when the install prompt is disabled', async () => {
		await start( { install_prompt_enabled: false } );

		const event = fireInstallPrompt();
		jest.advanceTimersByTime( 4000 );

		expect( event.defaultPrevented ).toBe( false );
		expect( document.querySelector( '.pwa-install-prompt' ) ).toBeNull();
	} );

	it( 'stays hidden after a permanent dismissal', async () => {
		localStorage.setItem( 'pwa-install-dismissed-permanently', 'true' );
		await start();

		fireInstallPrompt();
		jest.advanceTimersByTime( 4000 );

		expect( document.querySelector( '.pwa-install-prompt' ) ).toBeNull();
	} );

	it( 'stays hidden during a temporary dismissal and returns once it expires', async () => {
		localStorage.setItem(
			'pwa-install-dismissed-until',
			String( Date.now() + 60000 )
		);
		await start();
		fireInstallPrompt();
		jest.advanceTimersByTime( 3200 );
		expect( document.querySelector( '.pwa-install-prompt' ) ).toBeNull();

		localStorage.setItem(
			'pwa-install-dismissed-until',
			String( Date.now() - 1000 )
		);
		fireInstallPrompt();
		jest.advanceTimersByTime( 3200 );
		expect(
			document.querySelector( '.pwa-install-prompt' )
		).not.toBeNull();
		expect(
			localStorage.getItem( 'pwa-install-dismissed-until' )
		).toBeNull();
	} );

	it( 'stores a 24 hour dismissal for the later button', async () => {
		await start();
		fireInstallPrompt();
		jest.advanceTimersByTime( 3200 );

		(
			document.querySelector(
				'[data-action="dismiss-temp"]'
			) as HTMLElement
		 ).click();
		jest.advanceTimersByTime( 400 );

		const until = Number(
			localStorage.getItem( 'pwa-install-dismissed-until' )
		);
		expect( until - Date.now() ).toBeGreaterThan( 23 * 3600 * 1000 );
		expect( document.querySelector( '.pwa-install-prompt' ) ).toBeNull();
	} );

	it( 'stores a permanent dismissal for the no thanks button', async () => {
		await start();
		fireInstallPrompt();
		jest.advanceTimersByTime( 3200 );

		(
			document.querySelector(
				'[data-action="dismiss-permanent"]'
			) as HTMLElement
		 ).click();

		expect(
			localStorage.getItem( 'pwa-install-dismissed-permanently' )
		).toBe( 'true' );
	} );

	it( 'calls the deferred native prompt when install is clicked', async () => {
		await start();
		const event = fireInstallPrompt();
		jest.advanceTimersByTime( 3200 );

		(
			document.querySelector( '[data-action="install"]' ) as HTMLElement
		 ).click();

		expect( event.prompt ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'removes the prompt when the app gets installed', async () => {
		await start();
		fireInstallPrompt();
		jest.advanceTimersByTime( 3200 );

		window.dispatchEvent( new Event( 'appinstalled' ) );
		jest.advanceTimersByTime( 400 );

		expect( document.querySelector( '.pwa-install-prompt' ) ).toBeNull();
	} );
} );
