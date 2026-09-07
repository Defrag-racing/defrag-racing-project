/**
 * Who on a server is streaming right now, and where to watch them.
 *
 * `is_live` is refreshed every two minutes by twitch:check-live-status. The
 * channel name is what the player typed into their settings, which is
 * sometimes a whole address, so the channel is read out of the last part of
 * it and anything that is not a Twitch login is treated as no channel at all.
 */

export const twitchChannel = (profile) => {
    const raw = (profile?.twitch_name ?? '').trim();

    if (! raw) {
        return null;
    }

    const fromUrl = raw.match(/twitch\.tv\/([^/?#\s]+)/i);
    const name = (fromUrl ? fromUrl[1] : raw).replace(/^@/, '').replace(/\/+$/, '');

    return /^[a-zA-Z0-9_]{4,25}$/.test(name) ? name : null;
};

export const twitchUrl = (channel) => `https://www.twitch.tv/${channel}`;

/** A player row from the server list: live, and with a channel to point at. */
export const isStreaming = (player) => Boolean(player?.profile?.is_live && twitchChannel(player.profile));

/**
 * Everyone streaming on a server, players and their spectators alike. A
 * spectator streaming a top player's run is as much a stream as the run.
 */
export const streamersOn = (server) => {
    const out = [];

    for (const player of server?.online_players ?? []) {
        if (isStreaming(player)) {
            out.push(player);
        }

        for (const spectator of player.spectators ?? []) {
            if (isStreaming(spectator)) {
                out.push(spectator);
            }
        }
    }

    return out;
};

/** A nick without its colour codes, for a sentence rather than a row. */
export const plainName = (name) => (name ?? '').replace(/\^\d|\^x[\da-fA-F]{2}|\^[\da-fA-F]{6}/g, '');
