function el(id) { return document.getElementById(id); }

const state = {
    booted: false,
    user: null,
    status: { has_profile: false, has_quiz: false },
    rooms: [],
    activeTab: 'chats',
    activeRoom: null,
    matchesSub: 'list',
    onboardingProfileHtml: null,
    onboardingQuizHtml: null,
    messages: [],
    typing: false,
    loading: false,
};

// Keep memory stable on mobile: never hold the entire history in DOM/JS.
const MAX_MSGS_IN_MEMORY = 300;

let _renderMessagesScheduled = false;
let _renderScrollToBottom = false;

let _seenDebounceTimer = null;

function trimMessageWindow() {
  const extra = state.messages.length - MAX_MSGS_IN_MEMORY;
  if (extra > 0) state.messages.splice(0, extra);
}

function scheduleRenderChatMessages(scrollToBottom) {
  _renderScrollToBottom = _renderScrollToBottom || !!scrollToBottom;
  if (_renderMessagesScheduled) return;
  _renderMessagesScheduled = true;
  requestAnimationFrame(() => {
    _renderMessagesScheduled = false;
    renderChatMessages(_renderScrollToBottom);
    _renderScrollToBottom = false;
  });
}

function scheduleSeen() {
  if (!state.activeRoom?.uuid) return;
  if (document.visibilityState !== 'visible') return;
  clearTimeout(_seenDebounceTimer);
  _seenDebounceTimer = setTimeout(async () => {
    try { await apiPost(`/chat/${state.activeRoom.uuid}/seen`, {}); } catch {}
  }, 800);
}

function isNearBottom(elm, thresholdPx = 80) {
  if (!elm) return true;
  return (elm.scrollHeight - elm.scrollTop - elm.clientHeight) < thresholdPx;
}

function pushUniqueMessage(msg) {
  if (!msg) return;

  const cid = (msg.client_message_id ?? msg.clientMessageId ?? null);
  const idNum = Number(msg.id);

  // Prefer reconciliation by client_message_id (optimistic UI)
  if (cid) {
    const idx = state.messages.findIndex(m => (m.client_message_id ?? m.clientMessageId) === cid);
    if (idx >= 0) {
      state.messages[idx] = { ...state.messages[idx], ...msg, pending: false, failed: false };
      trimMessageWindow();
      return;
    }
  }

  // De-dupe by numeric id
  if (idNum && state.messages.some(m => Number(m.id) === idNum)) return;

  state.messages.push(msg);
  trimMessageWindow();
}

function initials(name) {
    const parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    return (parts[0][0] + (parts[1]?.[0] || '')).toUpperCase();
}

function setActiveTab(tab) {
    if (tab !== 'matches') state.matchesSub = 'list';
    state.activeTab = tab;
    document.querySelectorAll('.spa-tab').forEach(b => {
        b.classList.toggle('is-active', b.dataset.tab === tab);
    });
    render();
}

function setTopbar(title, subtitle) {
    el('spa-title').textContent = title || '';
    el('spa-subtitle').textContent = subtitle || '';
}

function setAvatar(text) {
    el('spa-avatar').textContent = text || '?';
}

function setAction(html, onClick) {
    const wrap = document.getElementById('spa-action-wrap');
    if (!wrap) return;
    wrap.innerHTML = html || '';
    const btn = wrap.querySelector('[data-spa-action]');
    if (btn && typeof onClick === 'function') {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            onClick();
        });
    }
}

function htmlEscape(s) {
    return (s ?? '').toString()
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

async function apiGet(url, params = {}) {
    const res = await window.axios.get(url, { params, headers: { 'Accept': 'application/json' } });
    return res.data;
}
async function apiPost(url, data = {}) {
    const res = await window.axios.post(url, data, { headers: { 'Accept': 'application/json' } });
    return res.data;
}

async function boot() {
    state.loading = true;
    renderLoading();

    const data = await apiGet('/api/bootstrap');
    state.user = data.user;
    state.status = data.status;
    state.rooms = data.rooms || [];
    state.booted = true;

    setAvatar(initials(state.user?.name));
    state.loading = false;

    // Listen for chat list updates (new message/unread changes)
    window.Echo.private(`user.${state.user.id}`)
        .listen('.chatlist.updated', (e) => {
            // Minimal: re-fetch rooms (fast enough now; later optimize by patching)
            refreshRooms();
        });

    // Default view
    setActiveTab('chats');
    // Preload other tabs in background to feel instant
    preloadMatchesHtml();
    preloadProfileHtml();
    preloadOnboardingProfileHtml();
    preloadOnboardingQuizHtml();
}

let cachedMatchesHtml = null;
let cachedProfileHtml = null;
let cachedOnboardingProfileHtml = null;
let cachedOnboardingQuizHtml = null;

async function preloadMatchesHtml() {
    try {
        const res = await window.axios.get('/partials/matches', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        cachedMatchesHtml = res.data;
    } catch {}
}
async function preloadProfileHtml() {
    try {
        const res = await window.axios.get('/partials/profile', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        cachedProfileHtml = res.data;
    } catch {}
}


async function preloadOnboardingProfileHtml() {
  const res = await window.axios.get('/partials/onboarding-profile', {
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  });
  cachedOnboardingProfileHtml = res.data;
}
async function preloadOnboardingQuizHtml() {
    try {
        const res = await window.axios.get('/partials/onboarding-quiz', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        cachedOnboardingQuizHtml = res.data;
    } catch {}
}


async function refreshRooms() {
    try {
        const data = await apiGet('/api/rooms');
        state.rooms = data.rooms || [];
        if (state.activeTab === 'chats' && !state.activeRoom) renderChatsList();
    } catch {}
}

function renderLoading() {
    const view = el('spa-view');
    view.innerHTML = `
        <div class="p-6">
            <div class="animate-pulse space-y-3">
                <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                <div class="h-12 bg-gray-200 rounded"></div>
                <div class="h-12 bg-gray-200 rounded"></div>
                <div class="h-12 bg-gray-200 rounded"></div>
            </div>
        </div>
    `;
}

function render() {
    if (state.loading || !state.booted) return renderLoading();

    if (state.activeTab === 'chats') {
        if (state.activeRoom) return renderChatRoom();
        return renderChatsList();
    }

    if (state.activeTab === 'matches') return renderMatches();
    if (state.activeTab === 'profile') return renderProfile();
}

function renderChatsList() {
    setTopbar('Chats', '');
    setAvatar(initials(state.user?.name));
    setAction(
        `<button data-spa-action class="h-9 w-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center" title="New chat">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/>
            </svg>
        </button>`,
        () => setActiveTab('matches')
    );

    const view = el('spa-view');
    if (!state.rooms.length) {
        view.innerHTML = `
          <div class="p-6 text-center">
            <div class="text-gray-800 font-semibold">No chats yet</div>
            <div class="text-gray-500 text-sm mt-1">Start matching to begin chatting.</div>

            <button id="spa-find-matches"
              class="inline-block mt-4 px-4 py-2 rounded-full bg-purple-600 text-white font-semibold">
              Find matches
            </button>
          </div>
        `;

        document.getElementById('spa-find-matches')?.addEventListener('click', async () => {
          // switch to matches tab + refresh latest
          state.activeRoom = null;
          state.activeTab = 'matches';
          document.querySelectorAll('.spa-tab').forEach(b => {
            b.classList.toggle('is-active', b.dataset.tab === 'matches');
          });

          // If user is new, show the onboarding screen in matches immediately
          state.matchesSub = state.status.has_profile ? 'list' : 'list';

          await refreshMatchesNow(); // will render matches list / onboarding screen correctly
        });

        return;
    }

    view.innerHTML = `
      <div style="padding:12px;">
        <div style="position:relative; margin-bottom:12px;">
          <input
            id="spa-search"
            type="text"
            placeholder="Search"
            style="
              width:100%;
              height:40px;
              border-radius:9999px;
              border:1px solid #e5e7eb;
              background:#f9fafb;
              padding-left:16px;
              padding-right:48px;
              font-size:14px;
              outline:none;
            "
          />

          <div style="
            position:absolute;
            right:16px;
            top:50%;
            transform:translateY(-50%);
            color:#9ca3af;
            pointer-events:none;
            display:flex;
            align-items:center;
            justify-content:center;
          ">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd"
                d="M12.9 14.32a8 8 0 111.414-1.414l3.387 3.387a1 1 0 01-1.414 1.414l-3.387-3.387zM14 8a6 6 0 11-12 0 6 6 0 0112 0z"
                clip-rule="evenodd"/>
            </svg>
          </div>
        </div>

        <div id="spa-room-list"></div>
      </div>
    `;


    const list = document.getElementById('spa-room-list');
    const renderList = (q = '') => {
        const query = q.trim().toLowerCase();
        const items = state.rooms.filter(r => {
            const name = r.other_user?.name || '';
            return !query || name.toLowerCase().includes(query);
        });

        list.innerHTML = items.map(r => {
            const name = r.other_user?.name || 'User';
            const last = r.typing ? 'Typing…' : (r.last_message?.body || '');
            const time = r.last_message?.created_at ? new Date(r.last_message.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '';
            const unread = r.unread_count || 0;

            return `
                <button class="w-full flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 text-left" data-room-id="${r.id}" data-room-uuid="${r.uuid}">
                    <div class="h-11 w-11 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center font-semibold">
                        ${htmlEscape(initials(name))}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold text-sm truncate">${htmlEscape(name)}</div>
                            <div class="text-[11px] text-gray-400 ml-2">${htmlEscape(time)}</div>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <div class="text-xs ${r.typing ? 'text-purple-600 font-semibold' : 'text-gray-500'} truncate">${htmlEscape(last)}</div>
                            ${unread ? `<span class="ml-2 text-[11px] bg-purple-600 text-white rounded-full px-2 py-0.5">${unread}</span>` : ``}
                        </div>
                    </div>
                </button>
            `;
        }).join('');
    };

    renderList();

    const search = document.getElementById('spa-search');
    search.addEventListener('input', () => renderList(search.value));

    list.querySelectorAll('button[data-room-id]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const roomId = btn.dataset.roomId;
            const roomUuid = btn.dataset.roomUuid;
            await openRoom(roomId, roomUuid);
        });
    });

    setViewScrollMode('scroll');
}

let roomChannel = null;

async function openRoom(roomId, roomUuid) {
    state.activeRoom = { id: roomId, uuid: roomUuid };
    state.messages = [];
    state.typing = false;

    // subscribe to room events
    if (roomChannel) {
        try { roomChannel.stopListening('.message.sent'); } catch {}
        try { window.Echo.leave(`private-chat.room.${state.activeRoom.uuid}`); } catch {}
    }

    roomChannel = window.Echo.private(`chat.room.${roomUuid}`)
        .listen('.message.sent', (e) => {
            pushUniqueMessage(e.message);
            const box = document.getElementById('spa-messages');
            const autoScroll = isNearBottom(box);
            scheduleRenderChatMessages(autoScroll);
            if (String(e.message?.user_id) !== String(state.user?.id)) scheduleSeen();
        })
        .listen('.typing.updated', (e) => {
            // Ignore my own typing echo
            if (String(e.userId) === String(state.user?.id)) return;
            state.typing = !!e.typing;
            renderTyping();
        })
        .listen('.message.seen', (e) => {
            // When the other user reads, mark my messages up to messageId as read.
            if (!e || !e.messageId) return;
            const me = state.user?.id;
            state.messages.forEach(m => {
                if (String(m.user_id) === String(me) && Number(m.id) <= Number(e.messageId)) {
                    m.read_at = e.readAt || (m.read_at ?? new Date().toISOString());
                }
            });
            scheduleRenderChatMessages(false);
        })
        .listen('.reaction.updated', (e) => {
            const msg = state.messages.find(m => m.id === e.messageId);
            if (msg) {
                msg.heart_count = e.heartCount;
                if (String(e.userId) === String(state.user?.id)) msg.my_hearted = !!e.hearted;
            }
            scheduleRenderChatMessages(false);
        })
        .listen('.message.deleted', (e) => {
            state.messages = state.messages.filter(m => m.id !== e.messageId);
            scheduleRenderChatMessages(false);
        })
        .listen('.chat.closed', (e) => {
            renderChatRoom(); // will disable input
        });

    renderChatRoom();
    await loadLatestMessages();
}

async function loadLatestMessages() {
    const data = await apiGet(`/chat/${state.activeRoom.uuid}/messages`, { limit: 30 });
    state.messages = (data.items || []);

    // Mark my messages as "seen" based on the other user's last_read id (no per-row DB writes)
    const otherLastReadId = Number(data.other_last_read_id || 0);
    if (otherLastReadId) {
        const me = state.user?.id;
        state.messages.forEach(m => {
            if (String(m.user_id) === String(me) && Number(m.id) <= otherLastReadId) {
                m.read_at = m.read_at || new Date().toISOString();
            }
        });
    }

    scheduleRenderChatMessages(true);
    scheduleSeen();
}

function renderChatRoom() {
    setViewScrollMode('no-scroll');
    const room = state.rooms.find(r => String(r.id) === String(state.activeRoom.id));
    const name = room?.other_user?.name || 'Chat';
    setTopbar(name, state.typing ? 'Typing…' : '');
    setAvatar(initials(name));

    // Right-side action = delete chat (trash)
    setAction(
        `<button data-spa-action class="h-9 w-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center" title="Delete chat">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                <path d="M10 11v6"/>
                <path d="M14 11v6"/>
                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
            </svg>
        </button>`,
        async () => {
            if (!confirm('Delete this chat?')) return;
            await apiPost(`/chat/${state.activeRoom.uuid}/delete-chat`, {});
            state.activeRoom = null;
            await refreshRooms();
            render();
        }
    );

    const closed = !!room?.closed_at;

    const view = el('spa-view');
    view.innerHTML = `
        <div class="h-full flex flex-col overflow-hidden">
            <div class="px-3 py-2 border-b bg-white flex items-center justify-between">
                <button id="spa-back" class="text-purple-700 text-sm font-semibold">← Back</button>
                <div class="text-xs text-gray-500">${closed ? 'Chat closed' : ''}</div>
            </div>

            <div id="spa-messages" class="flex-1 overflow-y-auto px-3 py-3 bg-gray-50"></div>

            <div id="spa-typing" class="px-4 pb-1 text-[11px] text-gray-500 hidden">Typing…</div>

            <div class="p-3 border-t bg-white">
                <form id="spa-send-form" class="flex items-center gap-2">
                    <input id="spa-input" class="flex-1 rounded-full border-gray-200 bg-gray-50 px-4 py-2 text-[16px] focus:ring-purple-500 focus:border-purple-500"
                           placeholder="${closed ? 'Chat closed' : 'Type a message…'}" ${closed ? 'disabled' : ''} autocomplete="off" />
                    <button class="h-10 px-4 rounded-full bg-purple-600 text-white text-sm font-semibold disabled:opacity-50" ${closed ? 'disabled' : ''}>Send</button>
                </form>
            </div>
        </div>
    `;

    const input = document.getElementById('spa-input');
    if (input) {
      input.style.position = 'relative';
      input.style.zIndex = '2';
    }
    
    const sendBtn = document.querySelector('#spa-send-form button');
    if (sendBtn) {
      sendBtn.style.position = 'relative';
      sendBtn.style.zIndex = '1';
    }

    el('spa-back').onclick = () => {
        state.activeRoom = null;
        render();
    };

    scheduleRenderChatMessages(true);

    if (!closed) {
        const input = document.getElementById('spa-input');
        let typingTimer = null;

        const sendBtn = document.querySelector('#spa-send-form button[type="submit"]');

        // Prevent the button tap from stealing focus (mobile fix)
        if (sendBtn) {
          sendBtn.addEventListener('pointerdown', (e) => {
            e.preventDefault();               // key line
            input.focus({ preventScroll: true });
          });
        }

        // Make tapping the whole composer focus the input (easier on mobile)
        const composer = document.querySelector('#spa-send-form');
        if (composer) {
          composer.addEventListener('click', () => {
            input.focus({ preventScroll: true });
          });
        }


        input.addEventListener('input', () => {
            apiPost(`/chat/${state.activeRoom.uuid}/typing`, { typing: true }).catch(()=>{});
            clearTimeout(typingTimer);
            typingTimer = setTimeout(() => {
                apiPost(`/chat/${state.activeRoom.uuid}/typing`, { typing: false }).catch(()=>{});
            }, 900);
        });

        document.getElementById('spa-send-form').addEventListener('submit', async (e) => {
          e.preventDefault();
          const body = input.value.trim();
          if (!body) return;

          // Keep focus BEFORE and AFTER send (mobile-friendly)
          input.focus({ preventScroll: true });

          // Optimistic UI: show message instantly
          const clientId = (window.crypto?.randomUUID ? window.crypto.randomUUID() : (Date.now().toString(36) + Math.random().toString(36).slice(2)));
          const tempMsg = {
            id: `tmp-${clientId}`,
            client_message_id: clientId,
            chat_room_id: state.activeRoom.id,
            user_id: state.user?.id,
            body,
            created_at: new Date().toISOString(),
            pending: true,
            failed: false,
            heart_count: 0,
            my_hearted: false,
          };

          pushUniqueMessage(tempMsg);
          scheduleRenderChatMessages(true);

          // Clear input (but keep focus)
          input.value = '';

          try {
            const res = await apiPost(`/chat/${state.activeRoom.uuid}/send`, { body, client_message_id: clientId });
            if (res?.message) {
              // Reconcile optimistic message with server response
              pushUniqueMessage(res.message);
              scheduleRenderChatMessages(true);
            }
          } catch (err) {
            // Mark failed; user can re-send by tapping the bubble
            const i = state.messages.findIndex(m => (m.client_message_id ?? m.clientMessageId) === clientId);
            if (i >= 0) {
              state.messages[i].pending = false;
              state.messages[i].failed = true;
            }
            scheduleRenderChatMessages(true);
          } finally {
            requestAnimationFrame(() => {
              input.focus({ preventScroll: true });
            });
          }
        });

    }
}

function renderChatMessages(scrollToBottom) {
    const box = document.getElementById('spa-messages');
    if (!box) return;

    const me = state.user?.id;

    box.innerHTML = state.messages.map(m => {
        const mine = String(m.user_id) === String(me);
        const t = m.created_at ? new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '';
        const pending = !!m.pending;
        const failed = !!m.failed;
        const seen = mine && !pending && !failed && m.read_at;
        const status = failed ? ' · Failed' : (pending ? ' · Sending…' : (seen ? ' · Seen' : ''));
        const resendAttr = (failed && mine && (m.client_message_id || m.clientMessageId)) ? `data-resend="${m.client_message_id || m.clientMessageId}"` : '';
        const hearts = Number(m.heart_count || 0);
        const myHearted = !!m.my_hearted;
        const body = (m.body === null || typeof m.body === 'undefined')
            ? '<span class="italic opacity-70">Message deleted</span>'
            : htmlEscape(m.body);

        return `
            <div class="flex ${mine ? 'justify-end' : 'justify-start'} mb-2">
                <div ${resendAttr} class="${mine ? 'bg-purple-600 text-white' : 'bg-white text-gray-900'} max-w-[82%] rounded-2xl px-4 py-2 shadow-sm">
                    <div class="text-sm whitespace-pre-wrap break-words">${body}</div>
                    <div class="mt-1 flex items-center justify-end gap-2 text-[10px] ${mine ? 'text-purple-100' : 'text-gray-400'}">
                        <span>${htmlEscape(t)}${status}</span>
                        <button class="inline-flex items-center gap-1" data-heart="${m.id}" title="React" ${pending || failed ? 'disabled' : ''}>
                            <span>${myHearted ? '❤' : '♡'}</span>${hearts ? `<span>${hearts}</span>` : ``}
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    // Wire heart buttons
    box.querySelectorAll('button[data-heart]').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const id = btn.dataset.heart;
            try {
                const res = await apiPost(`/chat/${state.activeRoom.uuid}/message/${id}/react`, {});
                // Optimistic toggle: API returns heart_count & hearted
                const msg = state.messages.find(m => String(m.id) === String(id));
                if (msg && res) {
                    msg.my_hearted = !!res.hearted;
                    msg.heart_count = res.heart_count ?? msg.heart_count;
                }
                scheduleRenderChatMessages(false);
            } catch {}
        });
    });

    // Retry failed sends (tap the bubble)
    box.querySelectorAll('[data-resend]').forEach(bubble => {
        bubble.addEventListener('click', async (e) => {
            e.preventDefault();
            const cid = bubble.getAttribute('data-resend');
            const msg = state.messages.find(m => (m.client_message_id ?? m.clientMessageId) === cid);
            if (!msg || !msg.failed) return;

            // New client id for the retry
            const newCid = (window.crypto?.randomUUID ? window.crypto.randomUUID() : (Date.now().toString(36) + Math.random().toString(36).slice(2)));
            msg.client_message_id = newCid;
            msg.pending = true;
            msg.failed = false;
            scheduleRenderChatMessages(true);

            try {
                const res = await apiPost(`/chat/${state.activeRoom.uuid}/send`, { body: msg.body, client_message_id: newCid });
                if (res?.message) {
                    pushUniqueMessage(res.message);
                }
            } catch {
                msg.pending = false;
                msg.failed = true;
            }
            scheduleRenderChatMessages(true);
        });
    });
    if (scrollToBottom) {
        box.scrollTop = box.scrollHeight;
    }
}

function renderTyping() {
    const t = document.getElementById('spa-typing');
    if (!t) return;
    t.classList.toggle('hidden', !state.typing);
    el('spa-subtitle').textContent = state.typing ? 'Typing…' : '';
}

async function renderMatches() {
    setTopbar('Matches', 'People you matched with');
    setAvatar(initials(state.user?.name));

    setAction(
        `<button data-spa-action class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200" title="Edit profile">
            <span class="text-xs font-semibold text-gray-800">Edit profile</span>
        </button>`,
        () => { state.matchesSub = 'profile'; render(); }
    );

    const view = el('spa-view');

    /* =========================================================
       ✅ FIRST: if user clicked Start / Edit profile → show form
    ========================================================= */

    if (state.matchesSub === 'profile') {
        setTopbar('Match profile', 'Your preferences for matching');
        setAction(
            `<button data-spa-action class="text-xs font-semibold px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200">Back</button>`,
            () => { state.matchesSub = 'list'; render(); }
        );

        if (!cachedOnboardingProfileHtml) {
            view.innerHTML = `<div class="p-6 text-sm text-gray-500">Loading profile…</div>`;
            await preloadOnboardingProfileHtml();
        }

        view.innerHTML = `<div class="p-3">${cachedOnboardingProfileHtml}</div>`;
        wireAjaxForms(view);
        wireAjaxLinks(view);
        return;
    }

    if (state.matchesSub === 'quiz') {
        setTopbar('Serious Compatibility', 'Answer a few quick questions');
        setAction(
            `<button data-spa-action class="text-xs font-semibold px-3 py-1.5 rounded-full bg-gray-100 hover:bg-gray-200">Back</button>`,
            () => { state.matchesSub = 'profile'; render(); }
        );

        if (!cachedOnboardingQuizHtml) {
            view.innerHTML = `<div class="p-6 text-sm text-gray-500">Loading quiz…</div>`;
            await preloadOnboardingQuizHtml();
        }

        view.innerHTML = `<div class="p-3">${cachedOnboardingQuizHtml}</div>`;
        wireAjaxForms(view);
        wireAjaxLinks(view);
        return;
    }

    /* =========================================================
       ✅ THEN: show “Complete your profile” screen
    ========================================================= */

    if (!state.status.has_profile) {
        view.innerHTML = `
            <div class="p-6">
                <div class="text-lg font-semibold">Complete your profile</div>
                <div class="text-sm text-gray-500 mt-1">
                    We need your Match Profile before showing matches.
                </div>
                <button type="button" data-spa-open-match-profile
                    class="inline-block mt-4 px-5 py-2.5 rounded-full bg-purple-600 text-white font-semibold">
                    Start
                </button>
            </div>
        `;
        wireMatchActions(view);
        return;
    }

    if (!state.status.has_quiz) {
        view.innerHTML = `
            <div class="p-6">
                <div class="text-lg font-semibold">Serious Compatibility</div>
                <div class="text-sm text-gray-500 mt-1">
                    Answer the short quiz to unlock better matching.
                </div>
                <button type="button" data-spa-open-match-quiz
                    class="inline-block mt-4 px-5 py-2.5 rounded-full bg-purple-600 text-white font-semibold">
                    Continue
                </button>
            </div>
        `;
        wireMatchActions(view);
        return;
    }

    /* =========================================================
       ✅ Finally: render matches list (cachedMatchesHtml)
    ========================================================= */

    if (!cachedMatchesHtml) {
        view.innerHTML = `<div class="p-6 text-sm text-gray-500">Loading matches…</div>`;
        await preloadMatchesHtml();
    }

    view.innerHTML = `
        <div class="p-3">
            <div class="bg-white border rounded-xl overflow-hidden">
                ${cachedMatchesHtml}
            </div>
        </div>
    `;
    wireAjaxLinks(view);
    wireMatchActions(view);
}


async function renderProfile() {
    setTopbar('Profile', 'Account & settings');
    setAvatar(initials(state.user?.name));
    setAction(
        `<div></div>`,
        null
    );
    const view = el('spa-view');

    if (cachedProfileHtml) {
        view.innerHTML = `
            <div class="p-3">
                <div class="bg-white border rounded-xl overflow-hidden">
                    ${cachedProfileHtml}
                </div>
            </div>
        `;
        wireAjaxForms(view);
        wireAjaxLinks(view);
        return;
    }

    view.innerHTML = `<div class="p-6 text-sm text-gray-500">Loading…</div>`;
    await preloadProfileHtml();
    return renderProfile();
}

function wireAjaxLinks(root) {
    // Intercept internal links to keep SPA feel (basic).
    root.querySelectorAll('a[href^="/"]').forEach(a => {
        const href = a.getAttribute('href');
        if (!href) return;
        a.addEventListener('click', async (e) => {
            // allow normal for logout etc
            if (href === '/logout') return;
            e.preventDefault();

            // SPA routing shortcuts
            if (href.startsWith('/profile')) return setActiveTab('profile');
            if (href.startsWith('/match')) return setActiveTab('matches');
            if (href.startsWith('/chat/')) {
                // href like /chat/{uuid}
                const uuid = href.split('/chat/')[1]?.split('?')[0];
                const room = state.rooms.find(r => r.uuid === uuid);
                if (room) return openRoom(room.id, room.uuid);
                // fallback: refresh rooms then try again
                await refreshRooms();
                const room2 = state.rooms.find(r => r.uuid === uuid);
                if (room2) return openRoom(room2.id, room2.uuid);
            }

            // For onboarding pages keep normal navigation (full pages)
            window.location.href = href;
        });
    });
}

function wireMatchActions(root) {

   root.querySelectorAll('[data-spa-open-match-profile]').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();

      console.log('[SPA] Start onboarding profile clicked ✅');

      state.activeTab = 'matches';
      state.matchesSub = 'profile';

      // highlight bottom tab
      document.querySelectorAll('.spa-tab').forEach(b => {
        b.classList.toggle('is-active', b.dataset.tab === 'matches');
      });

      // always refetch latest onboarding partial
      cachedOnboardingProfileHtml = null;

      // show loading in the current view so user sees something
      const view = document.getElementById('spa-view');
      if (view) view.innerHTML = `<div class="p-6 text-sm text-gray-500">Loading profile…</div>`;

      try {
        await preloadOnboardingProfileHtml();

        console.log('[SPA] onboarding profile html loaded?', !!cachedOnboardingProfileHtml);

        render(); // will render matchesSub === 'profile'
      } catch (err) {
        console.error('[SPA] preloadOnboardingProfileHtml failed', err);
        alert('Failed to load onboarding form. Check console/network.');
      }
    });
  });

    // Start chat buttons (preferred)
    root.querySelectorAll('[data-spa-start-chat]').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            const uid = btn.getAttribute('data-spa-start-chat');
            if (!uid) return;
            try {
                const res = await apiPost(`/match/start/${uid}`, {});
                const room = res?.room;
                if (room?.uuid) {
                    await refreshRooms();
                    const found = state.rooms.find(r => r.uuid === room.uuid) || room;
                    // Switch to Chats tab and open
                    state.matchesSub = 'list';
                    setActiveTab('chats');
                    return openRoom(found.id, found.uuid);
                }
            } catch (err) {
                console.error(err);
                alert('Could not start chat.');
            }
        });
    });

    // Backward compatibility: intercept old Start Chat forms if any remain
    root.querySelectorAll('form[action*="/match/start/"]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const action = form.getAttribute('action');
            if (!action) return;
            try {
                const res = await window.axios.post(action, new FormData(form), {
                    headers: { 'Accept': 'application/json' },
                });
                const room = res.data?.room;
                if (room?.uuid) {
                    await refreshRooms();
                    const found = state.rooms.find(r => r.uuid === room.uuid) || room;
                    state.matchesSub = 'list';
                    setActiveTab('chats');
                    return openRoom(found.id, found.uuid);
                }
            } catch (err) {
                console.error(err);
                alert('Could not start chat.');
            }
        });
    });

    // Edit match-profile links inside matches partial
    root.querySelectorAll('[data-spa-open-match-profile]').forEach(a => {
        a.addEventListener('click', (e) => {
            e.preventDefault();
            state.matchesSub = 'profile';
            render();
        });
    });

    root.querySelectorAll('[data-spa-open-match-quiz]').forEach(a => {
        a.addEventListener('click', (e) => {
            e.preventDefault();
            state.matchesSub = 'quiz';
            render();
        });
    });

}


async function refreshStatusFromBootstrap() {
  try {
    const data = await apiGet('/api/bootstrap');
    if (data?.status) state.status = data.status;
    if (data?.rooms) state.rooms = data.rooms;
  } catch {}
}

function wireAjaxForms(root) {
  root.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', async (e) => {
      const action = form.getAttribute('action');
      if (!action) return;

      e.preventDefault();

      const methodInput = form.querySelector('input[name="_method"]');
      const method = (methodInput?.value || form.getAttribute('method') || 'POST').toUpperCase();
      const fd = new FormData(form);

      try {
        const res = await window.axios({
          url: action,
          method: method === 'GET' ? 'GET' : 'POST',
          data: fd,
          headers: { 'Accept': 'application/json' },
        });

        const next = res.data?.next || null;

        /* =================================================
           QUIZ COMPLETED → GO BACK TO MATCHES (FINAL STEP)
        ================================================= */
        if (next === '/match') {

            // switch SPA state
            state.activeTab = 'matches';
            state.matchesSub = 'list';
            state.activeRoom = null;

            // activate Matches tab visually
            document.querySelectorAll('.spa-tab').forEach(btn => {
                btn.classList.toggle('is-active', btn.dataset.tab === 'matches');
            });

            // reset caches so fresh matches load
            cachedMatchesHtml = null;
            cachedOnboardingProfileHtml = null;
            cachedOnboardingQuizHtml = null;

            // fetch + render latest matches
            await refreshMatchesNow();
            renderMatches();

            return;
        }

        // =========================
        // ONBOARDING PROFILE SAVED
        // =========================
        if (action === '/onboarding') {
          // IMPORTANT: unlock the app state
          state.status.has_profile = true;

          // Go to quiz inside Matches tab (no refresh)
          state.activeTab = 'matches';
          state.matchesSub = 'quiz';
          document.querySelectorAll('.spa-tab').forEach(b => {
            b.classList.toggle('is-active', b.dataset.tab === 'matches');
          });

          // Always load fresh quiz HTML
          cachedOnboardingQuizHtml = null;
          await preloadOnboardingQuizHtml();
          render();
          return;
        }

        // =========================
        // ONBOARDING QUIZ SAVED
        // =========================
        if (action.startsWith('/onboarding/quiz')) {
          // IMPORTANT: unlock the app state
          state.status.has_quiz = true;

          // Optional but best: sync real status from server
          await refreshStatusFromBootstrap();

          // Close forms and show latest matches
          state.activeTab = 'matches';
          state.matchesSub = 'list';
          document.querySelectorAll('.spa-tab').forEach(b => {
            b.classList.toggle('is-active', b.dataset.tab === 'matches');
          });

          // Force latest matches HTML
          cachedMatchesHtml = null;
          cachedOnboardingProfileHtml = null;
          cachedOnboardingQuizHtml = null;

          await refreshMatchesNow();
          return;
        }

        // =========================
        // Other (profile/account etc.)
        // =========================
        // keep your existing behavior here if needed
      } catch (err) {
        alert('Could not save. Please check your inputs.');
      }
    });
  });
}


(function setupBackToExit() {
  let lastBack = 0;

  // Push an extra history state so the first "back" triggers popstate
  history.pushState({ spa: true }, "", location.href);

  window.addEventListener("popstate", (e) => {
    const now = Date.now();
    const withinWindow = now - lastBack < 1500;

    if (!withinWindow) {
      lastBack = now;

      // Re-push so we stay in app
      history.pushState({ spa: true }, "", location.href);

      // Show warning (replace with your toast UI)
      showExitToast("Press back again to exit");
    } else {
      // Allow exit (go back for real)
      history.back();
    }
  });

  function showExitToast(msg) {
    // Minimal: alert(msg) (not recommended)
    // Better: render a small toast at bottom
    console.log(msg);

    let t = document.getElementById("exit-toast");
    if (!t) {
      t = document.createElement("div");
      t.id = "exit-toast";
      t.style.position = "fixed";
      t.style.left = "50%";
      t.style.bottom = "90px";
      t.style.transform = "translateX(-50%)";
      t.style.padding = "10px 14px";
      t.style.borderRadius = "12px";
      t.style.background = "rgba(0,0,0,.85)";
      t.style.color = "#fff";
      t.style.fontSize = "13px";
      t.style.zIndex = "99999";
      document.body.appendChild(t);
    }
    t.textContent = msg;
    t.style.opacity = "1";
    clearTimeout(window.__exitToastTimer);
    window.__exitToastTimer = setTimeout(() => (t.style.opacity = "0"), 1200);
  }
})();


// Mount
document.addEventListener('DOMContentLoaded', () => {
    if (!el('spa-root')) return;

    document.querySelectorAll('.spa-tab').forEach(btn => {
      btn.addEventListener('click', async () => {
        const tab = btn.dataset.tab;

        // Always refresh matches tab with mini loader
        if (tab === 'matches') {
          state.activeRoom = null;
          state.activeTab = 'matches';
          document.querySelectorAll('.spa-tab').forEach(b => {
            b.classList.toggle('is-active', b.dataset.tab === 'matches');
          });

          await refreshMatchesNow();
          return;
        }

        setActiveTab(tab);
      });
    });


    boot().catch((e) => {
        console.error(e);
        el('spa-view').innerHTML = `<div class="p-6 text-sm text-red-600">Failed to load app.</div>`;
    });
});


function setViewScrollMode(mode) {
  const view = el('spa-view');
  if (!view) return;
  view.classList.remove('overflow-y-auto', 'overflow-hidden');
  view.classList.add(mode === 'scroll' ? 'overflow-y-auto' : 'overflow-hidden');
}

async function refreshMatchesNow() {
  // show mini loader immediately
  renderMiniLoading('Refreshing matches…');

  // reset caches so we ALWAYS fetch latest
  cachedMatchesHtml = null;

  try {
    // pull latest matches partial HTML
    const res = await window.axios.get('/partials/matches', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    cachedMatchesHtml = res.data;
  } catch (e) {
    cachedMatchesHtml = `<div class="p-6 text-sm text-red-600">Failed to load matches.</div>`;
  }

  // re-render matches tab (list mode)
  state.matchesSub = 'list';
  renderMatches();
}


function renderMiniLoading(message = 'Loading…') {
  const view = el('spa-view');
  if (!view) return;
  view.innerHTML = `
    <div class="p-6">
      <div class="flex items-center gap-3 text-sm text-gray-600">
        <div class="h-4 w-4 rounded-full border-2 border-gray-300 border-t-transparent animate-spin"></div>
        <div>${htmlEscape(message)}</div>
      </div>
    </div>
  `;
}

