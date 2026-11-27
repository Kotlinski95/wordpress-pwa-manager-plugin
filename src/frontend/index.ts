import { PWAManager } from './pwa-manager';
import { initInstallGate, isGateEnabled } from './install-gate';
import './styles/frontend.scss';

document.addEventListener('DOMContentLoaded', async () => {
    const pwaManager = new PWAManager();
    await pwaManager.init();

    const gate = window.wpPwaManager?.installGate;
    if (isGateEnabled(gate)) {
        initInstallGate(gate);
    }
});
