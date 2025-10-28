import TrackEntry from './TrackEntry';
import formatSeconds from '@public/js/modules/format-seconds.js';

const calculateTrackListDuration = (trackList) => {
    if (!trackList || trackList.length === 0) {
        return 0;
    }

    return trackList.reduce((total, trackEntry) => {
        const trackLength = trackEntry.track?.length || 0;
        return total + trackLength;
    }, 0);
};

const AttemptHeader = ({ attempt, onEdit, onSave }) => {
    const trackListDuration = calculateTrackListDuration(attempt.trackList);

    const handleEditClick = (e) => {
        if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
            return;
        }
        e.stopPropagation();
        onEdit(attempt.number, attempt.comment, attempt.created);
    };

    const handleSaveClick = (e) => {
        e.stopPropagation();
        onSave(attempt.number);
    };

    return (
        <div
            className="card-header ast-attempt-header position-relative cursor-pointer"
            onClick={handleEditClick}
            title="Kliknij, aby edytować dane próby"
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
            <div className="card-actions attempt-header-actions">
                <button
                    className="btn attempt-action-button"
                    onClick={handleSaveClick}
                    aria-label="Zapisz próbę"
                >
                    Zapisz próbę
                </button>
            </div>
        </div>
    );
};

const Attempt = ({ attempt, isActive, onEditTrack, onAddTrack, onDeleteTrack, onEditAttempt, onSaveAttempt }) => {
    return (
        <div className={`card mb-5 ${!isActive ? 'd-none' : ''}`}>
            <AttemptHeader
                attempt={attempt}
                onEdit={onEditAttempt}
                onSave={onSaveAttempt}
            />

            <div className="card-body">
                {attempt.trackList.map((trackEntry, idx) => (
                    <TrackEntry
                        key={idx}
                        trackEntry={trackEntry}
                        onEdit={() => onEditTrack(trackEntry.track, trackEntry.comments || [], idx, attempt.number)}
                        onDelete={() => onDeleteTrack(attempt, trackEntry)}
                    />
                ))}
            </div>

            {isActive && (
                <div className="card-footer text-center">
                    <button className="btn btn-primary" onClick={() => onAddTrack(attempt.number)}>
                        Dodaj nowy utwór
                    </button>
                </div>
            )}
        </div>
    );
};

export default Attempt;
