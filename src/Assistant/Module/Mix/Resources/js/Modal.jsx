import { useEffect, useRef } from 'react';

const Modal = ({ 
    isOpen, 
    onClose, 
    onOpen,
    title, 
    children,
    className = '',
    ...props 
}) => {
    const modalRef = useRef();

    // Call onOpen when modal opens
    useEffect(() => {
        if (isOpen && onOpen) {
            onOpen();
        }
    }, [isOpen, onOpen]);

    // Modal keyboard shortcuts
    useEffect(() => {
        if (!isOpen) return;

        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                onClose();
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen, onClose]);

    const handleBackdropClick = (e) => {
        if (e.target === modalRef.current) {
            onClose();
        }
    };

    if (!isOpen) {
        return null;
    }

    const modalClasses = `modal-dialog modal-dialog-centered ${className}`.trim();

    return (
        <div
            className="modal show"
            tabIndex="-1"
            style={{ display: 'block' }}
            aria-modal="true"
            ref={modalRef}
            onClick={handleBackdropClick}
            {...props}
        >
            <div className={modalClasses}>
                <div className="modal-content" onClick={(e) => e.stopPropagation()}>
                    <div className="modal-header">
                        <h5 className="modal-title">{title}</h5>
                        <button
                            type="button"
                            className="btn-close"
                            onClick={onClose}
                            aria-label="Close"
                        />
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
};

export default Modal; 