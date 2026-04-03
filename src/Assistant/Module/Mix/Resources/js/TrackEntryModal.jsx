import { useEffect, useRef, useState, useCallback } from 'react';
import TrackEntryComments from './TrackEntryComments';
import Modal from './Modal';
import initAutocompleter from '@public/js/modules/autocomplete.js';
import formatSeconds from '@public/js/modules/format-seconds.js';
import toSeconds from '@public/js/modules/to-seconds.js';

let commentIdCounter = 1;

const nextId = () => `comment-${commentIdCounter++}`;

const normalizeComment = (comment) => ({
    ...comment,
    id: comment.id ?? nextId(),
    displayTime: comment.time ? formatSeconds(comment.time) : ''
});

const sortByTime = (items) =>
    [...items].sort((a, b) => {
        if (!a.time && !b.time) return 0;
        if (!a.time) return 1;
        if (!b.time) return -1;

        return a.time - b.time;
    });

const TrackEntryModal = ({ trackEntry, autocompleteUrl, onSave, onClose, isOpen }) => {
    const [track, setTrack] = useState(null);
    const [comments, setComments] = useState([]);

    const inputRef = useRef(null);
    const formRef = useRef(null);

    const title = track?.name ?? 'Nowy utwór';

    const initializeState = useCallback(() => {
        const initialTrack = trackEntry?.track ?? null;
        const initialComments = trackEntry?.comments ?? [];

        setTrack(initialTrack);
        setComments(sortByTime(initialComments.map(normalizeComment)));

        if (!inputRef.current) {
            return;
        }

        inputRef.current.value = initialTrack?.name ?? '';

        if (!initialTrack?.found) {
            inputRef.current.removeAttribute('data-track');
        }

        initAutocompleter($(inputRef.current), setTrack);

        setTimeout(() => {
            if (inputRef.current) {
                inputRef.current.focus();
                inputRef.current.select();
            }
        }, 0);
    }, [trackEntry]);

    useEffect(() => {
        if (isOpen) initializeState();
    }, [isOpen, initializeState]);

    const addComment = useCallback(({ type, content, time }) => {
        const trimmed = content.trim();
        if (!trimmed) return false;

        const timeSeconds = time ? toSeconds(time) : null;

        const newComment = {
            id: nextId(),
            type,
            content: trimmed,
            time: timeSeconds,
            displayTime: timeSeconds ? formatSeconds(timeSeconds) : time || ''
        };

        setComments((prev) => sortByTime([...prev, newComment]));

        return true;
    }, []);

    const deleteComment = useCallback((index) => {
        setComments((prev) => prev.filter((_, i) => i !== index));
    }, []);

    const resolveTrackForSave = () => {
        if (track) return track;

        const el = inputRef.current;
        if (!el) return null;

        const raw = el.getAttribute('data-track');
        if (!raw) return null;

        try {
            return JSON.parse(raw);
        } catch {
            return null;
        }
    };

    const collectCommentsFromForm = () => {
        const form = formRef.current;
        
        if (!form) {
            return [];
        }

        const formData = new FormData(form);

        return comments.map((comment) => {
            const type = formData.get(`comment-${comment.id}-type`) ?? comment.type;
            const content = formData.get(`comment-${comment.id}-content`) ?? comment.content;
            const timeStr = formData.get(`comment-${comment.id}-time`) ?? comment.displayTime;
            const time = timeStr ? toSeconds(timeStr) : comment.time;

            return { type, content, time };
        });
    };

    const handleSave = () => {
        const trackToSave = resolveTrackForSave();

        if (!trackToSave?.guid) {
            inputRef.current?.focus();
            return;
        }

        const preparedComments = collectCommentsFromForm();

        onSave({ track: trackToSave, comments: preparedComments });
    };

    const isTypeaheadOpen = () => {
        const menu = inputRef.current?.parentElement?.querySelector('.typeahead.dropdown-menu');

        return menu && menu.style.display !== 'none' && menu.querySelector('.active');
    };

    const handleKeyDown = (event) => {
        if (event.key === 'Enter' && !isTypeaheadOpen()) {
            event.preventDefault();
            handleSave();
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={title}
            className="modal-lg"
        >
            <form ref={formRef}>
                <div className="modal-body">
                    <div className="mb-3">
                        <label className="form-label">Utwór</label>
                        <input
                            ref={inputRef}
                            type="text"
                            className="form-control"
                            placeholder="Wprowadź nazwę wykonawcy lub tytuł utworu"
                            autoComplete="off"
                            data-url={autocompleteUrl}
                            onKeyDown={handleKeyDown}
                        />
                    </div>

                    <TrackEntryComments
                        comments={comments}
                        onAddComment={addComment}
                        onDeleteComment={deleteComment}
                    />
                </div>

                <div className="modal-footer">
                    <button
                        type="button"
                        className="btn btn-success px-5"
                        onClick={handleSave}
                    >
                        OK
                    </button>
                </div>
            </form>
        </Modal>
    );
};

export default TrackEntryModal;
