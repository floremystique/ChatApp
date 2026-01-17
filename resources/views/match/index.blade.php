<x-app-layout>
    <div class="max-w-3xl mx-auto py-10">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Your matches</h1>
            <a href="{{ route('onboarding') }}" class="text-sm underline">Edit profile</a>
        </div>

        @if($users->count() === 0)
            <div class="bg-white p-6 rounded shadow">
                <p>No matches yet. Create another test user account and complete onboarding.</p>
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
                    <div class="bg-white p-5 rounded shadow flex items-center justify-between">
                        <div>
                            <div class="font-semibold text-lg">
                                {{ $p->user->name }}
                                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-600">
                                    {{ ucfirst($p->mode) }} conversations
                                </span>
                            </div>

                            @php
                                $band = $bands->first(fn($b) => $p->score >= $b->min_score) ?? null;
                            @endphp

                            <div class="mt-1 text-sm">
                                <span class="font-medium">{{ $band->label ?? 'Match' }}</span>
                                <span class="text-gray-500">· {{ $band->description ?? '' }}</span>
                            </div>

                            

                            <div class="mt-2 text-xs text-gray-500">
                                {{ collect([
                                    $p->score_breakdown['mode_match'] ? 'Same conversation mode' : null,
                                    $p->score_breakdown['looks_aligned'] ? 'Looks preference aligned' : null,
                                ])->filter()->implode(' · ') }}
                            </div>


                            <div class="text-sm text-gray-600 mt-1">
                                <span class="font-medium">
                                    {{ $p->common_tags->isEmpty() ? 'No shared tags yet' : $p->common_tags->join(', ') }}
                                </span>
                            </div>


                            @if($p->bio)
                                <div class="text-sm mt-1">{{ $p->bio }}</div>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('match.start', $p->user_id) }}">
                            @csrf
                            <button class="px-4 py-2 bg-black text-white rounded">
                                Start Chat
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
