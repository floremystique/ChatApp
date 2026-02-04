// Lightweight runtime tweaks when running inside Capacitor.
// Safe to load on web too (no hard dependency on @capacitor/*).

function isCapacitor() {
  return !!(window.Capacitor && window.Capacitor.isNativePlatform);
}

(function initNativeHints() {
  const native = isCapacitor();
  document.documentElement.classList.toggle('is-capacitor', native);
  window.__IS_CAPACITOR__ = native;

  // Open external links in system browser (important UX & security).
  // On Android, Capacitor can also be configured to do this; we enforce it here.
  document.addEventListener('click', (e) => {
    const a = e.target?.closest?.('a');
    if (!a) return;
    const href = a.getAttribute('href');
    if (!href) return;

    // Ignore internal navigation and anchors.
    if (href.startsWith('#') || href.startsWith('/')) return;

    // If it's a full URL and not your domain, open externally.
    if (href.startsWith('http')) {
      try {
        const url = new URL(href);
        const sameHost = url.host === window.location.host;
        if (!sameHost && native) {
          e.preventDefault();
          window.open(href, '_system');
        }
      } catch {
        // ignore
      }
    }
  }, true);
})();
