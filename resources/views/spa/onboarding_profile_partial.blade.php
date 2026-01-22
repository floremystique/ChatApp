<div class="bg-white border rounded-2xl overflow-hidden">
    <div class="p-4 border-b">
        <div class="text-base font-semibold">Match profile</div>
        <div class="text-xs text-gray-500 mt-0.5">These help us show better matches.</div>
    </div>

    <form method="POST" action="/onboarding" class="p-4 space-y-4">
        @csrf

        <div class="bg-gray-50 rounded-xl p-4">
            <label class="flex items-center gap-3">
                <input type="checkbox" name="looks_matter" value="1" class="rounded" @checked(old('looks_matter', $profile->looks_matter ?? false))>
                <div>
                    <div class="font-semibold text-sm">Looks matter</div>
                    <div class="text-xs text-gray-500">This affects how we rank matches for you.</div>
                </div>
            </label>
        </div>

        <div class="bg-gray-50 rounded-xl p-4">
            <label class="block font-semibold text-sm mb-2">Conversation mode</label>
            <select name="mode" class="w-full rounded-xl border-gray-300">
                @php $currentMode = old('mode', $profile->mode ?? 'casual'); @endphp
                <option value="casual" @selected($currentMode === 'casual')>Casual</option>
                <option value="friendly" @selected($currentMode === 'friendly')>Friendly</option>
                <option value="deep" @selected($currentMode === 'deep')>Deep</option>
                <option value="study" @selected($currentMode === 'study')>Study</option>
                <option value="inspiring" @selected($currentMode === 'inspiring')>Inspiring</option>
                <option value="matchmaking" @selected($currentMode === 'matchmaking')>Matchmaking</option>
                <option value="dark" @selected($currentMode === 'dark')>Dark</option>
            </select>
            <div class="text-xs text-gray-500 mt-2">If you pick matchmaking, you can optionally add DOB for horoscope matching.</div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="bg-gray-50 rounded-xl p-4">
                <label class="block font-semibold text-sm mb-2">Date of birth (optional)</label>
                <input type="date" name="dob" class="w-full rounded-xl border-gray-300"
                       value="{{ old('dob', optional($profile->dob)->format('Y-m-d')) }}">
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <label class="block font-semibold text-sm mb-2">Short bio (optional)</label>
                <input type="text" name="bio" maxlength="120" class="w-full rounded-xl border-gray-300"
                       value="{{ old('bio', $profile->bio ?? '') }}"
                       placeholder="A line about you…">
            </div>
        </div>

        <div class="bg-gray-50 rounded-xl p-4">
            <div class="font-semibold text-sm mb-2">Tags</div>
            <div class="text-xs text-gray-500 mb-3">Pick a few. Shared tags improve match quality.</div>

            <div class="flex flex-wrap gap-2">
                @foreach($tags as $tag)
                    @php
                        $checked = collect(old('tags', $profile->tags->pluck('id')->all() ?? []))->contains($tag->id);
                    @endphp
                    <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border bg-white text-sm">
                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="rounded" @checked($checked)>
                        <span class="text-sm">{{ $tag->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-between gap-3">
            <button type="button" data-spa-open-match-quiz class="px-4 py-2 rounded-full bg-gray-100 text-gray-800 text-sm font-semibold hover:bg-gray-200">
                Skip to quiz
            </button>
            <button type="submit" class="px-5 py-2.5 rounded-full bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700">
                Save & Continue
            </button>
        </div>
    </form>
</div>
