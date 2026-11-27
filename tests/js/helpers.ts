import { PWAManager } from '../../src/frontend/pwa-manager';

export const i18n = {
	installPromptTitle: 'Install App',
	installPromptText: 'Install text',
	installPromptInstall: 'Install',
	installPromptLater: 'Later',
	installPromptDismiss: 'No thanks',
	updatePromptTitle: 'Update',
	updatePromptText: 'Update text',
	updatePromptUpdate: 'Update Now',
	updatePromptLater: 'Later',
	connectionOnline: 'Back online',
	connectionOffline: 'You are offline',
};

export const baseSettings = {
	app_name: 'App',
	theme_color: '#000000',
	background_color: '#ffffff',
	install_prompt_enabled: true,
	install_prompt_text: 'Install',
	install_prompt_button_text: 'Install',
	install_prompt_dismiss_text: 'No',
	update_prompt_enabled: true,
	update_prompt_text: 'A new version is available!',
	update_prompt_button_text: 'Update',
	push_notifications_enabled: false,
	vapid_public_key: '',
};

export const configure = (
	overrides: Record< string, unknown > = {}
): void => {
	window.wpPwaManager = {
		settings: { ...baseSettings, ...overrides },
		ajaxUrl: '/wp-admin/admin-ajax.php',
		swUrl: 'https://example.test/sw.js',
		i18n,
	} as typeof window.wpPwaManager;
};

export const createManager = (
	overrides: Record< string, unknown > = {}
): PWAManager => {
	configure( overrides );
	return new PWAManager();
};

export const mockServiceWorker = (
	registration: Record< string, unknown > = {}
) => {
	const listeners: Record< string, Array< ( event: unknown ) => void > > = {};
	const container = {
		controller: {},
		register: jest.fn().mockResolvedValue( {
			addEventListener: jest.fn(),
			waiting: null,
			installing: null,
			update: jest.fn().mockResolvedValue( undefined ),
			...registration,
		} ),
		getRegistrations: jest.fn().mockResolvedValue( [] ),
		addEventListener: jest.fn(
			( type: string, handler: ( event: unknown ) => void ) => {
				( listeners[ type ] ??= [] ).push( handler );
			}
		),
	};
	Object.defineProperty( navigator, 'serviceWorker', {
		value: container,
		configurable: true,
	} );
	return {
		container,
		emit: ( type: string, event: unknown ) =>
			( listeners[ type ] ?? [] ).forEach( ( handler ) =>
				handler( event )
			),
	};
};

const tracked: Array<
	[ EventTarget, string, EventListenerOrEventListenerObject ]
> = [];

export const trackListeners = (): void => {
	for ( const target of [ window, document ] ) {
		const original = target.addEventListener.bind( target );
		jest.spyOn( target, 'addEventListener' ).mockImplementation(
			(
				type: string,
				listener: EventListenerOrEventListenerObject,
				options?: boolean | AddEventListenerOptions
			) => {
				tracked.push( [ target, type, listener ] );
				original( type, listener, options );
			}
		);
	}
};

export const releaseListeners = (): void => {
	while ( tracked.length > 0 ) {
		const [ target, type, listener ] = tracked.pop() as [
			EventTarget,
			string,
			EventListenerOrEventListenerObject,
		];
		target.removeEventListener( type, listener );
	}
};
