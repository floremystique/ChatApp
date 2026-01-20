<x-app-layout>
    <div class="max-w-2xl mx-auto py-10">
        <h1 class="text-2xl font-bold mb-2">Serious Compatibility</h1>
        <p class="text-sm text-gray-600 mb-6">
            Quick questions that help us find more stable, marriage-minded matches.
        </p>

        <form method="POST" action="{{ route('onboarding.quiz.store') }}" class="space-y-6">
            @csrf

            @foreach($questions as $q)
                <div class="bg-white p-5 rounded shadow">
                    <div class="font-semibold">{{ $q->title }}</div>
                    @if($q->subtitle)
                        <div class="text-sm text-gray-500 mt-1">{{ $q->subtitle }}</div>
                    @endif

                    <div class="mt-3 space-y-2">
                        @foreach($q->options as $opt)
                            @php
                                $picked = optional($existing->get($q->id))->match_question_option_id;
                            @endphp
                            <label class="flex items-center gap-2">
                                <input type="radio"
                                       name="answers[{{ $q->id }}]"
                                       value="{{ $opt->id }}"
                                       class="rounded"
                                       @checked((int)$picked === (int)$opt->id)>
                                <span>{{ $opt->label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <button class="px-4 py-2 bg-black text-white rounded">
                Save & Continue
            </button>
        </form>
    </div>
</x-app-layout>
