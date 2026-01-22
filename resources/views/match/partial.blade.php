<div class="p-3">
    <div class="space-y-3">
        @foreach($users as $p)
            @php
                $band = $p->band ?? null;
                $insights = $p->insights ?? [];
                $chips = $p->bond_chips ?? [];
                $tagNames = $p->common_tags ?? collect();
                $avatar = strtoupper(substr(trim($p->user->name ?? '?'), 0, 1));
            @endphp

            <div class="bg-white rounded-2xl border overflow-hidden">
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-purple-600 text-white flex items-center justify-center font-semibold">
                                {{ $avatar }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <div class="font-semibold text-base truncate">{{ $p->user->name }}</div>
                                    <span class="text-[11px] px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                        {{ ucfirst($p->mode) }}
                                    </span>
                                    <span class="text-[11px] px-2 py-1 rounded-full bg-black text-white">
                                        {{ (int)$p->score }}/100
                                    </span>
                                </div>

                                <div class="mt-0.5 text-xs">
                                    <span class="font-medium">{{ $band['label'] ?? 'Compatibility' }}</span>
                                    <span class="text-gray-500">· {{ $band['description'] ?? 'Compatibility summary.' }}</span>
                                </div>
                            </div>
                        </div>

                        <button type="button"
                                class="shrink-0 px-4 py-2 rounded-full bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700"
                                data-spa-start-chat="{{ $p->user_id }}">
                            Chat
                        </button>
                    </div>

                    {{-- Chips --}}
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach(['Stability','Trust','Replies','Conflict'] as $k)
                            @if(!empty($chips[$k]))
                                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                    {{ $chips[$k] }}
                                </span>
                            @endif
                        @endforeach
                    </div>

                    {{-- Insights --}}
                    @if(!empty($insights))
                        <div class="mt-2 text-[12px] text-gray-600">
                            {{ collect($insights)->implode(' · ') }}
                        </div>
                    @endif

                    {{-- Shared tags --}}
                    <div class="mt-3">
                        @if($tagNames->isEmpty())
                            <div class="text-sm text-gray-600"><span class="font-medium">No shared tags yet</span></div>
                        @else
                            <div class="flex gap-2 overflow-x-auto whitespace-nowrap py-1" style="scrollbar-width:none;">
                                @foreach($tagNames as $t)
                                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">{{ $t }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if($p->bio)
                        <div class="mt-3 text-sm text-gray-800">
                            {{ $p->bio }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
