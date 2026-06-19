// Self-contained React demo banner for Craft CMP, built on the same core.
import { useCookieConsent } from './useCookieConsent.js';

export function CookieConsentBanner({ apiBase }) {
    const { config, open, selection, setSelection, acceptAll, rejectAll, savePrefs } = useCookieConsent(apiBase);
    if (!open || !config) return null;

    return (
        <section className="cc-banner" role="dialog" aria-label={config.banner.title}>
            <div>
                <h2>{config.banner.title}</h2>
                <div dangerouslySetInnerHTML={{ __html: config.banner.body }} />
            </div>
            <div className="cc-actions">
                <button onClick={rejectAll}>{config.banner.rejectAllLabel}</button>
                <button onClick={acceptAll}>{config.banner.acceptAllLabel}</button>
                {config.categories.filter((c) => !c.required).map((c) => (
                    <label key={c.handle}>
                        <input
                            type="checkbox"
                            checked={!!selection[c.handle]}
                            onChange={(e) => setSelection({ ...selection, [c.handle]: e.target.checked })}
                        />
                        {c.label}
                    </label>
                ))}
                <button onClick={() => savePrefs(selection)}>{config.banner.savePrefsLabel}</button>
            </div>
            {config.links?.length > 0 && (
                <ul className="cc-links">
                    {config.links.map((link, i) => (
                        <li key={i}>
                            <a
                                href={link.url}
                                target={link.newTab ? '_blank' : undefined}
                                rel={link.newTab ? 'noopener noreferrer' : undefined}
                            >{link.label}</a>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
