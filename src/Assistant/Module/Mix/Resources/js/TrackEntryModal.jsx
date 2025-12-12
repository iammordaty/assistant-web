import { useEffect, useRef, useState, useCallback } from 'react';
import TrackEntryComments from './TrackEntryComments';
import Modal from './Modal';
import initAutocompleter from '@public/js/modules/autocomplete.js';
import formatSeconds from '@public/js/modules/format-seconds.js';
import toSeconds from '@public/js/modules/to-seconds.js';

let commentIdCounter = 1;

const createComment = (type, content, timeString) => {
    const time = timeString ? toSeconds(timeString) : null;
    const displayTime = time ? formatSeconds(time) : timeString || '';
    return { 
        id: `comment-${commentIdCounter++}`,
        type, 
        content, 
        time, 
        displayTime 
    };
};

const sortComments = (comments) => {
    return [...comments].sort((a, b) => {
        if (!a.time && !b.time) return 0;
        if (!a.time) return 1;
        if (!b.time) return -1;
        return a.time - b.time;
    });
};

const prepareCommentsForModal = (comments) => {
    return comments.map(comment => ({
        ...comment,
        id: comment.id || `comment-${commentIdCounter++}`, // Dodaj ID jeśli nie ma
        displayTime: comment.time ? formatSeconds(comment.time) : ''
    }));
};

const TrackEntryModal = ({ track = null, comments = [], autocompleteUrl, onSave, onClose, isOpen }) => {
    const [currentTrack, setCurrentTrack] = useState(track);
    const [trackComments, setTrackComments] = useState([]);

    const inputRef = useRef();
    const formRef = useRef();

    useEffect(() => {
        if (isOpen) {
            setCurrentTrack(track);
            setTrackComments(sortComments(prepareCommentsForModal(comments)));
            
            if (inputRef.current) {
                if (track === null) {
                    inputRef.current.value = '';
                    inputRef.current.removeAttribute('data-track');
                } else {
                    inputRef.current.value = track.name || '';
                }
                
                initAutocompleter($(inputRef.current), setCurrentTrack);
            }
        }
    }, [isOpen, track, comments, autocompleteUrl]);

    const addComment = useCallback((commentData) => {
        if (!commentData.content.trim()) return false;

        const newComment = createComment(commentData.type, commentData.content, commentData.time);
        setTrackComments(prev => sortComments([...prev, newComment]));
        return true;
    }, []);

    const deleteComment = useCallback((indexToRemove) => {
        setTrackComments(prev => prev.filter((_, i) => i !== indexToRemove));
    }, []);

    const handleModalOpen = useCallback(() => {
        if (inputRef.current && track === null) {
            inputRef.current.focus();
        }
    }, [track]);

    const handleSave = () => {
        let trackToSave = currentTrack;
        if (!trackToSave && inputRef.current) {
            const trackData = inputRef.current.getAttribute('data-track');
            if (trackData) {
                try {
                    trackToSave = JSON.parse(trackData);
                } catch (e) {
                    console.error('Failed to parse track data:', e);
                }
            }
        }

        if (!trackToSave) {
            if (inputRef.current) {
                inputRef.current.focus();
            }
            return;
        }

        // Collect data from uncontrolled comment inputs
        const updatedComments = [];
        if (formRef.current) {
            const formData = new FormData(formRef.current);
            
            trackComments.forEach((comment) => {
                const type = formData.get(`comment-${comment.id}-type`) || comment.type;
                const timeDisplay = formData.get(`comment-${comment.id}-time`) || comment.displayTime;
                const content = formData.get(`comment-${comment.id}-content`) || comment.content;
                const time = timeDisplay ? toSeconds(timeDisplay) : comment.time;
                
                updatedComments.push({ type, content, time });
            });
        }

        onSave({ track: trackToSave, comments: updatedComments });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            onOpen={handleModalOpen}
            title={currentTrack?.name || 'Nowy utwór'}
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
                            defaultValue={currentTrack?.name || ''}
                            data-url={autocompleteUrl}
                        />
                    </div>

                    <TrackEntryComments
                        comments={trackComments}
                        onAddComment={addComment}
                        onDeleteComment={deleteComment}
                    />
                </div>

                <div className="modal-footer">
                    <button type="button" className="btn btn-success px-5" onClick={handleSave}>
                        OK
                    </button>
                </div>
            </form>
        </Modal>
    );
};

export default TrackEntryModal;
