<x-app-layout>
    <div class="max-w-3xl mx-auto py-10">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Your matches</h1>
            <a href="{{ route('onboarding') }}" class="text-sm underline">Edit profile</a>
        </div>

        @if($users->count() === 0)
            <div class="bg-white p-6 rounded shadow">
                <p>No matches yet. Complete your profile and stay active to improve your chances.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($users as $p)
                    @php
                        $band = $p->band ?? null;
                        $insights = $p->insights ?? [];

                        $chips = $p->bond_chips ?? [];
                        $tagNames = $p->common_tags ?? collect();
                        $tagId = 'tags_' . $p->user_id;
                    @endphp

                    <div class="bg-white p-5 rounded shadow">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <div class="font-semibold text-lg truncate">
                                        {{ $p->user->name }}
                                    </div>

                                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                                        {{ ucfirst($p->mode) }} conversations
                                    </span>

                                    <span class="text-xs px-2 py-1 rounded-full bg-black text-white">
                                        {{ (int)$p->score }}/100
                                    </span>
                                </div>

                                <div class="mt-1 text-sm">
                                    <span class="font-medium">{{ $band['label'] ?? 'Compatibility' }}</span>
                                    <span class="text-gray-500">· {{ $band['description'] ?? 'Compatibility summary.' }}</span>
                                </div>

                                {{-- Bond chips (1 word, connection-focused) --}}
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if(!empty($chips['Stability']))
                                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                            {{ $chips['Stability'] }}
                                        </span>
                                    @endif

                                    @if(!empty($chips['Trust']))
                                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                           {{ $chips['Trust'] }}
                                        </span>
                                    @endif

                                    @if(!empty($chips['Replies']))
                                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                            {{ $chips['Replies'] }}
                                        </span>
                                    @endif

                                    @if(!empty($chips['Conflict']))
                                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                            {{ $chips['Conflict'] }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Insights --}}
                                @if(!empty($insights))
                                    <div class="mt-2 text-xs text-gray-600">
                                        {{ collect($insights)->implode(' · ') }}
                                    </div>
                                @endif

                                {{-- Old-style small notes (still useful) --}}
                                <div class="mt-2 text-xs text-gray-500">
                                    {{ collect([
                                        !empty($p->score_breakdown['mode_match']) ? 'Same conversation mode' : null,
                                        !empty($p->score_breakdown['looks_aligned']) ? 'Looks preference aligned' : null,
                                    ])->filter()->implode(' · ') }}
                                </div>

                                {{-- Shared tags: one line, horizontal scroll + arrows --}}
                                <div class="mt-3">
                                    @if($tagNames->isEmpty())
                                        <div class="text-sm text-gray-600">
                                            <span class="font-medium">No shared tags yet</span>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-2">
                                            <button type="button"
                                                    class="px-2 py-1 rounded bg-gray-100 text-gray-600 hover:bg-gray-200"
                                                    onclick="scrollTags('{{ $tagId }}', -220)"
                                                    aria-label="Scroll tags left">
                                                ‹
                                            </button>

                                            <div id="{{ $tagId }}"
                                                 class="flex gap-2 overflow-x-auto whitespace-nowrap scroll-smooth py-1"
                                                 style="scrollbar-width: none;">
                                                @foreach($tagNames as $t)
                                                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                                        {{ $t }}
                                                    </span>
                                                @endforeach
                                            </div>

                                            <button type="button"
                                                    class="px-2 py-1 rounded bg-gray-100 text-gray-600 hover:bg-gray-200"
                                                    onclick="scrollTags('{{ $tagId }}', 220)"
                                                    aria-label="Scroll tags right">
                                                ›
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                @if($p->bio)
                                    <div class="text-sm mt-3 text-gray-800">
                                        {{ $p->bio }}
                                    </div>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('match.start', $p->user_id) }}" class="shrink-0">
                                @csrf
                                <button class="px-4 py-2 bg-black text-white rounded">
                                    Start Chat
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <script>
                function scrollTags(id, dx) {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.scrollBy({ left: dx, behavior: 'smooth' });
                }
            </script>
        @endif
    </div>
</x-app-layout>

<style type="text/css">
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
