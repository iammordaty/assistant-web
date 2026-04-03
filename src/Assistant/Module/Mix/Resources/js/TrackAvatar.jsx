import React, { useMemo } from 'react';

const getAvatarClass = track => {
    let intensity = 0;
    let baseColor = 'blue';

    if (track.bpm >= 140) {
        intensity += 2;
    } else if (track.bpm >= 130) {
        intensity += 1;
    }

    const genre = track.genre;

    if (['Hard Techno', 'Techno'].includes(genre)) {
        intensity += 2;
        baseColor = 'red';
    } else if (['Trance', 'Progressive Trance', 'Progressive House'].includes(genre)) {
        baseColor = 'cyan';
    } else if (['House', 'Deep House', 'Tech House'].includes(genre)) {
        intensity += 1;
        baseColor = 'blue';
    } else if (['Electro House'].includes(genre)) {
        intensity += 1;
        baseColor = 'azure';
    }

    const key = track.initialKey;
    if (/^[0-9]+[AB]$/.test(key)) {
        if (key.endsWith('A')) {
            intensity += 1;
        }
    }

    if (intensity >= 3) {
        return `bg-${baseColor} text-${baseColor}-fg`;
    } else if (intensity >= 1) {
        return `bg-${baseColor}-lt text-${baseColor}`;
    }

    return `text-${baseColor} bg-transparent`;
};

const getTrackInitials = track => {
    const leftWords = track.artists.join(' ').split(' ');
    const rightWords = track.title.split(' ');

    if (leftWords.length > 1) {
        return ((leftWords[0][0] ?? '') + (leftWords[1][0] ?? '')).toUpperCase();
    }

    if (rightWords.length > 1) {
        return ((rightWords[0][0] ?? '') + (rightWords[1][0] ?? '')).toUpperCase();
    }

    return ((leftWords[0]?.[0] ?? '') + (rightWords[0]?.[0] ?? '')).toUpperCase();
};

// fixme: to jest słabe i źle działa, ale getTrackInitials także
const getGuidInitials = (guid) => {
    const compact = guid.replace(/-/g, '');

    return compact.length >= 2
        ? compact.slice(0, 2).toUpperCase()
        : '?';
};

const TrackAvatar = React.memo(({ track }) => {
    const className = useMemo(() => {
        if (!track.found) {
            return 'avatar-initials rounded-2 bg-secondary-lt text-secondary opacity-50';
        }

        return `avatar-initials rounded-2 ${getAvatarClass(track)}`;
    }, [track.found, track.bpm, track.genre, track.initialKey]);

    const initials = useMemo(() => {
        if (!track.found) {
            return getGuidInitials(track.guid);
        }

        return getTrackInitials(track);
    }, [track.found, track.guid, track.artists, track.title]);

    return <span className={className}>{initials}</span>;
});

export default TrackAvatar;
