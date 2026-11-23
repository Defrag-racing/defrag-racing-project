{{--
    The stylesheet is INSIDE the page component: a Livewire component has one
    root element, and a <style> tag beside it breaks every wire:click. The
    styles are written out because Tailwind does not scan these views.
--}}
<x-filament-panels::page>
    <div @if($busy) wire:poll.5s @endif>

    <style>
        .qrc-label { display: block; font-size: .8125rem; font-weight: 600; margin-bottom: .25rem; color: #374151; }
        .qrc-hint { margin-top: .25rem; font-size: .75rem; color: #6b7280; }
        .qrc-text { font-size: 1rem; line-height: 1.6; color: #374151; margin-bottom: .75rem; }
        .qrc-strong { font-weight: 700; color: #111827; }
        .qrc-bad { font-weight: 700; color: #b91c1c; }
        .qrc-ok { font-weight: 700; color: #15803d; }
        .qrc-grid { display: grid; gap: 1rem; grid-template-columns: 1fr; }
        @media (min-width: 768px) { .qrc-grid { grid-template-columns: 1fr 1fr auto auto; align-items: end; } }
        .qrc-scroll { overflow-x: auto; }
        .qrc-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .qrc-table th {
            text-align: left; padding: .5rem .75rem .5rem 0; font-size: .6875rem;
            text-transform: uppercase; letter-spacing: .05em; color: #6b7280;
            border-bottom: 1px solid rgba(0,0,0,.1); white-space: nowrap;
        }
        .qrc-table td { padding: .5rem .75rem .5rem 0; border-bottom: 1px solid rgba(0,0,0,.06); color: #374151; vertical-align: middle; }
        .qrc-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .qrc-dim { color: #6b7280; }
        .qrc-link { color: #2563eb; text-decoration: underline; font-weight: 600; background: none; border: 0; padding: 0; cursor: pointer; font: inherit; }
        .qrc-selected td { background: rgba(37,99,235,.06); }
        .qrc-actions { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
        .qrc-pager { display: flex; gap: .5rem; align-items: center; margin-top: .75rem; font-size: .875rem; color: #6b7280; }
        .dark .qrc-label, .dark .qrc-text, .dark .qrc-table td { color: #d1d5db; }
        .dark .qrc-hint, .dark .qrc-dim, .dark .qrc-table th, .dark .qrc-pager { color: #9ca3af; }
        .dark .qrc-strong { color: #fff; }
        .dark .qrc-bad { color: #f87171; }
        .dark .qrc-ok { color: #4ade80; }
        .dark .qrc-link { color: #60a5fa; }
        .dark .qrc-table th { border-bottom-color: rgba(255,255,255,.1); }
        .dark .qrc-table td { border-bottom-color: rgba(255,255,255,.06); }
        .dark .qrc-selected td { background: rgba(96,165,250,.08); }
    </style>

    <x-filament::section>
        <x-slot name="heading">Do we have every record q3df has?</x-slot>

        <p class="qrc-text">
            q3df.org lists
            <span class="qrc-strong">{{ $q3dfTotal === null ? 'an unknown number of' : number_format($q3dfTotal) }}</span>
            current records. We hold <span class="qrc-strong">{{ number_format($ourTotal) }}</span>.
            A check compares the two record by record and changes nothing. You decide afterwards what gets imported.
        </p>

        @unless($hasKey)
            <p class="qrc-text qrc-bad">
                Q3DF_API_KEY is not set. Without it q3df serves only the first 200 records, so a check cannot run.
            </p>
        @endunless

        <div class="qrc-grid">
            <div>
                <label class="qrc-label">From</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model="dateFrom" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="qrc-label">To</label>
                <x-filament::input.wrapper>
                    <x-filament::input type="date" wire:model="dateTo" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <x-filament::button wire:click="checkRange" icon="heroicon-o-magnifying-glass" :disabled="$busy">
                    Check these dates
                </x-filament::button>
            </div>
            <div>
                <x-filament::button wire:click="checkEverything" color="gray" icon="heroicon-o-globe-alt" :disabled="$busy"
                    wire:confirm="This reads about 675 pages from q3df and takes roughly half an hour. Start it?">
                    Check the whole history
                </x-filament::button>
            </div>
        </div>
        <p class="qrc-hint">
            q3df has no date filter. A date range is read from the newest record back to its first day,
            so an old range takes longer than a recent one.
        </p>
    </x-filament::section>

    @if($checks->isNotEmpty())
        <x-filament::section style="margin-top: 1.5rem;">
            <x-slot name="heading">Checks</x-slot>

            <div class="qrc-scroll">
                <table class="qrc-table">
                    <thead>
                        <tr>
                            <th>What</th>
                            <th>Status</th>
                            <th class="qrc-num">Read</th>
                            <th class="qrc-num">Matching</th>
                            <th class="qrc-num">Missing</th>
                            <th class="qrc-num">Other time</th>
                            <th class="qrc-num">Imported</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($checks as $c)
                            <tr wire:key="check-{{ $c->id }}" class="{{ $check && $check->id === $c->id ? 'qrc-selected' : '' }}">
                                <td>
                                    <button type="button" class="qrc-link" wire:click="show({{ $c->id }})">
                                        @if($c->scope === 'full')
                                            Whole history
                                        @else
                                            {{ $c->date_from->format('j M Y') }} to {{ $c->date_to->format('j M Y') }}
                                        @endif
                                    </button>
                                    <div class="qrc-hint">{{ $c->created_at->diffForHumans() }}</div>
                                </td>
                                <td>
                                    {{ $c->status }}
                                    @if($c->status === 'running')
                                        <span class="qrc-dim">
                                            page {{ $c->pages_read }}{{ $c->pages_total ? ' of ' . $c->pages_total : '' }}
                                        </span>
                                    @endif
                                    @if($c->error)
                                        <div class="qrc-hint qrc-bad">{{ $c->error }}</div>
                                    @endif
                                </td>
                                <td class="qrc-num">{{ number_format($c->q3df_rows) }}</td>
                                <td class="qrc-num">{{ number_format($c->matching) }}</td>
                                <td class="qrc-num {{ $c->missing ? 'qrc-bad' : '' }}">{{ number_format($c->missing) }}</td>
                                <td class="qrc-num">{{ number_format($c->different) }}</td>
                                <td class="qrc-num">{{ number_format($c->imported) }}</td>
                                <td class="qrc-num">
                                    @if($c->isBusy())
                                        <x-filament::button size="xs" color="danger" icon="heroicon-o-stop"
                                            wire:click="stop({{ $c->id }})" wire:confirm="Stop this {{ in_array($c->status, ['importing', 'updating']) ? 'import' : 'check' }}?">
                                            Stop
                                        </x-filament::button>
                                    @else
                                        <x-filament::icon-button icon="heroicon-o-trash" color="gray" size="sm" label="Delete"
                                            wire:click="remove({{ $c->id }})" wire:confirm="Delete this check and its list?" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @if($check)
        <x-filament::section style="margin-top: 1.5rem;">
            <x-slot name="heading">
                @if($check->scope === 'full')
                    Whole history
                @else
                    {{ $check->date_from->format('j M Y') }} to {{ $check->date_to->format('j M Y') }}
                @endif
            </x-slot>

            @if(in_array($check->status, ['queued', 'running']))
                <p class="qrc-text">
                    Still reading q3df: page {{ $check->pages_read }}{{ $check->pages_total ? ' of ' . $check->pages_total : '' }}.
                    The numbers grow while it runs.
                </p>
            @endif

            <p class="qrc-text">
                q3df listed <span class="qrc-strong">{{ number_format($check->q3df_rows) }}</span> records
                {{ $check->scope === 'full' ? 'in its whole history' : 'set in these dates' }}.
                <span class="qrc-ok">{{ number_format($check->matching) }}</span> are here with the same time.
                <span class="qrc-bad">{{ number_format($check->missing) }}</span> are not here at all.
                <span class="qrc-strong">{{ number_format($check->different) }}</span> are here with another time.
                @if($check->only_here !== null)
                    We also hold <span class="qrc-strong">{{ number_format($check->only_here) }}</span>
                    {{ $check->scope === 'full' ? '' : 'records dated in this range' }} that q3df does not list,
                    usually records deleted on q3df. Those are left alone.
                @endif
            </p>

            <div class="qrc-actions" style="margin-bottom: 1rem;">
                @if($importable > 0 && ! $check->isBusy())
                    <x-filament::button wire:click="import({{ $check->id }})" icon="heroicon-o-arrow-down-tray" :disabled="$busy"
                        wire:confirm="Import {{ $importable }} records into the records table?">
                        Import {{ number_format($importable) }} records
                    </x-filament::button>
                @endif
                @if($check->status === 'importing')
                    <span class="qrc-text">Importing: {{ number_format($check->imported) }} so far.</span>
                @elseif($check->status === 'updating')
                    <span class="qrc-text">{{ number_format($check->imported) }} imported. Now updating the ranks of each touched map and the stats of each touched player.</span>
                @endif
            </div>
            @if($deletedHere > 0)
                <p class="qrc-text">
                    <span class="qrc-strong">{{ number_format($deletedHere) }}</span>
                    {{ $deletedHere === 1 ? 'record was' : 'records were' }} not imported: we hold that exact time
                    as a deleted record, maybe removed by an admin. To bring one back, restore it in Records.
                </p>
            @endif
            @if($failedRows > 0)
                <p class="qrc-text qrc-bad">
                    {{ number_format($failedRows) }} {{ $failedRows === 1 ? 'record' : 'records' }} failed with an error.
                    The reason is in the list below.
                </p>
            @endif

            <p class="qrc-hint" style="margin-bottom: 1rem;">
                Import takes every missing record, and every record where q3df holds a faster time than we do.
                Where we hold the faster time, nothing changes. No player is notified about an imported record,
                and its map and profile are updated once at the end. Ratings catch up with the nightly
                recalculation, or start it now on <a class="qrc-link" href="{{ \App\Filament\Pages\RatingSettings::getUrl() }}">Rating Settings</a>.
            </p>

            @if($months->isNotEmpty())
                <div class="qrc-scroll" style="margin-bottom: 1.5rem;">
                    <table class="qrc-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th class="qrc-num">Missing</th>
                                <th class="qrc-num">Other time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($months as $m)
                                <tr wire:key="month-{{ $m->month }}">
                                    <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $m->month)->format('F Y') }}</td>
                                    <td class="qrc-num {{ $m->missing ? 'qrc-bad' : '' }}">{{ number_format($m->missing) }}</td>
                                    <td class="qrc-num">{{ number_format($m->different) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="max-width: 16rem; margin-bottom: .75rem;">
                    <label class="qrc-label">Show</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="kind">
                            <option value="all">Every difference</option>
                            <option value="missing">Missing only</option>
                            <option value="different">Other time only</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div class="qrc-scroll">
                    <table class="qrc-table">
                        <thead>
                            <tr>
                                <th>Set on</th>
                                <th>Player</th>
                                <th>Map</th>
                                <th>Physics</th>
                                <th class="qrc-num">q3df time</th>
                                <th class="qrc-num">Our time</th>
                                <th>Imported</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $r)
                                <tr wire:key="row-{{ $r->id }}">
                                    <td class="qrc-dim">{{ $r->date_set->format('Y-m-d H:i') }}</td>
                                    <td>{{ preg_replace('/\^./', '', $r->name) }} <span class="qrc-dim">#{{ $r->mdd_id }}</span></td>
                                    <td>{{ $r->mapname }}</td>
                                    <td class="qrc-dim">{{ strtoupper($r->physics) }} {{ $r->mode }}</td>
                                    <td class="qrc-num">{{ number_format($r->q3df_time / 1000, 3) }}</td>
                                    <td class="qrc-num {{ $r->our_time === null ? 'qrc-bad' : '' }}">
                                        {{ $r->our_time === null ? 'missing' : number_format($r->our_time / 1000, 3) }}
                                    </td>
                                    <td class="qrc-dim">
                                        @if($r->imported_at)
                                            {{ $r->imported_at->format('Y-m-d H:i') }}
                                        @elseif($r->skipped_reason)
                                            <span class="{{ str_starts_with($r->skipped_reason, 'error:') ? 'qrc-bad' : '' }}">{{ $r->skipped_reason }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="qrc-pager">
                    <x-filament::button size="sm" color="gray" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-filament::button>
                    <span>Page {{ $page }} of {{ $pages }} ({{ number_format($rowsTotal) }} rows)</span>
                    <x-filament::button size="sm" color="gray" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= $pages">Next</x-filament::button>
                </div>
            @elseif($check->status === 'done')
                <p class="qrc-text qrc-ok">No differences in this check.</p>
            @endif
        </x-filament::section>
    @endif

    </div>
</x-filament-panels::page>
