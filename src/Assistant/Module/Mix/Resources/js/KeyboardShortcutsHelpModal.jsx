import Modal from './Modal';

const shortcuts = [
    { keys: ['e'], description: 'Edytuje zaznaczony utwór lub próbę' },
    { keys: ['e', 'm'], description: 'Edytuje dane miksu', separator: '→' },
    { keys: ['e', 'a'], description: 'Edytuje wybraną próbę', separator: '→' },
    { keys: ['a', 't'], description: 'Dodaje utwór do próby' },
    { keys: ['n', 'p'], description: 'Dodaje nową próbę' },
    { keys: ['s'], description: 'Przełącza na pierwszą próbę' },
    { keys: ['s', 's'], description: 'Przełącza na ostatnią próbę', separator: '→' },
    { keys: ['s', '1-99'], description: 'Przełącza na próbę o danym numerze', separator: '→' },
    { keys: ['l'], description: 'Przechodzi do listy miksów' },
    { keys: ['g', 'l'], description: 'Przechodzi do listy miksów', separator: '→' },
    { keys: ['↑'], description: 'Zaznacza poprzedni utwór' },
    { keys: ['↓'], description: 'Zaznacza następny utwór' },
    { keys: ['Enter'], description: 'Edytuje zaznaczony utwór' },
    { keys: ['Shift', 'Delete'], description: 'Usuwa zaznaczony utwór', separator: '+' },
    { keys: ['Esc'], description: 'Zamyka okno modalne' },
    { keys: ['?', 'k', 'h'], description: 'Pokazuje tę pomoc skrótów klawiaturowych' },
];

const ShortcutKeys = ({ keys, separator = '/' }) => (
    <span className="d-inline-flex align-items-center gap-1">
        {keys.map((key, idx) => (
            <span key={idx} className="d-inline-flex align-items-center gap-1">
                {idx > 0 && <span className="text-muted mx-1">{separator}</span>}
                <kbd>{key}</kbd>
            </span>
        ))}
    </span>
);

const KeyboardShortcutsHelpModal = ({ isOpen, onClose }) => (
    <Modal isOpen={isOpen} onClose={onClose}>
        <div className="modal-body">
            <table className="table table-vcenter">
                <thead>
                    <tr>
                        <th>Skrót</th>
                        <th>Akcja</th>
                    </tr>
                </thead>
                <tbody>
                    {shortcuts.map((shortcut, idx) => (
                        <tr key={idx}>
                            <td>
                                <ShortcutKeys keys={shortcut.keys} separator={shortcut.separator} />
                            </td>
                            <td>{shortcut.description}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    </Modal>
);

export default KeyboardShortcutsHelpModal;
