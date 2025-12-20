import { useRef } from 'react';

import Modal from './Modal';
import { formatDateTimeForInput } from './utils/dateUtils';

const AttemptModal = ({ attempt, onSave, onClose, isOpen }) => {
    const dateInputRef = useRef();
    const formRef = useRef();

    const handleModalOpen = () => {
        if (dateInputRef.current) {
            dateInputRef.current.focus();
        }
    };

    const handleSave = () => {
        const formData = new FormData(formRef.current);

        onSave({
            comment: formData.get('comment'),
            date: formData.get('date'),
        });
    };

    const handleKeyDown = (event) => {
        console.log('handleKeyDown', event.key);
        
        if (event.key === 'Enter') {
            event.preventDefault();
            handleSave();
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            onOpen={handleModalOpen}
            title="Edycja"
        >
            <form ref={formRef}>
                <div className="modal-body">
                    <div className="mb-3">
                        <label className="form-label">Utworzono</label>
                        <input
                            ref={dateInputRef}
                            name="date"
                            type="datetime-local"
                            className="form-control"
                            defaultValue={formatDateTimeForInput(attempt?.created)}
                            onKeyDown={handleKeyDown}
                        />
                    </div>
                    <div className="mb-2">
                        <label className="form-label">Komentarz</label>
                        <textarea
                            name="comment"
                            className="form-control"
                            rows="4"
                            placeholder="Dodaj komentarz do próby..."
                            defaultValue={attempt?.comment || ''}
                        />
                    </div>
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

export default AttemptModal; 
