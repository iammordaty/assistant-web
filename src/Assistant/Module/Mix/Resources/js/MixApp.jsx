import React, { useState, useEffect } from 'react';
import AttemptList from './AttemptList';
import TrackEntryModal from './TrackEntryModal';
import AttemptModal from './AttemptModal';
import MixModal from './MixModal';
import AppBuildInfo from './AppBuildInfo';

import { IconMusicPlus } from '@tabler/icons-react';

const MixApp = ({ initialMix, autocompleteUrl, saveAttemptUrl, saveMixUrl }) => {
    const [mix, setMix] = useState(initialMix);
    const [selectedAttemptIndex, setSelectedAttemptIndex] = useState(-1);
    const [isTrackEntryModalOpen, setIsTrackEntryModalOpen] = useState(false);
    const [isAttemptModalOpen, setIsAttemptModalOpen] = useState(false);
    const [trackEntryModalData, setTrackEntryModalData] = useState({
        track: null,
        comments: [],
        editingTrackEntryIndex: null,
        editingAttemptNumber: null
    });
    const [attemptModalData, setAttemptModalData] = useState({
        comment: '',
        date: '',
        editingAttemptNumber: null
    });
    const [isMixModalOpen, setIsMixModalOpen] = useState(false);
    const [mixModalData, setMixModalData] = useState({
        name: '',
        description: '',
        comment: '',
        created: '',
        modified: '',
        performed: ''
    });
    const [savingAttempts, setSavingAttempts] = useState(new Set());

    useEffect(() => {
        if (mix && mix.attempts && mix.attempts.length > 0 && selectedAttemptIndex < 0) {
            // Set to the last (most recent) attempt by default, but only on initial load
            setSelectedAttemptIndex(mix.attempts.length - 1);
        }
    }, [mix]);

    const handleAttemptChange = (event) => {
        const attemptNumber = parseInt(event.target.value);

        // Find the index of attempt with the selected number
        const newIndex = mix.attempts.findIndex(attempt => attempt.number === attemptNumber);
        if (newIndex !== -1) {
            setSelectedAttemptIndex(newIndex);
        }
    };

    const handleNewAttempt = () => {
        // This will be handled by the parent component or backend
        console.log('Creating new attempt...');
        // You can implement AJAX call here to create a new attempt
        // and then update the mix data
    };

    const openModalToEdit = (track, comments, trackEntryIndex, attemptNumber) => {
        setTrackEntryModalData({
            track,
            comments,
            editingTrackEntryIndex: trackEntryIndex,
            editingAttemptNumber: attemptNumber
        });
        setIsTrackEntryModalOpen(true);
    };

    const openModalToAddNew = () => {
        setTrackEntryModalData({
            track: null,
            comments: [],
            editingTrackEntryIndex: null,
            editingAttemptNumber: null
        });
        setIsTrackEntryModalOpen(true);
    };

    const addTrackToAttempt = (attemptNumber) => {
        setTrackEntryModalData({
            track: null,
            comments: [],
            editingTrackEntryIndex: null,
            editingAttemptNumber: attemptNumber
        });
        setIsTrackEntryModalOpen(true);
    };

    const closeModal = () => {
        setIsTrackEntryModalOpen(false);
        setTrackEntryModalData({
            track: null,
            comments: [],
            editingTrackEntryIndex: null,
            editingAttemptNumber: null
        });
    };

    const saveTrackEntry = (trackData) => {
        const { track, comments } = trackData;
        const { editingAttemptNumber, editingTrackEntryIndex } = trackEntryModalData;

        setMix(prevMix => {
            const updatedMix = { ...prevMix };
            const attempts = [...updatedMix.attempts];

            if (editingAttemptNumber !== null && editingTrackEntryIndex !== null) {
                // Edytujemy istniejący trackEntry - znajdź indeks na podstawie numeru próby
                const editingAttemptIndex = attempts.findIndex(attempt => attempt.number === editingAttemptNumber);
                if (editingAttemptIndex !== -1) {
                    const attempt = { ...attempts[editingAttemptIndex] };
                    const updatedTrackList = [...attempt.trackList];
                    updatedTrackList[editingTrackEntryIndex] = {
                        ...updatedTrackList[editingTrackEntryIndex],
                        track,
                        trackGuid: track?.guid || null,
                        comments
                    };
                    attempt.trackList = updatedTrackList;
                    attempts[editingAttemptIndex] = attempt;
                }
            } else if (editingAttemptNumber !== null) {
                // Dodajemy nowy trackEntry do konkretnej próby
                const editingAttemptIndex = attempts.findIndex(attempt => attempt.number === editingAttemptNumber);
                if (editingAttemptIndex !== -1) {
                    const attempt = { ...attempts[editingAttemptIndex] };
                    const newTrackEntry = { track, trackGuid: track?.guid || null, comments };
                    attempt.trackList = [...attempt.trackList, newTrackEntry];
                    attempts[editingAttemptIndex] = attempt;
                }
            } else {
                // Dodajemy nowy trackEntry do aktualnie wybranej próby
                const selectedAttempt = { ...attempts[selectedAttemptIndex] };
                const newTrackEntry = { track, trackGuid: track?.guid || null, comments };
                selectedAttempt.trackList = [...selectedAttempt.trackList, newTrackEntry];
                attempts[selectedAttemptIndex] = selectedAttempt;
            }

            return { ...updatedMix, attempts };
        });

        closeModal();
    };

    const deleteTrack = (attempt, trackEntry) => {
        setMix(prevMix => {
            const updatedMix = { ...prevMix };
            const attempts = updatedMix.attempts.map(a => {
                if (a === attempt) {
                    return {
                        ...a,
                        trackList: a.trackList.filter(te => te !== trackEntry)
                    };
                }
                return a;
            });
            return { ...updatedMix, attempts };
        });
    };

    const openAttemptModal = (attemptNumber, currentComment, currentDate) => {
        // Convert ISO datetime to datetime-local format (YYYY-MM-DDTHH:MM)
        let formattedDate = '';
        if (currentDate) {
            try {
                const date = new Date(currentDate);
                formattedDate = date.toISOString().slice(0, 16);
            } catch (e) {
                console.warn('Failed to parse date:', currentDate);
            }
        }

        setAttemptModalData({
            comment: currentComment || '',
            date: formattedDate,
            editingAttemptNumber: attemptNumber
        });
        setIsAttemptModalOpen(true);
    };

    const closeAttemptModal = () => {
        setIsAttemptModalOpen(false);
        setAttemptModalData({
            comment: '',
            date: '',
            editingAttemptNumber: null
        });
    };

    const saveAttemptProperties = (data) => {
        const { comment, date } = data;
        const { editingAttemptNumber } = attemptModalData;

        if (editingAttemptNumber !== null) {
            // Convert datetime-local format back to ISO string
            let isoDate = date;
            if (date) {
                try {
                    const dateObj = new Date(date);
                    isoDate = dateObj.toISOString();
                } catch (e) {
                    console.warn('Failed to convert date:', date);
                }
            }

            setMix(prevMix => {
                const updatedMix = { ...prevMix };
                const attempts = [...updatedMix.attempts];
                // Znajdź indeks na podstawie numeru próby
                const editingAttemptIndex = attempts.findIndex(attempt => attempt.number === editingAttemptNumber);
                if (editingAttemptIndex !== -1) {
                    attempts[editingAttemptIndex] = {
                        ...attempts[editingAttemptIndex],
                        comment: comment,
                        created: isoDate
                    };
                }
                return { ...updatedMix, attempts };
            });
        }

        closeAttemptModal();
    };

    const openMixModal = () => {
        // Convert ISO dates to YYYY-MM-DD format for date inputs
        const formatDateForInput = (dateString) => {
            if (!dateString) return '';
            try {
                const date = new Date(dateString);
                return date.toISOString().split('T')[0];
            } catch (e) {
                console.warn('Failed to format date:', dateString);
                return '';
            }
        };

        setMixModalData({
            name: mix.name || '',
            description: mix.description || '',
            comment: mix.comment || '',
            created: formatDateForInput(mix.created),
            modified: formatDateForInput(mix.modified),
            performed: formatDateForInput(mix.performed)
        });
        setIsMixModalOpen(true);
    };

    const closeMixModal = () => {
        setIsMixModalOpen(false);
        setMixModalData({
            name: '',
            description: '',
            comment: '',
            created: '',
            modified: '',
            performed: ''
        });
    };

    const saveMixProperties = async (properties) => {
        console.log('Saving mix properties:', properties);

        // Convert date inputs (YYYY-MM-DD) back to ISO format for storage
        const formatDateForStorage = (dateString) => {
            if (!dateString) return null;
            try {
                const date = new Date(dateString);
                return date.toISOString();
            } catch (e) {
                console.warn('Failed to convert date for storage:', dateString);
                return null;
            }
        };

        const { name, description, comment, created, modified, performed } = properties;

        const dataToSend = {
            name: name,
            description: description,
            comment: comment || null,
            created: formatDateForStorage(created),
            modified: formatDateForStorage(modified),
            performed: formatDateForStorage(performed)
        };

        try {
            const response = await fetch(saveMixUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(dataToSend)
            });

            if (response.ok) {
                const result = await response.json();
                console.log('Mix properties saved successfully:', result);

                // Zaktualizuj lokalne dane miksem otrzymanym z serwera
                setMix(result);

            } else {
                const errorData = await response.json().catch(() => ({}));
                console.error('Failed to save mix properties:', response.status, errorData);

                // W przypadku błędu nadal zaktualizuj lokalne dane dla UX
                setMix(prevMix => ({
                    ...prevMix,
                    name: name,
                    description: description,
                    comment: comment,
                    created: formatDateForStorage(created) || prevMix.created,
                    modified: formatDateForStorage(modified) || prevMix.modified,
                    performed: formatDateForStorage(performed) || prevMix.performed
                }));
            }

        } catch (error) {
            console.error('Error saving mix properties:', error);

            // W przypadku błędu sieciowego nadal zaktualizuj lokalne dane
            setMix(prevMix => ({
                ...prevMix,
                name: name,
                description: description,
                comment: comment,
                created: formatDateForStorage(created) || prevMix.created,
                modified: formatDateForStorage(modified) || prevMix.modified,
                performed: formatDateForStorage(performed) || prevMix.performed
            }));
        }

        closeMixModal();
    };

    const saveAttemptTracks = async (attemptNumber) => {
        setSavingAttempts(prev => new Set([...prev, attemptNumber]));

        try {
            // Znajdź próbę do zapisania
            const attemptToSave = mix.attempts.find(attempt => attempt.number === attemptNumber);
            if (!attemptToSave) {
                console.error('Attempt not found:', attemptNumber);
                return;
            }

            // Wyślij dane do backendu
            const response = await fetch(saveAttemptUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(attemptToSave)
            });

            if (response.ok) {
                const result = await response.json();
                console.log('Attempt saved successfully:', result);

                // Możesz tutaj dodać powiadomienie o sukcesie
                // np. toast notification lub aktualizację stanu

            } else {
                const errorData = await response.json().catch(() => ({}));
                console.error('Failed to save attempt:', response.status, errorData);

                // Możesz tutaj dodać obsługę błędów
                // np. wyświetlenie komunikatu o błędzie
            }

        } catch (error) {
            console.error('Error saving attempt:', error);

            // Obsługa błędów sieciowych lub innych wyjątków
        } finally {
            // Usuń attempt z listy zapisywanych
            setSavingAttempts(prev => {
                const newSet = new Set(prev);
                newSet.delete(attemptNumber);
                return newSet;
            });
        }
    };

    const toRomanNumeral = (num) => {
        const romanNumerals = {
            1: 'I',
            2: 'II',
            3: 'III',
            4: 'IV',
            5: 'V',
            6: 'VI',
            7: 'VII',
            8: 'VIII',
            9: 'IX',
            10: 'X'
        };
        return romanNumerals[num] || num;
    };

    // Only show the selected attempt
    const visibleAttempts = mix.attempts && mix.attempts.length > 0 && selectedAttemptIndex >= 0
        ? [mix.attempts[selectedAttemptIndex]]
        : [];

    const buildInfo = window?.assistant?.mix?.buildInfo;

    return (
        <>
            <AppBuildInfo buildInfo={buildInfo} />
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
                            <div className="d-flex align-items-center gap-2">
                                <button
                                    className="btn btn-primary"
                                    onClick={handleNewAttempt}
                                >
                                    <IconMusicPlus className="icon" stroke={1.5} />
                                    Nowa próba
                                </button>
                                <select
                                    className="form-select w-auto"
                                    value={mix.attempts && mix.attempts[selectedAttemptIndex] ? mix.attempts[selectedAttemptIndex].number : 1}
                                    onChange={handleAttemptChange}
                                >
                                    {mix.attempts && mix.attempts.map((attempt, index) => (
                                        <option key={index} value={attempt.number}>
                                            Próba {toRomanNumeral(attempt.number)} ({new Date(attempt.created).toLocaleDateString('pl-PL')}, {new Date(attempt.created).toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' })})
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <AttemptList
                attempts={visibleAttempts}
                onEditTrack={openModalToEdit}
                onAddTrack={addTrackToAttempt}
                onDeleteTrack={deleteTrack}
                onEditAttempt={openAttemptModal}
                onSaveAttempt={saveAttemptTracks}
                savingAttempts={savingAttempts}
            />

            <TrackEntryModal
                key={`track-${trackEntryModalData.editingAttemptNumber}-${trackEntryModalData.editingTrackEntryIndex}`}
                isOpen={isTrackEntryModalOpen}
                track={trackEntryModalData.track}
                comments={trackEntryModalData.comments || []}
                autocompleteUrl={autocompleteUrl}
                onSave={saveTrackEntry}
                onClose={closeModal}
            />

            <AttemptModal
                key={`attempt-${attemptModalData.editingAttemptNumber}`}
                isOpen={isAttemptModalOpen}
                comment={attemptModalData.comment}
                date={attemptModalData.date}
                onSave={saveAttemptProperties}
                onClose={closeAttemptModal}
            />

            <MixModal
                isOpen={isMixModalOpen}
                name={mixModalData.name}
                description={mixModalData.description}
                comment={mixModalData.comment}
                created={mixModalData.created}
                modified={mixModalData.modified}
                performed={mixModalData.performed}
                onSave={saveMixProperties}
                onClose={closeMixModal}
            />
        </>
    );
};

export default MixApp; 