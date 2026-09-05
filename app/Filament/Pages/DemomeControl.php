<?php

namespace App\Filament\Pages;

use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use App\Models\SiteSetting;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DemomeControl extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Demome Control';

    protected static ?string $navigationGroup = 'Demome';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.demome-control';

    public ?int $unlistedTier = null; // null = all
    public int $unlistedPage = 1;
    public int $unlistedPerPage = 20;

    public ?string $discordRestartMessageId = null;
    public ?string $discordReprocessMessageId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function selectUnlistedTier(?int $tier): void
    {
        $this->unlistedTier = $tier;
        $this->unlistedPage = 1;
    }

    public function previousUnlistedPage(): void
    {
        $this->unlistedPage = max(1, $this->unlistedPage - 1);
    }

    public function nextUnlistedPage(): void
    {
        $this->unlistedPage++;
    }

    protected function getViewData(): array
    {
        $lastHeartbeat = Cache::get('demome:last_heartbeat');
        $isOnline = $lastHeartbeat && Carbon::parse($lastHeartbeat)->diffInMinutes(now()) < 5;
        $currentVideoId = Cache::get('demome:current_video_id');
        $currentVideo = $currentVideoId ? RenderedVideo::find($currentVideoId) : null;

        return [
            'apiToken' => config('services.demome.api_token') ?: 'Not set',
            'isPaused' => SiteSetting::getBool('demome:paused', false),
            'isAutoQueuePaused' => SiteSetting::getBool('demome:auto_queue_paused', false),
            'isOnline' => $isOnline,
            'currentStatus' => Cache::get('demome:current_status', 'unknown'),
            'lastHeartbeat' => $lastHeartbeat ? Carbon::parse($lastHeartbeat)->diffForHumans() : 'Never',
            'currentVideo' => $currentVideo,
            'stats' => Cache::remember('demome:control_stats', 300, function () {
                return [
                    'pending' => RenderedVideo::where('status', 'pending')->count(),
                    'rendering' => RenderedVideo::where('status', 'rendering')->count(),
                    'completed_total' => RenderedVideo::where('status', 'completed')->count(),
                    'completed_today' => RenderedVideo::where('status', 'completed')
                        ->whereDate('updated_at', today())->count(),
                    'failed' => RenderedVideo::where('status', 'failed')->count(),
                    'total_render_hours' => round(
                        RenderedVideo::where('status', 'completed')->sum('render_duration_seconds') / 3600, 1
                    ),
                ];
            }),
            // Filament re-renders the whole page on every wire:click action,
            // and the rendered_videos table is several hundred thousand rows
            // on prod - every uncached COUNT/SELECT here piled up to a
            // multi-minute Livewire round-trip + Cloudflare 504s on 2026-05-19.
            // Cache the global aggregates with a short TTL and invalidate from
            // the handful of action methods that actually mutate this data.
            'queue_by_priority' => Cache::remember('demome:control_queue_by_priority', 60, function () {
                return [
                    'wr' => RenderedVideo::where('status', 'pending')->where('priority', 1)->count(),
                    'verified' => RenderedVideo::where('status', 'pending')->where('priority', 2)->count(),
                    'normal' => RenderedVideo::where('status', 'pending')->where('priority', 3)->count(),
                ];
            }),
            'unlisted_count' => Cache::remember('demome:control_unlisted_count', 60, function () {
                return RenderedVideo::where('status', 'completed')
                    ->where('source', 'auto')
                    ->where('publish_approved', false)
                    ->whereNull('published_at')
                    ->whereNotNull('youtube_video_id')
                    ->count();
            }),
            'unlisted_tier_counts' => Cache::remember('demome:control_unlisted_tier_counts', 60, fn () => $this->getUnlistedTierCounts()),
            // Working out what belongs in thirty-odd playlists means one pass
            // over every published video, so it is cached: the numbers only
            // move when something is published, and a few minutes stale costs
            // nothing here.
            'playlist_buttons' => Cache::remember('demome:control_playlist_buttons', 600, fn () => $this->getPlaylistButtons()),
            // Cache the page list per (tier, page) so changing tier or paging
            // doesn't pay the full filter+sort cost every click.
            'unlisted_videos' => Cache::remember(
                "demome:control_unlisted_videos:{$this->unlistedTier}:{$this->unlistedPage}:{$this->unlistedPerPage}",
                30,
                fn () => $this->getUnlistedVideosPaginated()
            ),
            'unlisted_total_pages' => Cache::remember(
                "demome:control_unlisted_total_pages:{$this->unlistedTier}:{$this->unlistedPerPage}",
                60,
                fn () => $this->getUnlistedTotalPages()
            ),
            'publishing_count' => Cache::remember('demome:control_publishing_count', 60, function () {
                return RenderedVideo::where('status', 'completed')
                    ->where('publish_approved', true)
                    ->whereNull('published_at')
                    ->count();
            }),
            'manual_bulk_history' => $this->getManualBulkHistory(),
            'bulk_tier_buttons' => Cache::remember('demome:control_bulk_tier_buttons', 60, fn () => $this->getBulkTierButtonData()),
            'backlog' => $this->getBacklogStats(),
            'discordRestartMarker' => SiteSetting::get('demome:discord_restart_from_message_id') ?: null,
            'discordReprocessMarker' => SiteSetting::get('demome:discord_reprocess_single_message_id') ?: null,
        ];
    }

    private function getBulkTierButtonData(): array
    {
        $rows = $this->unlistedBaseQuery()
            ->selectRaw('COALESCE(quality_tier, 0) as tier, COUNT(*) as cnt')
            ->groupByRaw('COALESCE(quality_tier, 0)')
            ->pluck('cnt', 'tier');

        $total = 0;
        $buttons = [];
        foreach ($rows as $tier => $cnt) {
            $total += (int) $cnt;
            $label = \App\Services\RenderQueueService::TIER_LABELS[(int) $tier] ?? 'Unclassified';
            $buttons[] = [
                'tier' => (int) $tier,
                'label' => $label,
                'count' => (int) $cnt,
            ];
        }

        // Sort by tier number so button order matches TIER_LABELS definition
        usort($buttons, fn($a, $b) => $a['tier'] <=> $b['tier']);

        array_unshift($buttons, [
            'tier' => -1, // -1 = ALL sentinel
            'label' => 'All',
            'count' => $total,
        ]);

        return $buttons;
    }

    private function getManualBulkHistory(): array
    {
        $raw = SiteSetting::get('demome:manual_bulk_history');
        if (!$raw) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_slice($decoded, 0, 3);
    }

    private function recordManualBulk(string $label, int $count): void
    {
        $history = $this->getManualBulkHistory();
        array_unshift($history, [
            'ts' => now()->toIso8601String(),
            'label' => $label,
            'count' => $count,
        ]);
        $history = array_slice($history, 0, 3);
        SiteSetting::set('demome:manual_bulk_history', json_encode($history));
    }

    private function unlistedBaseQuery()
    {
        return RenderedVideo::where('status', 'completed')
            ->where('source', 'auto')
            ->where('publish_approved', false)
            ->whereNull('published_at')
            ->whereNotNull('youtube_video_id');
    }

    private function getUnlistedTierCounts(): array
    {
        $rows = $this->unlistedBaseQuery()
            ->selectRaw('COALESCE(quality_tier, 0) as tier, COUNT(*) as cnt')
            ->groupByRaw('COALESCE(quality_tier, 0)')
            ->pluck('cnt', 'tier');

        $counts = [];
        foreach ($rows as $tier => $cnt) {
            $counts[(int) $tier] = (int) $cnt;
        }
        return $counts;
    }

    private function getUnlistedVideosPaginated()
    {
        $query = $this->unlistedBaseQuery();

        if ($this->unlistedTier !== null) {
            if ($this->unlistedTier === 0) {
                $query->where(function ($q) {
                    $q->whereNull('quality_tier')->orWhere('quality_tier', 0);
                });
            } else {
                $query->where('quality_tier', $this->unlistedTier);
            }
        }

        return $query->orderBy('created_at', 'desc')
            ->offset(($this->unlistedPage - 1) * $this->unlistedPerPage)
            ->limit($this->unlistedPerPage)
            ->get();
    }

    private function getUnlistedTotalPages(): int
    {
        $query = $this->unlistedBaseQuery();

        if ($this->unlistedTier !== null) {
            if ($this->unlistedTier === 0) {
                $query->where(function ($q) {
                    $q->whereNull('quality_tier')->orWhere('quality_tier', 0);
                });
            } else {
                $query->where('quality_tier', $this->unlistedTier);
            }
        }

        return (int) ceil($query->count() / $this->unlistedPerPage);
    }

    private function getBacklogStats(): array
    {
        return Cache::remember('demome:backlog_stats', 1800, function () {
        $poolCounts = \App\Services\RenderQueueService::getPoolCounts();

        $wrRemaining = $poolCounts[\App\Services\RenderQueueService::TIER_ONLINE_WR]['available'] ?? 0;
        $onlineOther = ($poolCounts[\App\Services\RenderQueueService::TIER_ONLINE_TOP10]['available'] ?? 0)
            + ($poolCounts[\App\Services\RenderQueueService::TIER_ONLINE_RANK11]['available'] ?? 0);
        $offlineRemaining = ($poolCounts[\App\Services\RenderQueueService::TIER_OFFLINE_FASTER_WR]['available'] ?? 0)
            + ($poolCounts[\App\Services\RenderQueueService::TIER_OFFLINE_WITHIN_10]['available'] ?? 0)
            + ($poolCounts[\App\Services\RenderQueueService::TIER_OFFLINE_WITHIN_50]['available'] ?? 0);
        $longerRemaining = ($poolCounts[\App\Services\RenderQueueService::TIER_LONGER]['available'] ?? 0)
            + ($poolCounts[\App\Services\RenderQueueService::TIER_VERY_LONG]['available'] ?? 0);

        $todayAuto = RenderedVideo::where('status', 'completed')
            ->where('source', 'auto')
            ->whereDate('updated_at', today())
            ->count();

        $todayGameplayMs = RenderedVideo::where('status', 'completed')
            ->where('source', 'auto')
            ->whereDate('updated_at', today())
            ->sum('time_ms');

        $todayRenderSec = RenderedVideo::where('status', 'completed')
            ->where('source', 'auto')
            ->whereDate('updated_at', today())
            ->sum('render_duration_seconds');

        return [
            'wr_remaining' => $wrRemaining,
            'wr_gameplay_hours' => 0,
            'non_wr_remaining' => $onlineOther,
            'offline_remaining' => $offlineRemaining,
            'longer_remaining' => $longerRemaining,
            'total_remaining' => $wrRemaining + $onlineOther + $offlineRemaining + $longerRemaining,
            'today_auto' => $todayAuto,
            'today_gameplay_hours' => round($todayGameplayMs / 3600000, 1),
            'today_render_hours' => round($todayRenderSec / 3600, 1),
        ];
        });
    }

    /**
     * Drop every cache key the view layer relies on for rendered_videos
     * aggregates. Call this from any action that mutates publish_approved,
     * status, or other columns that feed the counts on screen - without it
     * the new state is invisible until the per-key TTL elapses, which makes
     * the panel look broken even though the change went through.
     */
    /**
     * Every playlist the channel can have, with how many videos it should hold
     * and where it stands.
     *
     * `planned` is what the definitions say belongs in it right now. `synced`
     * is what YouTube held when the bot last finished with it. The two coming
     * apart is the whole point of the page: that difference is the work.
     */
    private function getPlaylistButtons(): array
    {
        $service = new \App\Services\YoutubePlaylistService();
        $definitions = $service->definitions();

        $counts = array_map('count', $service->compute(array_keys($definitions)));
        $state = \App\Models\YoutubePlaylist::pluck('sync_queued', 'key');
        $synced = \App\Models\YoutubePlaylist::pluck('synced_count', 'key');
        $created = \App\Models\YoutubePlaylist::pluck('youtube_playlist_id', 'key');

        $out = [];

        foreach ($definitions as $key => $definition) {
            $out[] = [
                'key' => $key,
                'group' => $definition['group'],
                // The channel-wide prefix is on every one of them and says
                // nothing here. The shelf's own name is what has to be read.
                'label' => str_replace('Quake 3 Defrag - ', '', $definition['title']),
                'planned' => $counts[$key] ?? 0,
                'synced' => (int) ($synced[$key] ?? 0),
                'queued' => (bool) ($state[$key] ?? false),
                'exists' => (bool) ($created[$key] ?? false),
            ];
        }

        return $out;
    }

    /**
     * Work out one playlist's contents and put it in the queue the bot reads.
     */
    public function queuePlaylist(string $key): void
    {
        $service = new \App\Services\YoutubePlaylistService();

        if (! isset($service->definitions()[$key])) {
            Notification::make()->title('No such playlist')->danger()->send();

            return;
        }

        $counts = $service->snapshot([$key]);
        $this->flushDemomeViewCache();

        Notification::make()
            ->title('Playlist Queued')
            ->body(($counts[$key] ?? 0) . ' videos worked out. Run playlists_sync.py on the demome box.')
            ->success()
            ->send();
    }

    /**
     * Queue every playlist whose contents are not already what they should be.
     * A shelf that is already right is left alone: queueing it would make the
     * bot read it from YouTube for nothing.
     */
    public function queueAllPlaylists(): void
    {
        $service = new \App\Services\YoutubePlaylistService();
        $counts = array_map('count', $service->compute(array_keys($service->definitions())));

        $synced = \App\Models\YoutubePlaylist::pluck('synced_count', 'key');

        $keys = [];

        foreach ($counts as $key => $count) {
            if ($count > 0 && $count !== (int) ($synced[$key] ?? -1)) {
                $keys[] = $key;
            }
        }

        if (! $keys) {
            Notification::make()->title('Nothing to queue')->body('Every playlist already holds what it should.')->warning()->send();

            return;
        }

        $done = $service->snapshot($keys);
        $this->flushDemomeViewCache();

        Notification::make()
            ->title(count($keys) . ' Playlists Queued')
            ->body(array_sum($done) . ' video slots in total. Run playlists_sync.py on the demome box.')
            ->success()
            ->send();
    }

    /**
     * Empty the queue without touching YouTube. For a change of mind before
     * the bot has been run.
     */
    public function clearPlaylistQueue(): void
    {
        $cleared = \App\Models\YoutubePlaylist::where('sync_queued', true)->update(['sync_queued' => false]);
        $this->flushDemomeViewCache();

        Notification::make()->title("Queue cleared ({$cleared} playlists)")->success()->send();
    }

    /**
     * Work the planned counts out again, on demand.
     *
     * They are kept for ten minutes because working them out walks every
     * rendered video against all 59 definitions, which is not a price to pay
     * on an ordinary page load. But the counts also depend on the blocked map
     * list, and that is changed on another page, so after blocking a map this
     * panel shows the numbers from before and reloading does not help. This is
     * the way to ask for them again.
     */
    public function refreshPlaylistCounts(): void
    {
        Cache::forget('demome:control_playlist_buttons');

        Notification::make()
            ->title('Playlist counts worked out again')
            ->body('They now take the blocked map list as it stands.')
            ->success()
            ->send();
    }

    private function flushDemomeViewCache(): void
    {
        Cache::forget('demome:control_stats');
        Cache::forget('demome:control_queue_by_priority');
        Cache::forget('demome:control_unlisted_count');
        Cache::forget('demome:control_unlisted_tier_counts');
        Cache::forget('demome:control_publishing_count');
        Cache::forget('demome:control_bulk_tier_buttons');
        Cache::forget('demome:control_playlist_buttons');
        // unlisted_videos / unlisted_total_pages are keyed by tier+page; we
        // can't enumerate cheaply, so we let them expire on their short TTL
        // (30s/60s). The list view is the least state-sensitive piece - a
        // 30s lag on tier/page transitions is acceptable.
    }

    public function togglePause(): void
    {
        $currentState = SiteSetting::getBool('demome:paused', false);
        SiteSetting::set('demome:paused', !$currentState ? '1' : '0');

        Notification::make()
            ->title($currentState ? 'Rendering Unpaused' : 'Rendering Paused')
            ->body($currentState ? 'Demome will pick up renders on next poll.' : 'Demome will not pick up any videos until unpaused.')
            ->success()
            ->send();
    }

    public function toggleAutoQueue(): void
    {
        $currentState = SiteSetting::getBool('demome:auto_queue_paused', false);
        SiteSetting::set('demome:auto_queue_paused', !$currentState ? '1' : '0');

        Notification::make()
            ->title($currentState ? 'Auto-Queue Enabled' : 'Auto-Queue Disabled')
            ->body($currentState ? 'Verified records will be auto-added to render queue.' : 'Auto-queue stopped. User requests still work.')
            ->success()
            ->send();
    }

    public function populateQueue(): void
    {
        \Artisan::call('demome:populate-queue', ['--force' => true]);

        $this->flushDemomeViewCache();

        Notification::make()
            ->title('Queue Populated')
            ->body(\Artisan::output())
            ->success()
            ->send();
    }

    public function publishSingle(int $videoId): void
    {
        $video = RenderedVideo::find($videoId);
        if (!$video || $video->published_at || $video->publish_approved) {
            return;
        }

        $video->update(['publish_approved' => true]);

        $this->flushDemomeViewCache();

        Notification::make()
            ->title('Video Queued for Publishing')
            ->body("{$video->map_name} - {$video->player_name} will be published by demome on next cycle.")
            ->success()
            ->send();
    }

    public function publishUnlisted(): void
    {
        // Use tiered rotation - respects mix, no map duplicates, won't burn through WR
        $batch = \App\Services\RenderQueueService::getNextPublishBatch(12);

        if ($batch->isEmpty()) {
            Notification::make()
                ->title('No Videos to Publish')
                ->body('No unlisted videos matching the tier rotation are available.')
                ->warning()
                ->send();
            return;
        }

        $tierCounts = [];
        foreach ($batch as $video) {
            $video->update(['publish_approved' => true]);
            $tierLabel = \App\Services\RenderQueueService::TIER_LABELS[$video->quality_tier] ?? 'Unknown';
            $tierCounts[$tierLabel] = ($tierCounts[$tierLabel] ?? 0) + 1;
        }

        $breakdown = collect($tierCounts)->map(fn($c, $l) => "{$c}x {$l}")->join(', ');

        $this->recordManualBulk("Mix 12 ({$breakdown})", $batch->count());

        $this->flushDemomeViewCache();

        Notification::make()
            ->title("Publish Queued: {$batch->count()} videos")
            ->body("Mix: {$breakdown}")
            ->success()
            ->send();
    }

    /**
     * Bulk-approve every unlisted auto-rendered video matching a specific quality tier.
     * tier === -1 means "all tiers" (everything unlisted). This bypasses the rotation
     * mix and is intended for manual curation - admin picks which bucket to drain.
     */
    public function bulkPublishTier(int $tier): void
    {
        $query = $this->unlistedBaseQuery();

        if ($tier === 0) {
            $query->where(function ($q) {
                $q->whereNull('quality_tier')->orWhere('quality_tier', 0);
            });
            $label = 'Unclassified';
        } elseif ($tier === -1) {
            $label = 'All';
        } else {
            $query->where('quality_tier', $tier);
            $label = \App\Services\RenderQueueService::TIER_LABELS[$tier] ?? "Tier {$tier}";
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            Notification::make()
                ->title('Nothing to Publish')
                ->body("No unlisted videos in category: {$label}")
                ->warning()
                ->send();
            return;
        }

        // Mark every matching video for publishing. Python bot will pick these up via
        // get_videos_to_publish() on its next run and transition them unlisted -> public.
        $query->update(['publish_approved' => true]);

        $this->recordManualBulk($label, $count);

        // Invalidate every view cache key so the UI immediately reflects the change.
        $this->flushDemomeViewCache();

        Notification::make()
            ->title("Bulk Publish Queued: {$count} videos")
            ->body("Category: {$label}. Python bot will publish them on next cycle.")
            ->success()
            ->send();
    }

    public function setDiscordRestartMarker(): void
    {
        $id = trim((string) $this->discordRestartMessageId);

        if ($id === '') {
            SiteSetting::set('demome:discord_restart_from_message_id', '');
            Notification::make()
                ->title('Discord Restart Marker Cleared')
                ->body('Demome will continue normally from the last scraped message.')
                ->success()
                ->send();
            return;
        }

        if (!preg_match('/^\d{10,25}$/', $id)) {
            Notification::make()
                ->title('Invalid Message ID')
                ->body('Discord snowflake IDs are numeric (10-25 digits).')
                ->danger()
                ->send();
            return;
        }

        SiteSetting::set('demome:discord_restart_from_message_id', $id);

        Notification::make()
            ->title('Discord Restart Marker Set')
            ->body("On next demome startup it will rescan Discord from message ID {$id} onwards.")
            ->success()
            ->send();
    }

    public function clearDiscordRestartMarker(): void
    {
        SiteSetting::set('demome:discord_restart_from_message_id', '');
        $this->discordRestartMessageId = null;

        Notification::make()
            ->title('Discord Restart Marker Cleared')
            ->success()
            ->send();
    }

    public function setDiscordReprocessMarker(): void
    {
        $id = trim((string) $this->discordReprocessMessageId);

        if ($id === '') {
            SiteSetting::set('demome:discord_reprocess_single_message_id', '');
            Notification::make()
                ->title('Single-Message Reprocess Marker Cleared')
                ->success()
                ->send();
            return;
        }

        if (!preg_match('/^\d{10,25}$/', $id)) {
            Notification::make()
                ->title('Invalid Message ID')
                ->body('Discord snowflake IDs are numeric (10-25 digits).')
                ->danger()
                ->send();
            return;
        }

        SiteSetting::set('demome:discord_reprocess_single_message_id', $id);

        Notification::make()
            ->title('Single-Message Reprocess Marker Set')
            ->body("On next demome cycle it will fetch and reprocess exactly message ID {$id}.")
            ->success()
            ->send();
    }

    public function clearDiscordReprocessMarker(): void
    {
        SiteSetting::set('demome:discord_reprocess_single_message_id', '');
        $this->discordReprocessMessageId = null;

        Notification::make()
            ->title('Single-Message Reprocess Marker Cleared')
            ->success()
            ->send();
    }

    public function regenerateTokenAction(): Action
    {
        return Action::make('regenerateToken')
            ->label('Regenerate')
            ->color('warning')
            ->icon('heroicon-o-arrow-path')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Regenerate API Token')
            ->modalDescription('This will invalidate the current token. Demome will stop working until you update its settings.py with the new token. Type "yes" to confirm.')
            ->form([
                \Filament\Forms\Components\TextInput::make('confirmation')
                    ->label('Type "yes" to confirm')
                    ->required()
                    ->rule('in:yes'),
            ])
            ->action(fn () => $this->regenerateToken());
    }

    public function regenerateToken(): void
    {
        $newToken = Str::random(64);

        // Update .env file
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        if (str_contains($envContent, 'DEMOME_API_TOKEN=')) {
            $envContent = preg_replace('/DEMOME_API_TOKEN=.*/', "DEMOME_API_TOKEN={$newToken}", $envContent);
        } else {
            $envContent .= "\nDEMOME_API_TOKEN={$newToken}\n";
        }

        file_put_contents($envPath, $envContent);

        // Update runtime config (Octane keeps config in memory)
        config()->set('services.demome.api_token', $newToken);

        // Clear config cache for next worker restart
        \Artisan::call('config:clear');

        Notification::make()
            ->title('API Token Regenerated')
            ->body("New token: {$newToken} - Update this in demome's settings.py and restart demome.")
            ->warning()
            ->persistent()
            ->send();
    }
}
