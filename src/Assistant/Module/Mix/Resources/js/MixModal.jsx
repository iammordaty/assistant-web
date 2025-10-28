import { useRef, useState, useEffect } from 'react';
import Modal from './Modal';

const MixModal = ({
    name = '',
    description = '',
    comment = '',
    created = '',
    modified = '',
    performed = '',
    onSave,
    onClose,
    isOpen
}) => {
    const [currentName, setCurrentName] = useState(name);
    const nameInputRef = useRef();
    const formRef = useRef();

    // Update title when modal opens with new data
    useEffect(() => {
        if (isOpen) {
            setCurrentName(name);
        }
    }, [isOpen, name]);

    const handleModalOpen = () => {
        if (nameInputRef.current) {
            nameInputRef.current.focus();
            nameInputRef.current.setSelectionRange(nameInputRef.current.value.length, nameInputRef.current.value.length);
        }
    };

    const handleNameChange = (e) => {
        setCurrentName(e.target.value);
    };

    const handleSave = () => {
        const formData = new FormData(formRef.current);
        const data = {
            name: formData.get('name') || '',
            description: formData.get('description') || '',
            comment: formData.get('comment') || '',
            created: formData.get('created') || '',
            modified: formData.get('modified') || '',
            performed: formData.get('performed') || ''
        };
        onSave(data);
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            onOpen={handleModalOpen}
            title={currentName || 'Nowy miks'}
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
                        defaultValue={description}
                    />
                </div>
                <div className="mb-3">
                    <div className="row">
                        <div className="col-4">
                            <label className="form-label">Utworzono</label>
                            <input
                                name="created"
                                type="date"
                                className="form-control"
                                defaultValue={created}
                            />
                        </div>
                        <div className="col-4">
                            <label className="form-label">Ostatnia zmiana</label>
                            <input
                                name="modified"
                                type="date"
                                className="form-control"
                                defaultValue={modified}
                            />
                        </div>
                        <div className="col-4">
                            <label className="form-label">Data zagrania</label>
                            <input
                                name="performed"
                                type="date"
                                className="form-control"
                                defaultValue={performed}
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
                        defaultValue={comment}
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