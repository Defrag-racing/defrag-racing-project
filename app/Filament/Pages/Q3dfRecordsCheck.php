<?php

namespace App\Filament\Pages;

use App\External\Q3DFLastRecordsApi;
use App\Jobs\CheckQ3dfRecordsJob;
use App\Jobs\ImportQ3dfCheckRowsJob;
use App\Models\Q3dfRecordCheck;
use App\Models\Q3dfRecordCheckRow;
use App\Models\Record;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Does our records table hold every record q3df.org holds?
 *
 * The minute job only reads the newest records. A gap it missed while q3df
 * was down, or while the job was stopped, stays a gap for good. This page
 * compares the two for a range of dates or for the whole history, shows what
 * is missing, and imports it on a click.
 */
class Q3dfRecordsCheck extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Records Check';

    protected static ?string $title = 'q3df Records Check';

    protected static ?string $navigationGroup = 'System';

    protected static string $view = 'filament.pages.q3df-records-check';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $selected = null;

    /** all, missing or different */
    public string $kind = 'all';

    public int $page = 1;

    public int $perPage = 50;

    public function getViewData(): array
    {
        // Seeded here, not in mount(): mount() runs once, and a box emptied by
        // a round trip would stay empty for the rest of the visit.
        $this->dateFrom = $this->dateFrom !== '' ? $this->dateFrom : now()->subDays(30)->toDateString();
        $this->dateTo = $this->dateTo !== '' ? $this->dateTo : now()->toDateString();

        $checks = Q3dfRecordCheck::orderByDesc('id')->limit(15)->get();

        if ($this->selected === null && $checks->isNotEmpty()) {
            $this->selected = $checks->first()->id;
        }

        $check = $this->selected ? Q3dfRecordCheck::find($this->selected) : null;

        $months = collect();
        $rows = collect();
        $rowsTotal = 0;
        $importable = 0;
        $deletedHere = 0;
        $failedRows = 0;

        if ($check) {
            $months = Q3dfRecordCheckRow::where('check_id', $check->id)
                ->selectRaw("DATE_FORMAT(date_set, '%Y-%m') as month")
                ->selectRaw("SUM(kind = 'missing') as missing")
                ->selectRaw("SUM(kind = 'different') as different")
                ->groupBy('month')
                ->orderByDesc('month')
                ->get();

            $query = Q3dfRecordCheckRow::where('check_id', $check->id)
                ->when($this->kind !== 'all', fn ($q) => $q->where('kind', $this->kind));

            $rowsTotal = (clone $query)->count();
            $rows = $query->orderByDesc('date_set')
                ->offset(($this->page - 1) * $this->perPage)
                ->limit($this->perPage)
                ->get();

            $importable = ImportQ3dfCheckRowsJob::importable($check->id)->count();
            $deletedHere = Q3dfRecordCheckRow::where('check_id', $check->id)->where('skipped_reason', 'deleted here')->count();
            $failedRows = Q3dfRecordCheckRow::where('check_id', $check->id)->where('skipped_reason', 'like', 'error:%')->count();
        }

        return [
            'checks' => $checks,
            'check' => $check,
            'months' => $months,
            'rows' => $rows,
            'rowsTotal' => $rowsTotal,
            'pages' => max(1, (int) ceil($rowsTotal / $this->perPage)),
            'importable' => $importable,
            'deletedHere' => $deletedHere,
            'failedRows' => $failedRows,
            'busy' => $checks->contains(fn ($c) => $c->isBusy()),
            'q3dfTotal' => $this->q3dfTotal(),
            'ourTotal' => Cache::remember('q3df_check:our_total', 600, fn () => Record::count()),
            'hasKey' => (bool) config('services.q3df.key'),
        ];
    }

    public function checkRange(): void
    {
        try {
            $from = Carbon::parse($this->dateFrom)->startOfDay();
            $to = Carbon::parse($this->dateTo)->startOfDay();
        } catch (\Throwable) {
            Notification::make()->title('Those dates do not read as dates')->warning()->send();

            return;
        }

        if ($from->gt($to)) {
            Notification::make()->title('The first date is after the second')->warning()->send();

            return;
        }

        $this->start(['scope' => 'range', 'date_from' => $from, 'date_to' => $to]);
    }

    public function checkEverything(): void
    {
        $this->start(['scope' => 'full']);
    }

    public function import(int $id): void
    {
        if ($this->refuseWhenBusy()) {
            return;
        }

        $count = ImportQ3dfCheckRowsJob::importable($id)->count();

        if ($count === 0) {
            Notification::make()->title('Nothing left to import from this check')->warning()->send();

            return;
        }

        Q3dfRecordCheck::whereKey($id)->update(['status' => 'importing']);
        ImportQ3dfCheckRowsJob::dispatch($id);

        Notification::make()->title("Importing {$count} records")->success()->send();
    }

    /**
     * Stops a running check or import, and frees one that is stuck: a job lost
     * from the queue leaves its check queued or running for good, and nothing
     * else can start until it is cleared. A running job sees the mark at its
     * next page and ends there. Rows already imported stay imported.
     */
    public function stop(int $id): void
    {
        Q3dfRecordCheck::whereKey($id)
            ->whereIn('status', Q3dfRecordCheck::BUSY)
            ->update(['status' => 'stopped', 'finished_at' => now()]);

        Notification::make()->title('Stopped')->success()->send();
    }

    public function remove(int $id): void
    {
        $check = Q3dfRecordCheck::find($id);

        if (! $check || $check->isBusy()) {
            return;
        }

        $check->delete();
        $this->selected = null;
    }

    public function show(int $id): void
    {
        $this->selected = $id;
        $this->page = 1;
    }

    public function updatedKind(): void
    {
        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    private function start(array $attributes): void
    {
        if ($this->refuseWhenBusy()) {
            return;
        }

        $check = Q3dfRecordCheck::create($attributes + ['status' => 'queued']);
        CheckQ3dfRecordsJob::dispatch($check->id);

        $this->selected = $check->id;
        $this->page = 1;

        Notification::make()->title('The check has started')->success()->send();
    }

    private function refuseWhenBusy(): bool
    {
        $busy = Q3dfRecordCheck::whereIn('status', Q3dfRecordCheck::BUSY)->exists();

        if ($busy) {
            Notification::make()->title('Another check or import is still running')->warning()->send();
        }

        return $busy;
    }

    private function q3dfTotal(): ?int
    {
        return Cache::remember('q3df_check:q3df_total', 600, function () {
            $fetched = (new Q3DFLastRecordsApi())->getMddRecords(0, 10, config('services.q3df.key') ?: null);

            return $fetched === null ? null : (int) $fetched[1];
        });
    }
}
