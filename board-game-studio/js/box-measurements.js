/**
 * Box Die-Line Measurements & Segment Overlay Generator for Board Game Studio
 * Computes exact millimeter and pixel dimensions between all cut and fold lines
 * (walls, floor, lip flaps, tuck flaps, dust flaps, and glue tabs) across all 4 box styles.
 *
 * Designed as a high-visibility reference printout that maximizes all available space
 * with large, prominent typography and clear fold-to-cut dimension lines.
 */
(function() {
    'use strict';

    const mmToPx = mm => (mm / 25.4) * 300.0;
    const pxToMm = px => (px * 25.4) / 300.0;

    /**
     * Resolves all horizontal (X) and vertical (Y) segments between cut and fold lines,
     * panel centers, and 3D cavity metadata for a given box configuration.
     *
     * @param {Object} boxConfig
     * @returns {Object} { xSegments, ySegments, panelBadges, summary }
     */
    function getBoxSegments(boxConfig) {
        if (!boxConfig || typeof boxConfig !== 'object') {
            return null;
        }

        const boxType = String(boxConfig.boxType || 'double_wall_tray');
        const pad = parseFloat(boxConfig.padMm ?? 6.0);
        const stockMm = parseFloat(boxConfig.stockThicknessMm ?? 0.6);
        const W = parseFloat(boxConfig.effectiveW ?? boxConfig.finishedW ?? 120.0);
        const L = parseFloat(boxConfig.effectiveL ?? boxConfig.finishedL ?? 160.0);
        const H = parseFloat(boxConfig.effectiveH ?? boxConfig.finishedH ?? 40.0);
        const piece = boxConfig.piece === 'lid' ? 'lid' : 'base';

        let shoulder = parseFloat(boxConfig.shoulderMm ?? 0);
        let flapH = parseFloat(boxConfig.flapHMm ?? 0);

        if (boxType === 'double_wall_tray') {
            if (!shoulder) shoulder = Math.max(5.0, Math.min(18.0, (H * 0.22) + (stockMm * 1.5)));
            if (!flapH) flapH = H + shoulder;
        } else if (boxType === 'roll_end_tray') {
            if (!shoulder) shoulder = Math.max(5.0, Math.min(16.0, (H * 0.20) + (stockMm * 1.5)));
            flapH = 0;
        } else if (boxType === 'tuck_top_box') {
            if (!shoulder) shoulder = Math.min(22.0, Math.max(12.0, H * 0.45)); // Glue Tab
            if (!flapH) flapH = Math.min(28.0, Math.max(12.0, H * 0.60)); // Tuck Flap
        }

        let xSegments = [];
        let ySegments = [];
        let panelBadges = [];
        let typeName = 'Box Tray';

        if (boxType === 'double_wall_tray') {
            typeName = piece === 'lid' ? 'Heavy-Duty Double-Wall Tray (Top Lid)' : 'Heavy-Duty Double-Wall Tray (Base)';

            const x0 = pad;
            const x1 = pad + shoulder;
            const x2 = pad + shoulder + H;
            const x3 = pad + shoulder + 2 * H;
            const x4 = pad + shoulder + 2 * H + W;
            const x5 = pad + shoulder + 3 * H + W;
            const x6 = pad + shoulder + 4 * H + W;
            const x7 = pad + 2 * shoulder + 4 * H + W;

            xSegments = [
                { startMm: x0, endMm: x1, lengthMm: shoulder, label: 'Lip Flap', role: 'flap', startType: 'cut', endType: 'fold' },
                { startMm: x1, endMm: x2, lengthMm: H, label: 'Inner Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: x2, endMm: x3, lengthMm: H, label: 'Outer Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: x3, endMm: x4, lengthMm: W, label: 'Floor Width', role: 'floor', startType: 'fold', endType: 'fold' },
                { startMm: x4, endMm: x5, lengthMm: H, label: 'Outer Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: x5, endMm: x6, lengthMm: H, label: 'Inner Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: x6, endMm: x7, lengthMm: shoulder, label: 'Lip Flap', role: 'flap', startType: 'fold', endType: 'cut' },
            ];

            const y0 = pad;
            const y1 = pad + shoulder;
            const y2 = pad + shoulder + H;
            const y3 = pad + shoulder + 2 * H;
            const y4 = pad + shoulder + 2 * H + L;
            const y5 = pad + shoulder + 3 * H + L;
            const y6 = pad + shoulder + 4 * H + L;
            const y7 = pad + 2 * shoulder + 4 * H + L;

            ySegments = [
                { startMm: y0, endMm: y1, lengthMm: shoulder, label: 'Lip Flap', role: 'flap', startType: 'cut', endType: 'fold' },
                { startMm: y1, endMm: y2, lengthMm: H, label: 'Inner Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: y2, endMm: y3, lengthMm: H, label: 'Outer Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: y3, endMm: y4, lengthMm: L, label: 'Floor Length', role: 'floor', startType: 'fold', endType: 'fold' },
                { startMm: y4, endMm: y5, lengthMm: H, label: 'Outer Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: y5, endMm: y6, lengthMm: H, label: 'Inner Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: y6, endMm: y7, lengthMm: shoulder, label: 'Lip Flap', role: 'flap', startType: 'fold', endType: 'cut' },
            ];

            panelBadges = [
                { x: (x3 + x4) / 2, y: (y3 + y4) / 2, wMm: W, hMm: L, title: 'CENTER FLOOR PANEL', dimText: `${W.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Width: ${W.toFixed(1)} mm  •  Length: ${L.toFixed(1)} mm`, role: 'floor' },
                { x: (x3 + x4) / 2, y: (y2 + y3) / 2, wMm: W, hMm: H, title: 'TOP OUTER WALL', dimText: `${W.toFixed(1)} × ${H.toFixed(1)} mm`, subText: `Fold-to-Fold: ${H.toFixed(1)} mm`, role: 'wall' },
                { x: (x3 + x4) / 2, y: (y1 + y2) / 2, wMm: W, hMm: H, title: 'TOP INNER WALL', dimText: `${W.toFixed(1)} × ${H.toFixed(1)} mm`, subText: `Fold-to-Fold: ${H.toFixed(1)} mm`, role: 'wall' },
                { x: (x3 + x4) / 2, y: (y0 + y1) / 2, wMm: W, hMm: shoulder, title: 'TOP LIP FLAP', dimText: `${W.toFixed(1)} × ${shoulder.toFixed(1)} mm`, subText: `Lip: ${shoulder.toFixed(1)} mm`, role: 'flap' },
                { x: (x3 + x4) / 2, y: (y4 + y5) / 2, wMm: W, hMm: H, title: 'BOTTOM OUTER WALL', dimText: `${W.toFixed(1)} × ${H.toFixed(1)} mm`, subText: `Fold-to-Fold: ${H.toFixed(1)} mm`, role: 'wall' },
                { x: (x3 + x4) / 2, y: (y5 + y6) / 2, wMm: W, hMm: H, title: 'BOTTOM INNER WALL', dimText: `${W.toFixed(1)} × ${H.toFixed(1)} mm`, subText: `Fold-to-Fold: ${H.toFixed(1)} mm`, role: 'wall' },
                { x: (x3 + x4) / 2, y: (y6 + y7) / 2, wMm: W, hMm: shoulder, title: 'BOTTOM LIP FLAP', dimText: `${W.toFixed(1)} × ${shoulder.toFixed(1)} mm`, subText: `Lip: ${shoulder.toFixed(1)} mm`, role: 'flap' },
                { x: (x2 + x3) / 2, y: (y3 + y4) / 2, wMm: H, hMm: L, title: 'LEFT OUTER WALL', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Wall: ${H.toFixed(1)} mm`, angle: -90, role: 'wall' },
                { x: (x1 + x2) / 2, y: (y3 + y4) / 2, wMm: H, hMm: L, title: 'LEFT INNER WALL', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Wall: ${H.toFixed(1)} mm`, angle: -90, role: 'wall' },
                { x: (x0 + x1) / 2, y: (y3 + y4) / 2, wMm: shoulder, hMm: L, title: 'LEFT LIP FLAP', dimText: `${shoulder.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Lip: ${shoulder.toFixed(1)} mm`, angle: -90, role: 'flap' },
                { x: (x4 + x5) / 2, y: (y3 + y4) / 2, wMm: H, hMm: L, title: 'RIGHT OUTER WALL', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Wall: ${H.toFixed(1)} mm`, angle: 90, role: 'wall' },
                { x: (x5 + x6) / 2, y: (y3 + y4) / 2, wMm: H, hMm: L, title: 'RIGHT INNER WALL', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Wall: ${H.toFixed(1)} mm`, angle: 90, role: 'wall' },
                { x: (x6 + x7) / 2, y: (y3 + y4) / 2, wMm: shoulder, hMm: L, title: 'RIGHT LIP FLAP', dimText: `${shoulder.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Lip: ${shoulder.toFixed(1)} mm`, angle: 90, role: 'flap' },
            ];
        } else if (boxType === 'roll_end_tray') {
            typeName = piece === 'lid' ? 'Standard Roll-End Tray (Top Lid)' : 'Standard Roll-End Tray (Base)';

            const x0 = pad;
            const x1 = pad + shoulder;
            const x2 = pad + shoulder + H;
            const x3 = pad + shoulder + 2 * H;
            const x4 = pad + shoulder + 2 * H + W;
            const x5 = pad + shoulder + 3 * H + W;
            const x6 = pad + shoulder + 4 * H + W;
            const x7 = pad + 2 * shoulder + 4 * H + W;

            xSegments = [
                { startMm: x0, endMm: x1, lengthMm: shoulder, label: 'Lip Flap', role: 'flap', startType: 'cut', endType: 'fold' },
                { startMm: x1, endMm: x2, lengthMm: H, label: 'Inner Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: x2, endMm: x3, lengthMm: H, label: 'Side Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: x3, endMm: x4, lengthMm: W, label: 'Floor Width', role: 'floor', startType: 'fold', endType: 'fold' },
                { startMm: x4, endMm: x5, lengthMm: H, label: 'Side Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: x5, endMm: x6, lengthMm: H, label: 'Inner Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: x6, endMm: x7, lengthMm: shoulder, label: 'Lip Flap', role: 'flap', startType: 'fold', endType: 'cut' },
            ];

            const y0 = pad;
            const y1 = pad + H;
            const y2 = pad + H + L;
            const y3 = pad + 2 * H + L;

            ySegments = [
                { startMm: y0, endMm: y1, lengthMm: H, label: 'Top Wall', role: 'wall', startType: 'cut', endType: 'fold' },
                { startMm: y1, endMm: y2, lengthMm: L, label: 'Floor Length', role: 'floor', startType: 'fold', endType: 'fold' },
                { startMm: y2, endMm: y3, lengthMm: H, label: 'Bottom Wall', role: 'wall', startType: 'fold', endType: 'cut' },
            ];

            panelBadges = [
                { x: (x3 + x4) / 2, y: (y1 + y2) / 2, wMm: W, hMm: L, title: 'CENTER FLOOR PANEL', dimText: `${W.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Width: ${W.toFixed(1)} mm  •  Length: ${L.toFixed(1)} mm`, role: 'floor' },
                { x: (x3 + x4) / 2, y: (y0 + y1) / 2, wMm: W, hMm: H, title: 'TOP END WALL', dimText: `${W.toFixed(1)} × ${H.toFixed(1)} mm`, subText: `Wall Height: ${H.toFixed(1)} mm`, role: 'wall' },
                { x: (x3 + x4) / 2, y: (y2 + y3) / 2, wMm: W, hMm: H, title: 'BOTTOM END WALL', dimText: `${W.toFixed(1)} × ${H.toFixed(1)} mm`, subText: `Wall Height: ${H.toFixed(1)} mm`, role: 'wall' },
                { x: (x2 + x3) / 2, y: (y1 + y2) / 2, wMm: H, hMm: L, title: 'LEFT SIDE WALL', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Wall: ${H.toFixed(1)} mm`, angle: -90, role: 'wall' },
                { x: (x1 + x2) / 2, y: (y1 + y2) / 2, wMm: H, hMm: L, title: 'LEFT ROLLOVER FLAP', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Wall: ${H.toFixed(1)} mm`, angle: -90, role: 'flap' },
                { x: (x0 + x1) / 2, y: (y1 + y2) / 2, wMm: shoulder, hMm: L, title: 'LEFT LIP', dimText: `${shoulder.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Lip: ${shoulder.toFixed(1)} mm`, angle: -90, role: 'flap' },
                { x: (x4 + x5) / 2, y: (y1 + y2) / 2, wMm: H, hMm: L, title: 'RIGHT SIDE WALL', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Wall: ${H.toFixed(1)} mm`, angle: 90, role: 'wall' },
                { x: (x5 + x6) / 2, y: (y1 + y2) / 2, wMm: H, hMm: L, title: 'RIGHT ROLLOVER FLAP', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Wall: ${H.toFixed(1)} mm`, angle: 90, role: 'flap' },
                { x: (x6 + x7) / 2, y: (y1 + y2) / 2, wMm: shoulder, hMm: L, title: 'RIGHT LIP', dimText: `${shoulder.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Lip: ${shoulder.toFixed(1)} mm`, angle: 90, role: 'flap' },
            ];
        } else if (boxType === 'tuck_top_box') {
            typeName = 'One-Piece Hinged Tuck-Top Box';
            const glueW = shoulder;
            const tuckH = flapH;

            const x0 = pad;
            const x1 = pad + glueW;
            const x2 = pad + glueW + W;
            const x3 = pad + glueW + W + H;
            const x4 = pad + glueW + 2 * W + H;
            const x5 = pad + glueW + 2 * W + 2 * H;

            xSegments = [
                { startMm: x0, endMm: x1, lengthMm: glueW, label: 'Glue Tab', role: 'tab', startType: 'cut', endType: 'fold' },
                { startMm: x1, endMm: x2, lengthMm: W, label: 'Back Panel', role: 'panel', startType: 'fold', endType: 'fold' },
                { startMm: x2, endMm: x3, lengthMm: H, label: 'Left Spine', role: 'spine', startType: 'fold', endType: 'fold' },
                { startMm: x3, endMm: x4, lengthMm: W, label: 'Front Panel', role: 'panel', startType: 'fold', endType: 'fold' },
                { startMm: x4, endMm: x5, lengthMm: H, label: 'Right Spine', role: 'spine', startType: 'fold', endType: 'cut' },
            ];

            const y0 = pad;
            const y1 = pad + tuckH;
            const y2 = pad + tuckH + H;
            const y3 = pad + tuckH + H + L;
            const y4 = pad + tuckH + 2 * H + L;
            const y5 = pad + 2 * tuckH + 2 * H + L;

            ySegments = [
                { startMm: y0, endMm: y1, lengthMm: tuckH, label: 'Top Tuck Flap', role: 'flap', startType: 'cut', endType: 'fold' },
                { startMm: y1, endMm: y2, lengthMm: H, label: 'Top Cap / Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: y2, endMm: y3, lengthMm: L, label: 'Main Body', role: 'floor', startType: 'fold', endType: 'fold' },
                { startMm: y3, endMm: y4, lengthMm: H, label: 'Bottom Cap / Wall', role: 'wall', startType: 'fold', endType: 'fold' },
                { startMm: y4, endMm: y5, lengthMm: tuckH, label: 'Bottom Tuck Flap', role: 'flap', startType: 'fold', endType: 'cut' },
            ];

            panelBadges = [
                { x: (x1 + x2) / 2, y: (y2 + y3) / 2, wMm: W, hMm: L, title: 'BACK / BOTTOM PANEL', dimText: `${W.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Width: ${W.toFixed(1)} mm  •  Length: ${L.toFixed(1)} mm`, role: 'panel' },
                { x: (x3 + x4) / 2, y: (y2 + y3) / 2, wMm: W, hMm: L, title: 'FRONT / LID PANEL', dimText: `${W.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Width: ${W.toFixed(1)} mm  •  Length: ${L.toFixed(1)} mm`, role: 'panel' },
                { x: (x2 + x3) / 2, y: (y2 + y3) / 2, wMm: H, hMm: L, title: 'LEFT SPINE', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Spine Depth: ${H.toFixed(1)} mm`, angle: -90, role: 'spine' },
                { x: (x4 + x5) / 2, y: (y2 + y3) / 2, wMm: H, hMm: L, title: 'RIGHT SPINE', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Spine Depth: ${H.toFixed(1)} mm`, angle: 90, role: 'spine' },
                { x: (x0 + x1) / 2, y: (y2 + y3) / 2, wMm: glueW, hMm: L, title: 'GLUE TAB', dimText: `${glueW.toFixed(1)} mm`, subText: `Tab Width: ${glueW.toFixed(1)} mm`, angle: -90, role: 'tab' },
                { x: (x3 + x4) / 2, y: (y1 + y2) / 2, wMm: W, hMm: H, title: 'TOP WALL', dimText: `${W.toFixed(1)} × ${H.toFixed(1)} mm`, subText: `Height: ${H.toFixed(1)} mm`, role: 'wall' },
                { x: (x3 + x4) / 2, y: (y0 + y1) / 2, wMm: W, hMm: tuckH, title: 'TOP TUCK FLAP', dimText: `${W.toFixed(1)} × ${tuckH.toFixed(1)} mm`, subText: `Flap Height: ${tuckH.toFixed(1)} mm`, role: 'flap' },
                { x: (x3 + x4) / 2, y: (y3 + y4) / 2, wMm: W, hMm: H, title: 'BOTTOM WALL', dimText: `${W.toFixed(1)} × ${H.toFixed(1)} mm`, subText: `Height: ${H.toFixed(1)} mm`, role: 'wall' },
                { x: (x3 + x4) / 2, y: (y4 + y5) / 2, wMm: W, hMm: tuckH, title: 'BOTTOM TUCK FLAP', dimText: `${W.toFixed(1)} × ${tuckH.toFixed(1)} mm`, subText: `Flap Height: ${tuckH.toFixed(1)} mm`, role: 'flap' },
            ];
        } else {
            // simple_tray
            typeName = piece === 'lid' ? 'Single-Wall Setup Tray (Top Lid)' : 'Single-Wall Setup Tray (Base)';

            const x0 = pad;
            const x1 = pad + H;
            const x2 = pad + H + W;
            const x3 = pad + 2 * H + W;

            xSegments = [
                { startMm: x0, endMm: x1, lengthMm: H, label: 'Left Wall', role: 'wall', startType: 'cut', endType: 'fold' },
                { startMm: x1, endMm: x2, lengthMm: W, label: 'Floor Width', role: 'floor', startType: 'fold', endType: 'fold' },
                { startMm: x2, endMm: x3, lengthMm: H, label: 'Right Wall', role: 'wall', startType: 'fold', endType: 'cut' },
            ];

            const y0 = pad;
            const y1 = pad + H;
            const y2 = pad + H + L;
            const y3 = pad + 2 * H + L;

            ySegments = [
                { startMm: y0, endMm: y1, lengthMm: H, label: 'Top Wall', role: 'wall', startType: 'cut', endType: 'fold' },
                { startMm: y1, endMm: y2, lengthMm: L, label: 'Floor Length', role: 'floor', startType: 'fold', endType: 'fold' },
                { startMm: y2, endMm: y3, lengthMm: H, label: 'Bottom Wall', role: 'wall', startType: 'fold', endType: 'cut' },
            ];

            panelBadges = [
                { x: (x1 + x2) / 2, y: (y1 + y2) / 2, wMm: W, hMm: L, title: 'CENTER FLOOR PANEL', dimText: `${W.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Width: ${W.toFixed(1)} mm  •  Length: ${L.toFixed(1)} mm`, role: 'floor' },
                { x: (x1 + x2) / 2, y: (y0 + y1) / 2, wMm: W, hMm: H, title: 'TOP WALL', dimText: `${W.toFixed(1)} × ${H.toFixed(1)} mm`, subText: `Wall Height: ${H.toFixed(1)} mm`, role: 'wall' },
                { x: (x1 + x2) / 2, y: (y2 + y3) / 2, wMm: W, hMm: H, title: 'BOTTOM WALL', dimText: `${W.toFixed(1)} × ${H.toFixed(1)} mm`, subText: `Wall Height: ${H.toFixed(1)} mm`, role: 'wall' },
                { x: (x0 + x1) / 2, y: (y1 + y2) / 2, wMm: H, hMm: L, title: 'LEFT WALL', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Wall Height: ${H.toFixed(1)} mm`, angle: -90, role: 'wall' },
                { x: (x2 + x3) / 2, y: (y1 + y2) / 2, wMm: H, hMm: L, title: 'RIGHT WALL', dimText: `${H.toFixed(1)} × ${L.toFixed(1)} mm`, subText: `Wall Height: ${H.toFixed(1)} mm`, angle: 90, role: 'wall' },
            ];
        }

        const flatW = parseFloat(boxConfig.flatWidthMm ?? (xSegments[xSegments.length - 1].endMm + pad));
        const flatH = parseFloat(boxConfig.flatHeightMm ?? (ySegments[ySegments.length - 1].endMm + pad));

        return {
            xSegments,
            ySegments,
            panelBadges,
            summary: {
                boxType,
                typeName,
                piece,
                usableW: parseFloat(boxConfig.usableW ?? W),
                usableL: parseFloat(boxConfig.usableL ?? L),
                usableH: parseFloat(boxConfig.usableH ?? H),
                effectiveW: W,
                effectiveL: L,
                effectiveH: H,
                stockThicknessMm: stockMm,
                shoulderMm: shoulder,
                flapHMm: flapH,
                padMm: pad,
                flatWidthMm: flatW,
                flatHeightMm: flatH
            }
        };
    }

    /**
     * Renders high-visibility dimension lines, boundary ticks, and prominent measurement badges
     * between all cut and fold lines directly onto a Fabric.js canvas.
     * Takes up all available space across panels and margins so reference printouts are easily readable.
     *
     * @param {fabric.Canvas} canvas
     * @param {Object} boxConfig
     */
    function renderMeasurementsOnCanvas(canvas, boxConfig) {
        if (!canvas) return;

        removeMeasurementsFromCanvas(canvas);

        const data = getBoxSegments(boxConfig);
        if (!data) return;

        const { xSegments, ySegments, panelBadges, summary } = data;
        const padPx = mmToPx(summary.padMm);
        const canvasW = canvas.getWidth();
        const canvasH = canvas.getHeight();

        // Hide default small panel guide labels so they don't visually collide with measurement badges
        canvas.getObjects().forEach(o => {
            if (o.dielineRole === 'label' || (o.id && String(o.id).startsWith('box-dieline-label-'))) {
                o._preMeasurementVisible = o.visible;
                o.set('visible', false);
            }
        });

        const createdObjects = [];

        function addObj(fabricObj) {
            fabricObj.isBoxMeasurementOverlay = true;
            fabricObj.selectable = false;
            fabricObj.evented = false;
            fabricObj.lockMovementX = true;
            fabricObj.lockMovementY = true;
            createdObjects.push(fabricObj);
            canvas.add(fabricObj);
        }

        // --- 1. TOP RULER CHAIN (X SEGMENTS) ---
        // Generous ruler height and stroke thickness for high-contrast visibility
        const topRulerY = Math.max(32, padPx * 0.50);

        xSegments.forEach((seg, idx) => {
            const xStart = mmToPx(seg.startMm);
            const xEnd = mmToPx(seg.endMm);
            const segW = xEnd - xStart;
            const midX = (xStart + xEnd) / 2;

            // Thick boundary tick marks at start & end
            addObj(new fabric.Line([xStart, topRulerY - 18, xStart, topRulerY + 18], {
                stroke: seg.startType === 'cut' ? '#0f172a' : '#3730a3',
                strokeWidth: seg.startType === 'cut' ? 4.5 : 3.5,
                strokeDashArray: seg.startType === 'fold' ? [6, 4] : null
            }));

            // High-contrast vertical extension guide dropping down towards the box cut/fold line
            addObj(new fabric.Line([xStart, topRulerY + 18, xStart, Math.max(topRulerY + 18, padPx)], {
                stroke: '#6366f1',
                strokeWidth: 2,
                strokeDashArray: [6, 6],
                opacity: 0.75
            }));

            // If last segment, also draw the right end boundary tick
            if (idx === xSegments.length - 1) {
                addObj(new fabric.Line([xEnd, topRulerY - 18, xEnd, topRulerY + 18], {
                    stroke: seg.endType === 'cut' ? '#0f172a' : '#3730a3',
                    strokeWidth: seg.endType === 'cut' ? 4.5 : 3.5,
                    strokeDashArray: seg.endType === 'fold' ? [6, 4] : null
                }));
                addObj(new fabric.Line([xEnd, topRulerY + 18, xEnd, Math.max(topRulerY + 18, padPx)], {
                    stroke: '#6366f1',
                    strokeWidth: 2,
                    strokeDashArray: [6, 6],
                    opacity: 0.75
                }));
            }

            // Heavy horizontal dimension line across the segment
            addObj(new fabric.Line([xStart, topRulerY, xEnd, topRulerY], {
                stroke: '#3730a3',
                strokeWidth: 3.5
            }));

            // Inward arrowheads (large and bold)
            const arrowLen = Math.min(18, Math.max(8, segW * 0.18));
            if (segW > 24) {
                addObj(new fabric.Line([xStart, topRulerY, xStart + arrowLen, topRulerY - 8], { stroke: '#3730a3', strokeWidth: 3.5 }));
                addObj(new fabric.Line([xStart, topRulerY, xStart + arrowLen, topRulerY + 8], { stroke: '#3730a3', strokeWidth: 3.5 }));
                addObj(new fabric.Line([xEnd, topRulerY, xEnd - arrowLen, topRulerY - 8], { stroke: '#3730a3', strokeWidth: 3.5 }));
                addObj(new fabric.Line([xEnd, topRulerY, xEnd - arrowLen, topRulerY + 8], { stroke: '#3730a3', strokeWidth: 3.5 }));
            }

            // High-impact measurement pill badge (filling the available horizontal segment space!)
            const mainText = `${seg.lengthMm.toFixed(1)} mm`;
            const isWide = segW >= 180;
            const badgeLabel = isWide ? `${seg.label.toUpperCase()}\n${mainText}` : mainText;
            const badgeFontSize = isWide ? 22 : Math.min(32, Math.max(16, segW * 0.22));
            const badgeH = isWide ? 58 : 44;
            const badgeW = Math.min(segW - 8, Math.max(70, isWide ? Math.min(segW * 0.85, 340) : 100));

            addObj(new fabric.Text(badgeLabel, {
                left: midX,
                top: topRulerY - 14,
                originX: 'center',
                originY: 'center',
                fontFamily: 'Inter, system-ui, sans-serif',
                fontSize: badgeFontSize,
                fontWeight: '900',
                fill: '#1e1b4b',
                textAlign: 'center',
                lineHeight: 1.12
            }));
        });

        // --- 2. LEFT RULER CHAIN (Y SEGMENTS) ---
        const leftRulerX = Math.max(32, padPx * 0.50);

        ySegments.forEach((seg, idx) => {
            const yStart = mmToPx(seg.startMm);
            const yEnd = mmToPx(seg.endMm);
            const segH = yEnd - yStart;
            const midY = (yStart + yEnd) / 2;

            // Thick boundary tick marks
            addObj(new fabric.Line([leftRulerX - 18, yStart, leftRulerX + 18, yStart], {
                stroke: seg.startType === 'cut' ? '#0f172a' : '#3730a3',
                strokeWidth: seg.startType === 'cut' ? 4.5 : 3.5,
                strokeDashArray: seg.startType === 'fold' ? [6, 4] : null
            }));

            // High-contrast horizontal extension guide reaching towards the box cut/fold line
            addObj(new fabric.Line([leftRulerX + 18, yStart, Math.max(leftRulerX + 18, padPx), yStart], {
                stroke: '#6366f1',
                strokeWidth: 2,
                strokeDashArray: [6, 6],
                opacity: 0.75
            }));

            if (idx === ySegments.length - 1) {
                addObj(new fabric.Line([leftRulerX - 18, yEnd, leftRulerX + 18, yEnd], {
                    stroke: seg.endType === 'cut' ? '#0f172a' : '#3730a3',
                    strokeWidth: seg.endType === 'cut' ? 4.5 : 3.5,
                    strokeDashArray: seg.endType === 'fold' ? [6, 4] : null
                }));
                addObj(new fabric.Line([leftRulerX + 18, yEnd, Math.max(leftRulerX + 18, padPx), yEnd], {
                    stroke: '#6366f1',
                    strokeWidth: 2,
                    strokeDashArray: [6, 6],
                    opacity: 0.75
                }));
            }

            // Heavy vertical dimension line
            addObj(new fabric.Line([leftRulerX, yStart, leftRulerX, yEnd], {
                stroke: '#3730a3',
                strokeWidth: 3.5
            }));

            // Inward arrowheads
            const arrowLen = Math.min(18, Math.max(8, segH * 0.18));
            if (segH > 24) {
                addObj(new fabric.Line([leftRulerX, yStart, leftRulerX - 8, yStart + arrowLen], { stroke: '#3730a3', strokeWidth: 3.5 }));
                addObj(new fabric.Line([leftRulerX, yStart, leftRulerX + 8, yStart + arrowLen], { stroke: '#3730a3', strokeWidth: 3.5 }));
                addObj(new fabric.Line([leftRulerX, yEnd, leftRulerX - 8, yEnd - arrowLen], { stroke: '#3730a3', strokeWidth: 3.5 }));
                addObj(new fabric.Line([leftRulerX, yEnd, leftRulerX + 8, yEnd - arrowLen], { stroke: '#3730a3', strokeWidth: 3.5 }));
            }

            // Measurement text along left ruler (no background box)
            const mainText = `${seg.lengthMm.toFixed(1)} mm`;
            const isTall = segH >= 180;
            const badgeLabel = isTall ? `${seg.label.toUpperCase()} • ${mainText}` : mainText;
            const badgeFontSize = isTall ? 22 : Math.min(30, Math.max(16, segH * 0.22));

            addObj(new fabric.Text(badgeLabel, {
                left: leftRulerX - 14,
                top: midY,
                angle: -90,
                originX: 'center',
                originY: 'center',
                fontFamily: 'Inter, system-ui, sans-serif',
                fontSize: badgeFontSize,
                fontWeight: '900',
                fill: '#1e1b4b',
                textAlign: 'center'
            }));
        });

        // --- 3. INTERNAL PANEL DIMENSION BADGES (NO BACKGROUND RECTANGLES) ---
        panelBadges.forEach(pb => {
            const pX = mmToPx(pb.x);
            const pY = mmToPx(pb.y);
            const rot = pb.angle || 0;
            const isFloor = pb.role === 'floor';
            const isWall = pb.role === 'wall' || pb.role === 'panel' || pb.role === 'spine';

            const panelHeightPx = pb.hMm ? mmToPx(pb.hMm) : (isFloor ? 1000 : 350);

            let mainFontSize = 48;

            if (isFloor) {
                mainFontSize = Math.min(68, Math.max(34, panelHeightPx * 0.16));
            } else if (isWall) {
                mainFontSize = Math.min(54, Math.max(26, panelHeightPx * 0.26));
            } else {
                mainFontSize = Math.min(42, Math.max(22, panelHeightPx * 0.32));
            }

            let lines = [];
            if (panelHeightPx >= 200) {
                lines.push(pb.title);
                lines.push(pb.dimText);
                if (pb.subText) lines.push(pb.subText);
            } else if (panelHeightPx >= 100) {
                lines.push(pb.title);
                lines.push(pb.dimText);
            } else {
                lines.push(`${pb.title}: ${pb.dimText}`);
            }

            const formattedText = lines.join('\n');

            addObj(new fabric.Text(formattedText, {
                left: pX,
                top: pY,
                angle: rot,
                originX: 'center',
                originY: 'center',
                fontFamily: 'Inter, system-ui, sans-serif',
                fontSize: mainFontSize,
                fontWeight: '900',
                fill: isFloor ? '#1e1b4b' : '#0f172a',
                textAlign: 'center',
                lineHeight: 1.15
            }));
        });

        // --- 4. LARGE REFERENCE BLUEPRINT SPECIFICATION (NO BACKGROUND RECTANGLE) ---
        // Placed in the open corner margin where box flaps never intrude
        const legendX = Math.max(24, padPx * 0.50);
        const legendH = 180;
        const legendY = canvasH - Math.max(24, padPx * 0.50) - legendH;

        const legendText = 
            `📦 BOX REFERENCE BLUEPRINT (ALL MEASUREMENTS IN MM)\n\n` +
            `• Model: ${summary.typeName.toUpperCase()}\n` +
            `• Usable Cavity: ${summary.usableW.toFixed(1)} (W) × ${summary.usableL.toFixed(1)} (L) × ${summary.usableH.toFixed(1)} (H) mm\n` +
            `• Cardboard Caliper: ${summary.stockThicknessMm.toFixed(2)} mm  |  Margin Pad: ${summary.padMm.toFixed(1)} mm\n` +
            `• Flat Sheet Footprint: ${summary.flatWidthMm.toFixed(1)} × ${summary.flatHeightMm.toFixed(1)} mm\n` +
            `• Line Guide:  ━━ Solid: CUT THROUGH     - - - Dashed: SCORE & FOLD`;

        addObj(new fabric.Text(legendText, {
            left: legendX,
            top: legendY,
            fontFamily: 'Inter, system-ui, monospace',
            fontSize: 16.5,
            fontWeight: 'bold',
            fill: '#0f172a',
            lineHeight: 1.3
        }));

        // Bring all measurement elements to the absolute front
        createdObjects.forEach(obj => canvas.bringToFront(obj));
    }

    /**
     * Removes all measurement overlay objects and restores previously hidden guide labels.
     *
     * @param {fabric.Canvas} canvas
     */
    function removeMeasurementsFromCanvas(canvas) {
        if (!canvas) return;

        const toRemove = canvas.getObjects().filter(o => o.isBoxMeasurementOverlay);
        toRemove.forEach(o => canvas.remove(o));

        // Restore original labels
        canvas.getObjects().forEach(o => {
            if (o._preMeasurementVisible !== undefined) {
                o.set('visible', o._preMeasurementVisible);
                delete o._preMeasurementVisible;
            }
        });
    }

    window.boxMeasurements = {
        getBoxSegments,
        renderMeasurementsOnCanvas,
        removeMeasurementsFromCanvas,
        mmToPx,
        pxToMm
    };
})();
