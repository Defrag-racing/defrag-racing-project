<?php

namespace App\Services\Comps;

use App\Models\SiteSetting;
use Carbon\CarbonTimeZone;

/**
 * The knobs an admin can turn, read through SiteSetting so they survive a
 * deploy and need no migration to change.
 *
 * The defaults are the agreed schedule: a weekly starts Sunday at 20:00 Prague
 * time and runs to the next Sunday at 20:00, and voting for the following
 * week closes on the Saturday, a day before the map is played.
 *
 * That day of daylight is deliberate. It leaves room for a preview render to
 * finish, and it lets people fetch the map before it matters.
 */
class CompSettings
{
    public const KEY_TIMEZONE = 'comps_timezone';
    public const KEY_START_DOW = 'comps_weekly_start_dow';
    public const KEY_START_TIME = 'comps_weekly_start_time';
    public const KEY_VOTING_LEAD_HOURS = 'comps_voting_lead_hours';
    public const KEY_POOL_SIZE = 'comps_pool_size';
    public const KEY_ENABLED = 'comps_weekly_enabled';
    public const KEY_PRIZE_EUR = 'comps_prize_eur';
    public const KEY_CONTACT_USER_ID = 'comps_contact_user_id';

    /** Prague, not UTC. In UTC the hour would drift by one across the year. */
    public function timezone(): CarbonTimeZone
    {
        return CarbonTimeZone::create(SiteSetting::get(self::KEY_TIMEZONE, 'Europe/Prague'));
    }

    /** 0 = Sunday, matching Carbon's dayOfWeek. */
    public function startDayOfWeek(): int
    {
        return (int) SiteSetting::get(self::KEY_START_DOW, '0');
    }

    public function startTime(): string
    {
        return (string) SiteSetting::get(self::KEY_START_TIME, '20:00');
    }

    /**
     * How long before a round starts its ballot closes. A day by default, so
     * Saturday 20:00 for a Sunday 20:00 start.
     */
    public function votingLeadHours(): int
    {
        return (int) SiteSetting::get(self::KEY_VOTING_LEAD_HOURS, '24');
    }

    public function poolSize(): int
    {
        return (int) SiteSetting::get(self::KEY_POOL_SIZE, (string) CandidateSelector::POOL_SIZE);
    }

    /**
     * Off by default. Nothing schedules itself into existence until somebody
     * says so in the admin panel - a half-configured install should not start
     * running competitions on its own.
     */
    public function weeklyEnabled(): bool
    {
        return SiteSetting::getBool(self::KEY_ENABLED, false);
    }

    /**
     * What a weekly pays its winners, in euro, per physics.
     *
     * A setting rather than a constant because the number is a promise made to
     * players and the person making it should be able to change it without a
     * deploy. Zero hides the prize from the page entirely, which is what an
     * unfunded week should look like.
     */
    public function prizeEur(): int
    {
        return (int) SiteSetting::get(self::KEY_PRIZE_EUR, '5');
    }

    /**
     * Who "tell the admin" points at. A setting rather than a literal in the
     * page so the person who runs comps can stop being the person who runs
     * comps without a frontend change.
     */
    public function contactUserId(): int
    {
        return (int) SiteSetting::get(self::KEY_CONTACT_USER_ID, '8');
    }
}
