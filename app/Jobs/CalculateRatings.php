<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;

use App\Models\PlayerRating;

class CalculateRatings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const MIN_MAP_TOTAL_PARTICIPATORS = 5;
    const MIN_TOP1_TIME = 500;
    const MIN_TOP_RELTIME = 0.6;
    const MIN_TOTAL_RECORDS = 10;
    const ACTIVE_PLAYERS_MONTHS = 3; // keep in sync with one in RankingController.php
    const BANNED_MAPS = ['map1', 'map2', 'map3']; // to be filled with banned map names

    // map score configs
    const CFG_A = 1.5; // left horizontal asymptote
    const CFG_B = 2.086; // growth rate
    const CFG_M = 0.3; // mid point
    const CFG_V = 0.1; // I would not touch this one
    const CFG_Q = 0.5; // I would not touch this one

    // player rating configs
    const CFG_D = 0.02; // growth rate for map scores weights

    public $timeout = 7200;

    public function handle(): void
    {
        Log::info('CalculateRatings job started');

        $query = DB::table('records')
            ->whereNull('deleted_at')
            ->select(
                    'name',
                    'mdd_id',
                    'user_id',
                    'mapname',
                    'physics',
                    'mode',
                    'time',
                    'date_set');

        $query = $this->addRanks($query);
        $query = $this->addMapTotalParticipators($query);
        $query = $this->addTopTimes($query);
        $query = $this->addReltimes($query);
        $query = $this->addBannedMaps($query);
        $query = $this->addMapScores($query);
        $query = $this->addWeightedMapScores($query);
        $query = $this->addPlayerRecordsInCategory($query);
        $query = $this->addLastActivity($query);
        $query = $this->computePlayerRatings($query);
        $query = $this->addCategoryTotalParticipators($query);
        $query = $this->addAllPlayersRank($query);
        $query = $this->addActivePlayersRank($query);
        $query = $this->selectFinalColumns($query);

        $result = $query->get();

        // $this->mydebug($result);

        foreach ($result as $row) {
            // Check if a record with the same mdd_id, physics, and mode exists in player_ratings
            $existingRating = PlayerRating::where('mdd_id', $row->mdd_id)
                ->where('physics', $row->physics)
                ->where('mode', $row->mode)
                ->first();

            if ($existingRating) {
                // Soft delete the existing record
                $existingRating->delete();
            }

            // Insert the new record from new_ratings into player_ratings
            PlayerRating::create([
                'name' => $row->name,
                'mdd_id' => $row->mdd_id,
                'user_id' => $row->user_id,
                'physics' => $row->physics,
                'mode' => $row->mode,
                'all_players_rank' => $row->all_players_rank,
                'active_players_rank' => $row->active_players_rank,
                'category_total_participators' => $row->category_total_participators,
                'player_records_in_category' => $row->player_records_in_category,
                'last_activity' => $row->last_activity,
                'player_rating' => $row->player_rating,
            ]);
        }

        Log::info('CalculateRatings job ended');
    }
    protected function addRanks($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                DENSE_RANK()
                OVER (PARTITION BY mapname, physics, mode ORDER BY time)
                AS record_map_rank
            '));
    }

    protected function addMapTotalParticipators($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                COUNT(*)
                OVER (PARTITION BY mapname, physics, mode)
                AS map_total_participators
            '));
    }

    protected function addTopTimes($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect([
                DB::raw('
                    COALESCE(
                        NULLIF(MAX(CASE WHEN record_map_rank = 1 THEN time END)
                        OVER (PARTITION BY mapname, physics, mode), 0), 1)
                    AS top1_time'),
                DB::raw('
                    COALESCE(
                        MAX(CASE WHEN record_map_rank = 2 THEN time END)
                        OVER (PARTITION BY mapname, physics, mode),
                        COALESCE(
                            NULLIF(MAX(CASE WHEN record_map_rank = 1 THEN time END)
                            OVER (PARTITION BY mapname, physics, mode), 0), 1)
                    )
                    AS top2_time')
            ]);
    }

    protected function addReltimes($query)
    {
        $query = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                CASE
                WHEN record_map_rank != 1
                THEN time / top1_time
                ELSE time / top2_time
                END
                AS reltime
            '));

        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                MIN(reltime)
                OVER (PARTITION BY mapname, physics, mode)
                AS top_reltime
            '));
    }

    protected function addBannedMaps($query)
    {
        $bannedMaps = "'" . implode("','", self::BANNED_MAPS) . "'";

        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                CASE
                WHEN map_total_participators < ' . self::MIN_MAP_TOTAL_PARTICIPATORS . '
                OR top1_time < ' . self::MIN_TOP1_TIME . '
                OR top_reltime < ' . self::MIN_TOP_RELTIME . '
                OR mapname IN (' . $bannedMaps . ')
                THEN true
                ELSE false
                END
                AS map_banned
            '));
    }

    protected function addMapScores($query)
    {
        // map_score = 1000(CFG_A + (-CFG_A / (1 + CFG_Q * exp(-CFG_B * (reltime - CFG_M)))**(1/CFG_V)))
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                CASE WHEN map_banned = False THEN
                1000 * (
                    ' . self::CFG_A . ' +
                    (-' . self::CFG_A . ' /
                        POWER(
                        1 + ' . self::CFG_Q . ' *
                        EXP( - ' . self::CFG_B . ' * (reltime - ' . self::CFG_M . ')),
                        1 / ' . self::CFG_V . '
                        )
                    )
                )
                ELSE 0
                END
                AS map_score
            '));
    }

    protected function addWeightedMapScores($query)
    {
        // add ranks to the player records
        $query = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                DENSE_RANK()
                OVER (PARTITION BY mdd_id, physics, mode ORDER BY map_score DESC)
                AS record_player_rank
            '));

        // compute weights using exp decay
        // weight = exp(-CFG_D * record_player_rank)
        $query = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                EXP(-' . self::CFG_D . ' * record_player_rank)
                AS weight
            '));

        // compute weighted map scores
        // weighted_map_score = map_score * weight
        $query = DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                map_score * weight
                AS weighted_map_score
            '));

        return $query;
    }

    protected function addPlayerRecordsInCategory($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                COUNT(*)
                OVER (PARTITION BY mdd_id, physics, mode)
                AS player_records_in_category
            '));
    }

    protected function addLastActivity($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                MAX(date_set)
                OVER (PARTITION BY mdd_id, physics, mode)
                AS last_activity
            '));
    }

    protected function computePlayerRatings($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                    CASE
                    WHEN COUNT(time) < ' . self::MIN_TOTAL_RECORDS . '
                    THEN (SUM(weighted_map_score) / SUM(weight)) * COUNT(time) / ' . self::MIN_TOTAL_RECORDS . '
                    ELSE SUM(weighted_map_score) / SUM(weight)
                    END AS player_rating
            '))
            ->groupBy('mdd_id', 'physics', 'mode');
    }

    protected function addCategoryTotalParticipators($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                COUNT(*)
                OVER (PARTITION BY physics, mode)
                AS category_total_participators
            '));
    }

    protected function addAllPlayersRank($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw('
                DENSE_RANK()
                OVER (PARTITION BY physics, mode ORDER BY player_rating DESC)
                AS all_players_rank
            '));
    }

    protected function addActivePlayersRank($query)
    {
        $threeMonthsAgo = now()->subMonths(self::ACTIVE_PLAYERS_MONTHS);

        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->addSelect('*')
            ->addSelect(DB::raw("
                DENSE_RANK() OVER (
                    PARTITION BY physics, mode
                    ORDER BY CASE
                        WHEN last_activity >= '{$threeMonthsAgo}' THEN player_rating
                        ELSE 0
                    END DESC
                ) AS active_players_rank
            "));
    }

    protected function selectFinalColumns($query)
    {
        return DB::table(DB::raw("({$query->toSql()}) as sub"))
            ->mergeBindings($query)
            ->select([
                'name',
                'mdd_id',
                'user_id',
                'physics',
                'mode',
                'all_players_rank',
                'active_players_rank',
                'category_total_participators',
                'player_records_in_category',
                'last_activity',
                'player_rating'
            ]);
    }

// ----------------------------------------------------------------------------

    private function mydebug(BaseCollection $records): void
    {
        $array = array_map(function ($record) {
            return (array) $record;
        }, $records->toArray());

        $headers = array_keys($array[0]);
        $rows = array_map('array_values', $array);

        // Calculate the maximum width for each column
        $columnWidths = array_map(function ($header, $index) use ($rows) {
            $maxWidth = strlen($header);
            foreach ($rows as $row) {
                $maxWidth = max($maxWidth, strlen($row[$index]));
            }
            return $maxWidth;
        }, $headers, array_keys($headers));

        // Create the header row
        $headerRow = implode(' | ', array_map(function ($header, $width) {
            return str_pad($header, $width);
        }, $headers, $columnWidths)) . "\n";

        // Create the separator row
        $separatorRow = implode('-+-', array_map(function ($width) {
            return str_repeat('-', $width);
        }, $columnWidths)) . "\n";

        // Create the data rows
        $dataRows = array_map(function ($row) use ($columnWidths) {
            return implode(' | ', array_map(function ($value, $width) {
                return str_pad($value, $width);
            }, $row, $columnWidths));
        }, $rows);

        // Combine all rows into a single table string
        $table = $headerRow . $separatorRow . implode("\n", $dataRows) . "\n";

        Log::info("\n" . $table);
    }
}
