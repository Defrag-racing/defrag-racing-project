/**
 * How the two physics are told apart by colour, site-wide: CPM is violet,
 * VQ3 is blue. The servers page has drawn its CPM and VQ3 borders that way
 * since it got a card layout, and a badge that says CPM in blue somewhere
 * else reads as a different thing.
 */
export const PHYSICS_BADGE = {
    cpm: 'border-violet-400/50 bg-violet-500/20 text-violet-100',
    vq3: 'border-sky-400/50 bg-sky-500/20 text-sky-100',
};

export const PHYSICS_TEXT = {
    cpm: 'text-violet-300',
    vq3: 'text-sky-300',
};

export const physicsBadge = (physics) => PHYSICS_BADGE[physics] ?? 'border-white/15 bg-white/10 text-gray-200';

export const physicsText = (physics) => PHYSICS_TEXT[physics] ?? 'text-gray-300';
