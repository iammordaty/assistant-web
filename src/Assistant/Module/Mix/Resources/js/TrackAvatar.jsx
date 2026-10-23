import React from 'react';

// Inicjały i kolory awatara wylicza TrackAvatar.php (pole `avatar` utworu w API miksu)
const TrackAvatar = React.memo(({ track }) => {
    const { avatar } = track;

    const style = {
        '--ast-avatar-color': `var(--tblr-${avatar.color})`,
        '--ast-avatar-tint': `${avatar.tint}%`,
    };

    return (
        <span
            className={`avatar-initials rounded-2 ast-avatar ${track.found ? '' : 'opacity-50'}`}
            style={style}
            data-scale={avatar.scale ?? undefined}
        >
            {avatar.initials}
        </span>
    );
});

export default TrackAvatar;
