{{--
    Every style on this page is written out below rather than put on the
    elements as Tailwind classes. Tailwind does not scan app/ or these views,
    so a class like `bg-gray-800` here compiles to nothing: the first version
    of this page had white text in white boxes and links that did not look like
    links. Filament's own components keep their own styling and are used
    wherever one exists.
--}}
<style>
    .mrb-field { display: block; }
    .mrb-label { display: block; font-size: .8125rem; font-weight: 600; margin-bottom: .25rem; color: #374151; }
    .mrb-hint { margin-top: .25rem; font-size: .75rem; color: #6b7280; }
    .mrb-input {
        width: 100%; padding: .5rem .75rem; border-radius: .5rem;
        border: 1px solid #d1d5db; background: #fff; color: #111827; font-size: .875rem;
    }
    .mrb-input:focus { outline: 2px solid #f59e0b; outline-offset: -1px; }
    .mrb-note {
        margin-top: 1rem; padding: .75rem; border-radius: .5rem;
        background: rgba(0,0,0,.03); border: 1px solid rgba(0,0,0,.08);
        font-size: .875rem; color: #374151;
    }
    .mrb-strong { font-weight: 700; color: #111827; }
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
    .dark .mrb-input { background: #1f2937; border-color: #4b5563; color: #f9fafb; }
    .dark .mrb-note { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.1); color: #d1d5db; }
    .dark .mrb-strong { color: #fff; }
    .dark .mrb-table th { color: #9ca3af; border-bottom-color: rgba(255,255,255,.1); }
    .dark .mrb-table td { color: #d1d5db; border-bottom-color: rgba(255,255,255,.06); }
    .dark .mrb-map { color: #60a5fa; }
    .dark .mrb-map:hover { color: #93c5fd; }
    .dark .mrb-row { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.1); }
</style>

<x-filament-panels::page>

    <x-filament::section>
        <x-slot name="heading">When a map gets no video</x-slot>
        <x-slot name="description">
            Either test is enough. Nothing is deleted: the map keeps its page, its records and its demos.
            It stops getting videos rendered, and it stays out of the YouTube playlists.
        </x-slot>

        <div class="mrb-grid">
            <div class="mrb-field">
                <label class="mrb-label">Players on one time</label>
                <input type="number" min="2" wire:model="tiedLimit" class="mrb-input">
                <p class="mrb-hint">At any time. This is the main test.</p>
            </div>
            <div class="mrb-field">
                <label class="mrb-label">Players, short time</label>
                <input type="number" min="2" wire:model="shortLimit" class="mrb-input">
                <p class="mrb-hint">Fewer are enough when the time is absurd.</p>
            </div>
            <div class="mrb-field">
                <label class="mrb-label">Short time is under (ms)</label>
                <input type="number" min="0" step="100" wire:model="shortMs" class="mrb-input">
                <p class="mrb-hint">Nobody runs anything in under a second.</p>
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
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="map name" class="mrb-input">
            </div>
            <div class="mrb-field">
                <label class="mrb-label">Block one by hand</label>
                <div style="display: flex; gap: .5rem;">
                    <input type="text" wire:model="newMap" placeholder="map name" class="mrb-input" style="width: 12rem;">
                    <select wire:model="newPhysics" class="mrb-input" style="width: 6rem;">
                        <option value="cpm">cpm</option>
                        <option value="vq3">vq3</option>
                    </select>
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
                        <tr>
                            <th>Map</th>
                            <th>Physics</th>
                            <th class="mrb-num">Players on the time</th>
                            <th class="mrb-num">Record time</th>
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
                    <span class="mrb-hint">Page {{ $page }} of {{ $pages }}</span>
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
