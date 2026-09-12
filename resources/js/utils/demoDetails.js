/**
 * Says what a demo actually is, in words.
 *
 * Everything here was already in the database - the browse list just showed
 * the raw column, so `VQ3.2` sat there meaning nothing to anyone who did not
 * already know the format. A player asked to see what a demo is before
 * spending a download on it, and this is the decoding half of that.
 */

import { t } from '@/utils/i18n';

// The mode word is a thunk rather than a string: this table is built once at
// module load, and a string here would freeze in whichever language happened
// to load first. Written as literal t() calls so lang:sync can see them - a
// key it cannot read as a literal never reaches the language files.
const GAMETYPES = {
    df: { mode: () => t('Defrag run'), online: false },
    mdf: { mode: () => t('Defrag run'), online: true },
    fc: { mode: () => t('Fastcap'), online: false },
    mfc: { mode: () => t('Fastcap'), online: true },
    fs: { mode: () => t('Freestyle'), online: false },
    mfs: { mode: () => t('Freestyle'), online: true },
    unlagged: { mode: () => t('Unlagged'), online: true },
};

/**
 * `gametype` is the mode word, with an `m` prefix for the online variant.
 * Unknown values keep their own spelling rather than being dropped - a demo
 * from a mode we have not seen is still worth listing.
 */
export const describeGametype = (gametype) => {
    if (!gametype) {
        return null;
    }

    const key = gametype.toLowerCase();
    const known = GAMETYPES[key];

    if (!known) {
        return {
            mode: gametype.toUpperCase(),
            online: key.startsWith('m'),
            label: gametype.toUpperCase(),
        };
    }

    const mode = known.mode();

    return {
        mode,
        online: known.online,
        label: known.online ? t(':mode, online', { mode }) : t(':mode, offline', { mode }),
    };
};

/**
 * `physics` carries more than the physics. The parser writes the demo's
 * `<mode>.<physics>[.<variant>]` string and we keep it from the physics
 * onwards, so `CPM.TR` is a run with a timereset (the defrag demo namer's
 * `.tr` flag, as DemoCleaner writes it) and `VQ3.2` is capture mode 2.
 */
export const describePhysics = (physics) => {
    if (!physics) {
        return null;
    }

    const [base, ...rest] = physics.split('.');
    const variant = rest.join('.').toUpperCase();
    const ctf = /^\d+$/.test(variant) ? Number(variant) : null;

    return {
        base: base.toUpperCase(),
        timereset: variant === 'TR',
        ctf,
        raw: physics,
    };
};

/**
 * What the parser noticed about the run. Deliberately descriptive rather than
 * a verdict: the notes name the setting, and the panel around them says these
 * are the things that were flagged. Which of them voids a record is a rule,
 * and rules live on /rules, not in a tooltip.
 */
// Thunks for the same reason as GAMETYPES above, and literal keys so the
// extractor can find them. The cvar names inside the sentences are cvar
// names, so they stay as they are in every language.
const VALIDITY_NOTES = {
    sv_cheats: () => t('Cheats were enabled on the server'),
    sv_fps: () => t('Server tickrate, normally 125'),
    df_mp_interferenceoff: () => t('Player interference setting, normally 3'),
    timescale: () => t('Game speed, normally 1'),
    com_maxfps: () => t('Client framerate cap, normally 125'),
    client_finish: () => t('The client never registered the finish'),
    tool_assisted: () => t('The run looks tool-assisted'),
    pmove_fixed: () => t('pmove_fixed, normally 1'),
    g_speed: () => t('Movement speed, normally 320'),
    g_gravity: () => t('Gravity, normally 800'),
    g_knockback: () => t('Knockback, normally 1000'),
    pmove_msec: () => t('pmove_msec, normally 8'),
    handicap: () => t('Handicap, normally 100'),
    g_killWallbug: () => t('Wallbug kill setting, normally 1'),
};

export const describeValidity = (validity) => {
    if (!validity || typeof validity !== 'object') {
        return [];
    }

    return Object.entries(validity).map(([key, value]) => ({
        key,
        // The parser stores everything as a float, so 1.0 and 120.0 come back
        // for what are conceptually a flag and a whole number.
        value: String(value).replace(/\.0$/, ''),
        note: VALIDITY_NOTES[key]?.() ?? null,
    }));
};
