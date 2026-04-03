import { useState, useMemo } from 'react';
import { useKeyboardShortcuts } from './useKeyboardShortcuts';
import urlFor from '@public/js/modules/url-for.js';

export const useMixShortcuts = ({
    selectedAttempt,
    highlightedTrackIndex,
    highlightNextTrack,
    highlightPrevTrack,
    attemptsCount,
    openTrackEntryModal,
    openAttemptModal,
    openMixModal,
    handleNewAttempt,
    deleteTrack,
    switchToAttempt,
}) => {
    const [helpModalOpen, setHelpModalOpen] = useState(false);

    const editHighlightedTrack = () => {
        if (highlightedTrackIndex === null || !selectedAttempt) return;

        const trackEntry = selectedAttempt.trackList[highlightedTrackIndex];

        openTrackEntryModal(trackEntry, highlightedTrackIndex);
    };

    const editTrackOrAttempt = () => {
        if (highlightedTrackIndex !== null) {
            editHighlightedTrack();
        } else {
            openAttemptModal();
        }
    };

    const deleteHighlightedTrack = () => {
        if (highlightedTrackIndex === null || !selectedAttempt) {
            return;
        }

        const trackEntry = selectedAttempt.trackList[highlightedTrackIndex];

        deleteTrack(selectedAttempt, trackEntry);
    };

    const switchToFirstAttempt = () => switchToAttempt(1);
    const switchToLastAttempt = () => switchToAttempt(attemptsCount);

    const goToMixList = () => window.location.href = urlFor('mix.list.index');

    const openHelpModal = () => setHelpModalOpen(true);
    const closeHelpModal = () => setHelpModalOpen(false);

    const shortcuts = useMemo(() => ({
        'e': editTrackOrAttempt,
        'e,m': openMixModal,
        'e,a': openAttemptModal,
        'a': openTrackEntryModal,
        't': openTrackEntryModal,
        'n': handleNewAttempt,
        'p': handleNewAttempt,
        'arrowdown': highlightNextTrack,
        'arrowup': highlightPrevTrack,
        'enter': editHighlightedTrack,
        'shift+delete': deleteHighlightedTrack,
        's': switchToFirstAttempt,
        's,s': switchToLastAttempt,
        's,#': switchToAttempt,
        'l': goToMixList,
        'g,l': goToMixList,
        '?': openHelpModal,
        'k': openHelpModal,
        'h': openHelpModal,
    }), [
        selectedAttempt,
        highlightedTrackIndex,
        highlightNextTrack,
        highlightPrevTrack,
        attemptsCount,
        openTrackEntryModal,
        openAttemptModal,
        openMixModal,
        handleNewAttempt,
        deleteTrack,
        switchToAttempt,
    ]);

    useKeyboardShortcuts(shortcuts);

    return {
        helpModalOpen,
        closeHelpModal,
    };
};
