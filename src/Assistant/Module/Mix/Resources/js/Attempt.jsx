import TrackEntry from './TrackEntry';
import formatSeconds from '@public/js/modules/format-seconds.js';
import { useDragReorder } from './hooks/useDragReorder';

const calculateTrackListDuration = (trackList) => {
    if (!trackList || trackList.length === 0) {
        return 0;
    }

    return trackList.reduce((total, trackEntry) => total + (trackEntry.track.length || 0), 0);
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

const Attempt = ({ attempt, highlightedTrackIndex, onHighlightTrack, onEditTrack, onAddTrack, onDeleteTrack, onEditAttempt, onReorderTracks }) => {
    const trackListLength = attempt.trackList.length;
    
    const { handlers, getItemState, isDragging } = useDragReorder(
        trackListLength,
        (fromIndex, toIndex) => onReorderTracks(attempt, fromIndex, toIndex)
    );

    const handleCardDragOver = (e) => {
        if (!isDragging) {
            return;
        }

        e.preventDefault();
    };

    const handleCardDrop = (e) => {
        if (!isDragging) {
            return;
        }

        e.preventDefault();
        handlers.onDrop();
    };

    return (
        <div 
            className="card mb-5"
            onDragOver={handleCardDragOver}
            onDrop={handleCardDrop}
        >
            <AttemptHeader
                attempt={attempt}
                onEdit={onEditAttempt}
            />

            <div className="card-body" onMouseLeave={() => onHighlightTrack(null)}>
                {trackListLength === 0 ? (
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
                            index={idx}
                            isHighlighted={highlightedTrackIndex === idx}
                            dragState={getItemState(idx, idx === trackListLength - 1)}
                            dragHandlers={handlers}
                            onEdit={() => onEditTrack(trackEntry, idx)}
                            onDelete={() => onDeleteTrack(attempt, trackEntry)}
                            onHighlight={() => onHighlightTrack(idx)}
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
};

export default Attempt;
