/**
 * Board & Zone Layer Renderer (Tier 1: 100-199 & Tier 2: 200-299, spec §3, §5.1.2).
 */
(function () {
    'use strict';

    const { escapeHtml } = window.SandboxSchema;

    function renderBoardsAndZones(boardLayerEl, zoneLayerEl, state, ui, handlers) {
        boardLayerEl.innerHTML = '';
        zoneLayerEl.innerHTML = '';

        const boards = Object.values(state.boardObjects || {})
            .filter((o) => o && o.type === 'BOARD')
            .sort((a, b) => (a.zIndex || 100) - (b.zIndex || 100));

        boards.forEach((board) => {
            const el = document.createElement('div');
            const isSelected = ui.selectedIds.includes(board.id);
            const snapToolActive = ui.snapPointToolBoardId === board.id;
            el.className = `sbx-obj rounded-2xl border-2 transition ${isSelected ? 'is-selected border-sky-400' : 'border-slate-700/80'} ${board.isLocked ? 'cursor-default' : 'cursor-move'}`;
            el.dataset.objectId = board.id;
            el.style.left = `${board.x || 0}px`;
            el.style.top = `${board.y || 0}px`;
            el.style.width = `${board.width || 800}px`;
            el.style.height = `${board.height || 600}px`;
            el.style.zIndex = String(board.zIndex || 100);
            el.style.transform = `rotate(${board.rotation || 0}deg)`;
            el.style.backgroundColor = board.fill || '#111a2e';

            if (board.imageUrl) {
                el.style.backgroundImage = `url("${escapeHtml(board.imageUrl)}")`;
                el.style.backgroundSize = 'cover';
                el.style.backgroundPosition = 'center';
            }

            // Optional Cartesian grid overlay
            let gridStyle = '';
            if (board.snapGrid && board.snapGrid.enabled) {
                const gx = Math.max(16, Number(board.snapGrid.sizeX) || 80);
                const gy = Math.max(16, Number(board.snapGrid.sizeY) || 80);
                gridStyle = `background-image: linear-gradient(to right, rgba(56,189,248,0.18) 1px, transparent 1px), linear-gradient(to bottom, rgba(56,189,248,0.18) 1px, transparent 1px); background-size: ${gx}px ${gy}px;`;
            }

            el.innerHTML = `
                <div class="w-full h-full relative rounded-2xl overflow-hidden" style="${gridStyle}">
                    <div class="absolute top-2.5 left-3 right-3 flex items-center justify-between pointer-events-none">
                        <span class="px-2.5 py-1 rounded-lg bg-slate-950/80 border border-slate-700/70 text-[11px] font-bold text-slate-200 tracking-wide">
                            ${escapeHtml(board.name || 'Game Board')}
                        </span>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold ${board.isLocked ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-900/80 text-slate-400'}">
                            ${board.isLocked ? '🔒 Locked' : 'Unlocked (L to lock)'}
                        </span>
                    </div>
                    ${snapToolActive ? '<div class="absolute inset-0 bg-sky-500/10 border-2 border-dashed border-sky-400 flex items-end justify-center pb-3 pointer-events-none"><span class="px-3 py-1 rounded-full bg-sky-950/90 text-sky-300 text-xs font-semibold">Click anywhere on board to place magnetic Snap Point</span></div>' : ''}
                </div>
            `;

            // Render radial snap points in Setup Mode or when visible
            const pts = Array.isArray(board.snapPoints) ? board.snapPoints : [];
            pts.forEach((pt, idx) => {
                const node = document.createElement('div');
                const r = (Number(pt.radius) || 40) * 2;
                node.className = 'sbx-snap-node flex items-center justify-center';
                node.style.left = `${pt.x || 0}px`;
                node.style.top = `${pt.y || 0}px`;
                node.style.width = `${r}px`;
                node.style.height = `${r}px`;
                if (state.mode === 'SETUP') {
                    node.innerHTML = `<span class="text-[9px] font-bold text-sky-300 bg-slate-950/80 px-1.5 py-0.5 rounded">#${idx + 1}</span>`;
                }
                el.appendChild(node);
            });

            el.addEventListener('pointerdown', (e) => handlers.onObjectPointerDown(e, board));
            el.addEventListener('contextmenu', (e) => handlers.onObjectContextMenu(e, board));
            boardLayerEl.appendChild(el);
        });

        // Render Tier 2 Zones (Market Rivers, Discard Trays, Snap Zones)
        (state.zones || []).forEach((zone) => {
            const zEl = document.createElement('div');
            zEl.className = 'sbx-obj rounded-xl border-2 border-dashed border-indigo-400/50 bg-indigo-950/20 flex flex-col justify-between p-2';
            zEl.style.left = `${zone.x || 0}px`;
            zEl.style.top = `${zone.y || 0}px`;
            zEl.style.width = `${zone.width || 620}px`;
            zEl.style.height = `${zone.height || 220}px`;
            zEl.style.zIndex = '220';

            const slots = Math.max(1, Number(zone.slots) || 4);
            let slotsHtml = '';
            for (let i = 0; i < slots; i++) {
                slotsHtml += `<div class="flex-1 h-full border border-dashed border-indigo-400/25 rounded-lg flex items-center justify-center text-[10px] text-indigo-300/40 font-semibold">Slot ${i + 1}</div>`;
            }

            zEl.innerHTML = `
                <div class="flex items-center justify-between px-1 pointer-events-none">
                    <span class="text-[11px] font-bold text-indigo-300 uppercase tracking-wider">${escapeHtml(zone.name || zone.type)}</span>
                    <span class="text-[10px] text-indigo-400">${zone.autoReplenish ? 'Auto-Replenish' : zone.type}</span>
                </div>
                <div class="flex-1 flex gap-2 mt-1 pointer-events-none">${slotsHtml}</div>
            `;

            if (state.mode === 'SETUP') {
                zEl.style.cursor = 'move';
                zEl.addEventListener('pointerdown', (e) => handlers.onZonePointerDown(e, zone));
            }
            zEl.addEventListener('contextmenu', (e) => handlers.onZoneContextMenu(e, zone));
            zoneLayerEl.appendChild(zEl);
        });
    }

    window.SandboxBoardLayer = { renderBoardsAndZones };
})();
