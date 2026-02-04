import type { CapacitorConfig } from '@capacitor/cli';

// IMPORTANT:
// - Use https in production.
// - This "server.url" mode wraps your existing Laravel/Inertia app (best fit since Inertia needs the backend).
// - When you later add Push Notifications, you'll keep this shell and add native plugins.

const config: CapacitorConfig = {
  appId: 'mu.floremystique.joojo',
  appName: 'JooJo',

  // Minimal local shell (required). The real UI loads from server.url.
  webDir: 'www',

  server: {
    // TODO: set to your production domain (or Railway URL) hosting the Laravel app.
    url: 'https://chatapp-production-b5f0.up.railway.app/',

    // Allow cleartext only for local dev if needed (prefer https).
    // androidScheme: 'https',

    // On Android, open external links in the system browser (recommended).
    // (Also handle via JS if you want finer control.)
    // allowNavigation: ['YOUR-DOMAIN-HERE']
  },

  android: {
    allowMixedContent: false
  },

  ios: {
    contentInset: 'automatic'
  }
};

export default config;
