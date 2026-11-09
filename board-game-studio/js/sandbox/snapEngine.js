/**
 * Snap Engine: Magnetic radial snap points & Cartesian board grid calculations (spec §5.1.2).
 */
(function () {
    'use strict';

    /**
     * Given a component dropped at (x, y) with width/height, checks all boards' radial
     * snapPoints first, then Cartesian snapGrid, then Market River / Discard zones.
     *
     * @param {Object} state
     * @param {Object} movingObj
     * @param {number} targetX
     * @param {number} targetY
     * @returns {{x:number, y:number, snappedTo?:string, zoneId?:string, slotIndex?:number}}
     */
    function computeSnap(state, movingObj, targetX, targetY) {
        const cx = targetX + (movingObj.width || 100) / 2;
        const cy = targetY + (movingObj.height || 100) / 2;

        // 1. Check magnetic radial snap points on boards
        const boards = Object.values(state.boardObjects || {}).filter((o) => o && o.type === 'BOARD');
        let bestNode = null;
        let bestDist = Infinity;

        boards.forEach((board) => {
            const pts = Array.isArray(board.snapPoints) ? board.snapPoints : [];
            pts.forEach((pt) => {
                const accepted = Array.isArray(pt.acceptedTypes) ? pt.acceptedTypes : ['CARD', 'TOKEN'];
                if (accepted.length > 0 && !accepted.includes(movingObj.type)) return;
                const worldX = (board.x || 0) + (pt.x || 0);
                const worldY = (board.y || 0) + (pt.y || 0);
                const radius = Number(pt.radius) || 40;
                const d = Math.hypot(cx - worldX, cy - worldY);
                if (d <= radius && d < bestDist) {
                    bestDist = d;
                    bestNode = {
                        x: Math.round(worldX - (movingObj.width || 100) / 2),
                        y: Math.round(worldY - (movingObj.height || 100) / 2),
                        snappedTo: pt.id
                    };
                }
            });
        });

        if (bestNode) return bestNode;

        // 2. Check interactive zones (Discard / Market River / Snap Zone)
        const zones = Array.isArray(state.zones) ? state.zones : [];
        for (let i = 0; i < zones.length; i++) {
            const z = zones[i];
            if (cx >= z.x && cx <= z.x + z.width && cy >= z.y && cy <= z.y + z.height) {
                const slots = Math.max(1, Number(z.slots) || 1);
                const slotW = z.width / slots;
                const slotIdx = Math.min(slots - 1, Math.max(0, Math.floor((cx - z.x) / slotW)));
                const slotCenterX = z.x + slotIdx * slotW + slotW / 2;
                const slotCenterY = z.y + z.height / 2;
                return {
                    x: Math.round(slotCenterX - (movingObj.width || 100) / 2),
                    y: Math.round(slotCenterY - (movingObj.height || 100) / 2),
                    zoneId: z.id,
                    slotIndex: slotIdx
                };
            }
        }

        // 3. Check Cartesian board snapGrid if center is over a board with snapGrid.enabled
        for (let i = 0; i < boards.length; i++) {
            const b = boards[i];
            if (!b.snapGrid || !b.snapGrid.enabled) continue;
            if (cx >= b.x && cx <= b.x + b.width && cy >= b.y && cy <= b.y + b.height) {
                const gx = Math.max(8, Number(b.snapGrid.sizeX) || 80);
                const gy = Math.max(8, Number(b.snapGrid.sizeY) || 80);
                const relX = Math.round((targetX - b.x) / gx) * gx;
                const relY = Math.round((targetY - b.y) / gy) * gy;
                return {
                    x: Math.round(b.x + relX),
                    y: Math.round(b.y + relY),
                    snappedTo: `grid_${b.id}`
                };
            }
        }

        return { x: Math.round(targetX), y: Math.round(targetY) };
    }

    window.SandboxSnapEngine = {
        computeSnap
    };
})();
