import TrackEntry from './TrackEntry';
import formatSeconds from '@public/js/modules/format-seconds.js';

const calculateTrackListDuration = (trackList) => {
    if (!trackList || trackList.length === 0) {
        return 0;
    }

    return trackList.reduce((total, trackEntry) => {
        const trackLength = trackEntry.track.length;

        return total + trackLength;
    }, 0);
};

const AttemptHeader = ({ attempt, onEdit }) => {
    const trackListDuration = calculateTrackListDuration(attempt.trackList);

    return (
        <div
            className="card-header cursor-pointer"
            onClick={onEdit}
            title="Kliknij, aby edytować próbę"
        >
            <ol className="breadcrumb">
                {attempt.comment && (
                    <li className="breadcrumb-item">
                        <span>{attempt.comment}</span>
                    </li>
                )}
                <li className="breadcrumb-item">
                    {formatSeconds(trackListDuration)}
                </li>
                <li className="breadcrumb-item">
                    {attempt.trackList.length} utworów
                </li>
            </ol>
        </div>
    );
};

const Attempt = ({ attempt, onEditTrack, onAddTrack, onDeleteTrack, onEditAttempt }) => (
    <div className="card mb-5">
        <AttemptHeader
            attempt={attempt}
            onEdit={onEditAttempt}
        />

        <div className="card-body">
            {attempt.trackList.length === 0 ? (
                <div className="empty p-3 opacity-75">
                    <p className="empty-title">Ta próba nie ma jeszcze utworów</p>
                    <p className="empty-subtitle text-secondary">
                        Kliknij "Dodaj utwór" poniżej, aby rozpocząć
                    </p>
                </div>
            ) : (
                attempt.trackList.map((trackEntry, idx) => (
                    <TrackEntry
                        key={trackEntry.track.guid + idx + (trackEntry.comments || []).length}
                        trackEntry={trackEntry}
                        onEdit={() => onEditTrack(trackEntry.track, trackEntry.comments || [], idx)}
                        onDelete={() => onDeleteTrack(attempt, trackEntry)}
                    />
                ))
            )}
        </div>

        <div className="card-footer text-center">
            <button className="btn btn-primary" onClick={onAddTrack}>
                Dodaj utwór
            </button>
        </div>
    </div>
);

export default Attempt;
