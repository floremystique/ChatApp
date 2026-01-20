<x-app-layout>
    <div class="max-w-3xl mx-auto py-10">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Your matches</h1>
            <a href="{{ route('onboarding') }}" class="text-sm underline">Edit profile</a>
        </div>

        @if($users->count() === 0)
            <div class="bg-white p-6 rounded shadow">
                <p>No matches yet. Create another test user account and complete onboarding + quiz.</p>
            </div>
        @else
            <div class="space-y-4">
                @php
                    $bands = \Illuminate\Support\Facades\DB::table('match_score_bands')
                        ->where('is_active', 1)
                        ->orderByDesc('min_score')
                        ->get();
                @endphp

                @foreach($users as $p)
                    @php
                        $band = $bands->first(fn($b) => $p->score >= $b->min_score) ?? null;

                        $stability = $p->score_breakdown['stability'] ?? null;
                        $trust = $p->score_breakdown['trust'] ?? null;
                        $resp = $p->score_breakdown['responsiveness'] ?? null;
                        $conflictRisk = $p->score_breakdown['conflict_risk'] ?? null;

                        $conflictSafety = is_null($conflictRisk) ? null : (100 - (int)$conflictRisk);
                        $insights = $p->insights ?? [];
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
                                    <span class="font-medium">{{ $band->label ?? 'Compatibility' }}</span>
                                    <span class="text-gray-500">· {{ $band->description ?? 'Trust-first matching for serious relationships.' }}</span>
                                </div>

                                {{-- Trust-first chips --}}
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if(!is_null($stability))
                                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                            Stability {{ (int)$stability }}
                                        </span>
                                    @endif

                                    @if(!is_null($trust))
                                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                            Trust {{ (int)$trust }}
                                        </span>
                                    @endif

                                    @if(!is_null($resp))
                                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                            Replies {{ $resp >= 75 ? 'Fast' : ($resp >= 50 ? 'Average' : 'Slow') }}
                                        </span>
                                    @endif

                                    @if(!is_null($conflictSafety))
                                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                            Conflict Safety {{ (int)$conflictSafety }}
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
                                    @php
                                        $tagNames = $p->common_tags ?? collect();
                                        $tagId = 'tags_' . $p->user_id;
                                    @endphp

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

                                            <div id="{{ $tagId }}" class="flex gap-2 overflow-x-auto whitespace-nowrap scroll-smooth py-1"
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
