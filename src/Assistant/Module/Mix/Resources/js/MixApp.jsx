import { useEffect, useMemo, useState, useTransition } from 'react';

import Attempt from './Attempt';
import AttemptModal from './AttemptModal';
import AppBuildInfo from './AppBuildInfo';
import KeyboardShortcutsHelpModal from './KeyboardShortcutsHelpModal';
import MixModal from './MixModal';
import TrackEntryModal from './TrackEntryModal';
import toRomanNumeral from './utils/toRomanNumerals';
import { useMixApi } from './hooks/useMixApi';
import { useMixShortcuts } from './hooks/useMixShortcuts';
import { useTrackHighlight } from './hooks/useTrackHighlight';

import { IconPlus } from '@tabler/icons-react';

const MixApp = ({ initialMix, autocompleteUrl }) => {
    const [mix, setMix] = useState(initialMix);
    const [, startTransition] = useTransition();

    const attemptsWithNumbers = useMemo(() => {
        return mix.attempts.map((attempt, index) => ({
            ...attempt,
            number: index + 1,
        }));
    }, [mix.attempts]);

    const [selectedAttemptNumber, setSelectedAttemptNumber] = useState(() => initialMix.attempts.length);

    const selectedAttempt = useMemo(
        () => attemptsWithNumbers[selectedAttemptNumber - 1],
        [attemptsWithNumbers, selectedAttemptNumber]
    );

    useEffect(() => {
        if (attemptsWithNumbers.length > 0 && selectedAttemptNumber > attemptsWithNumbers.length) {
            setSelectedAttemptNumber(attemptsWithNumbers.length);
        }
    }, [attemptsWithNumbers.length, selectedAttemptNumber]);

    const trackListLength = selectedAttempt?.trackList?.length ?? 0;

    const {
        highlightedIndex: highlightedTrackIndex,
        setHighlightedIndex: setHighlightedTrackIndex,
        highlightNext: highlightNextTrack,
        highlightPrev: highlightPrevTrack,
    } = useTrackHighlight(trackListLength, [selectedAttemptNumber]);

    const [trackEntryModal, setTrackEntryModal] = useState({
        isOpen: false,
        trackEntry: null,
        editingTrackEntryIndex: null,
    });

    const [attemptModalOpen, setAttemptModalOpen] = useState(false);
    const [mixModalOpen, setMixModalOpen] = useState(false);

    const { saveMix, saveAttempt } = useMixApi(mix, setMix);

    useEffect(() => {
        if (!mix.guid) {
            setMixModalOpen(true);
        }
    }, []);

    const handleAttemptChange = (event) => {
        const selectedNumber = parseInt(event.target.value, 10);

        startTransition(() => {
            setSelectedAttemptNumber(selectedNumber);
        });
    };

    const handleNewAttempt = async () => {
        const createdDate = new Date();
        const number = mix.attempts.length + 1;
        const newAttempt = {
            created: createdDate.toISOString(),
            comment: null,
            trackList: []
        };

        setMix(prevMix => ({
            ...prevMix,
            attempts: [...prevMix.attempts, newAttempt]
        }));

        setSelectedAttemptNumber(number);
        
        await saveAttempt(newAttempt);
    };

    const openTrackEntryModal = (track = null, comments = [], trackEntryIndex = null) => {
        setTrackEntryModal({
            isOpen: true,
            trackEntry: track ? { track, comments } : null,
            editingTrackEntryIndex: trackEntryIndex,
        });
    };

    const closeTrackEntryModal = () => {
        setTrackEntryModal({
            isOpen: false,
            trackEntry: null,
            editingTrackEntryIndex: null,
        });
    };

    const saveTrackEntry = async (trackData) => {
        const { track, comments } = trackData;
        const { editingTrackEntryIndex } = trackEntryModal;

        if (!selectedAttempt) {
            return;
        }

        const newTrackEntry = { track, trackGuid: track.guid, comments };
        let updatedAttempt;

        if (editingTrackEntryIndex !== null) {
            const updatedTrackList = [...selectedAttempt.trackList];

            updatedTrackList[editingTrackEntryIndex] = {
                ...updatedTrackList[editingTrackEntryIndex],
                ...newTrackEntry
            };

            updatedAttempt = { ...selectedAttempt, trackList: updatedTrackList };
        } else {
            updatedAttempt = {
                ...selectedAttempt,
                trackList: [...selectedAttempt.trackList, newTrackEntry]
            };
        }

        await saveAttempt(updatedAttempt);

        closeTrackEntryModal();
    };

    const deleteTrack = async (attempt, trackEntry) => {
        const updatedAttempt = {
            ...attempt,
            trackList: attempt.trackList.filter(te => te !== trackEntry)
        };

        await saveAttempt(updatedAttempt);
    };

    const reorderTracks = async (attempt, fromIndex, toIndex) => {
        const trackList = [...attempt.trackList];
        const [movedTrack] = trackList.splice(fromIndex, 1);
        trackList.splice(toIndex, 0, movedTrack);

        const updatedAttempt = { ...attempt, trackList };

        await saveAttempt(updatedAttempt);
    };

    const openAttemptModal = () => setAttemptModalOpen(true);
    const closeAttemptModal = () => setAttemptModalOpen(false);

    const saveAttemptModal = async (data) => {
        const { comment, date } = data;

        if (!selectedAttempt) {
            return;
        }

        const updatedAttempt = {
            ...selectedAttempt,
            comment,
            created: date ? new Date(date).toISOString() : selectedAttempt.created
        };

        await saveAttempt(updatedAttempt);

        closeAttemptModal();
    };

    const openMixModal = () => setMixModalOpen(true);
    const closeMixModal = () => setMixModalOpen(false);

    const saveMixModal = async (mixData) => {
        await saveMix(mixData);

        closeMixModal();
    };

    const buildInfo = window?.assistant?.mix?.buildInfo;

    const isMixSaved = !!mix.guid;

    const switchToAttempt = (number) => {
        if (number >= 1 && number <= attemptsWithNumbers.length) {
            startTransition(() => {
                setSelectedAttemptNumber(number);
            });
        }
    };

    const { helpModalOpen, closeHelpModal } = useMixShortcuts({
        selectedAttempt,
        highlightedTrackIndex,
        highlightNextTrack,
        highlightPrevTrack,
        attemptsCount: attemptsWithNumbers.length,
        openTrackEntryModal,
        openAttemptModal,
        openMixModal,
        handleNewAttempt,
        deleteTrack,
        switchToAttempt,
    });

    return (
        <>
            <AppBuildInfo buildInfo={buildInfo} />
            <div style={{ display: isMixSaved ? 'block' : 'none' }}>
                <div className="page-header mt-0 mb-4 ml-0 mx-0">
                    <div className="row g-2 align-items-center">
                        <div
                            className="col cursor-pointer"
                            onClick={openMixModal}
                            title="Kliknij, aby edytować dane miksu"
                        >
                            <h2 className="page-title">
                                {mix.name}
                            </h2>
                            <span className="text-muted fs-5 mb-0">
                                {mix.description}
                            </span>
                        </div>

                        <div className="col-auto ms-auto">
                            <div className="btn-list">
                                <div className="d-flex align-items-center gap-1">
                                    <select
                                        name="attempt-number"
                                        className="form-select w-auto"
                                        value={selectedAttemptNumber}
                                        onChange={handleAttemptChange}
                                    >
                                        {attemptsWithNumbers.map((attempt) => (
                                            <option key={attempt.id || attempt.number} value={attempt.number}>
                                                Próba {toRomanNumeral(attempt.number)} ({new Date(attempt.created).toLocaleDateString('pl-PL')}, {new Date(attempt.created).toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' })})
                                            </option>
                                        ))}
                                    </select>
                                    <button
                                        className="btn btn-icon"
                                        onClick={handleNewAttempt}
                                        title="Dodaj nową próbę"
                                        aria-label="Dodaj nową próbę"
                                    >
                                        <IconPlus className="icon" stroke={1.5} />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="row row-deck row-cards">
                    <div className="col-12">
                        <Attempt
                            attempt={selectedAttempt}
                            highlightedTrackIndex={highlightedTrackIndex}
                            onHighlightTrack={setHighlightedTrackIndex}
                            onEditTrack={openTrackEntryModal}
                            onAddTrack={openTrackEntryModal}
                            onDeleteTrack={deleteTrack}
                            onEditAttempt={openAttemptModal}
                            onReorderTracks={reorderTracks}
                        />
                    </div>
                </div>
            </div>
            
            <TrackEntryModal
                key={`track-${selectedAttemptNumber}-${trackEntryModal.editingTrackEntryIndex}`}
                isOpen={trackEntryModal.isOpen}
                trackEntry={trackEntryModal.trackEntry}
                autocompleteUrl={autocompleteUrl}
                onSave={saveTrackEntry}
                onClose={closeTrackEntryModal}
            />

            <AttemptModal
                key={`attempt-${selectedAttemptNumber}`}
                isOpen={attemptModalOpen}
                attempt={selectedAttempt}
                onSave={saveAttemptModal}
                onClose={closeAttemptModal}
            />

            <MixModal
                isOpen={mixModalOpen}
                mix={mix}
                onSave={saveMixModal}
                onClose={isMixSaved ? closeMixModal : undefined}
            />

            <KeyboardShortcutsHelpModal
                isOpen={helpModalOpen}
                onClose={closeHelpModal}
            />
        </>
    );
};

export default MixApp; 
