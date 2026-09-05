<?php

namespace App\Http\Controllers;

use App\Models\RenderedVideo;
use App\Models\UploadedDemo;
use App\Models\Record;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class RenderRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'record_id' => 'required|exists:records,id',
            'demo_id' => 'required|exists:uploaded_demos,id',
        ]);

        $user = $request->user();

        // Rate limit: 20 requests per day
        $cacheKey = "render_requests_user_{$user->id}_" . now()->format('Y-m-d');
        $todayCount = Cache::get($cacheKey, 0);

        if ($todayCount >= 20) {
            return response()->json([
                'error' => 'Daily render limit reached (20/day)',
                'remaining' => 0,
            ], 429);
        }

        $demo = UploadedDemo::findOrFail($validated['demo_id']);
        $record = Record::findOrFail($validated['record_id']);

        // Check if already rendered, in queue, or failed (admin handles re-render)
        $existing = RenderedVideo::where('demo_id', $demo->id)
            ->whereIn('status', ['pending', 'rendering', 'uploading', 'completed', 'failed'])
            ->first();

        if ($existing) {
            return response()->json([
                'error' => 'This demo is already rendered or in queue',
                'status' => $existing->status,
                'youtube_url' => $existing->youtube_url,
            ], 409);
        }

        // A map that plays itself is worth no render. Same rule the automatic
        // queue uses, so a person cannot ask for what the queue would refuse.
        if (\App\Services\JokeMaps::isJoke($demo->map_name ?? $record->mapname, $demo->physics ?? $record->physics)) {
            return response()->json([
                'error' => 'This map is not eligible for rendering: too many players share its record time.',
            ], 422);
        }

        // Demome downloads via its authenticated API endpoint
        $demoUrl = config('app.url') . "/api/demome/download-demo/{$demo->id}";

        $video = RenderedVideo::create([
            'map_name' => $demo->map_name ?? $record->mapname,
            'player_name' => $demo->player_name ?? $record->name,
            'physics' => $demo->physics ?? $record->physics,
            'time_ms' => $demo->time_ms ?? $record->time,
            'gametype' => $demo->gametype ?? $record->gametype,
            'record_id' => $record->id,
            'demo_id' => $demo->id,
            'user_id' => $user->id,
            'source' => 'web',
            'requested_by' => $user->name,
            'status' => 'pending',
            'priority' => 0,
            'demo_url' => $demoUrl,
            'demo_filename' => $demo->original_filename,
        ]);

        // Increment daily counter
        Cache::put($cacheKey, $todayCount + 1, now()->endOfDay());

        $queuePosition = RenderedVideo::where('status', 'pending')
            ->where('id', '<', $video->id)
            ->count() + 1;

        return response()->json([
            'success' => true,
            'id' => $video->id,
            'queue_position' => $queuePosition,
            'remaining_today' => 20 - ($todayCount + 1),
        ]);
    }

    public function reportFailed(Request $request, $id)
    {
        $video = RenderedVideo::findOrFail($id);

        if ($video->status !== 'failed') {
            return response()->json(['error' => 'This video is not in failed state.'], 400);
        }

        $user = $request->user();

        // Rate limit: 3 reports per day per user
        $cacheKey = "render_reports_user_{$user->id}_" . now()->format('Y-m-d');
        $todayCount = Cache::get($cacheKey, 0);

        if ($todayCount >= 3) {
            return response()->json(['error' => 'Daily report limit reached.'], 429);
        }

        $adminEmail = config('app.admin_email');
        $editUrl = config('app.url') . "/defraghq/rendered-videos/{$video->id}/edit";
        $mapUrl = config('app.url') . "/maps/{$video->map_name}";
        $message = $request->input('message', '');
        $source = $request->input('source', '-');
        $recordId = $request->input('record_id');

        $timeFormatted = $video->time_ms
            ? sprintf('%02d.%06.3f', floor($video->time_ms / 60000), fmod($video->time_ms / 1000, 60))
            : '-';

        $profileUrl = config('app.url') . "/profile/{$user->id}";

        Mail::raw(
            "Failed render report\n\n" .
            "--- Reporter ---\n" .
            "Name: {$user->plain_name}\n" .
            "Email: {$user->email}\n" .
            "Profile: {$profileUrl}\n" .
            ($message ? "Message: {$message}\n" : '') .
            "\n--- Video ---\n" .
            "Video ID: {$video->id}\n" .
            "Map: {$video->map_name}\n" .
            "Player: {$video->player_name}\n" .
            "Physics: {$video->physics}\n" .
            "Time: {$timeFormatted}\n" .
            "Source: {$source}\n" .
            "Date: " . ($video->created_at?->format('Y-m-d H:i') ?? '-') . "\n" .
            ($recordId ? "Record ID: {$recordId}\n" : '') .
            "Failure reason: {$video->failure_reason}\n\n" .
            "Map page: {$mapUrl}\n" .
            "Admin edit: {$editUrl}",
            function ($mail) use ($adminEmail, $video) {
                $mail->to($adminEmail)
                    ->subject("[DeFRaG] Failed render report: {$video->map_name}");
            }
        );

        Cache::put($cacheKey, $todayCount + 1, now()->endOfDay());

        return response()->json(['success' => true]);
    }
}
