/**
 * Parent-Child Card Tucking & Stacking Engine (spec §5.2).
 *
 * - Dragging Card B onto the top/bottom edge of Card A highlights a "Tuck / Attach" zone.
 * - Dropping binds CardB.parentId = CardA.id and appends to CardA.tuckedChildren with offsetY: ±35px.
 * - Moving parent card moves all attached children in unison.
 * - Dragging a child card out detaches it from its parent.
 */
(function () {
    'use strict';

    const DEFAULT_OFFSET_Y = -35;

    /**
     * Finds a candidate parent card when hovering/dropping `movingCard` at `(x, y)`.
     */
    function findTuckCandidate(boardObjects, movingCard, x, y) {
        if (!movingCard || movingCard.type !== 'CARD') return null;
        const w = movingCard.width || 140;
        const h = movingCard.height || 200;
        const cx = x + w / 2;
        const cy = y + h / 2;

        const cards = Object.values(boardObjects || {}).filter(
            (o) => o && o.type === 'CARD' && o.id !== movingCard.id && o.ownerSeat === null && o.parentId !== movingCard.id
        );

        let best = null;
        let bestScore = Infinity;

        cards.forEach((target) => {
            // Walk up to root parent if target is itself a tucked child
            const root = target.parentId && boardObjects[target.parentId] ? boardObjects[target.parentId] : target;
            if (!root || root.id === movingCard.id) return;

            const tw = root.width || 140;
            const th = root.height || 200;
            const tcx = root.x + tw / 2;
            const tcy = root.y + th / 2;
            const dx = Math.abs(cx - tcx);
            const dy = Math.abs(cy - tcy);

            // Within top/bottom tuck band of target card
            if (dx < tw * 0.65 && dy < th * 0.65) {
                const dist = Math.hypot(dx, dy);
                if (dist < bestScore) {
                    bestScore = dist;
                    const isBottomEdge = cy > tcy + th * 0.15;
                    best = {
                        parentId: root.id,
                        edge: isBottomEdge ? 'BOTTOM' : 'TOP',
                        offsetY: isBottomEdge ? 38 : DEFAULT_OFFSET_Y
                    };
                }
            }
        });

        return best;
    }

    /**
     * Detaches `childId` from any parent card in `boardObjects`.
     */
    function detachFromParent(boardObjects, childId) {
        const child = boardObjects[childId];
        if (!child || !child.parentId) return false;
        const parent = boardObjects[child.parentId];
        child.parentId = null;
        if (parent && Array.isArray(parent.tuckedChildren)) {
            parent.tuckedChildren = parent.tuckedChildren.filter((tc) => tc.cardId !== childId);
        }
        return true;
    }

    /**
     * Attaches `childId` to `parentId` with cascading offset so each tucked card peeks out cleanly.
     */
    function attachToParent(boardObjects, parentId, childId, preferredOffsetY) {
        const parent = boardObjects[parentId];
        const child = boardObjects[childId];
        if (!parent || !child || parentId === childId) return false;

        detachFromParent(boardObjects, childId);

        if (!Array.isArray(parent.tuckedChildren)) {
            parent.tuckedChildren = [];
        }

        // If child itself had tucked children, re-home them onto the new parent too
        const grandChildren = Array.isArray(child.tuckedChildren) ? child.tuckedChildren.slice() : [];
        child.tuckedChildren = [];

        const step = Number.isFinite(preferredOffsetY) ? preferredOffsetY : DEFAULT_OFFSET_Y;
        const idx = parent.tuckedChildren.length + 1;
        const offsetX = 0;
        const offsetY = step * idx;
        const zIndexOffset = -idx;

        parent.tuckedChildren.push({
            cardId: childId,
            offsetX,
            offsetY,
            zIndexOffset
        });

        child.parentId = parentId;
        child.x = Math.round(parent.x + offsetX);
        child.y = Math.round(parent.y + offsetY);
        child.zIndex = window.SandboxSchema.clampZ('CARD', (parent.zIndex || 350) + zIndexOffset);

        grandChildren.forEach((gc) => {
            if (gc && gc.cardId && boardObjects[gc.cardId]) {
                attachToParent(boardObjects, parentId, gc.cardId, step);
            }
        });

        return true;
    }

    /**
     * Synchronizes coordinates and Z-indices of all `tuckedChildren` attached to `parentId`.
     */
    function syncChildrenPositions(boardObjects, parentId) {
        const parent = boardObjects[parentId];
        if (!parent || !Array.isArray(parent.tuckedChildren)) return;
        parent.tuckedChildren.forEach((tc, i) => {
            const child = boardObjects[tc.cardId];
            if (!child) return;
            child.parentId = parent.id;
            child.x = Math.round(parent.x + (Number(tc.offsetX) || 0));
            child.y = Math.round(parent.y + (Number(tc.offsetY) || (DEFAULT_OFFSET_Y * (i + 1))));
            child.zIndex = window.SandboxSchema.clampZ('CARD', (parent.zIndex || 350) + (Number(tc.zIndexOffset) || -(i + 1)));
        });
    }

    window.SandboxTuckingEngine = {
        findTuckCandidate,
        detachFromParent,
        attachToParent,
        syncChildrenPositions
    };
})();
