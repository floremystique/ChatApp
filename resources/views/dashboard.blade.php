<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <a href="/match"
                       class="inline-flex items-center gap-2 text-indigo-600 font-medium hover:text-indigo-800 transition duration-200">
                        Go to your matches →
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ANDROID FAB -->
    <a href="/chats" class="fab fab--chat" aria-label="Open chats">
        <!-- Material chat icon -->
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4 4h16v12H7l-3 3V4z"></path>
        </svg>
    </a>

</x-app-layout>

<style>
    /* Android-like Floating Action Button */
.fab {
  position: fixed !important;
  right: 16px;
  bottom: 16px;
  width: 56px;          /* Material FAB standard */
  height: 56px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;

  background: #000000;
  color: #fff;
  text-decoration: none;

  /* Material elevation */
  box-shadow:
    0 6px 10px rgba(0,0,0,.14),
    0 1px 18px rgba(0,0,0,.12),
    0 3px 5px rgba(0,0,0,.20);

  z-index: 2147483647; /* always on top */
  -webkit-tap-highlight-color: transparent;
  user-select: none;
  cursor: pointer;
}

/* icon size */
.fab svg {
  width: 24px;
  height: 24px;
  fill: currentColor;
}

/* Hover/press like Android */
.fab:hover { filter: brightness(1.05); }
.fab:active { transform: scale(.96); }

/* Ripple effect (Android-ish) */
.fab::after {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: rgba(255,255,255,.22);
  transform: scale(0);
  opacity: 0;
  transition: transform .25s ease, opacity .35s ease;
  pointer-events: none;
}

.fab:active::after {
  transform: scale(1.7);
  opacity: 1;
  transition: 0s;
}

</style>
