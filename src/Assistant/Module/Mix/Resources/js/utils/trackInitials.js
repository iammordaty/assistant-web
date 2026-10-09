// AI: te same reguły co TrackAvatar::getInitials() w PHP (nagłówek strony utworu); zmianę trzeba wprowadzić
// w obu miejscach

const getFirstCharacter = text => text.match(/[\p{L}\p{N}]/u)?.[0] ?? '';

/** Zwraca pierwszą literę lub cyfrę wykonawcy i tytułu, np. „Sash! - Encore Une Fois” → „SE” */
export const getTrackInitials = track => {
    const artistInitial = getFirstCharacter(track.artists.join(' '));
    const titleInitial = getFirstCharacter(track.title);

    return (artistInitial + titleInitial).toUpperCase();
};
