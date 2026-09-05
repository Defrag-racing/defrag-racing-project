<x-filament-panels::page>

    <x-filament::section>
        <x-slot name="heading">When a map gets no video</x-slot>
        <x-slot name="description">
            Either test is enough. Nothing is deleted: the map keeps its page, its records and its demos.
            It stops getting videos rendered, and it stays out of the YouTube playlists.
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Players on one time</label>
                <input type="number" min="2" wire:model="tiedLimit"
                       class="w-full rounded-lg bg-gray-800 border-gray-700 text-white text-sm">
                <p class="mt-1 text-xs text-gray-500">At any time. This is the main test.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Players, short time</label>
                <input type="number" min="2" wire:model="shortLimit"
                       class="w-full rounded-lg bg-gray-800 border-gray-700 text-white text-sm">
                <p class="mt-1 text-xs text-gray-500">Fewer are enough when the time is absurd.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Short time is under (ms)</label>
                <input type="number" min="0" step="100" wire:model="shortMs"
                       class="w-full rounded-lg bg-gray-800 border-gray-700 text-white text-sm">
                <p class="mt-1 text-xs text-gray-500">Nobody runs anything in under a second.</p>
            </div>
            <div>
                <x-filament::button wire:click="saveLimits" icon="heroicon-o-check" class="w-full">
                    Save and recount
                </x-filament::button>
            </div>
        </div>

        <div class="mt-4 p-3 rounded-lg bg-white/[0.03] border border-white/10 text-sm text-gray-300">
            <span class="font-bold text-white">{{ number_format($blocked_total) }}</span> map and physics pairs get no video.
            <span class="font-bold text-white">{{ number_format($rendered) }}</span> of them already have a video on YouTube,
            and <span class="font-bold text-white">{{ number_format($queued) }}</span> are still waiting in the render queue.

            @if($queued > 0)
                <div class="mt-3">
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach($allowed as $row)
                    <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-white/[0.03] border border-white/10">
                        <div class="min-w-0">
                            <div class="text-sm text-white truncate">{{ $row['map'] }}</div>
                            <div class="text-xs text-gray-500 uppercase">{{ $row['physics'] }}</div>
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

        <div class="flex flex-wrap items-end gap-3 mb-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-300 mb-1">Find a map</label>
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="map name"
                       class="w-full rounded-lg bg-gray-800 border-gray-700 text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-1">Block one by hand</label>
                <div class="flex gap-2">
                    <input type="text" wire:model="newMap" placeholder="map name"
                           class="rounded-lg bg-gray-800 border-gray-700 text-white text-sm">
                    <select wire:model="newPhysics" class="rounded-lg bg-gray-800 border-gray-700 text-white text-sm">
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
            <div class="text-sm text-gray-500">Nothing is blocked.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b border-white/10">
                            <th class="py-2 pr-4">Map</th>
                            <th class="py-2 pr-4">Physics</th>
                            <th class="py-2 pr-4 text-right">Players on the time</th>
                            <th class="py-2 pr-4 text-right">Record time</th>
                            <th class="py-2 pr-4">Why</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($blocked as $row)
                            <tr class="border-b border-white/5">
                                <td class="py-2 pr-4 text-white">
                                    <a href="https://defrag.racing/maps/{{ $row['map'] }}" target="_blank"
                                       class="hover:text-primary-400">{{ $row['map'] }}</a>
                                </td>
                                <td class="py-2 pr-4 uppercase text-gray-400">{{ $row['physics'] }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums text-white">{{ $row['players'] ?: '-' }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums text-gray-300">
                                    {{ $row['time'] ? number_format($row['time'] / 1000, 3) . 's' : '-' }}
                                </td>
                                <td class="py-2 pr-4 text-gray-500 text-xs">
                                    {{ $row['source'] === 'admin' ? 'blocked by hand' : 'the rule' }}
                                    @if($row['note']) - {{ $row['note'] }} @endif
                                </td>
                                <td class="py-2 text-right">
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
                <div class="flex items-center justify-between mt-4">
                    <div class="text-xs text-gray-500">Page {{ $page }} of {{ $pages }}</div>
                    <div class="flex gap-2">
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
