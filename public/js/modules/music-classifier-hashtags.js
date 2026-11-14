/**
 * AI: hashtagi są ładowane po wyrenderowaniu strony, ponieważ analiza utworu nieobecnego w cache
 * Music Classifier trwa kilka minut i nie może blokować wyświetlenia strony utworu.
 */
export default async function initMusicClassifierHashtags() {
    const container = document.querySelector('[data-role="track:music-classifier-hashtags"]');

    if (!container) {
        return;
    }

    try {
        const response = await fetch(container.dataset.url);

        if (!response.ok) {
            const payload = await response.json().catch(() => null);

            throw new Error(payload?.message || 'Nie udało się pobrać cech utworu z Music Classifier.');
        }

        container.innerHTML = await response.text();
    } catch (e) {
        const error = document.createElement('span');

        error.className = 'text-secondary';
        error.textContent = e.message;

        container.replaceChildren(error);
    }
}
