<style>
  .chat-preview{
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2; /* 👈 two lines */
    overflow: hidden;
    word-break: break-word;
    height: 40px;
  }
</style>

<x-app-layout>
    <div class="max-w-3xl mx-auto py-8">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold">My chats</h1>
            <a class="underline" href="{{ route('match') }}">Find matches</a>
        </div>

        <div class="bg-white border rounded">
            @forelse($rooms as $room)
                <a href="{{ route('chat.show', $room->uuid ) }}" class="block px-4 py-4 border-b hover:bg-gray-50">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <div id="chat-name-{{ $room->uuid  }}" class="font-semibold truncate">
                                    {{ $room->other_user?->name ?? 'Unknown' }}
                                </div>

                                <span id="chat-badge-{{ $room->uuid  }}"
                                      class="inline-flex items-center px-2 py-0.5 text-xs rounded bg-black text-white {{ $room->unread_count > 0 ? '' : 'hidden' }}">
                                    {{ $room->unread_count }} new
                                </span>
                            </div>

                            <div id="chat-preview-{{ $room->uuid  }}" class="text-sm text-gray-600 mt-1 chat-preview">
                                {{ $room->lastMessage?->body ?? 'No messages yet. Say hi 👋' }}
                            </div>
                        </div>

                        <div id="chat-time-{{ $room->uuid  }}" class="shrink-0 text-xs text-gray-500 whitespace-nowrap">
                            {{ optional($room->lastMessage?->created_at)->diffForHumans() }}
                        </div>
                    </div>
                </a>
            @empty
                <div class="p-6 text-gray-600">
                    No chats yet. Start one from <a class="underline" href="{{ route('match') }}">Matches</a>.
                </div>
            @endforelse
        </div>

    </div>
</x-app-layout>

<script>
const pollUrl = "{{ route('chats.poll') }}";

function timeAgo(iso) {
    if (!iso) return '';
    const s = Math.floor((Date.now() - new Date(iso)) / 1000);
    if (s < 10) return 'just now';
    if (s < 60) return `${s}s ago`;
    const m = Math.floor(s / 60);
    if (m < 60) return `${m} min ago`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h ago`;
    return `${Math.floor(h / 24)}d ago`;
}

async function pollChats() {
    try {
        const res = await fetch(pollUrl, { cache: 'no-store' });
        const chats = await res.json();

        chats.forEach(c => {
            const name = document.getElementById(`chat-name-${c.room_uuid}`);
            const preview = document.getElementById(`chat-preview-${c.room_uuid}`);
            const time = document.getElementById(`chat-time-${c.room_uuid}`);
            const badge = document.getElementById(`chat-badge-${c.room_uuid}`);

            if (!name) return;

            preview.textContent = c.typing ? 'Typing…' : (c.last_body || '');
            time.textContent = timeAgo(c.last_at);

            if (c.unread > 0) {
                badge.textContent = `${c.unread} new`;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        });
    } catch (e) {}
}

pollChats();
setInterval(pollChats, 2000);
</script>

