import urlFor from '@public/js/modules/url-for.js';
import { formatDateForStorage } from '../utils/dateUtils';

export const updateBrowserUrl = (guid, isNew) => {
    const newUrl = urlFor('mix.mix.view', { guid });

    if (isNew) {
        window.history.pushState(null, '', newUrl);
    } else {
        window.history.replaceState(null, '', newUrl);
    }
};

export const useMixApi = (mix, setMix) => {
    const saveMix = async ({ name, description, comment, created, modified, performed }) => {
        const mixData = {
            name,
            description,
            comment,
            created: formatDateForStorage(created),
            modified: formatDateForStorage(modified),
            performed: formatDateForStorage(performed)
        };

        try {
            const response = await fetch(urlFor('mix.mix.save-mix', { guid: mix.guid }), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(mixData),
            });

            const result = await response.json();

            if (result.guid !== mix.guid) {
                updateBrowserUrl(result.guid, !mix.guid);
            }

            setMix(result);
        } catch (error) {
            console.error('Error saving mix properties:', error);
        }
    };

    const saveAttempt = async (attempt) => {
        try {
            const response = await fetch(urlFor('mix.mix.save-attempt', { guid: mix.guid }), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(attempt),
            });

            const result = await response.json();
            setMix(result);
        } catch (error) {
            console.error('Error saving attempt:', error);
        }
    };

    return {
        saveMix,
        saveAttempt,
    };
};
