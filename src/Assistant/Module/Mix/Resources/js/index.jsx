import React from 'react';
import { createRoot } from 'react-dom/client';

import MixApp from './MixApp';

const getMixProps = (mixAppRoot) => {
    const rawMixData = mixAppRoot.getAttribute('data-mix');
    let initialMix = null;

    try {
        initialMix = rawMixData ? JSON.parse(rawMixData) : null;
    } catch (_) { }

    const autocompleteUrl = mixAppRoot.getAttribute('data-autocomplete-url');
    const saveAttemptUrl = mixAppRoot.getAttribute('data-save-attempt-url');
    const saveMixUrl = mixAppRoot.getAttribute('data-save-mix-url');

    return {
        initialMix,
        autocompleteUrl,
        saveAttemptUrl,
        saveMixUrl
    };
}

document.addEventListener('DOMContentLoaded', () => {
    const mixAppRoot = document.getElementById('mix-app-root');

    if (!mixAppRoot) {
        console.error('Element #mix-app-root nie został znaleziony.');

        return;
    }

    const { initialMix, autocompleteUrl, saveAttemptUrl, saveMixUrl } = getMixProps(mixAppRoot);

    if (!initialMix || !autocompleteUrl || !saveAttemptUrl || !saveMixUrl) {
        alert('Nie przekazano wszystkich wymaganych danych. Zerknij do konsoli.');
        console.error(mixAppRoot, { initialMix, autocompleteUrl, saveAttemptUrl, saveMixUrl });

        return;
    }

    createRoot(mixAppRoot).render(
        <MixApp
            initialMix={initialMix}
            autocompleteUrl={autocompleteUrl}
            saveAttemptUrl={saveAttemptUrl}
            saveMixUrl={saveMixUrl}
        />
    );
}); 