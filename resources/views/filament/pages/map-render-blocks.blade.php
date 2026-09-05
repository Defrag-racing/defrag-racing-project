{{--
    The stylesheet is INSIDE the page component, not before it. A Livewire
    component must have one root element, and a <style> tag sitting beside that
    root broke every wire:click on the page at once: no button did anything,
    paging included, with nothing in the console to say why.

    The styles are written out rather than put on elements as Tailwind classes
    because Tailwind does not scan app/ or these views, so `bg-gray-800` here
    compiles to nothing. Filament's own components carry their own styling and
    are used wherever one exists.
--}}
<x-filament-panels::page>

    <style>
        .mrb-field { display: block; }
        .mrb-label { display: block; font-size: .8125rem; font-weight: 600; margin-bottom: .25rem; color: #374151; }
        .mrb-hint { margin-top: .25rem; font-size: .75rem; color: #6b7280; }
        .mrb-note {
            margin-top: 1rem; padding: .75rem; border-radius: .5rem;
            background: rgba(0,0,0,.03); border: 1px solid rgba(0,0,0,.08);
            font-size: .875rem; color: #374151;
        }
        .mrb-strong { font-weight: 700; color: #111827; }
        .mrb-rule { font-size: 1rem; line-height: 1.6; color: #374151; margin-bottom: .5rem; }
        .mrb-sort { cursor: pointer; user-select: none; background: none; border: 0; padding: 0; font: inherit; color: inherit; text-transform: inherit; letter-spacing: inherit; }
        .mrb-sort:hover { color: #2563eb; }
        .mrb-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .mrb-table th {
            text-align: left; padding: .5rem .75rem .5rem 0; font-size: .6875rem;
            text-transform: uppercase; letter-spacing: .05em; color: #6b7280;
            border-bottom: 1px solid rgba(0,0,0,.1); white-space: nowrap;
        }
        .mrb-table td { padding: .5rem .75rem .5rem 0; border-bottom: 1px solid rgba(0,0,0,.06); color: #374151; }
        .mrb-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .mrb-map { color: #2563eb; text-decoration: underline; font-weight: 600; }
        .mrb-map:hover { color: #1d4ed8; }
        .mrb-physics { text-transform: uppercase; color: #6b7280; }
        .mrb-why { font-size: .75rem; color: #6b7280; }
        .mrb-row {
            display: flex; align-items: center; justify-content: space-between; gap: .5rem;
            padding: .5rem .75rem; border-radius: .5rem;
            background: rgba(0,0,0,.03); border: 1px solid rgba(0,0,0,.08);
        }
        .mrb-scroll { overflow-x: auto; }
        .mrb-grid { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        .mrb-cards { display: grid; gap: .5rem; grid-template-columns: 1fr; }
        @media (min-width: 768px) {
            .mrb-grid { grid-template-columns: repeat(4, 1fr); align-items: end; }
            .mrb-cards { grid-template-columns: repeat(3, 1fr); }
        }
        .dark .mrb-label { color: #d1d5db; }
        .dark .mrb-hint, .dark .mrb-why, .dark .mrb-physics { color: #9ca3af; }
        .dark .mrb-note { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.1); color: #d1d5db; }
        .dark .mrb-strong { color: #fff; }
        .dark .mrb-rule { color: #d1d5db; }
        .dark .mrb-sort:hover { color: #60a5fa; }
        .dark .mrb-table th { color: #9ca3af; border-bottom-color: rgba(255,255,255,.1); }
        .dark .mrb-table td { color: #d1d5db; border-bottom-color: rgba(255,255,255,.06); }
        .dark .mrb-map { color: #60a5fa; }
        .dark .mrb-map:hover { color: #93c5fd; }
        .dark .mrb-row { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.1); }
    </style>

    <x-filament::section>
        <x-slot name="heading">The rule</x-slot>

        {{-- The whole rule as one sentence with the live numbers in it. Three
             labelled boxes said what each number was called and never said what
             the rule did with them. --}}
        <p class="mrb-rule">
            A map gets no video when
            <span class="mrb-strong">{{ (int) $this->tiedLimit }}</span> or more players share its record time,
            or when
            <span class="mrb-strong">{{ (int) $this->shortLimit }}</span> or more share it
            and that time is under
            <span class="mrb-strong">{{ number_format((int) $this->shortMs / 1000, 3) }}s</span>.
        </p>
        <p class="mrb-hint" style="margin-bottom: 1rem;">
            CPM and VQ3 are counted apart. Nothing is deleted: the map keeps its page, its records
            and its demos. It stops getting videos rendered and stays out of the YouTube playlists.
            <strong>stumpf</strong> is here because 133 people all finish it in 0.008s.
        </p>

        <div class="mrb-grid">
            <div class="mrb-field">
                <label class="mrb-label">Players sharing the record</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" min="2" wire:model.live.debounce.500ms="tiedLimit" />
                </x-filament::input.wrapper>
                <p class="mrb-hint">The first number in the sentence. Any time counts.</p>
            </div>
            <div class="mrb-field">
                <label class="mrb-label">Players, when the time is silly</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" min="2" wire:model.live.debounce.500ms="shortLimit" />
                </x-filament::input.wrapper>
                <p class="mrb-hint">The second number. Fewer people are enough.</p>
            </div>
            <div class="mrb-field">
                <label class="mrb-label">A silly time is under (ms)</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="number" min="0" step="100" wire:model.live.debounce.500ms="shortMs" />
                </x-filament::input.wrapper>
                <p class="mrb-hint">1000 is one second. Nobody runs anything that fast.</p>
            </div>
            <div class="mrb-field">
                <x-filament::button wire:click="saveLimits" icon="heroicon-o-check">
                    Save and recount
                </x-filament::button>
            </div>
        </div>

        <div class="mrb-note">
            <span class="mrb-strong">{{ number_format($blocked_total) }}</span> map and physics pairs get no video.
            <span class="mrb-strong">{{ number_format($rendered) }}</span> of them already have a video on YouTube,
            and <span class="mrb-strong">{{ number_format($queued) }}</span> are still waiting in the render queue.

            @if($queued > 0)
                <div style="margin-top: .75rem;">
                    <x-filament::button wire:click="cleanQueue" color="danger" size="sm"
                                        wire:confirm="Drop {{ $queued }} queued render(s)? Videos already on YouTube are left alone.">
                        Drop those {{ $queued }} queued renders
                    </x-filament::button>
                </div>
            @endif
        </div>
    </x-filament::section>

    @if(count($allowed) > 0)
        <x-filament::section class="mt-4">
            <x-slot name="heading">Rendered anyway ({{ count($allowed) }})</x-slot>
            <x-slot name="description">
                Maps you let through. They are rendered and go in playlists whatever the count says.
            </x-slot>
            <div class="mrb-cards">
                @foreach($allowed as $row)
                    <div class="mrb-row">
                        <div style="min-width: 0;">
                            <a class="mrb-map" href="https://defrag.racing/maps/{{ urlencode($row['map']) }}" target="_blank" rel="noopener">{{ $row['map'] }}</a>
                            <div class="mrb-physics" style="font-size: .75rem;">{{ $row['physics'] }}</div>
                        </div>
                        <x-filament::button wire:click="revoke('{{ $row['key'] }}')" color="gray" size="xs">
                            Undo
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    <x-filament::section class="mt-4">
        <x-slot name="heading">Blocked maps ({{ number_format($blocked_total) }})</x-slot>
        <x-slot name="description">
            Worst first: the more people share one time, the less of a run it is.
            Press Render anyway on a map the rule got wrong.
        </x-slot>

        <div style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: .75rem; margin-bottom: 1rem;">
            <div class="mrb-field" style="flex: 1 1 220px;">
                <label class="mrb-label">Find a map</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model.live.debounce.400ms="search" placeholder="map name" />
                </x-filament::input.wrapper>
            </div>
            <div class="mrb-field">
                <label class="mrb-label">Block one by hand</label>
                <div style="display: flex; gap: .5rem;">
                    <x-filament::input.wrapper style="width: 12rem;">
                        <x-filament::input type="text" wire:model="newMap" placeholder="map name" />
                    </x-filament::input.wrapper>
                    <x-filament::input.wrapper style="width: 7rem;">
                        <x-filament::input.select wire:model="newPhysics">
                            <option value="cpm">cpm</option>
                            <option value="vq3">vq3</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    <x-filament::button wire:click="block" color="danger" icon="heroicon-o-no-symbol">
                        Block
                    </x-filament::button>
                </div>
            </div>
        </div>

        @if($blocked_total === 0)
            <p class="mrb-hint">Nothing is blocked.</p>
        @else
            <div class="mrb-scroll">
                <table class="mrb-table">
                    <thead>
                        @php
                            $arrow = fn ($col) => $this->sort === $col ? ($this->direction === 'asc' ? ' &uarr;' : ' &darr;') : '';
                        @endphp
                        <tr>
                            <th><button type="button" class="mrb-sort" wire:click="sortBy('map')">Map{!! $arrow('map') !!}</button></th>
                            <th><button type="button" class="mrb-sort" wire:click="sortBy('physics')">Physics{!! $arrow('physics') !!}</button></th>
                            <th class="mrb-num"><button type="button" class="mrb-sort" wire:click="sortBy('players')">Players on the time{!! $arrow('players') !!}</button></th>
                            <th class="mrb-num"><button type="button" class="mrb-sort" wire:click="sortBy('time')">Record time{!! $arrow('time') !!}</button></th>
                            <th>Why</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($blocked as $row)
                            <tr>
                                <td>
                                    <a class="mrb-map" href="https://defrag.racing/maps/{{ urlencode($row['map']) }}"
                                       target="_blank" rel="noopener">{{ $row['map'] }}</a>
                                </td>
                                <td class="mrb-physics">{{ $row['physics'] }}</td>
                                <td class="mrb-num">{{ $row['players'] ?: '-' }}</td>
                                <td class="mrb-num">{{ $row['time'] ? number_format($row['time'] / 1000, 3) . 's' : '-' }}</td>
                                <td class="mrb-why">
                                    {{ $row['source'] === 'admin' ? 'blocked by hand' : 'the rule' }}
                                    @if($row['note']) - {{ $row['note'] }} @endif
                                </td>
                                <td style="text-align: right;">
                                    @if($row['source'] === 'admin')
                                        <x-filament::button wire:click="revoke('{{ $row['key'] }}')" color="gray" size="xs">
                                            Unblock
                                        </x-filament::button>
                                    @else
                                        <x-filament::button wire:click="allow('{{ $row['key'] }}')" color="success" size="xs">
                                            Render anyway
                                        </x-filament::button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($pages > 1)
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 1rem;">
                    <span class="mrb-hint">Showing {{ $from }} to {{ $to }} of {{ number_format($blocked_total) }} &middot; page {{ $page }} of {{ $pages }}</span>
                    <div style="display: flex; gap: .5rem;">
                        <x-filament::button wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1" color="gray" size="sm">
                            Back
                        </x-filament::button>
                        <x-filament::button wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $pages" color="gray" size="sm">
                            Next
                        </x-filament::button>
                    </div>
                </div>
            @endif
        @endif
    </x-filament::section>

</x-filament-panels::page>
