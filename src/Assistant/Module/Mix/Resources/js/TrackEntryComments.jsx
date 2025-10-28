import { useRef } from 'react';
import { IconTrash, IconMessagePlus } from '@tabler/icons-react';

const TrackEntryComments = ({ comments, onAddComment, onDeleteComment }) => {
    const newCommentRef = useRef();

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleAddNewComment();
        }
    };

    const handleAddNewComment = () => {
        if (!newCommentRef.current) return;
        
        const typeSelect = newCommentRef.current.querySelector('[name="new-comment-type"]');
        const timeInput = newCommentRef.current.querySelector('[name="new-comment-time"]');
        const contentInput = newCommentRef.current.querySelector('[name="new-comment-content"]');
        
        const type = typeSelect?.value || '';
        const time = timeInput?.value || '';
        const content = contentInput?.value || '';
        
        if (!content.trim()) return;
        
        onAddComment({ type, time, content });
        
        // Reset form
        if (typeSelect) typeSelect.value = '';
        if (timeInput) timeInput.value = '';
        if (contentInput) contentInput.value = '';
    };

    return (
        <>
            <div className="row">
                <div className="col-3"><label className="form-label">Typ</label></div>
                <div className="col-2"><label className="form-label">Czas</label></div>
                <div className="col-6"><label className="form-label">Komentarz</label></div>
                <div className="col-1" />
            </div>

            {comments.map((comment, i) => (
                <div key={comment.id} className="row mb-3">
                    <Comment
                        index={i}
                        comment={comment}
                        displayTime={comment.displayTime}
                        onDelete={onDeleteComment}
                    />
                </div>
            ))}

            <div ref={newCommentRef} className="row mt-1 ast-new-comment-row">
                <Comment
                    onAddComment={handleAddNewComment}
                    onKeyDown={handleKeyDown}
                />
            </div>
        </>
    );
};

const Comment = ({ 
    comment, 
    index, 
    displayTime, 
    onDelete,
    onAddComment,
    onKeyDown
}) => {
    const isNew = !comment;
    
    return (
        <>
            <div className="col-3">
                <select 
                    className="form-select" 
                    name={isNew ? "new-comment-type" : `comment-${comment.id}-type`}
                    defaultValue={isNew ? "" : comment?.type}
                >
                    <option value=""></option>
                    <option value="cue">Start</option>
                    <option value="stop">Stop</option>
                </select>
            </div>
            <div className="col-2">
                <input 
                    type="text"
                    className="form-control" 
                    name={isNew ? "new-comment-time" : `comment-${comment.id}-time`}
                    defaultValue={isNew ? "" : displayTime || ""}
                    onKeyDown={onKeyDown}
                    placeholder="np. 1:30" 
                />
            </div>
            <div className="col-6">
                <input 
                    type="text"
                    className="form-control" 
                    name={isNew ? "new-comment-content" : `comment-${comment.id}-content`}
                    defaultValue={isNew ? "" : comment?.content}
                    onKeyDown={onKeyDown}
                    placeholder={isNew ? "Dodaj komentarz..." : ""} 
                />
            </div>
            <div className="col-1">
                {isNew ? (
                    <button 
                        type="button" 
                        className="btn btn-icon btn-success-outline" 
                        onClick={onAddComment}
                        title="Dodaj komentarz"
                        aria-label="Dodaj komentarz"
                    >
                        <IconMessagePlus className="icon" stroke={1.5} />
                    </button>
                ) : (
                    <button 
                        type="button" 
                        className="btn btn-icon" 
                        onClick={() => onDelete(index)}
                        aria-label="Usuń komentarz"
                    >
                        <IconTrash className="icon" stroke={1.5} />
                    </button>
                )}
            </div>
        </>
    );
};

export default TrackEntryComments;