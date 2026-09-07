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

// A tint for a whole box about one physics: faint enough to sit under
// white text, but enough that the two halves of a week read as two things.
export const PHYSICS_TINT = {
    cpm: 'bg-violet-500/[0.06]',
    vq3: 'bg-sky-500/[0.06]',
};

export const physicsTint = (physics) => PHYSICS_TINT[physics] ?? '';

export const physicsBadge = (physics) => PHYSICS_BADGE[physics] ?? 'border-white/15 bg-white/10 text-gray-200';

export const physicsText = (physics) => PHYSICS_TEXT[physics] ?? 'text-gray-300';
