/**
 * 2D Playtest Sandbox Viewport & Interaction Controller (spec §3, §5, §6).
 */
(function () {
    'use strict';

    const { escapeHtml, uid, nextZInTier, playSound } = window.SandboxSchema;

    function createCanvasController(store, bootstrap) {
        const viewportEl = document.getElementById('sbx-viewport');
        const worldEl = document.getElementById('sbx-world');
        const boardLayerEl = document.getElementById('sbx-layer-boards');
        const zoneLayerEl = document.getElementById('sbx-layer-zones');
        const cardLayerEl = document.getElementById('sbx-layer-cards');
        const tokenLayerEl = document.getElementById('sbx-layer-tokens');
        const notesLayerEl = document.getElementById('sbx-layer-notes');
        const overlayLayerEl = document.getElementById('sbx-layer-overlay');
        const marqueeEl = document.getElementById('sbx-marquee');
        const hudEl = document.getElementById('sbx-canvas-hud');
        const zoomPreviewEl = document.getElementById('sbx-zoom-preview');
        const ctxMenuEl = document.getElementById('sbx-context-menu');

        const cam = { x: 420, y: 260, scale: 0.85 };
        let isSpaceDown = false;
        let isGDown = false;
        let isAltDown = false;
        let dragState = null;

        function applyCamera() {
            worldEl.style.transform = `translate(${cam.x}px, ${cam.y}px) scale(${cam.scale})`;
            renderCanvasHud();
        }

        function screenToWorld(clientX, clientY) {
            const rect = viewportEl.getBoundingClientRect();
            return { x: (clientX - rect.left - cam.x) / cam.scale, y: (clientY - rect.top - cam.y) / cam.scale };
        }

        function renderCanvasHud() {
            const ui = store.getUI();
            const pct = Math.round(cam.scale * 100);
            hudEl.innerHTML = `
                <div class="flex items-center gap-1.5 bg-slate-900/90 border border-slate-700/80 rounded-xl px-2.5 py-1 text-xs shadow-lg">
                    <button type="button" id="sbx-zoom-out" class="px-1.5 hover:text-sky-400 font-bold">−</button>
                    <span class="font-mono text-[11px] text-slate-200 w-12 text-center">${pct}%</span>
                    <button type="button" id="sbx-zoom-in" class="px-1.5 hover:text-sky-400 font-bold">+</button>
                    <button type="button" id="sbx-zoom-reset" class="ml-1 pl-2 border-l border-slate-700 text-[10px] text-slate-400 hover:text-white">Center</button>
                </div>
                <div class="hidden xl:flex items-center gap-2 bg-slate-900/85 border border-slate-800 rounded-xl px-3 py-1 text-[10px] text-slate-400">
                    <span><strong class="text-slate-200">Space+Drag</strong> Pan</span>
                    <span>· <strong class="text-slate-200">F</strong> Flip</span>
                    <span>· <strong class="text-slate-200">T</strong> Tap</span>
                    <span>· <strong class="text-slate-200">R</strong> Rotate</span>
                    <span>· <strong class="text-slate-200">S</strong> Shuffle</span>
                    <span>· <strong class="text-slate-200">1-9</strong> Draw</span>
                    <span>· <strong class="text-slate-200">G+Click</strong> Ping</span>
                    <span>· <strong class="text-slate-200">Alt</strong> Preview</span>
                </div>
                ${ui.snapPointToolBoardId ? '<span class="px-2.5 py-1 rounded-xl bg-sky-500/20 border border-sky-400/40 text-sky-300 text-xs font-bold">Snap Point Tool Active (ESC to exit)</span>' : ''}
            `;
            hudEl.querySelector('#sbx-zoom-out').onclick = () => zoomBy(0.85);
            hudEl.querySelector('#sbx-zoom-in').onclick = () => zoomBy(1.15);
            hudEl.querySelector('#sbx-zoom-reset').onclick = () => { cam.x = 420; cam.y = 260; cam.scale = 0.85; applyCamera(); };
        }

        function zoomBy(factor, clientX, clientY) {
            const rect = viewportEl.getBoundingClientRect();
            const cx = clientX !== undefined ? clientX - rect.left : rect.width / 2;
            const cy = clientY !== undefined ? clientY - rect.top : rect.height / 2;
            const nextScale = Math.min(2.5, Math.max(0.22, cam.scale * factor));
            const ratio = nextScale / cam.scale;
            cam.x = cx - (cx - cam.x) * ratio;
            cam.y = cy - (cy - cam.y) * ratio;
            cam.scale = nextScale;
            applyCamera();
        }

        function updateZoomPreview() {
            const state = store.getState();
            const ui = store.getUI();
            const hov = ui.hoveredId ? state.boardObjects[ui.hoveredId] : null;
            if (!isAltDown || !hov || hov.type !== 'CARD') {
                zoomPreviewEl.classList.add('hidden');
                return;
            }
            const canSee = Boolean(hov.faceUp || ui.godMode || (Array.isArray(hov.peekingUserIds) && hov.peekingUserIds.includes(Number(bootstrap.currentUserId))));
            zoomPreviewEl.classList.remove('hidden');
            zoomPreviewEl.style.right = '340px';
            zoomPreviewEl.style.top = '72px';
            zoomPreviewEl.innerHTML = `
                <div class="w-64 rounded-2xl bg-slate-900 border-2 border-sky-400 shadow-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-extrabold text-white">${escapeHtml(canSee ? (hov.name || 'Card') : 'Face-Down Card')}</span>
                        <span class="text-[10px] px-2 py-0.5 rounded bg-sky-500/20 text-sky-300 font-bold">Alt Preview</span>
                    </div>
                    ${canSee && hov.frontImageUrl ? `<img src="${escapeHtml(hov.frontImageUrl)}" class="w-full h-44 object-contain rounded-xl bg-slate-950 p-2" alt="">` : ''}
                    <div class="text-xs text-slate-300 space-y-1">
                        ${canSee
                            ? Object.entries(hov.metadata || {}).map(([k, v]) => `<div><span class="text-slate-400">${escapeHtml(k)}:</span> <strong>${escapeHtml(v)}</strong></div>`).join('') || '<span class="text-slate-500">No metadata</span>'
                            : '<span class="text-amber-300">Right-click → Peek to inspect privately.</span>'
                        }
                    </div>
                </div>
            `;
        }

        function closeContextMenu() { ctxMenuEl.classList.add('hidden'); }

        function showContextMenu(clientX, clientY, items) {
            ctxMenuEl.innerHTML = items.map((it, i) =>
                it.divider
                    ? '<div class="my-1 border-t border-slate-800"></div>'
                    : `<button type="button" data-ctx-idx="${i}" class="w-full text-left px-3 py-1.5 hover:bg-slate-800 text-slate-200 flex items-center justify-between gap-4">
                        <span>${escapeHtml(it.label)}</span>
                        ${it.shortcut ? `<span class="text-[10px] text-slate-500 font-mono">${escapeHtml(it.shortcut)}</span>` : ''}
                    </button>`
            ).join('');
            ctxMenuEl.style.left = `${Math.min(window.innerWidth - 220, clientX)}px`;
            ctxMenuEl.style.top = `${Math.min(window.innerHeight - 240, clientY)}px`;
            ctxMenuEl.classList.remove('hidden');
            ctxMenuEl.querySelectorAll('button[data-ctx-idx]').forEach((btn) => {
                btn.onclick = () => {
                    const item = items[Number(btn.getAttribute('data-ctx-idx'))];
                    closeContextMenu();
                    if (item && typeof item.action === 'function') item.action();
                };
            });
        }

        function triggerSpatialPing(worldX, worldY) {
            const state = store.getState();
            const ui = store.getUI();
            const seat = state.seats.find((s) => s.seatIndex === ui.activeViewerSeat) || state.seats[0];
            const ping = { id: uid('ping'), x: Math.round(worldX), y: Math.round(worldY), color: seat ? seat.color : '#F43F5E' };
            ui.remotePings.push(ping);
            playSound('ping');
            store.notify(true);
            if (window.SandboxMultiplayer) window.SandboxMultiplayer.sendPing(ping);
            setTimeout(() => { ui.remotePings = ui.remotePings.filter((p) => p.id !== ping.id); store.notify(true); }, 1600);
        }

        const handlers = {
            onObjectHover(obj) { store.setHovered(obj ? obj.id : null); updateZoomPreview(); },
            onCardDoubleClick(card) { store.tapCards([card.id]); },
            onDeckDraw(deckId, count) { store.drawCards(deckId, count); },
            onDeckShuffle(deckId) { store.shuffleDeck(deckId); },
            onDeckSearch(deck) { if (window.SandboxWorkspaceModals) window.SandboxWorkspaceModals.openDeckSearchModal(store, deck); },
            onDieRoll(dieId) { store.rollDie(dieId); },
            onDeleteNote(noteId) { store.deleteStickyNote(noteId); },

            onObjectPointerDown(e, obj) {
                if (e.button !== 0 || isSpaceDown) return;
                e.stopPropagation();
                closeContextMenu();
                const state = store.getState();
                const ui = store.getUI();

                if (isGDown) {
                    const pt = screenToWorld(e.clientX, e.clientY);
                    triggerSpatialPing(pt.x, pt.y);
                    return;
                }
                if (ui.snapPointToolBoardId === obj.id && obj.type === 'BOARD') {
                    const pt = screenToWorld(e.clientX, e.clientY);
                    store.pushHistory();
                    if (!Array.isArray(obj.snapPoints)) obj.snapPoints = [];
                    obj.snapPoints.push({ id: uid('sp'), x: Math.round(pt.x - obj.x), y: Math.round(pt.y - obj.y), radius: 40, acceptedTypes: ['CARD', 'TOKEN'] });
                    store.logEvent(`Added Snap Point #${obj.snapPoints.length} on "${obj.name}".`);
                    return;
                }

                let activeObj = obj;
                if (obj.type === 'SPAWNER' && (state.mode === 'PLAY' || e.shiftKey)) {
                    activeObj = store.spawnObject({
                        type: 'TOKEN', assetId: obj.assetId || null,
                        name: (obj.name || 'Token').replace(/\s*Supply$/i, ''),
                        color: obj.color || '#E53935', imageUrl: obj.imageUrl || null,
                        x: obj.x + 8, y: obj.y + 8, width: 48, height: 48
                    });
                } else if (!ui.selectedIds.includes(obj.id)) {
                    store.setSelected([obj.id]);
                }

                if (activeObj.isLocked || (activeObj.lockedByUserId && Number(activeObj.lockedByUserId) !== Number(bootstrap.currentUserId))) return;

                store.pushHistory();
                const startWorld = screenToWorld(e.clientX, e.clientY);
                if (activeObj.type === 'CARD' && activeObj.parentId) {
                    window.SandboxTuckingEngine.detachFromParent(state.boardObjects, activeObj.id);
                }
                activeObj.zIndex = nextZInTier(state.boardObjects, activeObj.type);
                if (activeObj.type === 'CARD') window.SandboxTuckingEngine.syncChildrenPositions(state.boardObjects, activeObj.id);
                if (window.SandboxMultiplayer) window.SandboxMultiplayer.sendLockRequest(activeObj.id);

                dragState = { kind: 'OBJECT', objectId: activeObj.id, startWorldX: startWorld.x, startWorldY: startWorld.y, origX: activeObj.x || 0, origY: activeObj.y || 0 };
            },

            onZonePointerDown(e, zone) {
                if (e.button !== 0 || isSpaceDown) return;
                e.stopPropagation();
                const startWorld = screenToWorld(e.clientX, e.clientY);
                store.pushHistory();
                dragState = { kind: 'ZONE', zone, startWorldX: startWorld.x, startWorldY: startWorld.y, origX: zone.x || 0, origY: zone.y || 0 };
            },

            onObjectContextMenu(e, obj) {
                e.preventDefault();
                e.stopPropagation();
                const state = store.getState();
                const ui = store.getUI();
                if (!ui.selectedIds.includes(obj.id)) {
                    store.setSelected([obj.id]);
                }
                const worldPt = screenToWorld(e.clientX, e.clientY);
                const items = [];
                const selectedDecks = (ui.selectedIds || []).filter((id) => state.boardObjects[id] && state.boardObjects[id].type === 'DECK');

                if (obj.type === 'CARD') {
                    items.push({ label: obj.faceUp ? 'Flip Face-Down' : 'Flip Face-Up', shortcut: 'F', action: () => store.flipCards([obj.id]) });
                    items.push({ label: obj.isTapped ? 'Untap / Ready' : 'Tap 90°', shortcut: 'T', action: () => store.tapCards([obj.id]) });
                    items.push({ label: 'Peek Privately', shortcut: 'Alt', action: () => store.togglePeekCard(obj.id) });
                    items.push({ label: 'Take into Hand', action: () => store.returnCardToHand(obj.id) });
                    if (ui.selectedIds.length >= 2) {
                        items.push({ label: `Form Deck (${ui.selectedIds.length} cards)`, action: () => store.formDeckFromSelection(ui.selectedIds, 'Assembled Deck') });
                    }
                } else if (obj.type === 'DECK') {
                    items.push({ label: 'Draw 1 Card', shortcut: '1', action: () => store.drawCards(obj.id, 1) });
                    items.push({ label: 'Draw 3 Cards', shortcut: '3', action: () => store.drawCards(obj.id, 3) });
                    items.push({ label: 'Shuffle Deck', shortcut: 'S', action: () => store.shuffleDeck(obj.id) });
                    items.push({ label: 'Search / Peek Deck...', action: () => handlers.onDeckSearch(obj) });
                    items.push({ divider: true });
                    items.push({
                        label: '✂️ Deal Top 30 as Round Deck',
                        action: () => store.splitDeckTopN(obj.id, 30, `${obj.name || 'Deck'} (Round 30)`, false)
                    });
                    items.push({
                        label: '✂️ Split / Deal Top N Cards...',
                        action: () => window.SandboxWorkspaceModals && window.SandboxWorkspaceModals.openSplitDeckModal(store, obj)
                    });
                    if (selectedDecks.length >= 2) {
                        items.push({
                            label: `🔀 Merge & Shuffle Selected Decks (${selectedDecks.length})`,
                            action: () => store.mergeDecks(selectedDecks, null, true)
                        });
                    } else {
                        items.push({
                            label: '🔀 Merge with Another Deck...',
                            action: () => window.SandboxWorkspaceModals && window.SandboxWorkspaceModals.openMergeDecksModal(store, obj)
                        });
                    }
                    if (obj.parentSourceDeckId && state.boardObjects[obj.parentSourceDeckId]) {
                        const parentDeck = state.boardObjects[obj.parentSourceDeckId];
                        items.push({
                            label: `↩ Return to "${parentDeck.name || 'Main Deck'}" & Shuffle`,
                            action: () => store.mergeDecks([parentDeck.id, obj.id], parentDeck.name, true)
                        });
                    }
                } else if (obj.type === 'DIE') {
                    items.push({ label: `Roll d${obj.faces || 6}`, action: () => store.rollDie(obj.id) });
                } else if (obj.type === 'BOARD') {
                    items.push({
                        label: ui.snapPointToolBoardId === obj.id ? 'Exit Snap Point Tool' : 'Place Magnetic Snap Points',
                        action: () => { ui.snapPointToolBoardId = ui.snapPointToolBoardId === obj.id ? null : obj.id; store.notify(true); }
                    });
                    items.push({
                        label: obj.snapGrid && obj.snapGrid.enabled ? 'Disable Grid Snap' : 'Enable 80px Grid Snap',
                        action: () => { store.pushHistory(); obj.snapGrid = { enabled: !(obj.snapGrid && obj.snapGrid.enabled), type: 'CARTESIAN', sizeX: 80, sizeY: 80 }; store.notify(); }
                    });
                }

                items.push({ divider: true });
                items.push({ label: obj.isLocked ? 'Unlock Component' : 'Lock Component', shortcut: 'L', action: () => store.toggleLock([obj.id]) });
                items.push({ label: 'Drop Sticky Feedback Pin...', action: () => window.SandboxWorkspaceModals && window.SandboxWorkspaceModals.openStickyNoteModal(store, worldPt.x, worldPt.y, obj.id) });
                items.push({ label: 'Delete Component', shortcut: 'Del', action: () => store.deleteObjects([obj.id]) });
                showContextMenu(e.clientX, e.clientY, items);
            },

            onZoneContextMenu(e, zone) {
                e.preventDefault();
                e.stopPropagation();
                const state = store.getState();
                const items = Object.values(state.boardObjects).filter((o) => o && o.type === 'DECK').map((d) => ({
                    label: `Link Source Deck: ${d.name}`,
                    action: () => { store.pushHistory(); zone.sourceDeckId = d.id; store.replenishMarketRivers(); store.notify(); }
                }));
                items.push({ label: zone.autoReplenish ? 'Disable Auto-Replenish' : 'Enable Auto-Replenish', action: () => { zone.autoReplenish = !zone.autoReplenish; store.replenishMarketRivers(); store.notify(); } });
                items.push({ label: 'Delete Zone', action: () => { store.pushHistory(); state.zones = state.zones.filter((z) => z.id !== zone.id); store.notify(); } });
                showContextMenu(e.clientX, e.clientY, items);
            }
        };

        viewportEl.addEventListener('pointerdown', (e) => {
            closeContextMenu();
            viewportEl.focus();
            if (isGDown && e.button === 0) {
                const pt = screenToWorld(e.clientX, e.clientY);
                triggerSpatialPing(pt.x, pt.y);
                return;
            }
            if (e.button === 1 || (e.button === 0 && isSpaceDown)) {
                e.preventDefault();
                dragState = { kind: 'PAN', startClientX: e.clientX, startClientY: e.clientY, origCamX: cam.x, origCamY: cam.y };
                viewportEl.style.cursor = 'grabbing';
                return;
            }
            if (e.button === 0) {
                store.setSelected([]);
                const rect = viewportEl.getBoundingClientRect();
                dragState = { kind: 'MARQUEE', startX: e.clientX - rect.left, startY: e.clientY - rect.top, startWorld: screenToWorld(e.clientX, e.clientY) };
            }
        });

        window.addEventListener('pointermove', (e) => {
            const worldPt = screenToWorld(e.clientX, e.clientY);
            if (window.SandboxMultiplayer) window.SandboxMultiplayer.throttledSendCursor(worldPt.x, worldPt.y);
            if (!dragState) return;
            const state = store.getState();
            const ui = store.getUI();

            if (dragState.kind === 'PAN') {
                cam.x = dragState.origCamX + (e.clientX - dragState.startClientX);
                cam.y = dragState.origCamY + (e.clientY - dragState.startClientY);
                applyCamera();
            } else if (dragState.kind === 'OBJECT') {
                const obj = state.boardObjects[dragState.objectId];
                if (!obj) return;
                const dx = worldPt.x - dragState.startWorldX;
                const dy = worldPt.y - dragState.startWorldY;
                if (Math.hypot(dx, dy) > 3) {
                    const domEl = worldEl.querySelector(`[data-object-id="${obj.id}"]`);
                    if (domEl) domEl.dataset.wasDragged = '1';
                }
                obj.x = Math.round(dragState.origX + dx);
                obj.y = Math.round(dragState.origY + dy);
                if (obj.type === 'CARD') {
                    window.SandboxTuckingEngine.syncChildrenPositions(state.boardObjects, obj.id);
                    const candidate = window.SandboxTuckingEngine.findTuckCandidate(state.boardObjects, obj, obj.x, obj.y);
                    ui.tuckHighlightParentId = candidate ? candidate.parentId : null;
                }
                if (window.SandboxMultiplayer) window.SandboxMultiplayer.throttledSendDrag(obj.id, obj.x, obj.y);
                renderAll();
            } else if (dragState.kind === 'ZONE') {
                dragState.zone.x = Math.round(dragState.origX + (worldPt.x - dragState.startWorldX));
                dragState.zone.y = Math.round(dragState.origY + (worldPt.y - dragState.startWorldY));
                renderAll();
            } else if (dragState.kind === 'MARQUEE') {
                const rect = viewportEl.getBoundingClientRect();
                const curX = e.clientX - rect.left;
                const curY = e.clientY - rect.top;
                marqueeEl.classList.remove('hidden');
                marqueeEl.style.left = `${Math.min(dragState.startX, curX)}px`;
                marqueeEl.style.top = `${Math.min(dragState.startY, curY)}px`;
                marqueeEl.style.width = `${Math.abs(curX - dragState.startX)}px`;
                marqueeEl.style.height = `${Math.abs(curY - dragState.startY)}px`;
            }
        });

        window.addEventListener('pointerup', (e) => {
            if (!dragState) return;
            const state = store.getState();
            const ui = store.getUI();

            if (dragState.kind === 'PAN') {
                viewportEl.style.cursor = isSpaceDown ? 'grab' : '';
            } else if (dragState.kind === 'OBJECT') {
                const obj = state.boardObjects[dragState.objectId];
                ui.tuckHighlightParentId = null;
                if (obj) {
                    if (obj.type === 'TOKEN') {
                        const hitBag = Object.values(state.boardObjects).find((s) => s && s.type === 'SPAWNER' && Math.hypot((obj.x + 24) - (s.x + 32), (obj.y + 24) - (s.y + 32)) < 48);
                        if (hitBag) {
                            delete state.boardObjects[obj.id];
                            store.logEvent(`Returned token to "${hitBag.name}".`);
                            dragState = null;
                            store.notify();
                            return;
                        }
                    }
                    if (obj.type === 'DECK') {
                        const hitDeck = Object.values(state.boardObjects).find(
                            (d) => d && d.type === 'DECK' && d.id !== obj.id && Math.hypot((obj.x + 70) - (d.x + 70), (obj.y + 100) - (d.y + 100)) < 75
                        );
                        if (hitDeck) {
                            store.mergeDecks([hitDeck.id, obj.id], null, true);
                            dragState = null;
                            return;
                        }
                    }
                    if (obj.type === 'CARD') {
                        const hitDeck = Object.values(state.boardObjects).find((d) => d && d.type === 'DECK' && Math.hypot((obj.x + 70) - (d.x + 70), (obj.y + 100) - (d.y + 100)) < 75);
                        if (hitDeck) {
                            window.SandboxTuckingEngine.detachFromParent(state.boardObjects, obj.id);
                            obj.faceUp = false;
                            if (!hitDeck.cardIds.includes(obj.id)) hitDeck.cardIds.unshift(obj.id);
                            store.logEvent(`Placed "${obj.name}" on top of "${hitDeck.name}".`);
                            store.replenishMarketRivers();
                            dragState = null;
                            store.notify();
                            return;
                        }
                        const tuck = window.SandboxTuckingEngine.findTuckCandidate(state.boardObjects, obj, obj.x, obj.y);
                        if (tuck) {
                            window.SandboxTuckingEngine.attachToParent(state.boardObjects, tuck.parentId, obj.id, tuck.offsetY);
                            const parent = state.boardObjects[tuck.parentId];
                            store.logEvent(`Tucked "${obj.name}" onto "${parent ? parent.name : 'Card'}".`);
                            store.replenishMarketRivers();
                            dragState = null;
                            store.notify();
                            return;
                        }
                    }
                    const snapped = window.SandboxSnapEngine.computeSnap(state, obj, obj.x, obj.y);
                    obj.x = snapped.x;
                    obj.y = snapped.y;
                    if (obj.type === 'CARD') window.SandboxTuckingEngine.syncChildrenPositions(state.boardObjects, obj.id);
                    if (window.SandboxMultiplayer) window.SandboxMultiplayer.sendObjectDrop(obj.id, obj.x, obj.y);
                }
                store.replenishMarketRivers();
                store.notify();
            } else if (dragState.kind === 'ZONE') {
                store.replenishMarketRivers();
                store.notify();
            } else if (dragState.kind === 'MARQUEE') {
                marqueeEl.classList.add('hidden');
                const endWorld = screenToWorld(e.clientX, e.clientY);
                const minX = Math.min(dragState.startWorld.x, endWorld.x);
                const maxX = Math.max(dragState.startWorld.x, endWorld.x);
                const minY = Math.min(dragState.startWorld.y, endWorld.y);
                const maxY = Math.max(dragState.startWorld.y, endWorld.y);
                if (maxX - minX > 10 && maxY - minY > 10) {
                    const selected = Object.values(state.boardObjects)
                        .filter((o) => o && !o.isLocked && o.ownerSeat === null && (o.x + (o.width || 80) / 2) >= minX && (o.x + (o.width || 80) / 2) <= maxX && (o.y + (o.height || 80) / 2) >= minY && (o.y + (o.height || 80) / 2) <= maxY)
                        .map((o) => o.id);
                    store.setSelected(selected);
                }
            }
            dragState = null;
        });

        viewportEl.addEventListener('wheel', (e) => { e.preventDefault(); zoomBy(e.deltaY < 0 ? 1.1 : 0.9, e.clientX, e.clientY); }, { passive: false });

        viewportEl.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            const state = store.getState();
            const worldPt = screenToWorld(e.clientX, e.clientY);
            const ui = store.getUI();
            const items = [
                { label: 'Drop Sticky Feedback Pin Here...', action: () => window.SandboxWorkspaceModals && window.SandboxWorkspaceModals.openStickyNoteModal(store, worldPt.x, worldPt.y, null) },
                { label: 'Ping Location for Table', shortcut: 'G+Click', action: () => triggerSpatialPing(worldPt.x, worldPt.y) }
            ];
            const selectedDecks = (ui.selectedIds || []).filter((id) => state.boardObjects[id] && state.boardObjects[id].type === 'DECK');
            const selectedCards = (ui.selectedIds || []).filter((id) => state.boardObjects[id] && state.boardObjects[id].type === 'CARD');
            if (selectedDecks.length >= 2) {
                items.unshift({ label: `🔀 Merge & Shuffle Selected Decks (${selectedDecks.length})`, action: () => store.mergeDecks(selectedDecks, null, true) });
            }
            if (selectedCards.length >= 2) {
                items.unshift({ label: `Form Deck from Selected Cards (${selectedCards.length})`, action: () => store.formDeckFromSelection(selectedCards, 'Custom Deck') });
            }
            showContextMenu(e.clientX, e.clientY, items);
        });

        viewportEl.addEventListener('dragover', (e) => e.preventDefault());
        viewportEl.addEventListener('drop', (e) => {
            e.preventDefault();
            const raw = e.dataTransfer.getData('application/x-sandbox-item');
            if (!raw) return;
            try {
                const payload = JSON.parse(raw);
                const pt = screenToWorld(e.clientX, e.clientY);
                if ( payload.dragKind === 'HAND_CARD' ) store.playCardFromHand(payload.cardId, pt.x - 70, pt.y - 100, !e.shiftKey);
                else if (payload.dragKind === 'ASSET_SPAWN') {
                    if (payload.spawnType === 'CARD_BATCH') {
                        const state = store.getState();
                        const hitDeck = Object.values(state.boardObjects).find(
                            (d) => d && d.type === 'DECK' && Math.hypot((pt.x - 70 + 70) - (d.x + 70), (pt.y - 100 + 100) - (d.y + 100)) < 90
                        );
                        store.spawnCardBatchAsDeck(payload.batch, pt.x - 70, pt.y - 100);
                        if (hitDeck) {
                            const newDeckId = store.getUI().selectedIds[0];
                            if (newDeckId && newDeckId !== hitDeck.id) {
                                store.mergeDecks([hitDeck.id, newDeckId], null, true);
                            }
                        }
                    }
                    else if (payload.spawnType === 'ZONE') store.spawnZone(Object.assign({}, payload.spec, { x: Math.round(pt.x - 200), y: Math.round(pt.y - 100) }));
                    else store.spawnObject(Object.assign({}, payload.spec, { x: Math.round(pt.x - (payload.spec.width || 80) / 2), y: Math.round(pt.y - (payload.spec.height || 80) / 2) }));
                }
            } catch (_) {}
        });

        window.addEventListener('keydown', (e) => {
            const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
            if (e.code === 'Space') { isSpaceDown = true; viewportEl.style.cursor = 'grab'; }
            if (e.key === 'g' || e.key === 'G') isGDown = true;
            if (e.key === 'Alt') { isAltDown = true; updateZoomPreview(); }
            if (e.key === 'Escape') { store.getUI().snapPointToolBoardId = null; closeContextMenu(); store.notify(true); }
            if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) { e.preventDefault(); if (e.shiftKey) store.redo(); else store.undo(); return; }

            const state = store.getState();
            const ui = store.getUI();
            const hov = ui.hoveredId ? state.boardObjects[ui.hoveredId] : null;
            if (e.key === 'f' || e.key === 'F') { e.preventDefault(); store.flipCards(); }
            else if (e.key === 't' || e.key === 'T') { e.preventDefault(); store.tapCards(); }
            else if (e.key === 'r' || e.key === 'R') { e.preventDefault(); store.rotateObjects(null, e.shiftKey ? -45 : 45); }
            else if ((e.key === 's' || e.key === 'S') && hov && hov.type === 'DECK') { e.preventDefault(); store.shuffleDeck(hov.id); }
            else if (e.key === 'l' || e.key === 'L') { e.preventDefault(); store.toggleLock(); }
            else if ((e.key === 'Delete' || e.key === 'Backspace') && ui.selectedIds.length > 0) { e.preventDefault(); store.deleteObjects(ui.selectedIds); }
            else if (/^[1-9]$/.test(e.key) && hov && hov.type === 'DECK') { e.preventDefault(); store.drawCards(hov.id, Number(e.key)); }
        });

        window.addEventListener('keyup', (e) => {
            if (e.code === 'Space') { isSpaceDown = false; viewportEl.style.cursor = ''; }
            if (e.key === 'g' || e.key === 'G') isGDown = false;
            if (e.key === 'Alt') { isAltDown = false; updateZoomPreview(); }
        });

        function renderAll() {
            const state = store.getState();
            const ui = store.getUI();
            window.SandboxBoardLayer.renderBoardsAndZones(boardLayerEl, zoneLayerEl, state, ui, handlers);
            cardLayerEl.innerHTML = '';
            window.SandboxDeckComponent.renderDecks(cardLayerEl, state, ui, handlers);
            window.SandboxCardComponent.renderCards(cardLayerEl, state, ui, bootstrap.currentUserId, handlers);
            window.SandboxTokenSpawner.renderTokensAndDice(tokenLayerEl, state, ui, handlers);
            window.SandboxGhostOverlay.renderNotesAndOverlays(notesLayerEl, overlayLayerEl, state, ui, bootstrap.currentUserId, handlers);
            renderCanvasHud();
        }

        applyCamera();
        return { renderAll, screenToWorld };
    }

    window.SandboxCanvasController = { createCanvasController };
})();
