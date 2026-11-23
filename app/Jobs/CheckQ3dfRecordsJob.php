<?php

namespace App\Jobs;

use App\External\Q3DFLastRecordsApi;
use App\External\Q3DFRecordsApi;
use App\Models\Q3dfRecordCheck;
use App\Models\Q3dfRecordCheckRow;
use App\Models\Record;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Compares q3df.org with the records table and writes down every difference.
 * It changes no record. Importing is a separate step the admin starts.
 *
 * The API has no date filter, so a date range walks getLastRecords from the
 * newest record down until it passes the start of the range. The whole history
 * walks getRecords, the archive, about 675 pages of 1000.
 *
 * Both lists hold one row per player, map, physics and mode: the current
 * personal best. So does our records table, with older bests moved out to
 * record_histories. That is what makes the two comparable row by row.
 *
 * Not ShouldBeUnique. The page refuses a second start while one runs, and a
 * unique lock left behind by a lost job made the next start vanish without a
 * word for two hours.
 */
class CheckQ3dfRecordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $timeout = 7200;

    public $tries = 1;

    private const PER_PAGE = 1000;

    /** @var array<string, true> keys q3df listed inside the range */
    private array $seen = [];

    public function __construct(public int $checkId)
    {
    }

    public function handle(): void
    {
        $check = Q3dfRecordCheck::find($this->checkId);

        if (! $check) {
            return;
        }

        $check->update(['status' => 'running', 'started_at' => now(), 'error' => null]);

        try {
            $this->run($check);

            if (! self::stopped($check->id)) {
                $check->update(['status' => 'done', 'finished_at' => now()]);
            }
        } catch (\Throwable $e) {
            $check->update(['status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()]);
        }
    }

    /** The Stop button on the page marks the check stopped. */
    public static function stopped(int $checkId): bool
    {
        return Q3dfRecordCheck::whereKey($checkId)->value('status') === 'stopped';
    }

    public function failed(\Throwable $e): void
    {
        Q3dfRecordCheck::whereKey($this->checkId)->update([
            'status' => 'failed',
            'error' => 'The job was stopped: ' . $e->getMessage(),
            'finished_at' => now(),
        ]);
    }

    private function run(Q3dfRecordCheck $check): void
    {
        $key = config('services.q3df.key');

        if (! $key) {
            throw new \RuntimeException('Q3DF_API_KEY is not set. Without it q3df serves only the first 200 records.');
        }

        $range = $check->scope === 'range';
        $api = $range ? new Q3DFLastRecordsApi() : new Q3DFRecordsApi();
        $api->timeout = 60;

        $from = $range ? $check->date_from->format('Y-m-d') . ' 00:00:00' : null;
        $to = $range ? $check->date_to->format('Y-m-d') . ' 23:59:59' : null;

        for ($page = 0; ; $page++) {
            if (self::stopped($check->id)) {
                return;
            }

            [$count, $records] = $this->fetch($api, $page, $key);

            if (! $range && $page === 0) {
                $check->update(['pages_total' => (int) ceil($count / self::PER_PAGE)]);
            }

            if (count($records) === 0) {
                break;
            }

            $wanted = $range
                ? array_filter($records, fn ($r) => $r['date'] >= $from && $r['date'] <= $to)
                : $records;

            $this->compare($check, array_values($wanted), $range);

            Q3dfRecordCheck::whereKey($check->id)->update(['pages_read' => DB::raw('pages_read + 1')]);

            // Newest first: once the last row of a page is older than the
            // range, every later page is too.
            if ($range && end($records)['date'] < $from) {
                break;
            }

            if (count($records) < self::PER_PAGE) {
                break;
            }
        }

        $check->refresh();
        $check->update(['only_here' => $range
            ? $this->countOnlyHere($from, $to)
            : max(0, Record::count() - $check->matching - $check->different)]);
    }

    /**
     * @return array{0: int, 1: array}
     */
    private function fetch($api, int $page, string $key): array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $fetched = $api->getMddRecords($page, self::PER_PAGE, $key);

            if ($fetched !== null) {
                return [(int) $fetched[1], $fetched[2]];
            }

            sleep(5 * $attempt);
        }

        throw new \RuntimeException("q3df did not answer page $page three times in a row.");
    }

    private static function key($mddId, $map, $physics, $mode): string
    {
        return $mddId . '|' . strtolower($map) . '|' . $physics . '|' . $mode;
    }

    private function compare(Q3dfRecordCheck $check, array $records, bool $range): void
    {
        if (count($records) === 0) {
            return;
        }

        // One lookup for the whole page. The tuple matches the start of the
        // unique index (physics, mode, mdd_id, mapname, time).
        $tuples = [];
        $bindings = [];
        foreach ($records as $r) {
            $tuples[] = '(?, ?, ?, ?)';
            array_push($bindings, $r['physics'], $r['mode'], $r['mdd_id'], strtolower($r['map']));
        }

        $ours = [];
        DB::table('records')
            ->whereNull('deleted_at')
            ->whereRaw('(physics, mode, mdd_id, mapname) IN (' . implode(',', $tuples) . ')', $bindings)
            ->get(['mdd_id', 'mapname', 'physics', 'mode', 'time'])
            ->each(function ($row) use (&$ours) {
                $ours[self::key($row->mdd_id, $row->mapname, $row->physics, $row->mode)] = (int) $row->time;
            });

        $rows = [];
        $matching = 0;

        foreach ($records as $r) {
            $key = self::key($r['mdd_id'], $r['map'], $r['physics'], $r['mode']);

            if ($range) {
                $this->seen[$key] = true;
            }

            $ourTime = $ours[$key] ?? null;

            if ($ourTime === (int) $r['time']) {
                $matching++;
                continue;
            }

            $rows[] = [
                'check_id' => $check->id,
                'kind' => $ourTime === null ? 'missing' : 'different',
                'mdd_id' => $r['mdd_id'],
                'name' => mb_substr((string) $r['name'], 0, 255),
                'country' => $r['country'] ?? null,
                'mapname' => strtolower($r['map']),
                'physics' => $r['physics'],
                'mode' => $r['mode'],
                'q3df_time' => $r['time'],
                'our_time' => $ourTime,
                'date_set' => $r['date'],
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Q3dfRecordCheckRow::insert($chunk);
        }

        $missing = count(array_filter($rows, fn ($row) => $row['kind'] === 'missing'));

        // Through the query builder, not $check->increment(): the model keeps
        // the raw expressions as dirty attributes, and the next save() ran
        // them a second time, adding the last page twice.
        Q3dfRecordCheck::whereKey($check->id)->update([
            'q3df_rows' => DB::raw('q3df_rows + ' . count($records)),
            'matching' => DB::raw('matching + ' . $matching),
            'missing' => DB::raw('missing + ' . $missing),
            'different' => DB::raw('different + ' . (count($rows) - $missing)),
        ]);
    }

    /**
     * Our rows dated inside the range that q3df did not list there. Usually a
     * record deleted on q3df, or one whose date differs between the two sides.
     */
    private function countOnlyHere(string $from, string $to): int
    {
        $count = 0;

        DB::table('records')
            ->whereNull('deleted_at')
            ->whereBetween('date_set', [$from, $to])
            ->select(['id', 'mdd_id', 'mapname', 'physics', 'mode'])
            ->orderBy('id')
            ->chunk(5000, function ($chunk) use (&$count) {
                foreach ($chunk as $row) {
                    if (! isset($this->seen[self::key($row->mdd_id, $row->mapname, $row->physics, $row->mode)])) {
                        $count++;
                    }
                }
            });

        return $count;
    }
}
