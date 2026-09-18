<x-filament-panels::page>
    @php
        $round = $this->round();
        $players = $this->players();
        $tones = [
            'success' => 'bg-green-500/10 text-green-700 dark:text-green-400 ring-green-500/20',
            'danger' => 'bg-red-500/10 text-red-700 dark:text-red-400 ring-red-500/20',
            'warning' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 ring-amber-500/20',
            'gray' => 'bg-gray-500/10 text-gray-600 dark:text-gray-400 ring-gray-500/20',
        ];
    @endphp

    <div class="space-y-4">

        {{-- ============================= PICKER ============================= --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
            <div class="flex flex-wrap items-center gap-4">
                <div class="min-w-[18rem]">
                    <label class="block text-xs text-gray-500 mb-1">Round</label>
                    <select wire:model.live="roundId" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        @foreach($this->roundOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <label class="flex items-center gap-2 cursor-pointer mt-5">
                    <input type="checkbox" wire:model.live="onlyProblems" class="rounded border-gray-300 dark:border-gray-600" />
                    <span class="text-sm">Only what needs a look</span>
                </label>

                @if($round)
                    <div class="mt-5 text-xs text-gray-500">
                        @foreach($round->maps as $roundMap)
                            <span class="mr-3">
                                <span class="font-bold uppercase">{{ $roundMap->physics }}</span>
                                {{ $roundMap->map?->name ?? '-' }}
                            </span>
                        @endforeach
                        @if($round->maps->isEmpty() && $round->candidates->isNotEmpty())
                            <span class="font-bold uppercase">Candidates</span>
                            {{ $round->candidates->map(fn ($c) => $c->map?->name)->filter()->implode(', ') }}
                        @endif
                    </div>
                @endif
            </div>

            <p class="text-xs text-gray-500 mt-3">
                Everything on the round's maps that arrived after its ballot opened, entered or not. A demo
                with no entry is here for a reason - it could not be read, it is older than the round, or it
                is a run in the other physics - and the reason is the sentence the player was shown.
            </p>
        </div>

        {{-- ============================ PLAYERS ============================= --}}
        @forelse($players as $player)
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-950/5 dark:border-white/10">
                    <div class="text-sm font-extrabold">{!! $this->nick($player['user']) !!}</div>
                    <div class="text-xs text-gray-500">
                        {{ count($player['rows']) }} {{ count($player['rows']) === 1 ? 'demo' : 'demos' }}
                        @if($player['problems'] > 0)
                            <span class="ml-2 text-amber-600 dark:text-amber-400">{{ $player['problems'] }} to look at</span>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach($player['rows'] as $row)
                                <tr class="border-b border-gray-950/5 dark:border-white/5 last:border-0 align-top">
                                    <td class="px-4 py-2 w-24">
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-bold ring-1 ring-inset {{ $tones[$row['tone']] }}">
                                            {{ $row['verdict'] }}
                                        </span>
                                    </td>

                                    <td class="px-2 py-2 text-xs font-bold uppercase text-gray-500 w-16">{{ $row['physics'] ?: '-' }}</td>

                                    <td class="px-2 py-2 font-mono text-xs w-24">{{ $this->formatTime($row['time']) }}</td>

                                    <td class="px-2 py-2">
                                        <div class="text-xs text-gray-700 dark:text-gray-300 break-all">{{ $row['filename'] }}</div>
                                        @if($row['reason'])
                                            <div
                                                class="text-xs text-gray-500 mt-0.5 @if($row['notes']) cursor-help @endif"
                                                @if($row['notes']) title="The parser noted: {{ $row['notes'] }}" @endif
                                            >{{ $row['reason'] }}</div>
                                        @endif
                                    </td>

                                    <td class="px-2 py-2 w-56 text-right whitespace-nowrap">
                                        @if($row['auto'])
                                            <span class="text-[10px] uppercase tracking-wider text-gray-500 mr-2" title="Entered by the site, not by the player">auto</span>
                                        @endif

                                        <span class="text-[10px] uppercase tracking-wider text-gray-500 mr-2">{{ $row['online'] ? 'online' : 'offline' }}</span>

                                        @unless($row['paired'])
                                            <span class="text-[10px] uppercase tracking-wider text-amber-600 dark:text-amber-400 mr-2" title="No record on the site lines up with this demo">unpaired</span>
                                        @endunless

                                        @if($row['reports'] > 0)
                                            <span class="text-[10px] uppercase tracking-wider text-red-600 dark:text-red-400 mr-2">{{ $row['reports'] }} reported</span>
                                        @endif

                                        @if($row['demo_id'])
                                            <a href="{{ route('demos.download', $row['demo_id']) }}" target="_blank" class="text-xs text-primary-600 hover:underline">demo</a>
                                        @endif

                                        {{-- The only thing on this page that changes anything, and the
                                             only screen a withdrawn run shows up on: withdrawing deletes
                                             the entry, so the submissions table has no row to act on. --}}
                                        @if($row['restorable'])
                                            <button
                                                type="button"
                                                wire:click="restore({{ $row['demo_id'] }})"
                                                wire:confirm="Put this run back into the round? Do this when the player has asked for it."
                                                class="ml-2 text-xs font-semibold text-primary-600 hover:underline"
                                            >put back</button>
                                        @endif
                                    </td>

                                    <td class="px-4 py-2 w-32 text-right text-xs text-gray-500 whitespace-nowrap">
                                        {{ $row['at']?->format('d.m. H:i') ?? '' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-8 text-center text-sm text-gray-500">
                @if($round)
                    Nothing on this round's maps yet.
                @else
                    No rounds yet.
                @endif
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
