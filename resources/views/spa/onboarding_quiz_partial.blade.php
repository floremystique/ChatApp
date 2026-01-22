<div class="bg-white border rounded-2xl overflow-hidden">
    <div class="p-4 border-b">
        <div class="text-base font-semibold">Serious Compatibility</div>
        <div class="text-xs text-gray-500 mt-0.5">Quick questions to improve match quality.</div>
    </div>

    <form method="POST" action="{{ route('onboarding.quiz.store') }}" class="p-4 space-y-4">
        @csrf

        @foreach($questions as $q)
            <div class="bg-gray-50 rounded-xl p-4">
                <div class="font-semibold text-sm">{{ $q->title }}</div>
                @if($q->subtitle)
                    <div class="text-xs text-gray-500 mt-1">{{ $q->subtitle }}</div>
                @endif

                <div class="mt-3 space-y-2">
                    @foreach($q->options as $opt)
                        @php $picked = optional($existing->get($q->id))->match_question_option_id; @endphp
                        <label class="flex items-center gap-3 bg-white border rounded-xl px-3 py-2">
                            <input type="radio" name="answers[{{ $q->id }}]" value="{{ $opt->id }}" class="rounded"
                                   @checked((int)$picked === (int)$opt->id)>
                            <div class="text-sm">{{ $opt->label }}</div>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2.5 rounded-full bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700">
                Save
            </button>
        </div>
    </form>
</div>
