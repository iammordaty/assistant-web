import { useEffect, useRef, useState } from 'react';
import Modal from './Modal';
import { formatDateTimeForInput } from './utils/dateUtils';

const MixModal = ({
    mix,
    onSave,
    onClose,
    isOpen
}) => {
    const [currentName, setCurrentName] = useState(mix?.name || '');
    const nameInputRef = useRef();
    const formRef = useRef();

    useEffect(() => {
        if (isOpen && mix) {
            setCurrentName(mix.name || '');
        }
    }, [isOpen, mix]);

    const handleModalOpen = () => {
        if (nameInputRef.current) {
            nameInputRef.current.focus();
            nameInputRef.current.setSelectionRange(nameInputRef.current.value.length, nameInputRef.current.value.length);
        }
    };

    const handleNameChange = (e) => setCurrentName(e.target.value);

    const handleSave = () => {
        const formData = new FormData(formRef.current);

        onSave({
            name: formData.get('name'),
            description: formData.get('description'),
            comment: formData.get('comment'),
            created: formData.get('created'),
            modified: formData.get('modified'),
            performed: formData.get('performed')
        });
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            onOpen={handleModalOpen}
            title={currentName}
            className="modal-lg"
        >
            <form ref={formRef}>
                <div className="modal-body">
                    <div className="mb-3">
                        <label className="form-label">Nazwa</label>
                        <input
                            ref={nameInputRef}
                            name="name"
                            type="text"
                            className="form-control"
                            placeholder="Nazwa miksu..."
                            value={currentName}
                            onChange={handleNameChange}
                        />
                    </div>
                    <div className="mb-3">
                        <label className="form-label">Opis</label>
                        <textarea
                            name="description"
                            className="form-control"
                            rows="3"
                            placeholder="Wprowadź opis miksu..."
                            defaultValue={mix?.description || ''}
                        />
                    </div>
                    <div className="mb-3">
                        <div className="row">
                            <div className="col-4">
                                <label className="form-label">Utworzono</label>
                                <input
                                    name="created"
                                    type="datetime-local"
                                    className="form-control"
                                    defaultValue={formatDateTimeForInput(mix?.created)}
                                />
                            </div>
                            <div className="col-4">
                                <label className="form-label">Ostatnia zmiana</label>
                                <input
                                    name="modified"
                                    type="datetime-local"
                                    className="form-control"
                                    defaultValue={formatDateTimeForInput(mix?.modified)}
                                />
                            </div>
                            <div className="col-4">
                                <label className="form-label">Data zagrania</label>
                                <input
                                    name="performed"
                                    type="datetime-local"
                                    className="form-control"
                                    defaultValue={formatDateTimeForInput(mix?.performed)}
                                />
                            </div>
                        </div>
                    </div>
                    <div className="mb-3">
                        <label className="form-label">Komentarz</label>
                        <textarea
                            name="comment"
                            className="form-control"
                            rows="4"
                            placeholder="Dodaj komentarz do miksu..."
                            defaultValue={mix?.comment || ''}
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

export default MixModal; 
