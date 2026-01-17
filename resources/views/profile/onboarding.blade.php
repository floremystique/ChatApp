<x-app-layout>
    <div class="max-w-2xl mx-auto py-10">
        <h1 class="text-2xl font-bold mb-6">Profile setup</h1>

        <form method="POST" action="/onboarding" class="space-y-6">
            @csrf

            {{-- Looks matter --}}
            <div class="bg-white p-5 rounded shadow">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="looks_matter" value="1" class="rounded" @checked(old('looks_matter', $profile->looks_matter ?? false))>
                    <span>Looks matter</span>
                </label>
            </div>

            {{-- Conversation mode --}}
            <div class="bg-white p-5 rounded shadow">
                <label class="block font-semibold mb-2">Conversation mode</label>
                <select name="mode" class="w-full rounded border-gray-300">
                    @php $currentMode = old('mode', $profile->mode ?? 'casual'); @endphp
                    <option value="casual" @selected($currentMode === 'casual')>Casual</option>
                    <option value="friendly" @selected($currentMode === 'friendly')>Friendly</option>
                    <option value="deep" @selected($currentMode === 'deep')>Deep</option>
                    <option value="study" @selected($currentMode === 'study')>Study</option>
                    <option value="inspiring" @selected($currentMode === 'inspiring')>Inspiring</option>
                    <option value="matchmaking" @selected($currentMode === 'matchmaking')>Matchmaking</option>
                    <option value="dark" @selected($currentMode === 'dark')>Dark</option>
                </select>
                <p class="text-sm text-gray-500 mt-2">
                    If you pick matchmaking, you can optionally add DOB for horoscope matching.
                </p>
            </div>

            <input type="date" name="dob" class="w-full rounded border-gray-300"
                value="{{ old('dob', optional($profile->dob)->format('Y-m-d')) }}">

            <input type="text" name="bio" maxlength="120" class="w-full rounded border-gray-300"
                value="{{ old('bio', $profile->bio) }}"
                placeholder="One line about you…">


            {{-- Tags --}}
            <div class="bg-white p-5 rounded shadow">
                <label class="block font-semibold mb-3">Tags</label>
                <div class="grid grid-cols-2 gap-2">
                    @php
                        $selectedTags = collect(old('tags', $profile->tags?->pluck('id')->all() ?? []))->map(fn($v)=>(int)$v);
                    @endphp

                    @foreach($tags as $tag)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="rounded"
                                @checked($selectedTags->contains((int)$tag->id))>
                            <span>{{ $tag->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <button class="px-4 py-2 bg-black text-white rounded">
                Save & Find Matches
            </button>
        </form>
    </div>
</x-app-layout>
