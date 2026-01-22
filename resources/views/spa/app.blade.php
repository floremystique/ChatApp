<x-app-layout>
    <div id="spa-root"
         class="min-h-[calc(100vh-0px)] bg-gray-50 flex justify-center">
        <div class="w-full max-w-md bg-white min-h-screen flex flex-col">

            <!-- Top bar -->
            <div id="spa-topbar" class="h-14 px-4 flex items-center justify-between border-b bg-white">
                <div class="flex items-center gap-2">
                    <div class="h-9 w-9 rounded-full bg-purple-600 text-white flex items-center justify-center font-semibold">
                        <span id="spa-avatar">M</span>
                    </div>
                    <div>
                        <div id="spa-title" class="text-sm font-semibold leading-tight">Chats</div>
                        <div id="spa-subtitle" class="text-[11px] text-gray-500 leading-tight">Online</div>
                    </div>
                </div>

                <div id="spa-action-wrap" class="flex items-center gap-2">
                    <!-- Filled by spa.js depending on screen (new chat / delete chat / profile card) -->
                </div>
            </div>

            <!-- Content -->
            <div id="spa-view" class="flex-1 overflow-y-auto"></div>

            <!-- Bottom Tabs -->
            <div class="h-16 border-t bg-white px-4 flex items-center justify-around">
                <button class="spa-tab flex flex-col items-center gap-1 text-xs text-gray-500" data-tab="chats">
                    <span class="h-6 w-6 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>
                        </svg>
                    </span>
                    <span>Chats</span>
                </button>

                <button class="spa-tab flex flex-col items-center gap-1 text-xs text-gray-500" data-tab="matches">
                    <span class="h-6 w-6 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2 4 4 0 0 1 7 2c0 5.65-7 10-7 10z"/>
                        </svg>
                    </span>
                    <span>Matches</span>
                </button>

                <button class="spa-tab flex flex-col items-center gap-1 text-xs text-gray-500" data-tab="profile">
                    <span class="h-6 w-6 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </span>
                    <span>Profile</span>
                </button>
            </div>

        </div>
    </div>

    <style>
        /* Active tab */
        .spa-tab.is-active { color: rgb(147 51 234); }
        .spa-tab.is-active svg { color: rgb(147 51 234); }
    </style>
</x-app-layout>
