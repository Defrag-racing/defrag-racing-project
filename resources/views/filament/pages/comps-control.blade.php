<x-filament-panels::page>
    @php
        $rounds = $this->currentRounds();
        $playing = $rounds['playing'];
        $voting = $rounds['voting'];
        $pools = $this->poolSizes();
        $tz = $this->timezone;
    @endphp

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {{-- ============================ SETTINGS ============================ --}}
        <div class="space-y-4">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-primary-600 mb-3">Schedule</h3>

                <label class="flex items-center gap-2 mb-4 cursor-pointer">
                    <input type="checkbox" wire:model="enabled" class="rounded border-gray-300 dark:border-gray-600" />
                    <span class="text-sm font-semibold">Weekly comps running</span>
                </label>
                <p class="text-xs text-gray-500 -mt-3 mb-4">
                    Off, nothing is created and nothing rolls over. The first week appears within a minute of switching this on.
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Rounds start on</label>
                        <div class="flex gap-2">
                            <select wire:model="startDow" class="flex-1 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                @foreach($this->weekdays() as $n => $name)
                                    <option value="{{ $n }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            <input type="time" wire:model="startTime" class="w-28 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Timezone</label>
                        <input type="text" wire:model="timezone" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                        <p class="text-xs text-gray-500 mt-1">A real zone name, not UTC, so the hour stays put across daylight saving.</p>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Voting closes this many hours early</label>
                        <input type="number" wire:model="votingLeadHours" min="0" max="168" class="w-24 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                        <p class="text-xs text-gray-500 mt-1">The gap is what lets a preview render finish and lets people fetch the map before it counts.</p>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Maps on the ballot</label>
                        <input type="number" wire:model="poolSize" min="2" max="20" class="w-24 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                    </div>

                    <div class="pt-3 border-t border-gray-200 dark:border-white/10">
                        <label class="block text-xs text-gray-500 mb-1">Prize per weekly, per physics (EUR)</label>
                        <input type="number" wire:model="prizeEur" min="0" max="10000" class="w-24 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                        <p class="text-xs text-gray-500 mt-1">Only the fallback for a week nobody has funded. What a funded week pays comes from the donations that cover it - see Donations.</p>
                    </div>

                </div>

                <button wire:click="saveSettings"
                        class="mt-4 w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white hover:bg-primary-500">
                    Save
                </button>
                <p class="text-xs text-gray-500 mt-2">Times apply to the next round created, not to one already on the calendar.</p>
            </div>

            {{-- Rules, stated rather than editable: they are in the code and
                 this is here so nobody has to go read it. --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-primary-600 mb-3">Rules</h3>
                <dl class="space-y-2 text-xs">
                    <div>
                        <dt class="text-gray-500">Counted weapons</dt>
                        <dd class="font-mono">rl, pg, gl, lg, bfg</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Ignored</dt>
                        <dd class="font-mono">gauntlet, mg, hook, rg, sg</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Category rotation</dt>
                        <dd class="font-mono">strafe, weapon, strafe, combo, strafe</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Wildcard</dt>
                        <dd>One per season win, one per five weekly wins</dd>
                    </div>
                </dl>
            </div>

            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-primary-600 mb-3">Pool sizes</h3>
                <dl class="space-y-1.5 text-sm">
                    @foreach($pools as $category => $count)
                        <div class="flex justify-between">
                            <dt class="capitalize text-gray-500">{{ $category }}</dt>
                            <dd class="font-bold tabular-nums {{ $count < 10 ? 'text-danger-600' : '' }}">{{ number_format($count) }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="text-xs text-gray-500 mt-2">Maps with a record, in that category, never played in a comps.</p>
            </div>

            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-primary-600 mb-3">Coming up</h3>
                <ol class="space-y-1 text-sm">
                    @foreach($this->upcomingCategories() as $week)
                        <li class="flex justify-between">
                            <span class="text-gray-500">Weekly #{{ $week['number'] }}</span>
                            <span class="font-semibold capitalize">{{ $week['category'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>

        {{-- ============================== STATE ============================= --}}
        <div class="xl:col-span-2 space-y-4">
            <div class="flex justify-end">
                <button wire:click="runTick"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-800">
                    Run scheduler now
                </button>
            </div>

            {{-- Playing --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-success-600 mb-3">Playing now</h3>

                @if($playing)
                    <div class="mb-2 flex flex-wrap items-baseline gap-3">
                        <span class="font-bold">{{ $playing->comp->title }}</span>
                        <span class="text-xs text-gray-500 capitalize">
                            {{ $playing->category }}@if($playing->weapon) ({{ $playing->weapon }}) @endif
                        </span>
                        <span class="text-xs text-gray-500">
                            ends {{ $playing->ends_at->timezone($tz)->format('D d.m. H:i') }}
                        </span>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach(['cpm','vq3'] as $physics)
                            @php $m = $playing->maps->firstWhere('physics', $physics); @endphp
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-gray-500">{{ $physics }}</div>
                                <div class="font-semibold">{{ $m?->map?->name ?? '-' }}</div>
                                <div class="text-xs text-gray-500">{{ $m?->decided_by }}</div>
                            </div>
                        @endforeach
                    </div>

                    @include('filament.pages.partials.comps-round-prize', ['round' => $playing])
                @else
                    <p class="text-sm text-gray-500">Nothing is being played.</p>
                @endif
            </div>

            {{-- Ballot --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-primary-600 mb-3">Ballot</h3>

                @if($voting)
                    <div class="mb-3 flex flex-wrap items-baseline gap-3">
                        <span class="font-bold">{{ $voting->comp->title }}</span>
                        <span class="text-xs text-gray-500 capitalize">
                            {{ $voting->category }}@if($voting->weapon) ({{ $voting->weapon }}) @endif
                        </span>
                        <span class="text-xs text-gray-500">
                            closes {{ $voting->voting_closes_at->timezone($tz)->format('D d.m. H:i') }}
                        </span>
                        <span class="text-xs font-semibold {{ $voting->status === 'voting' ? 'text-success-600' : 'text-gray-500' }}">
                            {{ $voting->status }}
                        </span>
                    </div>

                    <table class="w-full text-sm">
                        <thead class="text-[10px] uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="py-1 text-left">Map</th>
                                <th class="py-1 text-right w-16">CPM</th>
                                <th class="py-1 text-right w-16">VQ3</th>
                                <th class="py-1 text-left w-28">Blocked</th>
                                <th class="py-1 text-right w-20"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($voting->candidates as $candidate)
                                <tr>
                                    <td class="py-1.5 font-medium">{{ $candidate->map?->name }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ $candidate->votes_cpm }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ $candidate->votes_vq3 }}</td>
                                    <td class="py-1.5 text-xs text-gray-500 uppercase">{{ $candidate->blocked_physics ?? '-' }}</td>
                                    <td class="py-1.5 text-right whitespace-nowrap">
                                        @if($voting->status === 'voting')
                                            <button wire:click="redrawCandidate({{ $candidate->id }})"
                                                    wire:confirm="Swap this map for another from the same pool? Votes cast for it are removed with it."
                                                    class="text-xs text-primary-600 hover:underline">
                                                redraw
                                            </button>
                                            <button wire:click="removeCandidate({{ $candidate->id }})"
                                                    wire:confirm="Take this map off the ballot without replacing it? Votes cast for it go with it."
                                                    class="ml-2 text-xs text-danger-600 hover:underline">
                                                remove
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($voting->status !== 'voting')
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach(['cpm','vq3'] as $physics)
                                @php $m = $voting->maps->firstWhere('physics', $physics); @endphp
                                <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                                    <div class="text-[10px] font-extrabold uppercase tracking-wider text-gray-500">{{ $physics }} winner</div>
                                    <div class="font-semibold">{{ $m?->map?->name ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ $m?->decided_by }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($voting->status === 'voting')
                        {{-- Manual overrides. Reroll obeys the draw's rules,
                             so it cannot smuggle in a map the rules refuse;
                             adding by name deliberately does not, because an
                             admin typing a map has a reason the draw cannot
                             know about. --}}
                        <div class="mt-3 flex flex-wrap items-end gap-2 border-t border-gray-200 dark:border-white/10 pt-3">
                            <div class="flex-1 min-w-[12rem]">
                                <label class="block text-xs text-gray-500 mb-1">Add a map by name</label>
                                <input type="text" wire:model="swapSearch" placeholder="exact name, or the start of one"
                                       wire:keydown.enter="addCandidate"
                                       class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                            </div>
                            <button wire:click="addCandidate"
                                    class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-bold text-white hover:bg-primary-500">
                                Add
                            </button>
                            <button wire:click="rerollBallot"
                                    wire:confirm="Redraw every map on this ballot, back to the configured pool size? All votes cast so far are removed with the old set."
                                    class="rounded-lg border border-danger-500/50 px-3 py-2 text-sm font-bold text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-950">
                                Reroll whole ballot
                            </button>
                        </div>
                    @endif

                    @include('filament.pages.partials.comps-round-prize', ['round' => $voting])
                @else
                    <p class="text-sm text-gray-500">No ballot is open.</p>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
