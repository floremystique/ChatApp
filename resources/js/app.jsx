import './bootstrap';
import '../css/app.css';
import '../css/native.css';

// Capacitor (native wrapper) UX tweaks. Safe on web.
import './native';

import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';

const pages = import.meta.glob('./Pages/**/*.jsx'); // ✅ supports nested folders

createInertiaApp({
  resolve: async (name) => {
    const path = `./Pages/${name}.jsx`;

    if (!pages[path]) {
      throw new Error(`Inertia page not found: ${path}`);
    }

    const mod = await pages[path]();
    return mod.default;
  },

  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
