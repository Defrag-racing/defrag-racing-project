<x-filament-panels::page>
    {{-- Row 1: Status + Statistics + Actions --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Status --}}
        <x-filament::section>
            <x-slot name="heading">Status</x-slot>
            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Connection</span>
                    <x-filament::badge :color="$isOnline ? 'success' : 'danger'">{{ $isOnline ? 'Online' : 'Offline' }}</x-filament::badge>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Status</span>
                    <x-filament::badge :color="match($currentStatus) { 'idle' => 'gray', 'rendering' => 'warning', 'uploading' => 'info', default => 'gray' }">{{ ucfirst($currentStatus) }}</x-filament::badge>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Heartbeat</span>
                    <span class="text-gray-300">{{ $lastHeartbeat }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Rendering</span>
                    <x-filament::badge :color="$isPaused ? 'danger' : 'success'">{{ $isPaused ? 'Paused' : 'Active' }}</x-filament::badge>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Auto-Queue</span>
                    <x-filament::badge :color="$isAutoQueuePaused ? 'danger' : 'success'">{{ $isAutoQueuePaused ? 'Disabled' : 'Enabled' }}</x-filament::badge>
                </div>
                @if($currentVideo)
                    <div class="pt-2 border-t dark:border-gray-700 text-gray-300">
                        <span class="text-gray-500">Rendering:</span>
                        {{ $currentVideo->map_name }} - {{ $currentVideo->player_name }}
                    </div>
                @endif
            </div>
        </x-filament::section>

        {{-- Statistics --}}
        <x-filament::section>
            <x-slot name="heading">Statistics</x-slot>
            <div class="grid grid-cols-2 gap-2 text-center">
                <div class="p-2 bg-gray-800 rounded">
                    <div class="text-xl font-bold text-primary-500">{{ $stats['completed_total'] }}</div>
                    <div class="text-xs text-gray-500">Completed</div>
                </div>
                <div class="p-2 bg-gray-800 rounded">
                    <div class="text-xl font-bold text-success-500">{{ $stats['completed_today'] }}</div>
                    <div class="text-xs text-gray-500">Today</div>
                </div>
                <div class="p-2 bg-gray-800 rounded">
                    <div class="text-xl font-bold text-warning-500">{{ $stats['pending'] }}</div>
                    <div class="text-xs text-gray-500">Pending</div>
                </div>
                <div class="p-2 bg-gray-800 rounded">
                    <div class="text-xl font-bold text-danger-500">{{ $stats['failed'] }}</div>
                    <div class="text-xs text-gray-500">Failed</div>
                </div>
            </div>
            <div class="mt-2 text-center p-2 bg-gray-800 rounded">
                <span class="text-sm font-bold text-info-500">{{ $stats['total_render_hours'] }}h</span>
                <span class="text-xs text-gray-500 ml-1">total render time</span>
            </div>
        </x-filament::section>

        {{-- Actions --}}
        <x-filament::section>
            <x-slot name="heading">Actions</x-slot>
            <div class="grid grid-cols-2 gap-2">
                <x-filament::button
                    wire:click="togglePause"
                    :color="$isPaused ? 'success' : 'danger'"
                    icon="{{ $isPaused ? 'heroicon-o-play' : 'heroicon-o-pause' }}"
                    class="w-full"
                >
                    {{ $isPaused ? 'Resume' : 'Pause' }}
                </x-filament::button>

                <x-filament::button
                    wire:click="toggleAutoQueue"
                    :color="$isAutoQueuePaused ? 'success' : 'warning'"
                    icon="{{ $isAutoQueuePaused ? 'heroicon-o-play' : 'heroicon-o-stop' }}"
                    class="w-full"
                >
                    {{ $isAutoQueuePaused ? 'Enable Queue' : 'Disable Queue' }}
                </x-filament::button>

                <x-filament::button
                    wire:click="populateQueue"
                    color="info"
                    icon="heroicon-o-queue-list"
                    class="w-full"
                >
                    Populate Now
                </x-filament::button>

                <x-filament::button
                    wire:click="publishUnlisted"
                    wire:confirm="Mark {{ $unlisted_count }} unlisted videos for publishing?"
                    color="success"
                    icon="heroicon-o-eye"
                    class="w-full"
                    :disabled="$unlisted_count === 0"
                >
                    Publish ({{ $unlisted_count }})
                </x-filament::button>
            </div>
        </x-filament::section>
    </div>

    {{-- Row 2: Backlog + Queue Breakdown + Auto-Publish --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
        {{-- Render Backlog --}}
        <x-filament::section>
            <x-slot name="heading">Render Backlog</x-slot>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-yellow-400">Online WR</span><span class="font-bold text-yellow-400">{{ number_format($backlog['wr_remaining']) }}</span></div>
                <div class="flex justify-between"><span class="text-blue-400">Online Other</span><span class="font-bold text-blue-400">{{ number_format($backlog['non_wr_remaining']) }}</span></div>
                <div class="flex justify-between"><span class="text-gray-400">Offline</span><span class="font-bold text-gray-300">{{ number_format($backlog['offline_remaining']) }}</span></div>
                <div class="flex justify-between"><span class="text-purple-400">Longer (10min+)</span><span class="font-bold text-purple-400">{{ number_format($backlog['longer_remaining'] ?? 0) }}</span></div>
                <div class="flex justify-between pt-2 border-t border-gray-700/50"><span class="text-white font-bold">Total</span><span class="font-bold text-white">{{ number_format($backlog['total_remaining']) }}</span></div>
            </div>
        </x-filament::section>

        {{-- Queue Breakdown --}}
        <x-filament::section>
            <x-slot name="heading">Queue Breakdown</x-slot>
            <div class="space-y-1 text-sm">
                <div class="flex items-center justify-between p-1.5 bg-yellow-900/20 rounded border border-yellow-800/50">
                    <span class="text-yellow-300">P1: World Records</span>
                    <span class="text-lg font-bold text-yellow-400">{{ $queue_by_priority['wr'] }}</span>
                </div>
                <div class="flex items-center justify-between p-1.5 bg-blue-900/20 rounded border border-blue-800/50">
                    <span class="text-blue-300">P2: Verified</span>
                    <span class="text-lg font-bold text-blue-400">{{ $queue_by_priority['verified'] }}</span>
                </div>
                <div class="flex items-center justify-between p-1.5 bg-gray-800 rounded border border-gray-700/50">
                    <span class="text-gray-300">P3: Normal</span>
                    <span class="text-lg font-bold text-gray-400">{{ $queue_by_priority['normal'] }}</span>
                </div>
            </div>
        </x-filament::section>

        {{-- Today + Last manual bulks --}}
        <x-filament::section>
            <x-slot name="heading">Today & Publishing</x-slot>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-500">Auto renders</span><span class="font-medium text-green-400">{{ $backlog['today_auto'] }}</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Gameplay</span><span class="text-gray-300">{{ $backlog['today_gameplay_hours'] }}h</span></div>
                <div class="flex justify-between"><span class="text-gray-500">Render time</span><span class="text-gray-300">{{ $backlog['today_render_hours'] }}h</span></div>
                <div class="pt-2 border-t border-gray-700/50 space-y-1">
                    <div class="text-gray-500 text-xs uppercase tracking-wider mb-1">Last manual bulks</div>
                    @forelse($manual_bulk_history as $entry)
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-gray-300 text-xs truncate">{{ $entry['label'] }}</span>
                            <span class="text-gray-500 text-xs whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($entry['ts'])->format('M j, H:i') }}
                                <span class="text-primary-400 font-medium ml-1">({{ $entry['count'] }})</span>
                            </span>
                        </div>
                    @empty
                        <div class="text-gray-600 text-xs italic">No manual bulk runs yet</div>
                    @endforelse
                    @if($publishing_count > 0)
                        <div class="flex items-center justify-between pt-2 border-t border-gray-700/30 mt-2">
                            <span class="text-gray-500">Awaiting demome</span>
                            <x-filament::badge color="warning">{{ $publishing_count }}</x-filament::badge>
                        </div>
                    @endif
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Row 2.5: Manual Bulk Publish (per-tier) --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">Manual Bulk Publish</x-slot>
        <x-slot name="description">
            Mark every unlisted auto-rendered video in a given category as approved for publishing.
            Demome bot will transition them from unlisted to public on its next run.
            Use this for manual curation - the triweekly automatic bulk has been disabled.
        </x-slot>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            @foreach($bulk_tier_buttons as $btn)
                @php
                    $isAll = $btn['tier'] === -1;
                    $disabled = $btn['count'] === 0;
                    $confirm = "Mark {$btn['count']} unlisted videos in '{$btn['label']}' for publishing?";
                @endphp
                <x-filament::button
                    wire:click="bulkPublishTier({{ $btn['tier'] }})"
                    wire:confirm="{{ $confirm }}"
                    :color="$isAll ? 'danger' : 'success'"
                    :disabled="$disabled"
                    icon="heroicon-o-eye"
                    class="w-full"
                >
                    {{ $btn['label'] }} ({{ $btn['count'] }})
                </x-filament::button>
            @endforeach
        </div>
    </x-filament::section>

    {{-- Row 2.6: YouTube playlists --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">YouTube Playlists</x-slot>
        <x-slot name="description">
            Pick the playlists to bring up to date, then run the sync on the demome box.
            Each holds one video per map: the fastest run on it. A playlist without (CPM) or (VQ3)
            holds both, with the two physics alternating so the same map is never twice in a row.
            Nothing here talks to YouTube - it only works out what should go where.
        </x-slot>

        @php
            $queuedPlaylists = collect($playlist_buttons)->where('queued', true);
            $queuedSlots = $queuedPlaylists->sum('planned');
            // Every add and every removal costs 50 units of the daily quota,
            // and a first fill is nearly all adds. Worth showing before the
            // click rather than after the run stops half way.
            $queuedQuota = $queuedSlots * 50;
        @endphp

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <x-filament::button wire:click="queueAllPlaylists" color="danger" icon="heroicon-o-queue-list">
                Queue everything out of date
            </x-filament::button>

            @if($queuedPlaylists->count() > 0)
                <x-filament::button wire:click="clearPlaylistQueue" color="gray" icon="heroicon-o-x-mark">
                    Clear queue
                </x-filament::button>
            @endif

            {{-- The counts below are kept for ten minutes, and the blocked map
                 list they depend on is changed on another page. After blocking
                 a map, press this. --}}
            <x-filament::button wire:click="refreshPlaylistCounts" color="gray" icon="heroicon-o-arrow-path">
                Recount
            </x-filament::button>

            <a href="https://console.cloud.google.com/apis/api/youtube.googleapis.com/quotas"
               target="_blank" rel="noopener"
               class="text-sm text-primary-400 hover:text-primary-300 underline">
                YouTube quota &rarr;
            </a>
        </div>

        @if($queuedPlaylists->count() > 0)
            <div class="mb-4 p-3 rounded-lg bg-white/[0.03] border border-white/10">
                <div class="text-sm text-gray-300 mb-2">
                    <span class="font-bold text-white">{{ $queuedPlaylists->count() }}</span> playlists queued,
                    <span class="font-bold text-white">{{ number_format($queuedSlots) }}</span> video slots,
                    up to <span class="font-bold text-white">{{ number_format($queuedQuota) }}</span> quota units
                    @if($queuedQuota > 463000)
                        <span class="text-amber-400">- more than one day. The run will stop and carry on tomorrow.</span>
                    @endif
                </div>
                <code class="block px-3 py-2 bg-gray-800 rounded text-sm font-mono text-gray-300 break-all select-all">python playlists_sync.py --apply</code>
            </div>
        @endif

        @foreach(collect($playlist_buttons)->groupBy('group') as $group => $buttons)
            <div class="text-xs font-bold uppercase tracking-wide text-gray-500 mt-4 mb-2">{{ $group }}</div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                @foreach($buttons as $btn)
                    @php
                        $behind = $btn['planned'] !== $btn['synced'];
                        $confirm = "Work out '{$btn['label']}' and put it in the sync queue? It should hold {$btn['planned']} videos.";
                    @endphp
                    <x-filament::button
                        wire:click="queuePlaylist('{{ $btn['key'] }}')"
                        wire:confirm="{{ $confirm }}"
                        :color="$btn['queued'] ? 'warning' : ($behind ? 'success' : 'gray')"
                        :disabled="$btn['planned'] === 0"
                        :icon="$btn['queued'] ? 'heroicon-o-clock' : 'heroicon-o-list-bullet'"
                        class="w-full"
                    >
                        {{ $btn['label'] }} ({{ $btn['planned'] }})
                    </x-filament::button>
                @endforeach
            </div>
        @endforeach

        <div class="mt-4 text-xs text-gray-500">
            Green means the playlist is out of date. Grey means YouTube already holds what it should.
            Amber means it is queued and waiting for the sync.
        </div>
    </x-filament::section>

    {{-- Row 3: API Token (compact) --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">API Token</x-slot>
        <div class="flex items-center gap-4">
            <code class="flex-1 px-3 py-2 bg-gray-800 rounded text-sm font-mono text-gray-300 break-all select-all">{{ $apiToken ?: 'Not set' }}</code>
            {{ $this->regenerateTokenAction }}
        </div>
    </x-filament::section>

    {{-- Row 3.5: Discord restart marker --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">Discord Restart Marker</x-slot>
        <x-slot name="description">
            Force demome to rescan Discord starting from a specific message ID on its next startup.
            Useful when a render crashed without writing anything and the bot has already advanced past it.
            The marker is one-shot: it is consumed by demome on startup and cleared automatically.
        </x-slot>
        <div class="space-y-3">
            @if($discordRestartMarker)
                <div class="flex items-center justify-between p-2 bg-yellow-900/20 border border-yellow-800/50 rounded">
                    <div class="text-sm">
                        <span class="text-gray-400">Pending marker:</span>
                        <code class="ml-2 text-yellow-300 font-mono">{{ $discordRestartMarker }}</code>
                    </div>
                    <x-filament::button wire:click="clearDiscordRestartMarker" size="sm" color="danger" icon="heroicon-o-x-mark">
                        Clear
                    </x-filament::button>
                </div>
            @endif
            <div class="flex items-center gap-3">
                <input
                    type="text"
                    wire:model="discordRestartMessageId"
                    placeholder="Discord message ID (e.g. 1492601473450774711)"
                    style="flex: 1; padding: 8px 12px; background: #1f2937; color: #e5e7eb; border: 1px solid #374151; border-radius: 6px; font-size: 13px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; outline: none;"
                    onfocus="this.style.borderColor='#ea580c'"
                    onblur="this.style.borderColor='#374151'"
                />
                <x-filament::button wire:click="setDiscordRestartMarker" color="warning" icon="heroicon-o-arrow-uturn-left">
                    Set Marker
                </x-filament::button>
            </div>
            <div class="text-xs text-gray-500">
                Demome will re-scrape the Discord channel(s) for messages with ID &gt; this value on next startup.
                Set the message ID to one <strong>just before</strong> the message you want reprocessed.
            </div>
        </div>
    </x-filament::section>

    {{-- Row 3.6: Single-message reprocess marker --}}
    <x-filament::section class="mt-4">
        <x-slot name="heading">Reprocess Single Discord Message</x-slot>
        <x-slot name="description">
            Force demome to fetch <strong>exactly one</strong> Discord message by ID and re-process the demo it contains.
            Use this when one specific render failed or needs a re-run - unlike the Restart Marker above, this does not
            touch any other messages around it. One-shot: consumed by demome on the next cycle and cleared automatically.
            <br><br>
            <strong>Dedup behavior:</strong> if the demo from this message is already in the system (matched by file
            hash) and has a YouTube video, demome will reply to the original requester with the existing YouTube URL
            instead of re-rendering. Only demos without an existing YouTube video get rendered fresh.
        </x-slot>
        <div class="space-y-3">
            @if($discordReprocessMarker)
                <div class="flex items-center justify-between p-2 bg-blue-900/20 border border-blue-800/50 rounded">
                    <div class="text-sm">
                        <span class="text-gray-400">Pending reprocess:</span>
                        <code class="ml-2 text-blue-300 font-mono">{{ $discordReprocessMarker }}</code>
                    </div>
                    <x-filament::button wire:click="clearDiscordReprocessMarker" size="sm" color="danger" icon="heroicon-o-x-mark">
                        Clear
                    </x-filament::button>
                </div>
            @endif
            <div class="flex items-center gap-3">
                <input
                    type="text"
                    wire:model="discordReprocessMessageId"
                    placeholder="Discord message ID (e.g. 1492601473450774711)"
                    style="flex: 1; padding: 8px 12px; background: #1f2937; color: #e5e7eb; border: 1px solid #374151; border-radius: 6px; font-size: 13px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; outline: none;"
                    onfocus="this.style.borderColor='#2563eb'"
                    onblur="this.style.borderColor='#374151'"
                />
                <x-filament::button wire:click="setDiscordReprocessMarker" color="info" icon="heroicon-o-arrow-path">
                    Set Marker
                </x-filament::button>
            </div>
            <div class="text-xs text-gray-500">
                Demome will fetch this single message directly by ID and process its demo attachment(s), independent of
                its normal scraping cursor. The exact ID is used (no rewind), so paste the message you want re-rendered.
            </div>
        </div>
    </x-filament::section>

    {{-- Row 4: Unlisted Videos with tier tabs + pagination --}}
    @if($unlisted_count > 0)
    <x-filament::section class="mt-4">
        <x-slot name="heading">Unlisted Videos ({{ $unlisted_count }})</x-slot>

        {{-- Tier filter tabs --}}
        @php $tierLabels = \App\Services\RenderQueueService::TIER_LABELS; @endphp
        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px;">
            <button wire:click="selectUnlistedTier(null)"
                style="padding: 4px 12px; font-size: 11px; font-weight: 700; border-radius: 6px; border: 1px solid {{ $this->unlistedTier === null ? '#ea580c' : '#374151' }}; background: {{ $this->unlistedTier === null ? '#ea580c33' : 'transparent' }}; color: {{ $this->unlistedTier === null ? '#ea580c' : '#9ca3af' }}; cursor: pointer;">
                All ({{ $unlisted_count }})
            </button>
            @foreach($unlisted_tier_counts as $tier => $count)
                <button wire:click="selectUnlistedTier({{ $tier }})"
                    style="padding: 4px 12px; font-size: 11px; font-weight: 700; border-radius: 6px; border: 1px solid {{ $this->unlistedTier === $tier ? '#ea580c' : '#374151' }}; background: {{ $this->unlistedTier === $tier ? '#ea580c33' : 'transparent' }}; color: {{ $this->unlistedTier === $tier ? '#ea580c' : '#9ca3af' }}; cursor: pointer;">
                    {{ $tier === 0 ? 'Unclassified' : ($tierLabels[$tier] ?? "Tier {$tier}") }} ({{ $count }})
                </button>
            @endforeach
        </div>

        {{-- Video table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-700/50">
                    @forelse($unlisted_videos as $video)
                    <tr>
                        <td class="py-1.5 px-3 text-gray-300" style="width: 200px;">{{ $video->map_name }}</td>
                        <td class="py-1.5 px-3 text-gray-300" style="width: 150px;">{!! \App\Filament\Resources\UserResource::q3tohtml($video->player_name) !!}</td>
                        <td class="py-1.5 px-3" style="width: 60px;"><x-filament::badge :color="$video->physics === 'cpm' ? 'info' : 'success'" size="sm">{{ strtoupper($video->physics) }}</x-filament::badge></td>
                        <td class="py-1.5 px-3" style="width: 80px;">
                            @if($this->unlistedTier === null)
                                <span style="font-size: 10px; color: #6b7280;">{{ $tierLabels[$video->quality_tier] ?? 'N/A' }}</span>
                            @endif
                        </td>
                        <td class="py-1.5 px-3" style="width: 60px;">
                            @if($video->youtube_url)
                                <a href="{{ $video->youtube_url }}" target="_blank" class="text-blue-400 hover:text-blue-300 text-xs">View</a>
                            @endif
                        </td>
                        <td class="py-1.5 px-3 text-gray-500 text-xs">{{ $video->created_at->diffForHumans() }}</td>
                        <td class="py-1.5 px-3 text-right">
                            <x-filament::button wire:click="publishSingle({{ $video->id }})" size="xs" color="success" icon="heroicon-o-eye">Publish</x-filament::button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="py-4 text-center text-gray-500 text-sm">No videos in this category</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($unlisted_total_pages > 1)
            @php
                $isFirstPage = $this->unlistedPage <= 1;
                $isLastPage = $this->unlistedPage >= $unlisted_total_pages;
            @endphp
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.05);">
                <button wire:click="previousUnlistedPage" {{ $isFirstPage ? 'disabled' : '' }}
                    style="padding: 6px 16px; font-size: 12px; font-weight: 600; border-radius: 6px; border: 1px solid #374151; background: {{ $isFirstPage ? 'transparent' : '#1f2937' }}; color: {{ $isFirstPage ? '#4b5563' : '#d1d5db' }}; cursor: {{ $isFirstPage ? 'not-allowed' : 'pointer' }};">
                    Previous
                </button>
                <span style="font-size: 12px; color: #6b7280;">Page {{ $this->unlistedPage }} of {{ $unlisted_total_pages }}</span>
                <button wire:click="nextUnlistedPage" {{ $isLastPage ? 'disabled' : '' }}
                    style="padding: 6px 16px; font-size: 12px; font-weight: 600; border-radius: 6px; border: 1px solid #374151; background: {{ $isLastPage ? 'transparent' : '#1f2937' }}; color: {{ $isLastPage ? '#4b5563' : '#d1d5db' }}; cursor: {{ $isLastPage ? 'not-allowed' : 'pointer' }};">
                    Next
                </button>
            </div>
        @endif
    </x-filament::section>
    @endif
</x-filament-panels::page>
