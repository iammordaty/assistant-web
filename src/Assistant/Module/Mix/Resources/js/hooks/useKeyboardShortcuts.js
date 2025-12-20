import { useEffect, useRef } from 'react';

const PENDING_KEY_TIMEOUT = 350;

const isInputElement = (element) => {
    const tagName = element?.tagName?.toLowerCase();

    return tagName === 'input' || tagName === 'textarea' || tagName === 'select';
};

const isModalOpen = () => document.querySelector('.modal.show') !== null;

const isDigit = (key) => /^[0-9]$/.test(key);

const getKeyWithModifiers = (event) => {
    const key = event.key.toLowerCase();
    const modifiers = [];

    if (event.shiftKey && key !== 'shift') modifiers.push('shift');
    if (event.ctrlKey && key !== 'control') modifiers.push('ctrl');
    if (event.altKey && key !== 'alt') modifiers.push('alt');
    if (event.metaKey && key !== 'meta') modifiers.push('meta');

    return modifiers.length > 0 ? `${modifiers.join('+')}+${key}` : key;
};

export const useKeyboardShortcuts = (shortcuts, enabled = true) => {
    const shortcutsRef = useRef(shortcuts);
    const pendingKeyRef = useRef(null);
    const collectedDigitsRef = useRef('');
    const timeoutRef = useRef(null);

    shortcutsRef.current = shortcuts;

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const clearPending = () => {
            pendingKeyRef.current = null;
            collectedDigitsRef.current = '';

            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
                timeoutRef.current = null;
            }
        };

        const executePendingFallback = () => {
            const currentShortcuts = shortcutsRef.current;
            const pendingKey = pendingKeyRef.current;

            clearPending();

            if (pendingKey) {
                const fallbackHandler = currentShortcuts[pendingKey];

                if (fallbackHandler) {
                    fallbackHandler();
                }
            }
        };

        const executeNumericHandler = () => {
            const currentShortcuts = shortcutsRef.current;
            const prefix = pendingKeyRef.current;
            const digits = collectedDigitsRef.current;

            if (prefix && digits) {
                const handler = currentShortcuts[`${prefix},#`];

                if (handler) {
                    handler(parseInt(digits, 10));
                }
            }

            clearPending();
        };

        const handleKeyDown = (event) => {
            if (isInputElement(event.target) || isModalOpen()) {
                clearPending();

                return;
            }

            const key = event.key.toLowerCase();
            const keyWithModifiers = getKeyWithModifiers(event);
            const currentShortcuts = shortcutsRef.current;

            if (keyWithModifiers !== key && currentShortcuts[keyWithModifiers]) {
                event.preventDefault();
                currentShortcuts[keyWithModifiers]();

                return;
            }

            if (pendingKeyRef.current) {
                const hasNumericHandler = currentShortcuts[`${pendingKeyRef.current},#`];

                if (hasNumericHandler && isDigit(key)) {
                    event.preventDefault();
                    collectedDigitsRef.current += key;

                    clearTimeout(timeoutRef.current);
                    timeoutRef.current = setTimeout(executeNumericHandler, PENDING_KEY_TIMEOUT);

                    return;
                }

                if (collectedDigitsRef.current) {
                    executeNumericHandler();

                    return;
                }

                const sequence = `${pendingKeyRef.current},${key}`;
                const handler = currentShortcuts[sequence];

                clearPending();

                if (handler) {
                    event.preventDefault();
                    handler();

                    return;
                }
            }

            const sequenceStarter = Object.keys(currentShortcuts).find(
                (shortcut) => shortcut.includes(',') && shortcut.startsWith(`${key},`)
            );

            if (sequenceStarter) {
                event.preventDefault();
                pendingKeyRef.current = key;
                timeoutRef.current = setTimeout(executePendingFallback, PENDING_KEY_TIMEOUT);

                return;
            }

            const handler = currentShortcuts[key];

            if (handler) {
                event.preventDefault();
                handler();
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);

            clearPending();
        };
    }, [enabled]);
};
