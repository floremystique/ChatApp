<x-app-layout>
    <div class="max-w-3xl mx-auto py-8">
        <h1 class="text-xl font-bold mb-4">Chat Room #</h1>

        <div class="relative mb-4">
            <div id="chatBox"
                 class="bg-white border rounded p-4 h-[420px] w-full overflow-y-auto overflow-x-hidden space-y-2">
            </div>

            <div id="typing-indicator"
                 class="absolute bottom-2 left-3 text-xs text-gray-500 opacity-0 transition-opacity pointer-events-none">
                Typing...
            </div>
        </div>


        <form id="chatForm" class="flex gap-2">
            <input id="msgInput"
                   name="body"
                   class="flex-1 rounded border-gray-300"
                   placeholder="Type..."
                   autocomplete="off">
            <button type="submit"
                    class="px-4 py-2 bg-black text-white rounded">
                Send
            </button>
        </form>
    </div>

   <script>
    /* ========================
       BASIC STATE
    ======================== */
    const chatBox = document.getElementById('chatBox');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('msgInput');
    const indicator = document.getElementById('typing-indicator');

    const myId = {{ auth()->id() }};
    const sendUrl = "{{ route('chat.send', $room, false) }}";
    const typingUrl = "{{ route('chat.typing', $room->uuid, false) }}";
    const typingStatusUrl = "{{ route('chat.typingStatus', $room->uuid, false) }}";
    const seenStatusUrl = "{{ route('chat.seenStatus', $room, false) }}";
    const messagesUrl = "{{ route('chat.messages', $room, false) }}";


    let newestId = 0;
    let beforeId = null;
    let hasMore = true;
    let loadingOlder = false;
    let lastTypingSent = 0;
    let typingTimer = null;
    const seenIds = new Set();

    /* ========================
       HELPERS
    ======================== */
    function esc(s) {
      return (s || '').replace(/[&<>"']/g, m =>
        ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;" }[m])
      );
    }

    function renderMessage(m) {
      const isMe = m.user_id === myId;

      return `
        <div class="flex ${isMe ? 'justify-end' : 'justify-start'}"
             data-id="${m.id}"
             data-mine="${isMe ? 1 : 0}"
             data-readat="${m.read_at ?? ''}">

          <div class="max-w-[80%] flex flex-col
                      ${isMe ? 'items-end text-right' : 'items-start'}">

            <div class="px-3 py-2 rounded bg-gray-100
                        whitespace-pre-wrap break-words">
              ${esc(m.body)}
            </div>

          </div>
        </div>
      `;
    }



    /* ========================
       SEEN LABEL
    ======================== */
    function updateSeen() {
      document.querySelectorAll('.seen-label').forEach(e => e.remove());

      const myMsgs = [...document.querySelectorAll('[data-mine="1"]')];
      if (!myMsgs.length) return;

      const last = myMsgs[myMsgs.length - 1];
      if (!last.dataset.readat) return;

      const container = last.querySelector('.flex-col');
      if (!container) return;

      container.insertAdjacentHTML('beforeend', `
        <div class="seen-label mt-1 text-[11px] text-gray-400 leading-none self-end">
          Seen
        </div>
      `);
    }



    /* ========================
       MESSAGE LOADING
    ======================== */
    async function loadInitial() {
      const res = await fetch(`${messagesUrl}?limit=30`, { cache: 'no-store' });
      const data = await res.json();

      chatBox.innerHTML = data.items.map(renderMessage).join('');
      data.items.forEach(m => seenIds.add(m.id));

      if (data.items.length) {
        newestId = data.items[data.items.length - 1].id;
        beforeId = data.next_before_id;
      }

      hasMore = data.has_more;
      chatBox.scrollTop = chatBox.scrollHeight;
      updateSeen();
    }

    async function loadOlder() {
      if (!hasMore || loadingOlder || !beforeId) return;
      loadingOlder = true;

      const oldHeight = chatBox.scrollHeight;
      const oldTop = chatBox.scrollTop;

      const res = await fetch(`${messagesUrl}?limit=30&before_id=${beforeId}`, { cache: 'no-store' });
      const data = await res.json();

      chatBox.insertAdjacentHTML('afterbegin',
        data.items.map(renderMessage).join('')
      );

      data.items.forEach(m => seenIds.add(m.id));

      chatBox.scrollTop = oldTop + (chatBox.scrollHeight - oldHeight);
      beforeId = data.next_before_id;
      hasMore = data.has_more;
      loadingOlder = false;
    }

    async function pollNew() {
      const res = await fetch(`${messagesUrl}?after_id=${newestId}`, { cache: 'no-store' });
      const data = await res.json();

      if (!data.items?.length) return;

      data.items.forEach(m => {
        if (seenIds.has(m.id)) return;
        seenIds.add(m.id);
        chatBox.insertAdjacentHTML('beforeend', renderMessage(m));
        newestId = Math.max(newestId, m.id);
      });

      const nearBottom =
        chatBox.scrollHeight - (chatBox.scrollTop + chatBox.clientHeight) < 120;

      if (nearBottom) chatBox.scrollTop = chatBox.scrollHeight;
      updateSeen();
    }

    /* ========================
       TYPING
    ======================== */
    input.addEventListener('input', () => {
      const now = Date.now();

      if (now - lastTypingSent > 700) {
        fetch(typingUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': "{{ csrf_token() }}"
          },
          body: JSON.stringify({ typing: true })
        });
        lastTypingSent = now;
      }

      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => {
        fetch(typingUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': "{{ csrf_token() }}"
          },
          body: JSON.stringify({ typing: false })
        });
      }, 1200);
    });

    /* ========================
       SEND MESSAGE
    ======================== */
    form.addEventListener('submit', async e => {
      e.preventDefault();

      const text = input.value.trim();
      if (!text) return;

      input.value = '';
      input.focus();

      const res = await fetch(sendUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': "{{ csrf_token() }}",
          'Accept': 'application/json'
        },
        body: JSON.stringify({ body: text })
      });

      const data = await res.json();
      if (!data?.message) return;

      if (!seenIds.has(data.message.id)) {
        seenIds.add(data.message.id);
        chatBox.insertAdjacentHTML('beforeend', renderMessage(data.message));
        newestId = data.message.id;
        chatBox.scrollTop = chatBox.scrollHeight;
      }
    });

    /* ========================
       POLLING (SINGLE INTERVAL)
    ======================== */
    async function pollAll() {
      try {
        await pollNew();

        const typingRes = await fetch(typingStatusUrl, { cache: 'no-store' });
        const typing = await typingRes.json();
        indicator.classList.toggle('opacity-0', !typing.typing);

        const seenRes = await fetch(seenStatusUrl, { cache: 'no-store' });
        const seen = await seenRes.json();

        if (seen?.id && seen?.read_at) {
          const el = document.querySelector(`[data-id="${seen.id}"][data-mine="1"]`);
          if (el) {
            el.dataset.readat = seen.read_at;
            updateSeen();
          }
        }
      } catch (e) {}
    }

    /* ========================
       INIT
    ======================== */
    chatBox.addEventListener('scroll', () => {
      if (chatBox.scrollTop < 60) loadOlder();
    });

    loadInitial();
    setInterval(pollAll, 1200);
</script>


</x-app-layout>
