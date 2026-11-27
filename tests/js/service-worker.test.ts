import {
	createManager,
	mockServiceWorker,
	releaseListeners,
	trackListeners,
} from './helpers';

describe( 'service worker registration', () => {
	let errorSpy: jest.SpyInstance;

	beforeEach( () => {
		trackListeners();
		jest.spyOn( console, 'log' ).mockImplementation( () => undefined );
		jest.spyOn( console, 'warn' ).mockImplementation( () => undefined );
		errorSpy = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => undefined );
		document.body.innerHTML = '';
	} );

	afterEach( () => {
		releaseListeners();
		jest.restoreAllMocks();
		Reflect.deleteProperty( navigator, 'serviceWorker' );
	} );

	it( 'registers the configured worker URL at the site root without caching the script', async () => {
		const { container } = mockServiceWorker();

		await createManager().init();

		expect( container.register ).toHaveBeenCalledWith(
			'https://example.test/sw.js',
			{ scope: '/', updateViaCache: 'none' }
		);
	} );

	it( 'unregisters workers that were registered from a different script URL', async () => {
		const { container } = mockServiceWorker();
		const unregister = jest.fn().mockResolvedValue( true );
		const keep = jest.fn();
		container.getRegistrations.mockResolvedValue( [
			{
				active: { scriptURL: 'https://example.test/old-sw.js' },
				unregister,
			},
			{
				active: { scriptURL: 'https://example.test/sw.js' },
				unregister: keep,
			},
		] );

		await createManager().init();

		expect( unregister ).toHaveBeenCalledTimes( 1 );
		expect( keep ).not.toHaveBeenCalled();
	} );

	it( 'does not throw when service workers are unsupported', async () => {
		await expect( createManager().init() ).resolves.toBeUndefined();
	} );

	it( 'survives a failed registration', async () => {
		const { container } = mockServiceWorker();
		container.register.mockRejectedValue( new Error( 'denied' ) );

		await expect( createManager().init() ).resolves.toBeUndefined();
		expect( errorSpy ).toHaveBeenCalled();
	} );

	it( 'shows the update prompt when a waiting worker already exists', async () => {
		jest.useFakeTimers();
		mockServiceWorker( { waiting: {} } );

		await createManager().init();
		jest.advanceTimersByTime( 150 );

		expect(
			document.querySelector( '.pwa-update-prompt.show' )
		).not.toBeNull();
		jest.useRealTimers();
	} );

	it( 'does not show the update prompt when it is disabled', async () => {
		mockServiceWorker( { waiting: {} } );

		await createManager( { update_prompt_enabled: false } ).init();

		expect( document.querySelector( '.pwa-update-prompt' ) ).toBeNull();
	} );

	it( 'shows the update prompt on an UPDATE_AVAILABLE message and only once', async () => {
		const { emit } = mockServiceWorker();
		await createManager().init();

		emit( 'message', { data: { type: 'UPDATE_AVAILABLE' } } );
		emit( 'message', { data: { type: 'UPDATE_AVAILABLE' } } );

		expect(
			document.querySelectorAll( '.pwa-update-prompt' )
		).toHaveLength( 1 );
	} );

	it( 'posts SKIP_WAITING to the waiting worker when the update is confirmed', async () => {
		jest.useFakeTimers();
		const postMessage = jest.fn();
		mockServiceWorker( { waiting: { postMessage } } );
		await createManager().init();

		(
			document.querySelector( '[data-action="update"]' ) as HTMLElement
		 ).click();

		expect( postMessage ).toHaveBeenCalledWith( { type: 'SKIP_WAITING' } );
		jest.useRealTimers();
	} );

	it( 'removes the update prompt when dismissed', async () => {
		jest.useFakeTimers();
		mockServiceWorker( { waiting: {} } );
		await createManager().init();

		(
			document.querySelector( '[data-action="dismiss"]' ) as HTMLElement
		 ).click();
		jest.advanceTimersByTime( 400 );

		expect( document.querySelector( '.pwa-update-prompt' ) ).toBeNull();
		jest.useRealTimers();
	} );
} );
