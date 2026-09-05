<?php

namespace App\Console\Commands;

use App\Models\RenderedVideo;
use App\Models\SiteSetting;
use App\Services\JokeMaps;
use Illuminate\Console\Command;

/**
 * See and act on the maps that play themselves.
 *
 * The bar itself lives in JokeMaps and applies from the moment it is set. This
 * is for looking at it before choosing a number, and for the rows already in
 * the queue or already rendered, which the bar cannot reach.
 */
class JokeMapsCommand extends Command
{
    protected $signature = 'demome:joke-maps
                            {--limit= : try a different number of tied players without saving it}
                            {--set= : save a new number and use it from now on}
                            {--clean : drop the pending queue rows for these maps}
                            {--show=30 : how many to print}';

    protected $description = 'Maps where too many players share the record time, so no video is worth rendering.';

    public function handle(): int
    {
        if ($set = $this->option('set')) {
            SiteSetting::set('demome:tied_wr_limit', (string) (int) $set);
            JokeMaps::forget();
            $this->info("Saved: a map is a joke when {$set} or more players share its record time.");
        }

        // A trial number must not be left behind in the cache for the queue to
        // pick up, so it is put back whatever happens below.
        $trial = $this->option('limit');
        $saved = SiteSetting::get('demome:tied_wr_limit', 10);

        if ($trial) {
            SiteSetting::set('demome:tied_wr_limit', (string) (int) $trial);
            JokeMaps::forget();
        }

        try {
            $pairs = array_keys(JokeMaps::pairs());

            $this->line('Tied players needed: ' . JokeMaps::limit());
            $this->line('Maps barred:         ' . count($pairs) . ' (map and physics counted apart)');

            $rendered = RenderedVideo::whereNotNull('youtube_video_id')->get(['id', 'map_name', 'physics'])
                ->filter(fn ($v) => JokeMaps::isJoke($v->map_name, $v->physics));

            $pending = RenderedVideo::where('status', 'pending')->get(['id', 'map_name', 'physics'])
                ->filter(fn ($v) => JokeMaps::isJoke($v->map_name, $v->physics));

            $this->line('Already on YouTube:  ' . $rendered->count());
            $this->line('Waiting in queue:    ' . $pending->count());

            $show = (int) $this->option('show');

            if ($show > 0 && $pairs) {
                $this->newLine();

                foreach (array_slice($pairs, 0, $show) as $pair) {
                    [$map, $physics] = explode('|', $pair);
                    $this->line("  {$map} ({$physics})");
                }

                if (count($pairs) > $show) {
                    $this->line('  ... and ' . (count($pairs) - $show) . ' more.');
                }
            }

            if ($this->option('clean')) {
                if ($trial) {
                    $this->error('Not cleaning against a trial number. Save it with --set first.');

                    return self::FAILURE;
                }

                // Only what has not been rendered yet. A video already on the
                // channel is somebody's upload and is not thrown away here.
                $dropped = RenderedVideo::whereIn('id', $pending->pluck('id'))->delete();
                $this->newLine();
                $this->info("Dropped {$dropped} queued render(s) for these maps.");
                $this->line('Videos already on YouTube were left alone.');
            } elseif ($pending->count()) {
                $this->newLine();
                $this->warn('Run again with --clean to drop those ' . $pending->count() . ' queued renders.');
            }
        } finally {
            if ($trial) {
                SiteSetting::set('demome:tied_wr_limit', (string) $saved);
                JokeMaps::forget();
            }
        }

        return self::SUCCESS;
    }
}
