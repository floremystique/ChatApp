<x-app-layout>
    <div class="max-w-3xl mx-auto py-8">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold">Chat</h1>
            <button id="deleteChatBtn"
                    class="text-sm px-3 py-2 rounded border border-gray-300 hover:bg-gray-50">
                Delete chat
            </button>
        </div>

        <div class="relative mb-4">
            <div id="chatBox"
                 class="bg-white border rounded p-4 h-[420px] w-full overflow-y-auto overflow-x-hidden space-y-2">
            </div>

            <div id="typing-indicator"
                 class="absolute bottom-2 left-3 text-xs text-gray-500 opacity-0 transition-opacity pointer-events-none">
                Typing...
            </div>

            <!-- Closed overlay -->
            <div id="closedOverlay" class="hidden absolute inset-0 bg-white/70 backdrop-blur-sm rounded">
                <div class="h-full w-full flex items-center justify-center">
                    <div class="text-center px-6">
                        <div class="text-sm text-gray-700 font-medium">You can no longer send messages here.</div>
                        <div id="closedOverlaySub" class="text-xs text-gray-500 mt-1">This chat was deleted.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reply bar -->
        <div id="replyBar" class="hidden mb-2 p-3 border rounded bg-gray-50">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-semibold text-gray-700">Replying</div>
                    <div id="replyPreview" class="text-xs text-gray-600 truncate"></div>
                </div>
                <button id="cancelReply" class="text-xs px-2 py-1 rounded border border-gray-300 hover:bg-white">Cancel</button>
            </div>
        </div>

        <form id="chatForm" class="flex gap-2">
            <input id="msgInput"
                   name="body"
                   class="flex-1 rounded border-gray-300"
                   placeholder="Type..."
                   autocomplete="off">
            <button id="sendBtn" type="submit"
                    class="px-4 py-2 bg-black text-white rounded">
                Send
            </button>
        </form>
    </div>

    <!-- Context menu (reply/delete) -->
    <div id="msgMenu" class="hidden fixed z-50">
        <div class="bg-white border rounded shadow-lg overflow-hidden text-sm">
            <button id="menuReply" class="block w-full text-left px-4 py-2 hover:bg-gray-50">Reply</button>
            <button id="menuDelete" class="hidden block w-full text-left px-4 py-2 hover:bg-gray-50 text-red-600">Delete</button>
        </div>
    </div>

    <script>
        /* ========================
           BASIC STATE
        ======================== */
        const chatBox = document.getElementById('chatBox');
        const form = document.getElementById('chatForm');
        const input = document.getElementById('msgInput');
        const indicator = document.getElementById('typing-indicator');
        const replyBar = document.getElementById('replyBar');
        const replyPreview = document.getElementById('replyPreview');
        const cancelReply = document.getElementById('cancelReply');
        const closedOverlay = document.getElementById('closedOverlay');
        const closedOverlaySub = document.getElementById('closedOverlaySub');
        const deleteChatBtn = document.getElementById('deleteChatBtn');

        const menu = document.getElementById('msgMenu');
        const menuReply = document.getElementById('menuReply');
        const menuDelete = document.getElementById('menuDelete');

        const myId = {{ auth()->id() }};
        const csrf = "{{ csrf_token() }}";

        const roomUuid = "{{ $room->uuid }}";
        const sendUrl = "{{ route('chat.send', $room, false) }}";
        const typingUrl = "{{ route('chat.typing', $room->uuid, false) }}";
        const typingStatusUrl = "{{ route('chat.typingStatus', $room->uuid, false) }}";
        const seenStatusUrl = "{{ route('chat.seenStatus', $room, false) }}";
        const messagesUrl = "{{ route('chat.messages', $room, false) }}";
        const deleteChatUrl = "{{ route('chat.delete', $room, false) }}";
        const heartUrlBase = "{{ url('/chat/'.$room->uuid.'/message') }}";

        let newestId = 0;
        let beforeId = null;
        let hasMore = true;
        let loadingOlder = false;
        let lastTypingSent = 0;
        let typingTimer = null;
        const seenIds = new Set();

        // Reply state
        let replyToId = null;

        // Closed state (server-driven)
        let chatClosedAt = @json(optional($room->closed_at)->toISOString());
        let chatClosedBy = @json($room->closed_by);

        // Tap/press state
        let lastTapAt = 0;
        let lastTapMsgId = null;
        let dateHideTimer = null;
        let longPressTimer = null;
        let longPressTarget = null;

        /* ========================
           HELPERS
        ======================== */
        function esc(s) {
            return (s || '').replace(/[&<>"']/g, m =>
                ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[m])
            );
        }

        function fmtTime(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function fmtDate(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            return d.toLocaleDateString([], { weekday: 'short', year: 'numeric', month: 'short', day: '2-digit' });
        }

        function isClosed() {
            return !!chatClosedAt;
        }

        function applyClosedUI() {
            const closed = isClosed();

            // Disable send
            input.disabled = closed;
            document.getElementById('sendBtn').disabled = closed;
            input.classList.toggle('bg-gray-100', closed);

            if (closed) {
                closedOverlay.classList.remove('hidden');
                if (chatClosedBy && chatClosedBy !== myId) {
                    closedOverlaySub.textContent = 'The other user has deleted the chat. Messages remain visible.';
                } else {
                    closedOverlaySub.textContent = 'You deleted this chat. Messages remain visible.';
                }
            } else {
                closedOverlay.classList.add('hidden');
            }
        }

        function setReplyTarget(msg) {
            replyToId = msg.id;
            const who = (msg.user_id === myId) ? 'You' : 'Them';
            const preview = msg.body ? msg.body : 'Message deleted';
            replyPreview.textContent = `${who}: ${preview}`;
            replyBar.classList.remove('hidden');
            input.focus();
        }

        function clearReplyTarget() {
            replyToId = null;
            replyBar.classList.add('hidden');
            replyPreview.textContent = '';
        }

        function hideMenu() {
            menu.classList.add('hidden');
            longPressTarget = null;
        }

        function showMenuForMessage(el, msg) {
            longPressTarget = { el, msg };

            // Position near bubble
            const rect = el.getBoundingClientRect();
            const top = Math.min(window.innerHeight - 10, rect.top + window.scrollY + rect.height);
            const left = Math.min(window.innerWidth - 10, rect.left + window.scrollX + 10);
            menu.style.top = `${top}px`;
            menu.style.left = `${left}px`;

            // Toggle delete option if mine
            if (msg.user_id === myId && !msg.deleted_at) {
                menuDelete.classList.remove('hidden');
            } else {
                menuDelete.classList.add('hidden');
            }

            menu.classList.remove('hidden');
        }

        function renderMessage(m) {
            const isMe = m.user_id === myId;
            const time = fmtTime(m.created_at);
            const date = fmtDate(m.created_at);

            const bubbleStyle = isMe
              ? 'background:#111827;color:#fff;'
              : 'background:#f3f4f6;color:#111827;';

            const timeStyle = isMe
              ? 'color:rgba(255,255,255,.75);'
              : 'color:rgba(17,24,39,.55);';


            const bodyHtml = m.deleted_at
                ? `<span class="italic text-gray-500">This message was deleted</span>`
                : esc(m.body);

            const replyHtml = m.reply_to
                ? `
                  <button class="reply-quote w-full text-left mb-1 px-2 py-1 rounded bg-white/60 border border-gray-200"
                          data-jump="${m.reply_to.id}">
                      <div class="text-[11px] font-semibold text-gray-600">
                          Reply to ${m.reply_to.user_id === myId ? 'you' : 'them'}
                      </div>
                      <div class="text-[11px] text-gray-600 truncate">
                          ${m.reply_to.deleted_at ? 'Message deleted' : esc(m.reply_to.body_preview || '')}
                      </div>
                  </button>
                `
                : '';

            const heartVisible = (m.heart_count > 0) || m.my_hearted;
            const heartHtml = heartVisible
                ? `
                  <div class="heart-badge mt-1 inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full border ${m.my_hearted ? 'border-red-300 text-red-600 bg-red-50' : 'border-gray-200 text-gray-500 bg-white'}"
                       data-heart>
                      <span aria-hidden="true">♥</span>
                      <span data-heart-count>${m.heart_count}</span>
                  </div>
                `
                : '';

            return `
                <div class="flex ${isMe ? 'justify-end' : 'justify-start'}"
                     data-id="${m.id}"
                     data-mine="${isMe ? 1 : 0}"
                     data-readat="${m.read_at ?? ''}">

                    <div class="msg-bubble max-w-[80%] flex flex-col ${isMe ? 'items-end text-right' : 'items-start'}"
                         data-msgbubble>

                        <div class="px-3 py-2 rounded-lg whitespace-pre-wrap break-words inline-block max-w-full"
                             style="${bubbleStyle}">
                            ${replyHtml}
                            <div class="msg-body">${bodyHtml}</div>

                            <div class="mt-1 flex items-center justify-end gap-2">
                                <span class="msg-time text-[11px]" style="${timeStyle}" data-time>${time}</span>
                                <span class="msg-date hidden text-[11px]" style="${timeStyle}" data-date>${date}</span>
                            </div>
                        </div>


                        ${heartHtml}

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

            const container = last.querySelector('[data-msgbubble]');
            if (!container) return;

            container.insertAdjacentHTML('beforeend', `
                <div class="seen-label mt-1 text-[11px] text-gray-400 leading-none self-end">Seen</div>
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

            // update closed state from API
            chatClosedAt = data.chat_closed_at || null;
            chatClosedBy = data.chat_closed_by || null;
            applyClosedUI();

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

            chatBox.insertAdjacentHTML('afterbegin', data.items.map(renderMessage).join(''));
            data.items.forEach(m => seenIds.add(m.id));

            chatBox.scrollTop = oldTop + (chatBox.scrollHeight - oldHeight);
            beforeId = data.next_before_id;
            hasMore = data.has_more;
            loadingOlder = false;
        }

        async function pollNew() {
            const res = await fetch(`${messagesUrl}?after_id=${newestId}`, { cache: 'no-store' });
            const data = await res.json();

            // If chat becomes closed, lock UI immediately
            if ((data.chat_closed_at || null) !== (chatClosedAt || null)) {
                chatClosedAt = data.chat_closed_at || null;
                chatClosedBy = data.chat_closed_by || null;
                applyClosedUI();
                clearReplyTarget();
            }

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
            if (isClosed()) return;

            const now = Date.now();
            if (now - lastTypingSent > 700) {
                fetch(typingUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ typing: true })
                });
                lastTypingSent = now;
            }

            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                fetch(typingUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ typing: false })
                });
            }, 1200);
        });

        /* ========================
           SEND MESSAGE
        ======================== */
        form.addEventListener('submit', async e => {
            e.preventDefault();
            if (isClosed()) return;

            const text = input.value.trim();
            if (!text) return;

            input.value = '';
            input.focus();

            const payload = { body: text };
            if (replyToId) payload.reply_to_id = replyToId;

            clearReplyTarget();

            const res = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            if (!res.ok) return;
            const data = await res.json();
            if (!data?.message) return;

            if (!seenIds.has(data.message.id)) {
                seenIds.add(data.message.id);
                chatBox.insertAdjacentHTML('beforeend', renderMessage(data.message));
                newestId = data.message.id;
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        });

        cancelReply.addEventListener('click', (e) => {
            e.preventDefault();
            clearReplyTarget();
        });

        /* ========================
           MESSAGE INTERACTIONS (DELEGATED)
        ======================== */
        function getMsgIdFromEl(el) {
            const row = el.closest('[data-id]');
            return row ? parseInt(row.dataset.id, 10) : null;
        }

        function getMessageSnapshot(el) {
            const row = el.closest('[data-id]');
            if (!row) return null;

            // We keep only what we need for reply UI/menu logic
            const id = parseInt(row.dataset.id, 10);
            const user_id = row.dataset.mine === '1' ? myId : null; // fallback

            // Better: read from rendered content when needed
            return { id, user_id };
        }

        // Single tap: show date briefly
        function showDateForRow(row) {
            const dateEl = row.querySelector('[data-date]');
            if (!dateEl) return;

            dateEl.classList.remove('hidden');
            clearTimeout(dateHideTimer);
            dateHideTimer = setTimeout(() => {
                dateEl.classList.add('hidden');
            }, 1800);
        }

        // Toggle heart reaction
        async function toggleHeartForMessage(messageId, row) {
            const res = await fetch(`${heartUrlBase}/${messageId}/react`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ type: 'heart' })
            });
            if (!res.ok) return;
            const data = await res.json();

            // Update / create heart badge
            const bubble = row.querySelector('[data-msgbubble]');
            let badge = bubble.querySelector('[data-heart]');

            const shouldShow = data.hearted || (data.heart_count > 0);

            if (!shouldShow) {
                if (badge) badge.remove();
                return;
            }

            if (!badge) {
                bubble.insertAdjacentHTML('beforeend', `
                    <div class="heart-badge mt-1 inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full border" data-heart>
                        <span aria-hidden="true">♥</span>
                        <span data-heart-count>0</span>
                    </div>
                `);
                badge = bubble.querySelector('[data-heart]');
            }

            badge.querySelector('[data-heart-count]').textContent = data.heart_count;
            badge.className = `heart-badge mt-1 inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full border ${data.hearted ? 'border-red-300 text-red-600 bg-red-50' : 'border-gray-200 text-gray-500 bg-white'}`;
        }

        // Long press menu
        function startLongPress(e, row) {
            clearTimeout(longPressTimer);
            longPressTimer = setTimeout(() => {
                // build message object from DOM
                const msgId = parseInt(row.dataset.id, 10);
                const isMine = row.dataset.mine === '1';
                const bodyText = row.innerText?.trim()?.slice(0, 140) || '';
                const msg = { id: msgId, user_id: isMine ? myId : -1, body: bodyText, deleted_at: null };

                showMenuForMessage(row, msg);
            }, 520);
        }

        function cancelLongPress() {
            clearTimeout(longPressTimer);
        }

        // Hide menu on any outside click
        document.addEventListener('click', (e) => {
            if (!menu.contains(e.target)) hideMenu();
        });

        menuReply.addEventListener('click', () => {
            if (!longPressTarget) return;
            const row = longPressTarget.el;

            // Try to recover body quickly for preview
            const msgId = parseInt(row.dataset.id, 10);
            const isMine = row.dataset.mine === '1';
            const body = row.querySelector('.msg-bubble')?.innerText?.trim()?.split('\n')[0] || '';

            setReplyTarget({ id: msgId, user_id: isMine ? myId : -1, body });
            hideMenu();
        });

        menuDelete.addEventListener('click', async () => {
            if (!longPressTarget) return;
            const row = longPressTarget.el;
            const msgId = parseInt(row.dataset.id, 10);

            const res = await fetch(`${heartUrlBase}/${msgId}`.replace('/react',''), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            if (!res.ok) {
                hideMenu();
                return;
            }

            // Update UI in-place
            const textEl = row.querySelector('.px-3.py-2');
            if (textEl) {
                // Replace content, but keep time/date area
                const bodyContainer = textEl.querySelector('div');
                if (bodyContainer) bodyContainer.innerHTML = '<span class="italic text-gray-500">This message was deleted</span>';
            }

            hideMenu();
        });

        // Click/tap handlers on chatBox
        chatBox.addEventListener('click', async (e) => {
            const row = e.target.closest('[data-id]');
            if (!row) return;

            // Jump to replied message if clicked quote
            const quoteBtn = e.target.closest('[data-jump]');
            if (quoteBtn) {
                const jumpId = quoteBtn.getAttribute('data-jump');
                const target = chatBox.querySelector(`[data-id="${jumpId}"]`);
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }

            // Tap / double tap logic
            const msgId = parseInt(row.dataset.id, 10);
            const now = Date.now();

            const isDoubleTap = (lastTapMsgId === msgId) && (now - lastTapAt < 300);
            lastTapAt = now;
            lastTapMsgId = msgId;

            if (isDoubleTap) {
                await toggleHeartForMessage(msgId, row);
            } else {
                showDateForRow(row);
            }
        });

        chatBox.addEventListener('pointerdown', (e) => {
            const row = e.target.closest('[data-id]');
            if (!row) return;

            startLongPress(e, row);
        });

        chatBox.addEventListener('pointerup', cancelLongPress);
        chatBox.addEventListener('pointercancel', cancelLongPress);
        chatBox.addEventListener('pointermove', cancelLongPress);
        chatBox.addEventListener('scroll', () => {
            hideMenu();
            cancelLongPress();
            if (chatBox.scrollTop < 60) loadOlder();
        });

        /* ========================
           DELETE CHAT
        ======================== */
        deleteChatBtn.addEventListener('click', async () => {
            if (!confirm('Delete this chat? You and the other user will no longer be able to send messages here.')) return;

            const res = await fetch(deleteChatUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
            });
            if (!res.ok) return;

            const data = await res.json();
            chatClosedAt = data.closed_at || null;
            chatClosedBy = data.closed_by || null;
            applyClosedUI();
            clearReplyTarget();
        });

        /* ========================
           POLLING
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
            } catch (e) {
            }
        }

        /* ========================
           INIT
        ======================== */
        loadInitial();
        applyClosedUI();
        setInterval(pollAll, 1200);
    </script>
</x-app-layout>
