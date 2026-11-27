export const VIEWS_KEY = 'wp-pwa-manager-views';
export const VISIBLE_MS_KEY = 'wp-pwa-manager-visible-ms';
const TICK_MS = 1000;

export interface InstallGateConfig {
    min_views: number;
    min_visible_ms: number;
    consent_cookie: string;
}

type InstallPromptEvent = Event & {
    prompt?: () => Promise<void>;
    userChoice?: Promise<unknown>;
    platforms?: string[];
};

type GatedEvent = InstallPromptEvent & { ktReplayed?: boolean };

interface EarlyCapture {
    event: GatedEvent | null;
    listener: (event: Event) => void;
}

declare global {
    interface Window {
        wpPwaEarlyPrompt?: EarlyCapture;
    }
}

export const isGateEnabled = (config: InstallGateConfig | undefined): config is InstallGateConfig =>
    !!config && (config.min_views > 0 || config.min_visible_ms > 0 || config.consent_cookie !== '');

const cloneForReplay = (original: GatedEvent): GatedEvent => {
    const clone = new Event('beforeinstallprompt', { cancelable: true }) as GatedEvent;
    Object.defineProperties(clone, {
        ktReplayed: { value: true },
        prompt: { value: () => original.prompt?.() ?? Promise.resolve() },
        userChoice: { get: () => original.userChoice },
        platforms: { get: () => original.platforms ?? [] },
    });
    return clone;
};

const readNumber = (key: string): number => {
    try {
        return Number(window.localStorage.getItem(key)) || 0;
    } catch {
        return 0;
    }
};

const writeNumber = (key: string, value: number): void => {
    try {
        window.localStorage.setItem(key, String(value));
    } catch (error) {
        void error;
    }
};

export const initInstallGate = (config: InstallGateConfig): (() => void) => {
    let stored: GatedEvent | null = null;
    let replayed = false;

    const views = readNumber(VIEWS_KEY) + 1;
    writeNumber(VIEWS_KEY, views);

    let visibleMs = readNumber(VISIBLE_MS_KEY);
    let visibleSince: number | null = document.visibilityState === 'visible' ? Date.now() : null;

    const currentVisibleMs = (): number =>
        visibleMs + (visibleSince === null ? 0 : Date.now() - visibleSince);

    const persistVisible = (): void => {
        visibleMs = currentVisibleMs();
        visibleSince = document.visibilityState === 'visible' ? Date.now() : null;
        writeNumber(VISIBLE_MS_KEY, visibleMs);
    };

    const engaged = (): boolean => {
        if (config.min_views <= 0 && config.min_visible_ms <= 0) {
            return true;
        }
        return (
            (config.min_views > 0 && views >= config.min_views) ||
            (config.min_visible_ms > 0 && currentVisibleMs() >= config.min_visible_ms)
        );
    };

    const consentResolved = (): boolean =>
        config.consent_cookie === '' ||
        document.cookie.split(';').some((part) => part.trim().startsWith(`${config.consent_cookie}=`));

    const replay = (): void => {
        if (replayed || !stored || !engaged() || !consentResolved()) {
            return;
        }
        replayed = true;
        window.dispatchEvent(cloneForReplay(stored));
    };

    const capturePrompt = (event: Event): void => {
        const gated = event as GatedEvent;
        if (gated.ktReplayed) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        stored = gated;
        window.setTimeout(replay, 0);
    };

    const onVisibilityChange = (): void => {
        persistVisible();
        replay();
    };

    window.addEventListener('beforeinstallprompt', capturePrompt, true);

    const early = window.wpPwaEarlyPrompt;
    if (early) {
        window.removeEventListener('beforeinstallprompt', early.listener, true);
        if (early.event) {
            stored = early.event;
            early.event = null;
            window.setTimeout(replay, 0);
        }
    }

    document.addEventListener('visibilitychange', onVisibilityChange);
    window.addEventListener('pagehide', persistVisible);

    const timer = window.setInterval(() => {
        if (replayed) {
            window.clearInterval(timer);
            return;
        }
        replay();
    }, TICK_MS);

    return () => {
        window.clearInterval(timer);
        window.removeEventListener('beforeinstallprompt', capturePrompt, true);
        window.removeEventListener('pagehide', persistVisible);
        document.removeEventListener('visibilitychange', onVisibilityChange);
    };
};
