import { useRef } from 'react';
import { IconArrowBackUp, IconCaretUpDown, IconEdit, IconMessage, IconMoodSmile, IconTrash } from '@tabler/icons-react';

import TrackAvatar from './TrackAvatar';
import formatSeconds from '@public/js/modules/format-seconds.js';
import urlFor from '@public/js/modules/url-for.js';

const TrackEntry = ({ 
    trackEntry, 
    index,
    isHighlighted,
    dragState,
    dragHandlers,
    onEdit, 
    onDelete,
    onHighlight
}) => {
    const rowRef = useRef(null);
    const avatarRef = useRef(null);
    const { track, comments } = trackEntry;

    if (!track) {
        return null;
    }

    const handleDragStart = (e) => {
        if (rowRef.current && avatarRef.current) {
            const rowRect = rowRef.current.getBoundingClientRect();
            const avatarRect = avatarRef.current.getBoundingClientRect();
            e.dataTransfer.setDragImage(
                rowRef.current,
                e.clientX - rowRect.left,
                avatarRect.top - rowRect.top + avatarRect.height / 2
            );
        }
        e.dataTransfer.effectAllowed = 'move';
        dragHandlers.onDragStart(index);
    };

    const handleDragOver = (e) => {
        e.preventDefault();
        
        if (!rowRef.current) {
            return;
        }

        const rect = rowRef.current.getBoundingClientRect();
        
        dragHandlers.onDragOver(index, e.clientY < rect.top + rect.height * 0.20);
    };

    const handleDrop = (e) => {
        e.preventDefault();

        dragHandlers.onDrop();
    };

    const isCancel = dragState?.cancel;

    return (
        <div 
            ref={rowRef}
            className="row ast-track-item py-2 align-items-center rounded-3"
            data-highlighted={isHighlighted || undefined}
            data-dragging={dragState?.dragging || undefined}
            data-cancel={isCancel || undefined}
            data-indicator={dragState?.indicator}
            data-shift={dragState?.shift}
            onMouseEnter={onHighlight}
            onDoubleClick={onEdit}
            onDragOver={handleDragOver}
            onDrop={handleDrop}
        >
            <div className="col d-flex align-items-center">
                <div 
                    ref={avatarRef}
                    className="avatar track-avatar-hoverable me-3"
                    draggable
                    onDragStart={handleDragStart}
                    onDragEnd={dragHandlers.onDragEnd}
                >
                    <TrackAvatar track={track} />
                    <span className="avatar-drag-icon">
                        {isCancel ? (
                            <IconMoodSmile className="icon" stroke={1.5} />
                        ) : dragState?.dragging ? (
                            <IconArrowBackUp className="icon" stroke={1.5} />
                        ) : (
                            <IconCaretUpDown className="icon" stroke={1.5} />
                        )}
                    </span>
                </div>
                <div>
                    <div className="ast-track-name">
                        <a
                            className="text-reset"
                            href={urlFor('track.track.index', { guid: track.guid })}
                            title={track.name}
                        >
                            {track.name}
                        </a>
                    </div>
                    <div className="ast-track-meta text-muted small">
                        <ol className="breadcrumb">
                            <li className="breadcrumb-item">
                                <a
                                    className="text-reset"
                                    href={urlFor('search.advanced.index', { genre: track.genre })}
                                >
                                    {track.genre}
                                </a>
                            </li>
                            <li className="breadcrumb-item">
                                {formatSeconds(track.length)}
                            </li>
                            <li className="breadcrumb-item">
                                <a
                                    className="text-reset"
                                    href={urlFor('search.advanced.index', { bpm: track.bpm })}
                                >
                                    {track.bpm} BPM
                                </a>
                            </li>
                            <li className="breadcrumb-item">
                                <a
                                    className="text-reset"
                                    href={urlFor('search.advanced.index', { key: track.initialKey })}
                                >
                                    {track.initialKey}
                                </a>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>

            <div className="col-4">
                {comments.map((comment, idx) => (
                    <div key={idx} className="row comment d-flex align-items-center small mb-1">
                        <div className="col-2 text-end p-0">
                            {comment.time ? (
                                <span
                                    className={`badge font-monospace ${
                                        comment.type === 'cue'
                                            ? 'bg-success'
                                            : comment.type === 'stop'
                                                ? 'bg-danger'
                                                : 'bg-secondary'
                                    }`}
                                >
                                    {comment.displayTime || formatSeconds(comment.time)}
                                </span>
                            ) : (
                                <span className="text-muted">
                                    <IconMessage className="icon" stroke={1.5} />
                                </span>
                            )}
                        </div>
                        <div className="col">
                            <span className="text-muted">{comment.content}</span>
                        </div>
                    </div>
                ))}
            </div>

            <div className="col-1 d-flex justify-content-end">
                <div className="btn-list flex-nowrap gap-2 track-actions">
                    <button
                        className="btn btn-icon ast-track-action-edit-entry"
                        onClick={onEdit}
                        aria-label="Edytuj utwór"
                    >
                        <IconEdit className="icon" stroke={1.5} />
                    </button>
                    <button
                        className="btn btn-icon ast-track-action-delete"
                        onClick={onDelete}
                        aria-label="Usuń utwór"
                    >
                        <IconTrash className="icon" stroke={1.5} />
                    </button>
                </div>
            </div>
        </div>
    );
};

export default TrackEntry;
