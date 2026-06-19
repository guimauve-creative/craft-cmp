// React hook wrapping the framework-agnostic Craft CMP core.
// Copy `cookie-consent-core.js` (see ../javascript) alongside this file.
import { useEffect, useRef, useState, useCallback } from 'react';
import { CookieConsentManager } from './cookie-consent-core.js';

export function useCookieConsent(apiBase = '') {
    const cc = useRef(null);
    const [config, setConfig] = useState(null);
    const [open, setOpen] = useState(false);
    const [selection, setSelection] = useState({});

    useEffect(() => {
        cc.current = new CookieConsentManager({ apiBase });
        (async () => {
            const cfg = await cc.current.loadConfig();
            cc.current.bootstrapConsentMode();              // default-denied → GA + scripts
            setConfig(cfg);
            setSelection(cc.current.currentCategories());
            setOpen(cc.current.needsConsent());
        })();
    }, [apiBase]);

    const acceptAll = useCallback(() => { cc.current.acceptAll(); setOpen(false); }, []);
    const rejectAll = useCallback(() => { cc.current.rejectAll(); setOpen(false); }, []);
    const savePrefs = useCallback((sel) => { cc.current.savePreferences(sel); setOpen(false); }, []);
    const manage = useCallback(() => { setSelection(cc.current.currentCategories()); setOpen(true); }, []);

    return { config, open, selection, setSelection, acceptAll, rejectAll, savePrefs, manage };
}
