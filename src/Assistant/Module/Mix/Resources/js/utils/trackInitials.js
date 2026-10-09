/**
 * AI: te same reguły co TrackAvatar::getInitials() w PHP (nagłówek strony utworu); zmianę trzeba wprowadzić
 * w obu miejscach. Pierwsza litera lub cyfra wykonawcy i pierwsza litera lub cyfra tytułu,
 * np. „Sash! - Encore Une Fois” → „SE”, „Jens - '99 Psycho Strings” → „J9”.
 */
const firstCharacter = text => text.match(/[\p{L}\p{N}]/u)?.[0] ?? '';

export const getTrackInitials = (artists, title) =>
    (firstCharacter(artists.join(' ')) + firstCharacter(title)).toUpperCase();
