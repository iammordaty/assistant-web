import { useState } from 'react';

const DATE_FALLBACK = '(⊙.☉)7';

const styles = {
    backgroundColor: 'rgba(255, 255, 255, 0.8)',
    border: '1px solid rgba(108, 117, 125, 0.2)',
    borderRadius: '6px',
    bottom: '10px',
    color: '#6c757d',
    cursor: 'default',
    fontFamily: 'monospace',
    fontSize: '11px',
    lineHeight: '1.2',
    padding: '4px 6px',
    position: 'fixed',
    right: '10px',
    transition: 'opacity 0.2s ease-in-out',
    zIndex: 1000,
};

const formatBuildDate = isoString => {
    if (!isoString) return null;
    return (new Date(isoString)).toLocaleString('pl-PL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).replace(',', ', ');
};

const formatBuildTime = timeMs => {
    if (!timeMs && timeMs !== 0) return null;
    if (timeMs < 1000) {
        return `${timeMs}ms`;
    } else {
        const timeInSeconds = parseFloat((timeMs / 1000).toFixed(2));
        return `${timeInSeconds}s`;
    }
};

const formatBuildSize = sizeKb => {
    if (!sizeKb && sizeKb !== 0) return null;
    if (sizeKb < 1024) {
        return `~${Math.floor(sizeKb)}KB`;
    } else {
        const sizeInMb = parseFloat((sizeKb / 1024).toFixed(2));
        return `~${sizeInMb}MB`;
    }
};

export default function ({ buildInfo }) {
    const [isHovered, setIsHovered] = useState(false);

    const breadcrumbItems = !!buildInfo ? [
        formatBuildDate(buildInfo.buildDate),
        formatBuildTime(buildInfo.buildTimeMs),
        formatBuildSize(buildInfo.buildSizeKb),
      ] : [DATE_FALLBACK];

    return (
        <div
            style={{
                ...styles,
                opacity: isHovered ? 1 : 0.3,
            }}
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <ul className="breadcrumb ast-breadcrumb-dotted">
                {breadcrumbItems.map((item, index) => (
                    <li key={index} className="breadcrumb-item">{item}</li>
                ))}
            </ul>
        </div>
    );
};
