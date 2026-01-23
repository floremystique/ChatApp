<x-app-layout>
    <div class="max-w-2xl mx-auto py-8 space-y-4">
        <h1 class="text-xl font-bold">Broadcast Test</h1>

        <div class="p-3 rounded bg-gray-100 text-sm">
            Open this page in <b>two tabs</b>. Click “Fire event” in one tab.
            The other tab should receive it instantly.
        </div>

        <button id="fire"
            class="px-4 py-2 rounded bg-purple-600 text-white">
            Fire event
        </button>

        <pre id="log" class="p-3 bg-black text-green-200 rounded text-xs overflow-auto h-64"></pre>
    </div>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
      const log = (m) => {
        const el = document.getElementById('log');
        el.textContent += m + "\n";
        el.scrollTop = el.scrollHeight;
        console.log(m);
      };

      log("Listening on public channel: test-channel");

      function startListener() {
        if (!window.Echo) {
          log("⏳ Echo not ready yet... waiting");
          setTimeout(startListener, 300);
          return;
        }

        log("✅ Echo is ready. Subscribing...");
        window.Echo.channel('test-channel')
          .listen('.test.event', (e) => {
            log("✅ Received: " + JSON.stringify(e));
          });
      }

      startListener();

      document.getElementById('fire').addEventListener('click', async () => {
        log("Firing event...");
        const res = await fetch('/broadcast-test/fire', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
            'Accept': 'application/json'
          }
        });
        log("Fire response: " + res.status);
      });
    </script>

</x-app-layout>
