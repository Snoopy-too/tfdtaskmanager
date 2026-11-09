/**
 * Setup Mode Component Drawer & Object Inspector (spec §4.1, §5.1).
 * Groups imported project assets into Boards, Cards/Decks, Tokens/Meeples, Dice, and Utility Zones.
 */
(function () {
    'use strict';

    const { escapeHtml, SEAT_COLORS } = window.SandboxSchema;

    function renderComponentDrawer(drawerEl, inspectorEl, store, bootstrap) {
        const state = store.getState();
        const ui = store.getUI();
        const assets = (bootstrap.projectAssets && bootstrap.projectAssets.importedAssets) || { boards: [], cardBatches: [], tokens: [] };

        if (state.mode === 'PLAY') {
            drawerEl.classList.add('hidden');
        } else {
            drawerEl.classList.remove('hidden');
            drawerEl.innerHTML = `
                <div class="p-3 border-b border-slate-800 flex items-center justify-between">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-sky-400">Project Asset Importer</span>
                    <span class="text-[10px] text-slate-500">Click or Drag</span>
                </div>
                <div class="flex-1 overflow-y-auto p-3 space-y-4 text-xs">
                    <!-- 1. Boards & Mats -->
                    <div class="space-y-1.5">
                        <div class="font-bold text-slate-300 flex items-center justify-between">
                            <span>🗺 Boards & Mats</span>
                            <span class="text-[10px] text-slate-500">Tier 1</span>
                        </div>
                        <div class="space-y-1" id="sbx-drawer-boards"></div>
                    </div>

                    <!-- 2. Card Batches / Decks -->
                    <div class="space-y-1.5">
                        <div class="font-bold text-slate-300 flex items-center justify-between">
                            <span>🃏 Card Decks</span>
                            <span class="text-[10px] text-slate-500">Tier 3</span>
                        </div>
                        <div class="space-y-1" id="sbx-drawer-decks"></div>
                    </div>

                    <!-- 3. Tokens, Infinite Spawners & Meeples -->
                    <div class="space-y-1.5">
                        <div class="font-bold text-slate-300 flex items-center justify-between">
                            <span>♟ Tokens & Spawners</span>
                            <span class="text-[10px] text-slate-500">Tier 4</span>
                        </div>
                        <div class="grid grid-cols-3 gap-1" id="sbx-drawer-meeples"></div>
                        <div class="space-y-1 mt-1" id="sbx-drawer-tokens"></div>
                    </div>

                    <!-- 4. Instant 2D Dice Rollers -->
                    <div class="space-y-1.5">
                        <div class="font-bold text-slate-300">🎲 2D Dice Rollers</div>
                        <div class="grid grid-cols-3 gap-1" id="sbx-drawer-dice"></div>
                    </div>

                    <!-- 5. Utility Zones -->
                    <div class="space-y-1.5">
                        <div class="font-bold text-slate-300 flex items-center justify-between">
                            <span>📥 Utility Zones</span>
                            <span class="text-[10px] text-slate-500">Tier 2</span>
                        </div>
                        <div class="space-y-1" id="sbx-drawer-zones"></div>
                    </div>

                    <!-- 6. Seating & Resource Seeding -->
                    <div class="space-y-2 pt-2 border-t border-slate-800">
                        <div class="font-bold text-slate-300">👥 Seating (${state.seats.length} Seats)</div>
                        <div class="flex items-center gap-1">
                            ${[1, 2, 3, 4, 5, 6].map((n) => `
                                <button type="button" data-seat-cnt="${n}" class="flex-1 py-1 rounded-lg border text-[11px] font-bold ${state.seats.length === n ? 'bg-sky-600 border-sky-400 text-white' : 'bg-slate-950 border-slate-800 text-slate-400 hover:text-white'}">${n}P</button>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;

            // Populate Boards
            const boardsBox = drawerEl.querySelector('#sbx-drawer-boards');
            (assets.boards || []).forEach((b) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.draggable = true;
                btn.className = 'w-full text-left px-2.5 py-2 rounded-xl bg-slate-950 hover:bg-slate-800 border border-slate-800 flex items-center justify-between transition';
                btn.innerHTML = `<span class="truncate font-semibold text-slate-200">${escapeHtml(b.name)}</span><span class="text-[10px] text-sky-400">+ Spawn</span>`;
                const spec = { type: 'BOARD', assetId: b.assetId, name: b.name, imageUrl: b.imageUrl || null, width: b.width || 800, height: b.height || 600, x: -200, y: -120, isLocked: false };
                btn.onclick = () => store.spawnObject(spec);
                btn.ondragstart = (e) => e.dataTransfer.setData('application/x-sandbox-item', JSON.stringify({ dragKind: 'ASSET_SPAWN', spawnType: 'OBJECT', spec }));
                boardsBox.appendChild(btn);
            });

            // Populate Card Batches
            const decksBox = drawerEl.querySelector('#sbx-drawer-decks');
            (assets.cardBatches || []).forEach((cb) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.draggable = true;
                btn.className = 'w-full text-left px-2.5 py-2 rounded-xl bg-slate-950 hover:bg-slate-800 border border-slate-800 flex items-center justify-between transition';
                btn.innerHTML = `<span class="truncate font-semibold text-slate-200">${escapeHtml(cb.name)}</span><span class="text-[10px] text-indigo-400">${(cb.cards || []).length} cards</span>`;
                btn.onclick = () => store.spawnCardBatchAsDeck(cb, 80, 40);
                btn.ondragstart = (e) => e.dataTransfer.setData('application/x-sandbox-item', JSON.stringify({ dragKind: 'ASSET_SPAWN', spawnType: 'CARD_BATCH', batch: cb }));
                decksBox.appendChild(btn);
            });

            // Populate Colored Worker Meeple Infinite Spawners
            const meeplesBox = drawerEl.querySelector('#sbx-drawer-meeples');
            SEAT_COLORS.forEach((col, idx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'px-2 py-1.5 rounded-lg bg-slate-950 hover:bg-slate-800 border border-slate-800 flex items-center gap-1.5 text-[10px] font-bold text-slate-200';
                btn.innerHTML = `<span class="w-3 h-3 rounded-full flex-shrink-0" style="background:${col}"></span><span>Bag P${idx + 1}</span>`;
                btn.onclick = () => store.spawnObject({
                    type: 'SPAWNER',
                    name: `P${idx + 1} Supply`,
                    color: col,
                    infinite: true,
                    x: -260,
                    y: -80 + idx * 72,
                    width: 64,
                    height: 64
                });
                meeplesBox.appendChild(btn);
            });

            // Project Token Assets
            const tokensBox = drawerEl.querySelector('#sbx-drawer-tokens');
            (assets.tokens || []).slice(0, 12).forEach((t) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'w-full text-left px-2.5 py-1.5 rounded-lg bg-slate-950 hover:bg-slate-800 border border-slate-800 flex items-center justify-between text-[11px]';
                btn.innerHTML = `<span class="truncate text-slate-300">${escapeHtml(t.name)}</span><span class="text-[10px] text-emerald-400">+ Bag</span>`;
                btn.onclick = () => store.spawnObject({
                    type: 'SPAWNER',
                    assetId: t.assetId,
                    name: `${t.name} Supply`,
                    imageUrl: t.imageUrl || null,
                    color: '#10B981',
                    infinite: true,
                    x: -180,
                    y: 40,
                    width: 64,
                    height: 64
                });
                tokensBox.appendChild(btn);
            });

            // Populate Standard Dice (d4, d6, d8, d10, d12, d20)
            const diceBox = drawerEl.querySelector('#sbx-drawer-dice');
            [4, 6, 8, 10, 12, 20].forEach((faces) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'py-1.5 rounded-lg bg-slate-950 hover:bg-slate-800 border border-slate-800 text-amber-300 font-bold text-[11px]';
                btn.textContent = `d${faces}`;
                btn.onclick = () => store.spawnObject({
                    type: 'DIE',
                    name: `d${faces}`,
                    faces,
                    value: faces,
                    x: 0 + faces * 4,
                    y: -120,
                    width: 56,
                    height: 56
                });
                diceBox.appendChild(btn);
            });

            // Populate Utility Zones
            const zonesBox = drawerEl.querySelector('#sbx-drawer-zones');
            [
                { type: 'MARKET_RIVER', name: '4-Slot Market River', slots: 4, width: 620, height: 220, autoReplenish: true },
                { type: 'DISCARD', name: 'Discard Tray', slots: 1, width: 170, height: 220, autoReplenish: false },
                { type: 'SNAP_ZONE', name: '3-Slot Tableau Zone', slots: 3, width: 460, height: 220, autoReplenish: false }
            ].forEach((z) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'w-full text-left px-2.5 py-1.5 rounded-xl bg-slate-950 hover:bg-slate-800 border border-slate-800 flex items-center justify-between';
                btn.innerHTML = `<span class="font-semibold text-indigo-300">${escapeHtml(z.name)}</span><span class="text-[10px] text-slate-400">+ Zone</span>`;
                btn.onclick = () => store.spawnZone(z);
                zonesBox.appendChild(btn);
            });

            drawerEl.querySelectorAll('button[data-seat-cnt]').forEach((b) => {
                b.onclick = () => store.setSeatCount(Number(b.getAttribute('data-seat-cnt')));
            });
        }

        // --- Object Inspector Panel ---
        const selId = ui.selectedIds[0];
        const selObj = selId ? state.boardObjects[selId] : null;
        if (!selObj) {
            inspectorEl.innerHTML = `
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Inspector</div>
                <p class="text-[11px] text-slate-500">Select any board, deck, card, or die on the canvas to configure snap grids, market links, or locking.</p>
            `;
            return;
        }

        const decks = Object.values(state.boardObjects).filter((o) => o && o.type === 'DECK');
        inspectorEl.innerHTML = `
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-sky-400">${escapeHtml(selObj.type)} Inspector</span>
                <button type="button" id="sbx-insp-lock" class="px-2 py-0.5 rounded text-[10px] font-bold ${selObj.isLocked ? 'bg-amber-500/20 text-amber-300' : 'bg-slate-800 text-slate-300'}">
                    ${selObj.isLocked ? '🔒 Locked' : '🔓 Lock (L)'}
                </button>
            </div>
            <div class="text-xs font-bold text-white truncate">${escapeHtml(selObj.name || selObj.id)}</div>
            <div class="grid grid-cols-2 gap-1.5 text-[11px]">
                <label class="bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 flex items-center justify-between">
                    <span class="text-slate-400">W</span>
                    <input type="number" id="sbx-insp-w" value="${Math.round(selObj.width || 100)}" class="w-16 bg-transparent text-right text-white font-mono focus:outline-none">
                </label>
                <label class="bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 flex items-center justify-between">
                    <span class="text-slate-400">H</span>
                    <input type="number" id="sbx-insp-h" value="${Math.round(selObj.height || 100)}" class="w-16 bg-transparent text-right text-white font-mono focus:outline-none">
                </label>
            </div>
            ${selObj.type === 'BOARD' ? `
                <div class="flex flex-wrap gap-1.5 pt-1">
                    <button type="button" id="sbx-insp-grid" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] font-semibold text-slate-200">
                        ${selObj.snapGrid && selObj.snapGrid.enabled ? 'Grid Snap: ON (80px)' : 'Grid Snap: OFF'}
                    </button>
                    <button type="button" id="sbx-insp-snaptool" class="px-2.5 py-1 rounded-lg ${ui.snapPointToolBoardId === selObj.id ? 'bg-sky-600 text-white' : 'bg-slate-800 text-sky-300'} text-[11px] font-semibold">
                        + Snap Point (${(selObj.snapPoints || []).length})
                    </button>
                </div>
            ` : ''}
            ${selObj.type === 'DECK' ? `
                <label class="flex items-center gap-2 text-[11px] text-slate-300 pt-1">
                    <input type="checkbox" id="sbx-insp-autoshuffle" ${selObj.autoShuffleOnReset ? 'checked' : ''}>
                    <span>Auto-shuffle on Reset</span>
                </label>
            ` : ''}
        `;

        inspectorEl.querySelector('#sbx-insp-lock').onclick = () => store.toggleLock([selObj.id]);
        inspectorEl.querySelector('#sbx-insp-w').onchange = (e) => {
            selObj.width = Math.max(24, Number(e.target.value) || 100);
            store.notify();
        };
        inspectorEl.querySelector('#sbx-insp-h').onchange = (e) => {
            selObj.height = Math.max(24, Number(e.target.value) || 100);
            store.notify();
        };
        const gridBtn = inspectorEl.querySelector('#sbx-insp-grid');
        if (gridBtn) {
            gridBtn.onclick = () => {
                selObj.snapGrid = { enabled: !(selObj.snapGrid && selObj.snapGrid.enabled), type: 'CARTESIAN', sizeX: 80, sizeY: 80 };
                store.notify();
            };
        }
        const snapToolBtn = inspectorEl.querySelector('#sbx-insp-snaptool');
        if (snapToolBtn) {
            snapToolBtn.onclick = () => {
                ui.snapPointToolBoardId = ui.snapPointToolBoardId === selObj.id ? null : selObj.id;
                store.notify(true);
            };
        }
        const autoShuffleChk = inspectorEl.querySelector('#sbx-insp-autoshuffle');
        if (autoShuffleChk) {
            autoShuffleChk.onchange = (e) => {
                selObj.autoShuffleOnReset = Boolean(e.target.checked);
                store.notify();
            };
        }
    }

    window.SandboxComponentDrawer = { renderComponentDrawer };
})();
