import { useState, useCallback, useMemo } from 'react';

const EMPTY_STATE = { dragIndex: null, dropPosition: null };

export const useDragReorder = (listLength, onReorder) => {
    const [state, setState] = useState(EMPTY_STATE);
    const { dragIndex, dropPosition } = state;

    const handlers = useMemo(() => ({
        onDragStart: (index) => {
            setState({ dragIndex: index, dropPosition: null });
        },

        onDragOver: (index, isTopHalf) => {
            const pos = isTopHalf ? index : index + 1;
            setState(prev => prev.dropPosition === pos ? prev : { ...prev, dropPosition: pos });
        },

        onDragEnd: () => {
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    setState(EMPTY_STATE);
                });
            });
        },

        onDrop: () => {
            setState(prev => {
                const { dragIndex, dropPosition } = prev;

                if (dragIndex !== null && dropPosition !== null) {
                    const targetIndex = dropPosition > dragIndex ? dropPosition - 1 : dropPosition;

                    if (targetIndex !== dragIndex) {
                        onReorder(dragIndex, targetIndex);
                    }
                }

                return prev;
            });
        }
    }), [onReorder]);

    const getItemState = useCallback((index, isLast) => {
        if (dragIndex === null) {
            return null;
        }

        const isNoOp = dropPosition === null ||
                       dropPosition === dragIndex ||
                       dropPosition === dragIndex + 1;

        if (index === dragIndex) {
            return {
                dragging: true,
                cancel: isNoOp && dropPosition !== null
            };
        }

        if (isNoOp) {
            return null;
        }

        let indicator = null;
        if (dropPosition === index) {
            indicator = 'before';
        } else if (isLast && dropPosition === listLength) {
            indicator = 'after';
        }

        let shift = null;
        if (dropPosition === 0 && index === 0) {
            shift = 'down-edge';
        } else if (dropPosition === listLength && index === listLength - 1) {
            shift = 'up-edge';
        } else if (index === dropPosition - 1) {
            shift = 'up';
        } else if (index === dropPosition) {
            shift = 'down';
        }

        return { indicator, shift };
    }, [dragIndex, dropPosition, listLength]);

    const isDragging = dragIndex !== null;

    return { handlers, getItemState, isDragging };
};
