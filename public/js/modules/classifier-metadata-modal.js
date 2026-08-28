import renderJsonTree from './json-tree.js';

export default function initClassifierMetadataModal() {
    const trigger = document.querySelector('[data-role="track:classifier-metadata"]');
    const modalEl = document.getElementById('modal-classifier-metadata');

    console.log('classifier-metadata-modal.js loaded', trigger, modalEl);
    if (!trigger || !modalEl) {
        return;
    }

    const modal = new window.bootstrap.Modal(modalEl);
    const loading = modalEl.querySelector('[data-role="classifier-metadata:loading"]');
    const error = modalEl.querySelector('[data-role="classifier-metadata:error"]');
    const tree = modalEl.querySelector('[data-role="classifier-metadata:tree"]');
    const copyBtn = modalEl.querySelector('[data-role="classifier-metadata:copy"]');
    const downloadBtn = modalEl.querySelector('[data-role="classifier-metadata:download"]');
    const copyLabel = modalEl.querySelector('[data-role="classifier-metadata:copy-label"]');
    const originalCopyLabel = copyLabel.textContent;

    let data = null;
    let filename = modalEl.dataset.filename || 'essentia.json';
    let copyResetTimer;

    const setActionButtonsDisabled = (disabled) => {
        copyBtn.disabled = disabled;
        downloadBtn.disabled = disabled;
    };

    const showState = (state) => {
        loading.classList.toggle('d-none', state !== 'loading');
        error.classList.toggle('d-none', state !== 'error');
        tree.classList.toggle('d-none', state !== 'tree');
    };

    const load = async () => {
        if (data) {
            showState('tree');
            setActionButtonsDisabled(false);

            return;
        }

        showState('loading');
        setActionButtonsDisabled(true);
        tree.replaceChildren();

        try {
            const response = await fetch(modalEl.dataset.url);

            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error('Nie znaleziono metadanych Essentia dla tego utworu.');
                }

                const payload = await response.json().catch(() => null);

                throw new Error(payload?.message || 'Nie udało się pobrać metadanych Essentia.');
            }

            const disposition = response.headers.get('Content-Disposition');
            const match = disposition?.match(/filename="([^"]+)"/);

            if (match) {
                filename = match[1];
            }

            data = await response.json();
            tree.replaceChildren(renderJsonTree(data));
            showState('tree');
            setActionButtonsDisabled(false);
        } catch (e) {
            error.textContent = e.message;
            showState('error');
        }
    };

    trigger.addEventListener('click', () => modal.show());
    modalEl.addEventListener('show.bs.modal', load);

    copyBtn.addEventListener('click', async () => {
        if (!data) {
            return;
        }

        try {
            await navigator.clipboard.writeText(JSON.stringify(data, null, 2));
        } catch {
            alert('Nie udało się skopiować danych do schowka.');

            return;
        }

        copyLabel.textContent = 'Skopiowano';
        clearTimeout(copyResetTimer);
        copyResetTimer = setTimeout(() => {
            copyLabel.textContent = originalCopyLabel;
        }, 2000);
    });

    downloadBtn.addEventListener('click', () => {
        if (!data) {
            return;
        }

        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = filename;
        link.click();
        URL.revokeObjectURL(url);
    });
}
