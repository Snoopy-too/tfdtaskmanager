/**
 * Guide Renderer Module
 * Draws the bleed and safe margin guidelines as overlay rects on the canvas.
 */
(function() {
    'use strict';

    let guidesVisible = true;

    function mmToPx(mm) {
        return Math.round((mm / 25.4) * 300);
    }

    function renderGuides() {
        const canvas = window.editorCanvas;
        if (!canvas) return;

        // Remove existing guides if present
        const oldBleed = canvas.getObjects().find(o => o.id === 'bleed-zone-guide');
        const oldSafe = canvas.getObjects().find(o => o.id === 'safe-zone-guide');
        if (oldBleed) canvas.remove(oldBleed);
        if (oldSafe) canvas.remove(oldSafe);

        if (!guidesVisible) {
            canvas.renderAll();
            return;
        }

        const width = window.studioConfig.canvasWidth;
        const height = window.studioConfig.canvasHeight;
        
        const bleedPx = mmToPx(window.studioConfig.bleedMm);
        const safeMarginPx = mmToPx(window.studioConfig.safeMarginMm);

        // 1. Bleed Zone Rect (Red dashed)
        // Bleed margin is drawn inwards from the border
        const bleedRect = new fabric.Rect({
            id: 'bleed-zone-guide',
            left: bleedPx,
            top: bleedPx,
            width: width - (bleedPx * 2),
            height: height - (bleedPx * 2),
            fill: 'transparent',
            stroke: '#ef4444',
            strokeWidth: 2,
            strokeDashArray: [6, 4],
            selectable: false,
            evented: false,
            excludeFromExport: true // Custom prop for PDF generation
        });

        // 2. Safe Margin Rect (Green dashed)
        // Safe margin is drawn inwards from the border
        const safeRect = new fabric.Rect({
            id: 'safe-zone-guide',
            left: safeMarginPx,
            top: safeMarginPx,
            width: width - (safeMarginPx * 2),
            height: height - (safeMarginPx * 2),
            fill: 'transparent',
            stroke: '#22c55e',
            strokeWidth: 1.5,
            strokeDashArray: [4, 4],
            selectable: false,
            evented: false,
            excludeFromExport: true
        });

        canvas.add(bleedRect);
        canvas.add(safeRect);

        // Bring to front
        bleedRect.bringToFront();
        safeRect.bringToFront();
        canvas.renderAll();
    }

    function toggleGuides() {
        guidesVisible = !guidesVisible;
        const btn = document.getElementById('btn-toggle-guides');
        btn.textContent = `Guides: ${guidesVisible ? 'ON' : 'OFF'}`;
        
        if (guidesVisible) {
            btn.classList.remove('bg-slate-900/60', 'text-slate-400', 'border-slate-800');
            btn.classList.add('bg-indigo-500/10', 'text-indigo-400', 'border-indigo-500/20');
        } else {
            btn.classList.remove('bg-indigo-500/10', 'text-indigo-400', 'border-indigo-500/20');
            btn.classList.add('bg-slate-900/60', 'text-slate-400', 'border-slate-800');
        }

        renderGuides();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const toggleBtn = document.getElementById('btn-toggle-guides');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleGuides);
        }
    });

    window.guideRenderer = {
        renderGuides: renderGuides,
        toggleGuides: toggleGuides,
        mmToPx: mmToPx
    };
})();
