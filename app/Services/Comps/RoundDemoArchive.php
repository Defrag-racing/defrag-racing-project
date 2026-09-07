<?php

namespace App\Services\Comps;

use App\Models\CompResult;
use App\Models\CompRound;
use App\Models\CompSubmission;
use App\Models\UploadedDemo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Every counting demo of one physics of a finished round, in one 7z.
 *
 * Two flavours, because people want two different things from it. Someone
 * who wants to watch the week's runs cold, and guess who is behind each one,
 * takes the anonymized one: the file names carry the rank and the time and
 * nothing else. Someone who wants the record straight takes the revealed
 * one, with the player's name in every file name. Same demos, same order,
 * different labels.
 *
 * The archive is built once and kept. Standings are frozen when the round
 * ends, and a re-freeze after a report changes the rows, so the file name
 * carries a fingerprint of the rows it was built from: a changed standing
 * gets a new archive, an unchanged one is served straight from disk.
 */
class RoundDemoArchive
{
    public const ANONYMIZED = 'anonymized';
    public const REVEALED = 'revealed';

    public const MODES = [self::ANONYMIZED, self::REVEALED];

    private const DISK = 'local';
    private const DIR = 'comps/demo-archives';

    /**
     * How many demos the archive would hold, for the button.
     */
    public function count(CompRound $round, string $physics): int
    {
        return count($this->entries($round, $physics));
    }

    /**
     * Absolute path of the ready archive, building it first if it does not
     * exist yet. Null when there is nothing to put in it.
     */
    public function path(CompRound $round, string $physics, string $mode): ?string
    {
        $entries = $this->entries($round, $physics);

        if (! $entries) {
            return null;
        }

        $fingerprint = substr(md5(json_encode(array_map(
            fn ($e) => [$e['result']->id, $e['result']->rank, $e['result']->time, $e['demo']->id],
            $entries
        ))), 0, 10);

        $relative = sprintf('%s/round-%d-%s-%s-%s.7z', self::DIR, $round->id, $physics, $mode, $fingerprint);
        $disk = Storage::disk(self::DISK);

        if ($disk->exists($relative)) {
            return $disk->path($relative);
        }

        return $this->build($round, $physics, $mode, $entries, $relative);
    }

    /**
     * The download's file name, the same shape as the demos inside it.
     */
    public function downloadName(CompRound $round, string $physics, string $mode): string
    {
        $map = $round->mapFor($physics)?->map?->name ?? 'comp';

        return sprintf('%s-%s-%s-%s.7z', $this->slug($round->comp->title), $map, $physics, $mode);
    }

    /**
     * One entry per standing: the result row and the demo that made it.
     *
     * The standing says who and what time; the demo is the counting
     * submission of that person with that time. A person who uploaded the
     * same time twice has two, and either is the run, so the first is taken.
     *
     * @return array<int, array{result: CompResult, demo: UploadedDemo}>
     */
    private function entries(CompRound $round, string $physics): array
    {
        $results = CompResult::where('comp_round_id', $round->id)
            ->where('physics', $physics)
            ->with('user:id,name')
            ->orderBy('rank')
            ->orderBy('time')
            ->get();

        if ($results->isEmpty()) {
            return [];
        }

        $submissions = CompSubmission::counting()
            ->where('comp_round_id', $round->id)
            ->where('physics', $physics)
            ->whereIn('user_id', $results->pluck('user_id'))
            ->whereNotNull('uploaded_demo_id')
            ->with('demo:id,file_path,processed_filename,original_filename')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id');

        $out = [];

        foreach ($results as $result) {
            $match = ($submissions[$result->user_id] ?? collect())
                ->first(fn (CompSubmission $s) => (int) $s->time === (int) $result->time && $s->demo);

            if ($match) {
                $out[] = ['result' => $result, 'demo' => $match->demo];
            }
        }

        return $out;
    }

    private function build(CompRound $round, string $physics, string $mode, array $entries, string $relative): ?string
    {
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(self::DIR);

        $work = storage_path('app/tmp/comp-archive-' . uniqid());
        $folder = $work . '/demos';
        mkdir($folder, 0755, true);

        $map = $round->mapFor($physics)?->map?->name ?? 'map';
        $written = 0;

        try {
            foreach ($entries as $entry) {
                $contents = $this->demoContents($entry['demo']);

                if ($contents === null) {
                    continue;
                }

                $name = $this->fileName($entry['result'], $map, $physics, $mode);

                // Two people first with the same time would otherwise be one
                // file. Only a tie gets the suffix; a plain listing stays clean.
                if (is_file($folder . '/' . $name)) {
                    $name = preg_replace('/\.dm_68$/', '_' . $entry['result']->id . '.dm_68', $name);
                }

                file_put_contents($folder . '/' . $name, $contents);
                $written++;
            }

            if ($written === 0) {
                return null;
            }

            $target = $disk->path($relative);
            $cmd = sprintf(
                'cd %s && 7z a -t7z -mx=5 %s ./* 2>&1',
                escapeshellarg($folder),
                escapeshellarg($target)
            );
            exec($cmd, $output, $code);

            if ($code !== 0 || ! is_file($target)) {
                Log::warning('Comp demo archive failed', ['round' => $round->id, 'physics' => $physics, 'out' => implode("\n", $output)]);
                @unlink($target);

                return null;
            }

            return $target;
        } finally {
            $this->removeDir($work);
        }
    }

    /**
     * `01_map[df.cpm]00.15.216.dm_68`, or with `(nick)` before the extension
     * when the names are revealed. The shape defrag itself writes, with the
     * rank in front so a file listing is already the leaderboard.
     */
    private function fileName(CompResult $result, string $map, string $physics, string $mode): string
    {
        $ms = (int) $result->time;
        $time = sprintf('%02d.%02d.%03d', intdiv($ms, 60000), intdiv($ms % 60000, 1000), $ms % 1000);
        $name = sprintf('%02d_%s[df.%s]%s', $result->rank, $map, $physics, $time);

        if ($mode === self::REVEALED) {
            $nick = trim((string) preg_replace('/\^[0-9A-Za-z]/', '', (string) $result->user?->name));
            $nick = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $nick) ?: 'player';
            $name .= '(' . $nick . ')';
        }

        return $name . '.dm_68';
    }

    /**
     * The raw .dm_68 of an uploaded demo. Processed demos sit in B2 as a 7z
     * around the demo; the ones that never got that far sit on the box as
     * they came in. Either way the bytes handed back are the demo itself.
     */
    private function demoContents(UploadedDemo $demo): ?string
    {
        if (empty($demo->file_path)) {
            return null;
        }

        $isLocal = str_starts_with($demo->file_path, 'demos/temp/') || str_starts_with($demo->file_path, 'demos/failed/');

        try {
            $bytes = $isLocal
                ? (is_file(storage_path("app/{$demo->file_path}")) ? file_get_contents(storage_path("app/{$demo->file_path}")) : null)
                : Storage::get($demo->file_path);
        } catch (\Throwable $e) {
            Log::warning('Comp demo archive: demo unreadable', ['demo' => $demo->id, 'error' => $e->getMessage()]);

            return null;
        }

        if ($bytes === null) {
            return null;
        }

        $name = $demo->processed_filename ?: $demo->original_filename ?: basename($demo->file_path);

        if (! str_ends_with(strtolower($name), '.7z') && ! str_ends_with(strtolower($demo->file_path), '.7z')) {
            return $bytes;
        }

        return $this->extract($bytes) ?? $bytes;
    }

    private function extract(string $archive): ?string
    {
        $dir = storage_path('app/tmp/comp-extract-' . uniqid());
        mkdir($dir, 0755, true);

        try {
            file_put_contents($dir . '/in.7z', $archive);
            exec(sprintf('7z x %s -o%s -p"" -y 2>&1', escapeshellarg($dir . '/in.7z'), escapeshellarg($dir)), $out, $code);

            if ($code !== 0) {
                return null;
            }

            $files = glob($dir . '/*.dm_*') ?: [];

            return $files ? file_get_contents(reset($files)) : null;
        } finally {
            $this->removeDir($dir);
        }
    }

    private function slug(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-') ?: 'comp';
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
