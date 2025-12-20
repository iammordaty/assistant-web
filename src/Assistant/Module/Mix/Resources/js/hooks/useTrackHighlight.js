import { useState, useEffect, useCallback } from 'react';

export const useTrackHighlight = (trackListLength, resetDeps = []) => {
    const [highlightedIndex, setHighlightedIndex] = useState(null);

    useEffect(() => {
        setHighlightedIndex(null);
    }, resetDeps);

    const highlightNext = useCallback(() => {
        if (trackListLength === 0) return;

        setHighlightedIndex(prev => 
            prev === null ? 0 : Math.min(prev + 1, trackListLength - 1)
        );
    }, [trackListLength]);

    const highlightPrev = useCallback(() => {
        if (trackListLength === 0) return;

        setHighlightedIndex(prev => 
            prev === null ? trackListLength - 1 : Math.max(prev - 1, 0)
        );
    }, [trackListLength]);

    return {
        highlightedIndex,
        setHighlightedIndex,
        highlightNext,
        highlightPrev,
    };
};
