<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

use App\Rules\MddProfile;
use App\Models\User;
use App\Models\Record;
use App\Models\RecordHistory;
use App\External\ImageGenerator;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SettingsController extends Controller
{
    public function linkAccount(Request $request) {
        $user = $request->user();

        if ($user->mdd_id && ctype_digit($user->mdd_id)) {
            return redirect()->route('profile.index', ['userId' => $user->id]);
        }

        return Inertia::render('LinkAccount', [
            'user' => $user,
        ]);
    }

    public function socialmedia(Request $request) {
        $user = $request->user();

        if ($request->has('twitter_name')) {
            $user->twitter_name = $request->twitter_name;
        }

        if ($request->has('twitch_name')) {
            $user->twitch_name = $request->twitch_name;
        }

        if ($request->has('discord_name')) {
            $user->discord_name = $request->discord_name;
        }

        $user->save();
    }

    public function notifications(Request $request) {
        $request->validate([
            'records_vq3'       =>      ['required', 'string', 'in:all,wr'],
            'records_cpm'       =>      ['required', 'string', 'in:all,wr'],
            'preview_records'   =>      ['required', 'string', 'in:all,wr,none'],
            'preview_system'    =>      ['required', 'array'],
            'preview_system.*'  =>      ['string', 'in:announcement,clan,tournament,render,map']
        ]);

        $user = $request->user();

        $tournament_news = $request->input('tournament_news', false);
        $map_news = $request->input('map_news', false);
        $clan_notifications = $request->input('clan_notifications', false);

        // `defrag_news` is deliberately not read here any more. Announcements
        // reach everybody, so the form no longer offers the switch - and a
        // missing checkbox posts nothing, which would have written 0 for every
        // account that saved this form.
        $user->tournament_news = $tournament_news;
        $user->map_news = $map_news;
        $user->clan_notifications = $clan_notifications;

        $user->records_vq3 = $request->records_vq3;
        $user->records_cpm = $request->records_cpm;

        // Ensure announcement is always in preview_system
        $preview_system = $request->preview_system;
        if (!in_array('announcement', $preview_system)) {
            $preview_system[] = 'announcement';
        }
        $user->preview_system = $preview_system;
        $user->preview_records = $request->preview_records;

        $user->save();
    }

    public function preferences(Request $request) {
        $user = $request->user();

        if ($request->has('color')) {
            if (! preg_match('/^#[a-fA-F0-9]{6}$/', $request->color)) {
                return;
            }
            $user->color = $request->color;
        }

        if ($request->has('avatar_effect')) {
            $user->avatar_effect = $request->avatar_effect;
        }

        if ($request->has('name_effect')) {
            $user->name_effect = $request->name_effect;
        }

        if ($request->has('avatar_border_color')) {
            if (! preg_match('/^#[a-fA-F0-9]{6}$/', $request->avatar_border_color)) {
                return;
            }
            $user->avatar_border_color = $request->avatar_border_color;
        }

        $user->save();
    }

    public function generate (Request $request) {
        $request->validate([
            'profile_link' => ['required', 'string', 'url:http,https', new MddProfile]
        ]);

        $validation = $this->validate_link($request->user(), $request->profile_link);

        if ($validation !== NULL) {
            return [
                'success'   =>  false,
                'message'   =>  $validation
            ];
        }

        $id = $this->get_profile_id($request->profile_link);

        $generator = new ImageGenerator();

        return [
            'success'       =>      true,
            'image'         =>      $generator->generate($id),
            'name'          =>      $generator->get_name($id)
        ];
    }

    public function get_profile_id($profile_link) {
        $queryString = parse_url($profile_link, PHP_URL_QUERY);
        parse_str($queryString, $params);

        if (! isset($params['id']) || ! ctype_digit($params['id'])) {
            return -1;
        }

        $id = $params['id'];

        return $id;
    }

    public function validate_link($user, $profile_link) {
        if (ctype_digit($user->mdd_id)) {
            return 'Your account is already linked to ' . $user->mdd_id . ' MDD Profile.';
        }
        
        $id = $this->get_profile_id($profile_link);

        if ($id === -1) {
            return 'The link doesn\'t have a valid id.';
        }

        $user = User::where('mdd_id', $id)->first();

        if ($user) {
            return 'There is another user who linked his account to this mDd Profile.';
        }

        $client = new Client();

        $found = false;

        try {
            $response = $client->head($profile_link, ['allow_redirects' => true, 'verify' => false]);
            $statusCode = $response->getStatusCode();

            $found = $statusCode === 200;
        } catch (RequestException $e) {
            $found = false;
        }

        if (! $found) {
            return 'This profile doesnt Exist on Q3DF, are you sure you posted the correct link ?';
        }

        return null;
    }

    public function verify(Request $request) {
        $request->validate([
            'profile_link' => ['required', 'string', 'url:http,https', new MddProfile]
        ]);

        $validation = $this->validate_link($request->user(), $request->profile_link);

        if ($validation !== NULL) {
            return [
                'success'   =>  false,
                'message'   =>  $validation
            ];
        }

        $id = $this->get_profile_id($request->profile_link);

        $generator = new ImageGenerator();
        $verification = $generator->verify($id);
        
        if (! $verification) {
            return [
                'success'       =>      false
            ];
        }

        $request->user()->update([
            'mdd_id'    =>  $id
        ]);

        // Ensure mdd_profiles record exists, link user_id
        $mddProfile = \App\Models\MddProfile::find($id);
        if ($mddProfile) {
            $mddProfile->update(['user_id' => $request->user()->id]);
        } else {
            \App\Jobs\ScrapeProfile::dispatch($id);
        }

        Record::where('mdd_id', $id)->update([
            'user_id'   =>  $request->user()->id
        ]);

        RecordHistory::where('mdd_id', $id)->update([
            'user_id'   =>  $request->user()->id
        ]);

        // Auto-create marketplace creator profile with is_listed=true
        \App\Models\MarketplaceCreatorProfile::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['is_listed' => true, 'accepting_commissions' => true, 'specialties' => []]
        );

        return [
            'success'   =>      true,
            'mdd_id'    =>      $id
        ];
    }

    public function background(Request $request) {
        $request->validate([
            'background' => ['required', 'image', 'max:10240'] // Max 10MB
        ]);

        $user = $request->user();

        // Delete old background if exists
        if ($user->profile_background_path) {
            Storage::disk('public')->delete($user->profile_background_path);
        }

        // Store new background
        $path = $request->file('background')->store('profile-backgrounds', 'public');
        $user->profile_background_path = $path;
        $user->save();
    }

    public function mapViewPreferences(Request $request) {
        $user = $request->user();

        $user->default_show_oldtop = $request->boolean('default_show_oldtop');
        $user->default_show_offline = $request->boolean('default_show_offline');
        $user->save();
    }

    public function physicsOrderPreferences(Request $request) {
        $user = $request->user();

        $order = $request->input('default_physics_order', 'vq3_first');
        $user->default_physics_order = in_array($order, ['vq3_first', 'cpm_first']) ? $order : 'vq3_first';
        $user->save();
    }

    public function profileLayout(Request $request) {
        $request->validate([
            'stat_boxes' => ['required', 'array', 'min:1', 'max:7'],
            'stat_boxes.*' => ['string', 'in:performance,activity,record_types,map_features,demos_statistics,top_downloaded_demos,renders'],
            'sections' => ['required', 'array'],
            'sections.*.id' => ['required', 'string', 'in:activity_history,records,rendered_videos,freestyle_demos,similar_skill_rivals,competitor_comparison,known_aliases,featured_maplists,map_completionist'],
            'sections.*.visible' => ['required', 'boolean'],
            'header_items' => ['sometimes', 'array'],
            'header_items.*.id' => ['required', 'string', 'in:badge_admin,badge_moderator,badge_donor,badge_community,badge_tagger,badge_assigner,clan,wr_counters,socials,player_rank,community_rank'],
            'header_items.*.visible' => ['required', 'boolean'],
            'header_items.*.row' => ['required', 'integer', 'in:1,2,3'],
        ]);

        $user = $request->user();
        $layout = [
            'stat_boxes' => $request->stat_boxes,
            'sections' => $request->sections,
        ];
        if ($request->has('header_items')) {
            $layout['header_items'] = $request->header_items;
        } else {
            $layout['header_items'] = $user->profile_layout['header_items'] ?? null;
        }
        $user->profile_layout = $layout;
        $user->save();

        return back();
    }

    public function globalProfilePreferences(Request $request) {
        $request->validate([
            'hidden_sections' => ['present', 'array'],
            'hidden_sections.*' => ['string', 'in:activity_history,records,rendered_videos,freestyle_demos,similar_skill_rivals,competitor_comparison,known_aliases,featured_maplists,map_completionist'],
            'hidden_stat_boxes' => ['present', 'array'],
            'hidden_stat_boxes.*' => ['string', 'in:performance,activity,record_types,map_features,renders'],
            'date_format' => ['sometimes', 'string', 'in:ymd,dmy,Ymd,dmY'],
            'time_format' => ['sometimes', 'string', 'in:colon,dot'],
        ]);

        $user = $request->user();
        $user->global_profile_preferences = [
            'hidden_sections' => $request->hidden_sections,
            'hidden_stat_boxes' => $request->hidden_stat_boxes,
            'date_format' => $request->date_format ?? $user->global_profile_preferences['date_format'] ?? 'dmY',
            // Default is what the engine's own timer prints, so nobody who
            // never opens this page sees their times change.
            'time_format' => $request->time_format ?? $user->global_profile_preferences['time_format'] ?? 'dot',
        ];
        $user->save();

        return back();
    }

    public function mapperClaims(Request $request)
    {
        $request->validate([
            'claims' => ['present', 'array', 'max:20'],
            'claims.*.name' => ['required', 'string', 'max:255'],
            'claims.*.type' => ['required', 'string', 'in:map,model'],
        ]);

        $user = $request->user();

        // Sync claims: delete removed, add new
        $newClaims = collect($request->claims ?? [])->map(fn ($c) => [
            'name' => trim($c['name']),
            'type' => $c['type'],
        ])->filter(fn ($c) => !empty($c['name']))->unique(fn ($c) => $c['name'] . '|' . $c['type']);

        // Check if any claim is already taken by another user
        foreach ($newClaims as $claim) {
            $existing = \App\Models\MapperClaim::where('name', $claim['name'])
                ->where('type', $claim['type'])
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($existing) {
                return response()->json([
                    'error' => "The name \"{$claim['name']}\" is already claimed by another user."
                ], 422);
            }
        }

        // Sync claims preserving IDs (to keep exclusions intact)
        $existingClaims = $user->mapperClaims()->get()->keyBy(fn($c) => $c->name . '|' . $c->type);
        $newKeys = $newClaims->map(fn($c) => $c['name'] . '|' . $c['type']);

        // Delete claims not in new list
        $keepIds = $existingClaims->filter(fn($c, $key) => $newKeys->contains($key))->pluck('id');
        $user->mapperClaims()->whereNotIn('id', $keepIds)->delete();

        // Create only genuinely new claims
        foreach ($newClaims as $claim) {
            $key = $claim['name'] . '|' . $claim['type'];
            if (!$existingClaims->has($key)) {
                $user->mapperClaims()->create($claim);
            }
        }

        // Clear cache
        $userId = $user->id;
        \Illuminate\Support\Facades\Cache::forget("mapper_stats_{$userId}");
        \Illuminate\Support\Facades\Cache::forget("mapper_top_players_{$userId}");
        \Illuminate\Support\Facades\Cache::forget("mapper_heatmap_{$userId}_v2");
        \Illuminate\Support\Facades\Cache::forget("mapper_highlighted_{$userId}_v2");

        return back();
    }

    public function getMapperClaims(Request $request)
    {
        $claims = $request->user()->mapperClaims()->withCount('exclusions')->get(['id', 'name', 'type']);

        // Enrich each claim with matching count + sample names
        foreach ($claims as $claim) {
            if ($claim->type === 'model') {
                $query = \App\Models\PlayerModel::where('author', 'LIKE', '%' . $claim->name . '%')
                    ->where('approval_status', 'approved');
                $claim->matching_count = $query->count();
                $claim->matching_samples = $query->orderByDesc('created_at')
                    ->limit(5)
                    ->pluck('name')
                    ->toArray();
            } else {
                $query = \App\Models\Map::where('visible', true)
                    ->where('author', 'REGEXP', \App\Models\MapperClaim::authorRegexp($claim->name));
                $claim->matching_count = $query->count();
                $claim->matching_samples = $query->orderByDesc('date_added')
                    ->limit(5)
                    ->pluck('name')
                    ->toArray();
            }
        }

        return $claims;
    }

    public function previewMapperClaim(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:map,model'],
        ]);

        $name = trim($request->name);

        // Check if someone else already claimed this name
        $existingClaim = \App\Models\MapperClaim::where('name', $name)
            ->where('type', $request->type)
            ->where('user_id', '!=', $request->user()->id)
            ->with('user:id,name')
            ->first();

        $claimed_by = null;
        if ($existingClaim) {
            $claimed_by = [
                'claim_id' => $existingClaim->id,
                'user_id' => $existingClaim->user_id,
                'user_name' => $existingClaim->user->name,
            ];
        }

        if ($request->type === 'map') {
            $query = \App\Models\Map::where('visible', true)
                ->where('author', 'REGEXP', \App\Models\MapperClaim::authorRegexp($name));

            return [
                'count' => $query->count(),
                'maps' => $query->orderByDesc('date_added')
                    ->limit(8)
                    ->get(['name', 'author', 'thumbnail', 'physics', 'gametype', 'date_added']),
                'claimed_by' => $claimed_by,
            ];
        }

        if ($request->type === 'model') {
            $query = \App\Models\PlayerModel::where('author', 'LIKE', '%' . $name . '%')
                ->where('approval_status', 'approved');

            return [
                'count' => $query->count(),
                'models' => $query->orderByDesc('created_at')
                    ->limit(8)
                    ->get(['name', 'author', 'thumbnail', 'base_model']),
                'claimed_by' => $claimed_by,
            ];
        }

        return ['count' => 0];
    }

    public function getClaimMaps(Request $request, $claimId)
    {
        $claim = \App\Models\MapperClaim::where('id', $claimId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $query = \App\Models\Map::where('visible', true)
            ->where('author', 'REGEXP', \App\Models\MapperClaim::authorRegexp($claim->name));

        if ($request->search) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $excludedIds = $claim->excludedMapIds();

        $maps = $query->orderByDesc('date_added')
            ->get(['id', 'name', 'author', 'thumbnail', 'date_added']);

        $maps->each(function ($map) use ($excludedIds) {
            $map->excluded = in_array($map->id, $excludedIds);
        });

        return [
            'maps' => $maps,
            'total' => $maps->count(),
            'excluded_count' => count($excludedIds),
        ];
    }

    public function toggleClaimExclusion(Request $request, $claimId)
    {
        $request->validate([
            'map_id' => ['required', 'integer'],
        ]);

        $claim = \App\Models\MapperClaim::where('id', $claimId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $exclusion = $claim->exclusions()->where('map_id', $request->map_id)->first();

        if ($exclusion) {
            $exclusion->delete();
            $excluded = false;
        } else {
            $claim->exclusions()->create(['map_id' => $request->map_id]);
            $excluded = true;
        }

        // Clear cache
        $userId = $request->user()->id;
        \Illuminate\Support\Facades\Cache::forget("mapper_stats_{$userId}");
        \Illuminate\Support\Facades\Cache::forget("mapper_top_players_{$userId}");
        \Illuminate\Support\Facades\Cache::forget("mapper_heatmap_{$userId}_v2");
        \Illuminate\Support\Facades\Cache::forget("mapper_highlighted_{$userId}_v2");

        return ['excluded' => $excluded];
    }

    public function reportMapperClaim(Request $request)
    {
        $request->validate([
            'mapper_claim_id' => ['required', 'integer', 'exists:mapper_claims,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $claim = \App\Models\MapperClaim::findOrFail($request->mapper_claim_id);

        if ($claim->user_id === $request->user()->id) {
            return response()->json(['error' => 'You cannot report your own claim.'], 422);
        }

        $existing = \App\Models\MapperClaimReport::where('reporter_id', $request->user()->id)
            ->where('mapper_claim_id', $request->mapper_claim_id)
            ->first();

        if ($existing) {
            return response()->json(['error' => 'You have already reported this claim.'], 422);
        }

        \App\Models\MapperClaimReport::create([
            'reporter_id' => $request->user()->id,
            'mapper_claim_id' => $request->mapper_claim_id,
            'reason' => $request->reason,
        ]);

        return response()->json(['success' => true]);
    }

    public function effectsIntensity(Request $request) {
        $request->validate([
            'avatar_effects_intensity' => ['required', 'integer', 'min:0', 'max:100'],
            'name_effects_intensity' => ['required', 'integer', 'min:0', 'max:100'],
            'avatar_effects_speed' => ['required', 'integer', 'min:0', 'max:100'],
            'name_effects_speed' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $user = $request->user();
        $user->avatar_effects_intensity = $request->avatar_effects_intensity;
        $user->name_effects_intensity = $request->name_effects_intensity;
        $user->avatar_effects_speed = $request->avatar_effects_speed;
        $user->name_effects_speed = $request->name_effects_speed;
        $user->save();
    }

    public function deleteBackground(Request $request) {
        $user = $request->user();

        if ($user->profile_background_path) {
            Storage::disk('public')->delete($user->profile_background_path);
            $user->profile_background_path = null;
            $user->save();
        }
    }

    public function widgetSettings(Request $request) {
        $user = $request->user();

        if (!$user->twitch_id) {
            abort(403, 'Twitch account required');
        }

        $settings = $request->validate([
            'bar_width' => 'nullable|integer|min:200|max:1200',
            'bar_height' => 'nullable|integer|min:20|max:120',
            'bar_border_color' => 'nullable|string|max:20',
            'bar_border_width' => 'nullable|integer|min:0|max:8',
            'bar_border_radius' => 'nullable|integer|min:0|max:60',
            'bar_bg_color' => 'nullable|string|max:20',
            'bar_fill_color' => 'nullable|string|max:20',
            'bar_fill_color2' => 'nullable|string|max:20',
            'bar_animation' => 'nullable|string|in:none,shimmer,pulse,stripes,gradient,wave',
            'bg_type' => 'nullable|string|in:transparent,chroma,custom',
            'bg_color' => 'nullable|string|max:20',
            'text_player_name' => 'nullable|array',
            'text_subtitle' => 'nullable|array',
            'text_count' => 'nullable|array',
            'text_label' => 'nullable|array',
            'text_percentage' => 'nullable|array',
            'text_remaining' => 'nullable|array',
            'text_position' => 'nullable|string|in:above,inside,below',
        ]);

        $user->widget_settings = $settings;
        $user->save();

        return back();
    }
}
