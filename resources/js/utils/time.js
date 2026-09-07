import { ref } from 'vue';
import { t, currentLocale } from '@/utils/i18n';

/**
 * How a run time is written, site-wide.
 *
 * Defrag's own timer prints MM:SS:mmm, but most of the community reads a
 * colon before the milliseconds as a third clock field and expects a decimal
 * point there instead: minutes:seconds.milliseconds. That is the default
 * since 2026-09-07, when it was asked for as a wish; `colon` stays as a
 * preference for anyone who wants the engine's own punctuation.
 *
 * A ref, not a plain variable, so a component that renders a time re-renders
 * when the preference changes rather than keeping the old punctuation until
 * the next full page load.
 */
const separator = ref('.');

/** `dot` (default) or `colon`, what the engine prints. */
export const setTimeFormat = (format) => {
    separator.value = format === 'colon' ? ':' : '.';
};

export const timeSeparator = () => separator.value;

const padZero = (n) => n.toString().padStart(2, '0');

/**
 * Milliseconds as the game writes them.
 *
 * Defrag's smallest practical run is sub-minute and the largest pushes a
 * couple of hours, but the engine only ever displays MM:SS.mmm - minutes roll
 * past 60 and there is never an "H:" prefix. Match that: a 1:30:45.123
 * hh:mm:ss display would confuse players used to reading "90:45.123" off the
 * in-game timer.
 */
export const formatTime = (milliseconds) => {
    milliseconds = Math.max(0, milliseconds);

    const minutes = Math.floor(milliseconds / 60000);
    milliseconds %= 60000;
    const seconds = Math.floor(milliseconds / 1000);
    milliseconds %= 1000;

    const head = minutes > 0 ? `${padZero(minutes)}:` : '';

    return `${head}${padZero(seconds)}${separator.value}${milliseconds.toString().padStart(3, '0')}`;
};

/**
 * How long ago something happened, in the reader's language.
 *
 * Anything older than a month is a date rather than a count: "47d ago" is not
 * how anybody reads a date that far back, and the locale's own date format
 * says it better than a number of days does.
 */
export const timeAgo = (date) => {
    if (!date) return '';

    const then = new Date(date);
    const seconds = Math.floor((Date.now() - then.getTime()) / 1000);

    if (seconds < 60) return t('just now');
    if (seconds < 3600) return t(':count m ago', { count: Math.floor(seconds / 60) });
    if (seconds < 86400) return t(':count h ago', { count: Math.floor(seconds / 3600) });
    if (seconds < 2592000) return t(':count d ago', { count: Math.floor(seconds / 86400) });

    return then.toLocaleDateString(currentLocale(), { year: 'numeric', month: 'short', day: 'numeric' });
};
