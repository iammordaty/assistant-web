export const formatDateTimeForInput = (isoString) => {
    if (!isoString) {
        return '';
    }

    const parts = new Intl.DateTimeFormat('sv-SE', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    }).formatToParts(new Date(isoString));

    const get = (type) => parts.find(p => p.type === type)?.value;

    return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`;
};

export const formatDateForStorage = (dateInput) => {
    if (!dateInput) {
        return null;
    }

    return new Date(dateInput).toISOString();
};

