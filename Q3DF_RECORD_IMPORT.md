# q3df record import

Recent records come from the q3df.org JSON API. The scheduler runs `ImportLastMddRecordsJob` every minute. A queue worker has to be running, because the artisan commands only queue that job.

Set `Q3DF_API_KEY` in the environment, then refresh the config cache (`php artisan config:cache`) if production uses a cached config. The key is read as `config('services.q3df.key')`.

## Commands

### `php artisan scrape:records`

Queues `ImportLastMddRecordsJob`. Use this after a gap, or when the gap-alert email says to run it again. It takes no arguments. Output is written by the worker, not by the shell that dispatched the job.

`php artisan get:last:mdd:records` and `php artisan import:last:mdd:records` queue the same job.

### `php artisan import:mdd:records {api_key} {--page=0} {--per_page=10}`

Walks `GET /api/getRecords`, oldest record id first. This is the full archive. It is the wrong tool for catching up a few days of missed records.

Each successful page is its own database transaction. A failed request keeps the pages already imported and exits with status 1. Resume with the same `--page` the message prints. An empty `data` list is the last page.

Pages are 0-based. `per_page` below 10 is rejected by the API. Missing profiles are queued with `ScrapeProfile` after the page commits. Deactivated accounts are skipped by that profile job. The record row is still stored.

```bash
php artisan import:mdd:records "$Q3DF_API_KEY" --page=0 --per_page=1000
```

## How the minute job works

`ImportLastMddRecordsJob` calls `GET /api/getLastRecords`, newest first.

| | With `Q3DF_API_KEY` | Without a key |
| --- | --- | --- |
| `per_page` | 1000 | 100 |
| First page | 0 | 0 |
| Request timeout | 30 seconds | 30 seconds |

Anonymous callers are limited to 100 records per page and page 1. Page 2 without a key is HTTP 401. A key allows `per_page` 1000.

The job starts at page 0 and keeps going while a page still inserts or updates rows and the page is full. It stops when:

- the request fails (non-200 or bad JSON), or
- the page is empty, or
- the page inserts and updates nothing (overlap with rows already stored), or
- the page is shorter than `per_page` (end of the feed).

A page that mixes new rows and duplicates continues to the next page, so a gap larger than one page is filled. One extra all-duplicate page after the overlap is expected. One bad record is logged and skipped. The rest of the page still runs. Job timeout is 1200 seconds, and the scheduler uses `withoutOverlapping()`.

If the walk never reaches an existing row, fetched at least 20 records, and saw no duplicates, admins get a gap email. Check `Q3DF_API_KEY` and run `php artisan scrape:records` again. A failed first page logs a warning and does not send that email.

After each new record the job updates map ranks, the records cache, profile stats when an `MddProfile` already exists, and queues `ScrapeProfile` when it does not. It also queues rank notifications and the map rating recalc.

## Catching up a gap

Deploy this code, set `Q3DF_API_KEY`, refresh the config cache, and let the next minute run finish. Or queue it yourself:

```bash
php artisan scrape:records
```

The worker must be up. With a key, each page is up to 1000 records and the job continues until a page adds nothing.

## What was removed

The HTML scrape of `https://q3df.org/records?page=` is gone. These commands, models, and the `SCRAPING_GUIDE.md` file are gone with it:

- `scrape:records-fetch-new`
- `scrape:records-process-new`
- `scrape:records-api`
- `scrape:reset`
- `scrape:status`
- `scrape:kill`

`php artisan scrape:records` is still the catch-up command. It no longer accepts a start page or a page count, and it no longer reads HTML.

Migration `2026_11_17_000000_drop_scraper_tables` drops `scraped_records_queue`, `scraped_pages`, and `scraper_progress`. Run it on deploy. A job of the old `App\Jobs\ScrapeRecords` class still sitting on the queue will fail to unserialize after that deploy. Clear those jobs before switching the worker onto this code.

Server, map, forum, and profile scrapers are separate and still run.
