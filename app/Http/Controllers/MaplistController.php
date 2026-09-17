<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Maplist;
use App\Models\MaplistMap;
use App\Models\MaplistLike;
use App\Models\MaplistFavorite;
use App\Models\Map;
use App\Models\Record;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MaplistController extends Controller
{
    /**
     * Display a listing of maplists (public, sorted by likes/favorites)
     */
    public function index(Request $request)
    {
        $sort = $request->get('sort', 'likes');
        $userId = $request->get('user');
        $view = $request->get('view', 'public');
        $search = $request->get('search');
        $authors = $request->get('authors');

        $query = Maplist::with(['user', 'maps'])->withCount(['maps', 'likes', 'favorites']);
        $playLater = null;
        $myMaplists = collect();
        $myFavoriteMaplists = collect();
        $myLikedMaplists = collect();

        // If viewing "likes", show maplists the user has liked
        if ($view === 'likes' && Auth::check()) {
            $likedMaplistIds = \DB::table('maplist_likes')
                ->where('user_id', Auth::id())
                ->pluck('maplist_id');

            $query->whereIn('id', $likedMaplistIds)
                  ->where('is_public', true)
                  ->where('is_play_later', false);
        }
        // If viewing "favorites", show maplists the user has favorited
        elseif ($view === 'favorites' && Auth::check()) {
            $favoriteMaplistIds = \DB::table('maplist_favorites')
                ->where('user_id', Auth::id())
                ->pluck('maplist_id');

            $query->whereIn('id', $favoriteMaplistIds)
                  ->where('is_public', true)
                  ->where('is_play_later', false);
        }
        // If viewing "mine", show user's own maplists
        elseif ($view === 'mine' && Auth::check()) {
            $query->where('user_id', Auth::id());

            // Get Play Later separately to show it first
            $playLater = Maplist::where('user_id', Auth::id())
                ->where('is_play_later', true)
                ->with(['user', 'maps'])
                ->first();

            // Exclude Play Later from main query since we'll prepend it
            $query->where('is_play_later', false);
        } elseif ($userId) {
            // If filtering by specific user
            $query->where('user_id', $userId);

            // Only show public maplists unless it's the current user viewing their own
            if (!Auth::check() || Auth::id() != $userId) {
                $query->where('is_public', true)
                      ->where('is_play_later', false);
            } else {
                // Get Play Later separately
                $playLater = Maplist::where('user_id', $userId)
                    ->where('is_play_later', true)
                    ->with(['user', 'maps'])
                    ->first();
                $query->where('is_play_later', false);
            }
        } else {
            // Public browse mode - hide Play Later maplists
            $query->where('is_public', true)
                  ->where('is_play_later', false);
        }

        // Search by map name or maplist name
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhereHas('maps', function ($mq) use ($search) {
                      $mq->where('maps.name', 'like', '%' . $search . '%');
                  });
            });
        }

        // Filter by authors (maplist creators)
        if ($authors) {
            $authorIds = explode(',', $authors);
            $query->whereHas('user', function ($q) use ($authorIds) {
                $q->whereIn('id', $authorIds);
            });
        }

        // Apply sorting
        if ($sort === 'favorites') {
            $query->orderBy('favorites_count', 'desc');
        } elseif ($sort === 'newest') {
            $query->orderBy('created_at', 'desc');
        } elseif ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sort === 'most_maps') {
            $query->orderBy('maps_count', 'desc');
        } elseif ($sort === 'least_maps') {
            $query->orderBy('maps_count', 'asc');
        } elseif ($sort === 'most_views') {
            $query->orderBy('views_count', 'desc');
        } else {
            $query->orderBy('likes_count', 'desc');
        }

        $maplists = $query->paginate(12);

        // Prepend Play Later to the collection if it exists
        if ($playLater) {
            $maplists->getCollection()->prepend($playLater);
        }

        // Add user interaction status if authenticated
        if (Auth::check()) {
            $maplists->getCollection()->transform(function ($maplist) {
                $maplist->is_liked = $maplist->isLikedBy(Auth::id());
                $maplist->is_favorited = $maplist->isFavoritedBy(Auth::id());
                return $maplist;
            });

            // Fetch user's own maplists (limited to 10, newest first) when viewing public
            if ($view === 'public') {
                $myMaplists = Maplist::where('user_id', Auth::id())
                    ->where('is_play_later', false)
                    ->with(['maps', 'user'])
                    ->withCount(['maps', 'likes', 'favorites'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                // Fetch user's favorited maplists (limited to 10)
                $favoriteMaplistIds = \DB::table('maplist_favorites')
                    ->where('user_id', Auth::id())
                    ->pluck('maplist_id');

                $myFavoriteMaplists = Maplist::whereIn('id', $favoriteMaplistIds)
                    ->where('is_public', true)
                    ->where('is_play_later', false)
                    ->with(['maps', 'user'])
                    ->withCount(['maps', 'likes', 'favorites'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();

                // Fetch or create user's Play Later maplist
                $playLater = Maplist::firstOrCreate(
                    [
                        'user_id' => Auth::id(),
                        'is_play_later' => true
                    ],
                    [
                        'name' => 'Play Later',
                        'description' => 'Maps you want to play later',
                        'is_public' => false
                    ]
                );
                $playLater->load(['maps', 'user']);
                $playLater->loadCount(['maps', 'likes', 'favorites']);

                // Fetch user's liked maplists (limited to 10)
                $likedMaplistIds = \DB::table('maplist_likes')
                    ->where('user_id', Auth::id())
                    ->pluck('maplist_id');

                $myLikedMaplists = Maplist::whereIn('id', $likedMaplistIds)
                    ->where('is_public', true)
                    ->where('is_play_later', false)
                    ->with(['maps', 'user'])
                    ->withCount(['maps', 'likes', 'favorites'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
            }
        }

        // Build available authors list (maplist creators with counts)
        $availableAuthors = \DB::table('maplists')
            ->join('users', 'maplists.user_id', '=', 'users.id')
            ->where('maplists.is_public', true)
            ->where('maplists.is_play_later', false)
            ->where('maplists.is_draft', false)
            ->select('users.id', 'users.name', \DB::raw('COUNT(*) as count'))
            ->groupBy('users.id', 'users.name')
            ->orderBy('count', 'desc')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => ['name' => $row->name, 'count' => $row->count]]);

        return Inertia::render('Maplists/Index', [
            'maplists' => $maplists,
            'myMaplists' => $myMaplists,
            'myFavoriteMaplists' => $myFavoriteMaplists,
            'myLikedMaplists' => $myLikedMaplists ?? collect(),
            'myPlayLater' => $playLater ?? null,
            'sort' => $sort,
            'user_id' => $userId,
            'view' => $view,
            'search' => $search,
            'authors' => $authors,
            'availableAuthors' => $availableAuthors,
        ]);
    }

    /**
     * Show the authenticated user's Play Later maplist
     */
    public function showPlayLater()
    {
        $playLater = Maplist::where('user_id', Auth::id())
            ->where('is_play_later', true)
            ->with(['user', 'maps', 'tags'])
            ->first();

        // Create Play Later if it doesn't exist
        if (!$playLater) {
            $playLater = Maplist::create([
                'user_id' => Auth::id(),
                'name' => 'Play Later',
                'description' => 'Maps you want to play later',
                'is_public' => false,
                'is_play_later' => true,
            ]);
            $playLater->load(['user', 'maps', 'tags']);
        }

        $this->attachPlayed($playLater->maps);

        $isLiked = false;
        $isFavorited = false;

        // Fetch servers for Play Later functionality
        $servers = \App\Models\Server::where('online', true)
            ->where('visible', true)
            ->with('onlinePlayers')
            ->get();

        return Inertia::render('Maplists/Show', [
            'maplist' => $playLater,
            'is_liked' => $isLiked,
            'is_favorited' => $isFavorited,
            'is_owner' => true,
            'servers' => $servers,
        ]);
    }

    /**
     * Show a single maplist
     */
    public function show(Request $request, $id)
    {
        $maplist = Maplist::with(['user', 'maps.tags', 'tags'])->findOrFail($id);

        // Check if user can view this maplist
        if (!$maplist->is_public && (!Auth::check() || Auth::id() !== $maplist->user_id)) {
            abort(403, 'This maplist is private');
        }

        // Redirect Play Later to the friendly URL
        if ($maplist->is_play_later && Auth::check() && Auth::id() === $maplist->user_id) {
            return redirect()->route('maplists.playLater');
        }

        // Check if partial reload
        $only = $request->header('X-Inertia-Partial-Data') ?? '';
        $isPartial = !empty($only);

        // Only increment views on full page load
        if (!$isPartial) {
            $maplist->increment('views_count');
        }

        $isLiked = Auth::check() ? $maplist->isLikedBy(Auth::id()) : false;
        $isFavorited = Auth::check() ? $maplist->isFavoritedBy(Auth::id()) : false;

        $this->attachPlayed($maplist->maps);

        // Fetch servers for Play Later functionality (only on full load)
        $servers = [];
        if (!$isPartial && $maplist->is_play_later && Auth::check() && Auth::id() === $maplist->user_id) {
            $servers = \App\Models\Server::where('online', true)
                ->where('visible', true)
                ->with('onlinePlayers')
                ->orderBy('plain_name', 'asc')
                ->get()
                ->map(function ($server) {
                    return [
                        'id' => $server->id,
                        'name' => $server->plain_name,
                        'address' => $server->ip,
                        'port' => $server->port,
                        'players_current' => $server->onlinePlayers->count(),
                        'players_max' => 64,
                        'location' => $server->location,
                    ];
                });
        }

        return Inertia::render('Maplists/Show', [
            'maplist' => $maplist,
            'is_liked' => $isLiked,
            'is_favorited' => $isFavorited,
            'is_owner' => Auth::check() && Auth::id() === $maplist->user_id,
            'servers' => $servers,
        ]);
    }

    /**
     * Flag the maps the viewer already has a record on, the way the maps
     * listing does, so MapCard shows its "Played" badge on a maplist too.
     * Only for a viewer paired to an MDD id - it is their own records the
     * badge is built from - and it counts any physics and any mode.
     */
    private function attachPlayed($maps)
    {
        $mddId = Auth::user()?->mdd_id;

        if (! $mddId || $maps->isEmpty()) {
            return $maps;
        }

        $played = Record::where('mdd_id', $mddId)
            ->whereIn('mapname', $maps->pluck('name')->filter())
            ->select('mapname', 'physics')
            ->distinct()
            ->get()
            // Keyed lowercase on both sides: MySQL matches map names
            // case-insensitively, PHP does not.
            ->groupBy(fn ($record) => mb_strtolower($record->mapname));

        return $maps->each(function ($map) use ($played) {
            $found = $played->get(mb_strtolower($map->name))?->pluck('physics')->unique();

            $map->played = $found !== null;
            $map->played_physics = match (true) {
                $found === null => null,
                $found->count() > 1 => 'both',
                default => $found->first(),
            };
        });
    }

    /**
     * Get user's maplists (for adding maps to maplist)
     */
    public function getUserMaplists()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplists = Maplist::where('user_id', Auth::id())
            ->withCount('maps')
            ->orderBy('is_play_later', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($maplists);
    }

    /**
     * Create a new maplist
     */
    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $maplist = Maplist::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'] ?? true,
            'is_play_later' => false,
        ]);

        return response()->json([
            'message' => 'Maplist created successfully',
            'maplist' => $maplist,
        ], 201);
    }

    /**
     * Update a maplist
     */
    public function update(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check ownership
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'sometimes|boolean',
        ]);

        // Don't allow changing "Play Later" maplist name
        if ($maplist->is_play_later && isset($validated['name'])) {
            unset($validated['name']);
        }

        $maplist->update($validated);

        return response()->json([
            'message' => 'Maplist updated successfully',
            'maplist' => $maplist,
        ]);
    }

    /**
     * Delete a maplist
     */
    public function destroy($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check ownership
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Don't allow deleting "Play Later" maplist
        if ($maplist->is_play_later) {
            return response()->json(['error' => 'Cannot delete Play Later maplist'], 400);
        }

        // Don't allow deleting if maplist has been favorited by anyone
        $favoritesCount = \DB::table('maplist_favorites')
            ->where('maplist_id', $maplist->id)
            ->count();

        if ($favoritesCount > 0) {
            return response()->json([
                'error' => 'Cannot delete maplist that has been favorited by users. This maplist has ' . $favoritesCount . ' favorite(s).'
            ], 400);
        }

        $maplist->delete();

        return response()->json([
            'message' => 'Maplist deleted successfully',
        ]);
    }

    /**
     * Add a map to a maplist
     */
    public function addMap(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check ownership
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'map_id' => 'required|exists:maps,id',
        ]);

        // Check if map already exists in maplist
        $exists = MaplistMap::where('maplist_id', $id)
            ->where('map_id', $validated['map_id'])
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Map already in maplist'], 400);
        }

        // Get the max position
        $maxPosition = MaplistMap::where('maplist_id', $id)->max('position') ?? -1;

        MaplistMap::create([
            'maplist_id' => $id,
            'map_id' => $validated['map_id'],
            'position' => $maxPosition + 1,
        ]);

        return response()->json([
            'message' => 'Map added to maplist successfully',
        ]);
    }

    /**
     * Remove a map from a maplist
     */
    public function removeMap($maplistId, $mapId)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($maplistId);

        // Check ownership
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        MaplistMap::where('maplist_id', $maplistId)
            ->where('map_id', $mapId)
            ->delete();

        return response()->json([
            'message' => 'Map removed from maplist successfully',
        ]);
    }

    /**
     * Toggle like on a maplist
     */
    public function toggleLike($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check if maplist is public (allow if user is the owner)
        if (!$maplist->is_public && $maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Cannot like private maplists'], 400);
        }

        $like = MaplistLike::where('user_id', Auth::id())
            ->where('maplist_id', $id)
            ->first();

        if ($like) {
            // Unlike
            $like->delete();
            $maplist->decrement('likes_count');
            $isLiked = false;
        } else {
            // Like
            MaplistLike::create([
                'user_id' => Auth::id(),
                'maplist_id' => $id,
            ]);
            $maplist->increment('likes_count');
            $isLiked = true;
        }

        return response()->json([
            'message' => $isLiked ? 'Maplist liked' : 'Maplist unliked',
            'is_liked' => $isLiked,
            'likes_count' => $maplist->fresh()->likes_count,
        ]);
    }

    /**
     * Toggle favorite on a maplist
     */
    public function toggleFavorite($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check if maplist is public (allow if user is the owner)
        if (!$maplist->is_public && $maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Cannot favorite private maplists'], 400);
        }

        $favorite = MaplistFavorite::where('user_id', Auth::id())
            ->where('maplist_id', $id)
            ->first();

        if ($favorite) {
            // Unfavorite
            $favorite->delete();
            $maplist->decrement('favorites_count');
            $isFavorited = false;
        } else {
            // Favorite
            MaplistFavorite::create([
                'user_id' => Auth::id(),
                'maplist_id' => $id,
            ]);
            $maplist->increment('favorites_count');
            $isFavorited = true;
        }

        return response()->json([
            'message' => $isFavorited ? 'Maplist favorited' : 'Maplist unfavorited',
            'is_favorited' => $isFavorited,
            'favorites_count' => $maplist->fresh()->favorites_count,
        ]);
    }

    /**
     * Search maps (for adding to maplist)
     */
    public function searchMaps(Request $request)
    {
        $query = $request->get('q', '');

        $maps = Map::searchByName($query)
            ->take(20)
            ->get()
            ->map(fn($map) => [
                'id' => $map->id,
                'name' => $map->name,
                'author' => $map->author,
                'thumbnail' => $map->thumbnail,
            ]);

        return response()->json($maps);
    }

    /**
     * Create maplist with maps in bulk
     */
    public function createWithMaps(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
            'map_names' => 'array',
            'map_names.*' => 'string',
        ]);

        // Validate all map names first
        $mapNames = $validated['map_names'] ?? [];
        $errors = [];
        $validMapIds = [];

        if (!empty($mapNames)) {
            foreach ($mapNames as $mapName) {
                $map = Map::where('name', $mapName)->first();

                if (!$map) {
                    // Find suggestions (similar map names)
                    $suggestions = Map::where('name', 'LIKE', '%' . $mapName . '%')
                        ->orWhere('name', 'LIKE', str_replace(' ', '%', $mapName) . '%')
                        ->limit(5)
                        ->pluck('name')
                        ->toArray();

                    $errors[] = [
                        'map_name' => $mapName,
                        'message' => 'Map not found',
                        'suggestions' => $suggestions,
                    ];
                } else {
                    $validMapIds[] = $map->id;
                }
            }
        }

        // If any maps not found, return errors and don't create maplist
        if (!empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }

        // Create the maplist
        $maplist = Maplist::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'is_public' => $validated['is_public'] ?? true,
            'is_play_later' => false,
        ]);

        // Add maps to maplist with positions
        foreach ($validMapIds as $position => $mapId) {
            MaplistMap::create([
                'maplist_id' => $maplist->id,
                'map_id' => $mapId,
                'position' => $position,
            ]);
        }

        return response()->json([
            'message' => 'Maplist created successfully',
            'maplist' => $maplist,
        ]);
    }

    /**
     * Reorder maps in a maplist
     */
    public function reorderMaps(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check ownership
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'map_ids' => 'required|array',
            'map_ids.*' => 'exists:maps,id',
        ]);

        // Update positions
        foreach ($validated['map_ids'] as $position => $mapId) {
            MaplistMap::where('maplist_id', $id)
                ->where('map_id', $mapId)
                ->update(['position' => $position]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }

    /**
     * Save or update a draft maplist
     */
    public function saveDraft(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'id' => 'nullable|exists:maplists,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'map_names' => 'nullable|string',
        ]);

        // If draft ID provided, update existing draft
        if (!empty($validated['id'])) {
            $maplist = Maplist::findOrFail($validated['id']);

            // Check ownership
            if ($maplist->user_id !== Auth::id()) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            // Only update if it's a draft
            if (!$maplist->is_draft) {
                return response()->json(['error' => 'Cannot update non-draft maplist'], 403);
            }

            $maplist->update([
                'name' => $validated['name'] ?? $maplist->name,
                'description' => $validated['description'] ?? $maplist->description,
            ]);

            // Store map names in description temporarily (we'll parse them on publish)
            // For now, just store the raw text
            if (isset($validated['map_names'])) {
                // Store map_names in a JSON field or separate table if needed
                // For simplicity, we'll just return success
            }

            return response()->json([
                'message' => 'Draft updated',
                'maplist' => $maplist,
            ]);
        }

        // Create new draft
        $maplist = Maplist::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'] ?? 'Untitled Draft',
            'description' => $validated['description'] ?? '',
            'is_public' => false,
            'is_play_later' => false,
            'is_draft' => true,
        ]);

        return response()->json([
            'message' => 'Draft created',
            'maplist' => $maplist,
        ]);
    }

    /**
     * Get user's draft maplists
     */
    public function getDrafts(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $drafts = Maplist::where('user_id', Auth::id())
            ->where('is_draft', true)
            ->with(['user', 'maps'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json(['drafts' => $drafts]);
    }

    /**
     * Delete a draft
     */
    public function deleteDraft($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $maplist = Maplist::findOrFail($id);

        // Check ownership
        if ($maplist->user_id !== Auth::id()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // Only delete if it's a draft
        if (!$maplist->is_draft) {
            return response()->json(['error' => 'Cannot delete non-draft maplist'], 403);
        }

        $maplist->delete();

        return response()->json(['message' => 'Draft deleted']);
    }

    /**
     * Get suggested tags for a map from maplists containing it
     */
    public function getSuggestedTagsForMap($mapId)
    {
        $map = Map::with('tags')->findOrFail($mapId);

        // Get all PUBLIC maplists that contain this map
        $maplistsWithTags = Maplist::whereHas('maps', function($query) use ($mapId) {
            $query->where('maps.id', $mapId);
        })
        ->where('is_public', true)
        ->with('tags')
        ->get();

        // Collect all unique tags from these maplists
        $suggestedTags = collect();
        foreach ($maplistsWithTags as $maplist) {
            foreach ($maplist->tags as $tag) {
                // Check if map already has this tag
                $alreadyHas = $map->tags->contains('id', $tag->id);

                // Add tag info with adoption status
                $existingTag = $suggestedTags->firstWhere('id', $tag->id);
                if (!$existingTag) {
                    $suggestedTags->push([
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'display_name' => $tag->display_name,
                        'already_adopted' => $alreadyHas,
                        'maplist_names' => [$maplist->name]
                    ]);
                } else {
                    // Tag appears in multiple maplists, add this maplist name
                    $index = $suggestedTags->search(fn($t) => $t['id'] === $tag->id);
                    $suggestedTags[$index]['maplist_names'][] = $maplist->name;
                }
            }
        }

        return response()->json([
            'suggested_tags' => $suggestedTags->values()
        ]);
    }
}
