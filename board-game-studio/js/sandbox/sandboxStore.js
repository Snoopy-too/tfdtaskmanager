/**
 * Sandbox Client State Store + 5-Step Undo/Redo History Ring (spec §5.2, §5.3, §5.4).
 */
(function () {
    'use strict';

    const { cloneDeep, uid, clampZ, nextZInTier, SEAT_COLORS, playSound } = window.SandboxSchema;
    const MAX_HISTORY = 5;

    function createStore(bootstrap) {
        const init = cloneDeep(bootstrap.initialState || {});
        const state = {
            sandboxId: bootstrap.sandboxId,
            projectId: bootstrap.projectId,
            name: bootstrap.sandboxName || init.name || 'Sandbox',
            mode: init.mode === 'PLAY' ? 'PLAY' : 'SETUP',
            turnState: init.turnState || {
                activeSeat: 1,
                roundNumber: 1,
                currentPhase: 'Draw',
                phases: ['Draw', 'Action Phase', 'Market Buy', 'Cleanup']
            },
            seats: Array.isArray(init.seats) && init.seats.length > 0 ? init.seats : [
                { seatIndex: 1, color: SEAT_COLORS[0], assignedUserId: null, playerName: 'Player 1', handCardIds: [], resources: { Money: 10, VP: 0 } }
            ],
            boardObjects: init.boardObjects || {},
            zones: Array.isArray(init.zones) ? init.zones : [],
            notes: Array.isArray(init.notes) ? init.notes : [],
            scenarios: Array.isArray(init.scenarios) ? init.scenarios : [],
            eventLog: Array.isArray(init.eventLog) ? init.eventLog : []
        };

        state.seats.forEach((s) => {
            if (!s.resources || typeof s.resources !== 'object' || Object.keys(s.resources).length === 0) {
                s.resources = { Money: 10, VP: 0 };
            }
            if (!Array.isArray(s.handCardIds)) s.handCardIds = [];
        });

        const ui = {
            activeViewerSeat: 1,
            godMode: false,
            selectedIds: [],
            hoveredId: null,
            snapPointToolBoardId: null,
            tuckHighlightParentId: null,
            saveStatus: 'Saved',
            hasStartingSnapshot: Boolean(bootstrap.hasStartingSnapshot),
            liveRoomCode: bootstrap.roomCode || null,
            isMultiplayerActive: Boolean(bootstrap.autoHost),
            remoteCursors: {},
            remotePings: []
        };

        const undoStack = [];
        const redoStack = [];
        const listeners = new Set();
        let saveTimer = null;

        function snapshotCore() {
            return cloneDeep({
                mode: state.mode,
                turnState: state.turnState,
                seats: state.seats,
                boardObjects: state.boardObjects,
                zones: state.zones,
                notes: state.notes
            });
        }

        function applyCore(snap) {
            state.mode = snap.mode;
            state.turnState = snap.turnState;
            state.seats = snap.seats;
            state.boardObjects = snap.boardObjects;
            state.zones = snap.zones;
            state.notes = snap.notes;
        }

        function pushHistory() {
            undoStack.push(snapshotCore());
            if (undoStack.length > MAX_HISTORY) undoStack.shift();
            redoStack.length = 0;
        }

        function logEvent(text, type = 'ACTION') {
            const seat = state.seats.find((s) => s.seatIndex === ui.activeViewerSeat) || state.seats[0];
            state.eventLog.push({ id: uid('evt'), type, author: ui.godMode ? 'God Mode' : (seat ? seat.playerName : (bootstrap.currentUserName || 'Player')), seatColor: seat ? seat.color : '#38BDF8', text: String(text).slice(0, 480), ts: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }) });
            if (state.eventLog.length > 150) state.eventLog.shift();
        }

        function notify(skipAutoSave) {
            listeners.forEach((fn) => fn(state, ui));
            if (!skipAutoSave) {
                ui.saveStatus = 'Saving...';
                listeners.forEach((fn) => fn(state, ui));
                if (saveTimer) clearTimeout(saveTimer);
                saveTimer = setTimeout(persistToServer, 650);
            }
        }

        async function persistToServer() {
            try {
                const payload = window.SandboxSchema.serializeForSave(state);
                const fd = new FormData();
                fd.append('action', 'save_sandbox_state');
                fd.append('sandbox_id', String(bootstrap.sandboxId));
                fd.append('csrf_token', bootstrap.csrfToken);
                fd.append('state', JSON.stringify(payload));
                const res = await fetch('api.php', { method: 'POST', body: fd });
                const data = await res.json();
                ui.saveStatus = data && data.success ? 'All changes saved' : 'Offline (local)';
                if (ui.isMultiplayerActive && window.SandboxMultiplayer) window.SandboxMultiplayer.broadcastFullState(payload);
            } catch (_) { ui.saveStatus = 'Save error'; }
            listeners.forEach((fn) => fn(state, ui));
        }

        function replenishMarketRivers() {
            (state.zones || []).forEach((zone) => {
                if (zone.type !== 'MARKET_RIVER' || !zone.autoReplenish || !zone.sourceDeckId) return;
                const deck = state.boardObjects[zone.sourceDeckId];
                if (!deck || deck.type !== 'DECK' || !Array.isArray(deck.cardIds) || !deck.cardIds.length) return;
                const slots = Math.max(1, Number(zone.slots) || 4);
                const slotW = zone.width / slots;
                for (let s = 0; s < slots && deck.cardIds.length > 0; s++) {
                    const slotMinX = zone.x + s * slotW;
                    const slotMaxX = slotMinX + slotW;
                    const occupied = Object.values(state.boardObjects).some((o) => o && o.type === 'CARD' && o.ownerSeat === null && !o.parentId && (o.x + (o.width || 140) / 2) >= slotMinX && (o.x + (o.width || 140) / 2) <= slotMaxX && (o.y + (o.height || 200) / 2) >= zone.y && (o.y + (o.height || 200) / 2) <= zone.y + zone.height);
                    if (!occupied) {
                        const card = state.boardObjects[deck.cardIds.shift()];
                        if (card) {
                            card.ownerSeat = null; card.parentId = null; card.faceUp = true; card.isTapped = false;
                            card.x = Math.round(slotMinX + (slotW - (card.width || 140)) / 2);
                            card.y = Math.round(zone.y + (zone.height - (card.height || 200)) / 2);
                            card.zIndex = nextZInTier(state.boardObjects, 'CARD');
                            logEvent(`Market "${zone.name || 'Market'}" replenished "${card.name || 'Card'}".`);
                        }
                    }
                }
            });
        }

        return {
            getState: () => state,
            getUI: () => ui,
            subscribe: (fn) => { listeners.add(fn); return () => listeners.delete(fn); },
            notify,
            pushHistory,
            logEvent: (txt, type) => { logEvent(txt, type); notify(); },
            undo() { if (!undoStack.length) return; redoStack.push(snapshotCore()); applyCore(undoStack.pop()); logEvent('Undid last action.'); notify(); },
            redo() { if (!redoStack.length) return; undoStack.push(snapshotCore()); applyCore(redoStack.pop()); logEvent('Redid action.'); notify(); },
            canUndo: () => undoStack.length > 0,
            canRedo: () => redoStack.length > 0,
            setMode(mode) { pushHistory(); state.mode = mode === 'PLAY' ? 'PLAY' : 'SETUP'; ui.snapPointToolBoardId = null; logEvent(`Switched to ${state.mode === 'PLAY' ? 'Play Mode' : 'Setup Mode'}.`); notify(); },
            setViewerSeat(seatIdx, godMode = false) { ui.godMode = Boolean(godMode); if (!godMode && seatIdx) ui.activeViewerSeat = Number(seatIdx); notify(true); },
            setSelected(ids) { ui.selectedIds = Array.isArray(ids) ? ids : []; notify(true); },
            setHovered(id) { ui.hoveredId = id || null; },
            spawnObject(objSpec) {
                pushHistory();
                const id = objSpec.id || uid('inst_' + (objSpec.type || 'obj').toLowerCase());
                const type = objSpec.type || 'TOKEN';
                const obj = Object.assign({ id, type, x: Math.round(objSpec.x || 0), y: Math.round(objSpec.y || 0), width: objSpec.width || 100, height: objSpec.height || 100, rotation: 0, isLocked: type === 'BOARD' ? Boolean(objSpec.isLocked) : false, zIndex: nextZInTier(state.boardObjects, type) }, objSpec, { id });
                obj.zIndex = clampZ(type, obj.zIndex);
                state.boardObjects[id] = obj;
                ui.selectedIds = [id];
                logEvent(`Spawned ${obj.name || obj.type}.`);
                notify();
                return obj;
            },
            spawnCardBatchAsDeck(batch, x, y) {
                pushHistory();
                let spawnX = Math.round(x);
                let spawnY = Math.round(y);
                while (Object.values(state.boardObjects).some((o) => o && o.type === 'DECK' && Math.hypot((o.x || 0) - spawnX, (o.y || 0) - spawnY) < 16)) {
                    spawnX += 32;
                    spawnY += 24;
                }
                const deckId = uid('inst_deck');
                const cardIds = (batch.cards || []).map((c, idx) => {
                    const cid = uid('inst_card');
                    state.boardObjects[cid] = {
                        id: cid, type: 'CARD', cardAssetId: c.cardAssetId || `c_${idx + 1}`,
                        templateId: c.templateId || batch.templateId || null,
                        canvasJson: c.canvasJson || batch.canvasJson || null, rowData: c.rowData || null,
                        name: c.name || `${batch.name} #${idx + 1}`,
                        frontImageUrl: c.frontImageUrl || null, backImageUrl: batch.backImageUrl || null,
                        metadata: c.metadata || {}, x: spawnX, y: spawnY,
                        width: batch.width || 140, height: batch.height || 200, zIndex: 310,
                        rotation: 0, isTapped: false, faceUp: false, ownerSeat: null,
                        parentId: null, tuckedChildren: [], peekingUserIds: [], isLocked: false
                    };
                    return cid;
                });
                state.boardObjects[deckId] = {
                    id: deckId, type: 'DECK', name: batch.name || 'Deck',
                    x: spawnX, y: spawnY, width: batch.width || 140, height: batch.height || 200,
                    zIndex: nextZInTier(state.boardObjects, 'DECK'), rotation: 0, isLocked: false,
                    faceUp: false, backImageUrl: batch.backImageUrl || null,
                    cardIds, autoShuffleOnReset: true, linkedDiscardId: null
                };
                ui.selectedIds = [deckId];
                logEvent(`Imported deck "${batch.name}" (${cardIds.length} cards).`);
                notify();
            },
            spawnZone(zoneSpec) {
                pushHistory();
                const z = Object.assign({
                    id: uid('zone'), type: 'MARKET_RIVER', name: 'Market River',
                    x: 200, y: -150, width: 620, height: 220, sourceDeckId: null, slots: 4, autoReplenish: true
                }, zoneSpec);
                state.zones.push(z);
                replenishMarketRivers();
                logEvent(`Placed zone "${z.name}".`);
                notify();
            },
            deleteObjects(ids) {
                if (!ids || !ids.length) return;
                pushHistory();
                ids.forEach((id) => {
                    const obj = state.boardObjects[id];
                    if (!obj) return;
                    if (obj.type === 'CARD') {
                        window.SandboxTuckingEngine.detachFromParent(state.boardObjects, id);
                        (obj.tuckedChildren || []).forEach((tc) => {
                            if (state.boardObjects[tc.cardId]) state.boardObjects[tc.cardId].parentId = null;
                        });
                    }
                    delete state.boardObjects[id];
                    state.seats.forEach((s) => { s.handCardIds = (s.handCardIds || []).filter((cid) => cid !== id); });
                });
                ui.selectedIds = [];
                replenishMarketRivers();
                notify();
            },
            toggleLock(ids) {
                const targetIds = ids && ids.length ? ids : ui.selectedIds;
                if (!targetIds.length) return;
                pushHistory();
                targetIds.forEach((id) => { if (state.boardObjects[id]) state.boardObjects[id].isLocked = !state.boardObjects[id].isLocked; });
                notify();
            },
            flipCards(ids) {
                const targetIds = ids && ids.length ? ids : (ui.hoveredId ? [ui.hoveredId] : ui.selectedIds);
                if (!targetIds.length) return;
                pushHistory();
                targetIds.forEach((id) => {
                    const o = state.boardObjects[id];
                    if (o && (o.type === 'CARD' || o.type === 'DECK')) o.faceUp = !o.faceUp;
                });
                playSound('flip');
                logEvent(`Flipped ${targetIds.length} component(s).`);
                notify();
            },
            tapCards(ids) {
                const targetIds = ids && ids.length ? ids : (ui.hoveredId ? [ui.hoveredId] : ui.selectedIds);
                if (!targetIds.length) return;
                pushHistory();
                targetIds.forEach((id) => {
                    const o = state.boardObjects[id];
                    if (o && o.type === 'CARD') { o.isTapped = !o.isTapped; o.rotation = o.isTapped ? 90 : 0; }
                });
                playSound('tap');
                notify();
            },
            readyAllPlayerCards(seatIndex) {
                pushHistory();
                let count = 0;
                Object.values(state.boardObjects).forEach((o) => {
                    if (o && o.type === 'CARD' && o.isTapped && (o.ownerSeat === null || o.ownerSeat === seatIndex)) {
                        o.isTapped = false; o.rotation = 0; count++;
                    }
                });
                playSound('tap');
                logEvent(`Readied ${count} tapped card(s).`);
                notify();
            },
            rotateObjects(ids, deltaDeg) {
                const targetIds = ids && ids.length ? ids : (ui.hoveredId ? [ui.hoveredId] : ui.selectedIds);
                if (!targetIds.length) return;
                pushHistory();
                targetIds.forEach((id) => {
                    const o = state.boardObjects[id];
                    if (o && !o.isLocked) o.rotation = (((o.rotation || 0) + deltaDeg) % 360 + 360) % 360;
                });
                notify();
            },
            togglePeekCard(cardId) {
                const card = state.boardObjects[cardId];
                if (!card || card.type !== 'CARD') return;
                if (!Array.isArray(card.peekingUserIds)) card.peekingUserIds = [];
                const uidNum = Number(bootstrap.currentUserId) || 1;
                const idx = card.peekingUserIds.indexOf(uidNum);
                if (idx >= 0) card.peekingUserIds.splice(idx, 1);
                else { card.peekingUserIds.push(uidNum); logEvent('Peeked privately at a face-down card.'); }
                notify();
            },
            shuffleDeck(deckId) {
                const deck = state.boardObjects[deckId];
                if (!deck || deck.type !== 'DECK' || !Array.isArray(deck.cardIds)) return;
                pushHistory();
                for (let i = deck.cardIds.length - 1; i > 0; i--) {
                    const j = Math.floor(Math.random() * (i + 1));
                    [deck.cardIds[i], deck.cardIds[j]] = [deck.cardIds[j], deck.cardIds[i]];
                }
                playSound('shuffle');
                logEvent(`Shuffled "${deck.name || 'Deck'}" (${deck.cardIds.length} cards).`);
                notify();
            },
            drawCards(deckId, count, targetSeatIndex) {
                const deck = state.boardObjects[deckId];
                if (!deck || deck.type !== 'DECK' || !Array.isArray(deck.cardIds)) return;
                const seat = state.seats.find((s) => s.seatIndex === (targetSeatIndex || ui.activeViewerSeat || 1)) || state.seats[0];
                if (!seat) return;
                pushHistory();
                const n = Math.min(Math.max(1, Number(count) || 1), deck.cardIds.length);
                for (let i = 0; i < n; i++) {
                    const cardId = deck.cardIds.shift();
                    const card = state.boardObjects[cardId];
                    if (card) {
                        card.ownerSeat = seat.seatIndex; card.parentId = null; card.isTapped = false; card.faceUp = true;
                        if (!seat.handCardIds.includes(cardId)) seat.handCardIds.push(cardId);
                    }
                }
                playSound('flip');
                logEvent(`${seat.playerName} drew ${n} card(s) from "${deck.name || 'Deck'}".`);
                replenishMarketRivers();
                notify();
            },
            playCardFromHand(cardId, worldX, worldY, faceUp = true) {
                const card = state.boardObjects[cardId];
                if (!card) return;
                pushHistory();
                state.seats.forEach((s) => { s.handCardIds = (s.handCardIds || []).filter((id) => id !== cardId); });
                card.ownerSeat = null; card.faceUp = Boolean(faceUp); card.isTapped = false; card.rotation = 0;
                const snapped = window.SandboxSnapEngine.computeSnap(state, card, worldX, worldY);
                card.x = snapped.x; card.y = snapped.y;
                card.zIndex = nextZInTier(state.boardObjects, 'CARD');
                playSound('flip');
                logEvent(`Played "${faceUp ? (card.name || 'Card') : 'a face-down card'}" to the table.`);
                notify();
            },
            returnCardToHand(cardId, seatIdx) {
                const card = state.boardObjects[cardId];
                const seat = state.seats.find((s) => s.seatIndex === (seatIdx || ui.activeViewerSeat)) || state.seats[0];
                if (!card || !seat) return;
                pushHistory();
                window.SandboxTuckingEngine.detachFromParent(state.boardObjects, cardId);
                card.ownerSeat = seat.seatIndex;
                if (!seat.handCardIds.includes(cardId)) seat.handCardIds.push(cardId);
                logEvent(`Moved card to ${seat.playerName}'s hand.`);
                replenishMarketRivers();
                notify();
            },
            formDeckFromSelection(ids, name) {
                const cardIds = (ids || ui.selectedIds).filter((id) => state.boardObjects[id] && state.boardObjects[id].type === 'CARD' && state.boardObjects[id].ownerSeat === null);
                if (cardIds.length < 2) return;
                pushHistory();
                const first = state.boardObjects[cardIds[0]];
                const deckId = uid('inst_deck');
                cardIds.forEach((cid) => { window.SandboxTuckingEngine.detachFromParent(state.boardObjects, cid); state.boardObjects[cid].faceUp = false; });
                state.boardObjects[deckId] = {
                    id: deckId, type: 'DECK', name: name || 'Custom Deck',
                    x: first.x, y: first.y, width: first.width || 140, height: first.height || 200,
                    zIndex: nextZInTier(state.boardObjects, 'DECK'), rotation: 0, isLocked: false,
                    faceUp: false, backImageUrl: first.backImageUrl || null, cardIds, autoShuffleOnReset: true, linkedDiscardId: null
                };
                ui.selectedIds = [deckId];
                logEvent(`Formed deck "${name || 'Custom Deck'}" (${cardIds.length} cards).`);
                notify();
            },
            mergeDecks(deckIds, customName = null, shouldShuffle = true) {
                const validDecks = (deckIds || [])
                    .map((id) => state.boardObjects[id])
                    .filter((d) => d && d.type === 'DECK');
                if (validDecks.length < 2) return null;
                pushHistory();
                const target = validDecks[0];
                const mergedNames = [target.name || 'Deck'];
                if (!Array.isArray(target.cardIds)) target.cardIds = [];

                for (let i = 1; i < validDecks.length; i++) {
                    const src = validDecks[i];
                    if (src.name && !mergedNames.includes(src.name)) mergedNames.push(src.name);
                    if (Array.isArray(src.cardIds)) {
                        src.cardIds.forEach((cid) => {
                            if (!target.cardIds.includes(cid)) target.cardIds.push(cid);
                            if (state.boardObjects[cid]) state.boardObjects[cid].faceUp = false;
                        });
                    }
                    delete state.boardObjects[src.id];
                }

                if (customName && String(customName).trim()) {
                    target.name = String(customName).trim().slice(0, 80);
                } else if (mergedNames.length > 1) {
                    target.name = mergedNames.join(' + ').slice(0, 80);
                }

                if (shouldShuffle && target.cardIds.length > 1) {
                    for (let i = target.cardIds.length - 1; i > 0; i--) {
                        const j = Math.floor(Math.random() * (i + 1));
                        [target.cardIds[i], target.cardIds[j]] = [target.cardIds[j], target.cardIds[i]];
                    }
                    playSound('shuffle');
                }

                ui.selectedIds = [target.id];
                logEvent(`Merged ${validDecks.length} decks into "${target.name}" (${target.cardIds.length} cards)${shouldShuffle ? ' and shuffled' : ''}.`);
                replenishMarketRivers();
                notify();
                return target;
            },
            splitDeckTopN(deckId, count = 30, newDeckName = null, shuffleSourceFirst = false) {
                const src = state.boardObjects[deckId];
                if (!src || src.type !== 'DECK' || !Array.isArray(src.cardIds) || src.cardIds.length === 0) return null;
                pushHistory();

                if (shuffleSourceFirst && src.cardIds.length > 1) {
                    for (let i = src.cardIds.length - 1; i > 0; i--) {
                        const j = Math.floor(Math.random() * (i + 1));
                        [src.cardIds[i], src.cardIds[j]] = [src.cardIds[j], src.cardIds[i]];
                    }
                }

                const n = Math.min(Math.max(1, Math.round(Number(count) || 30)), src.cardIds.length);
                const splitCardIds = src.cardIds.splice(0, n);
                const newDeckId = uid('inst_deck');
                const cleanName = (newDeckName && String(newDeckName).trim())
                    ? String(newDeckName).trim().slice(0, 80)
                    : `${src.name || 'Deck'} (Top ${n})`;

                state.boardObjects[newDeckId] = {
                    id: newDeckId,
                    type: 'DECK',
                    name: cleanName,
                    x: Math.round((src.x || 0) + (src.width || 140) + 28),
                    y: Math.round(src.y || 0),
                    width: src.width || 140,
                    height: src.height || 200,
                    zIndex: nextZInTier(state.boardObjects, 'DECK'),
                    rotation: 0,
                    isLocked: false,
                    faceUp: false,
                    backImageUrl: src.backImageUrl || null,
                    cardIds: splitCardIds,
                    autoShuffleOnReset: false,
                    linkedDiscardId: src.linkedDiscardId || null,
                    parentSourceDeckId: src.id
                };

                playSound('shuffle');
                ui.selectedIds = [newDeckId];
                logEvent(`Dealt top ${n} cards from "${src.name || 'Deck'}" into new deck "${cleanName}" (${src.cardIds.length} remaining).`);
                replenishMarketRivers();
                notify();
                return state.boardObjects[newDeckId];
            },
            rollDie(dieId) {
                const die = state.boardObjects[dieId];
                if (!die || die.type !== 'DIE') return;
                pushHistory();
                const faces = Math.max(2, Number(die.faces) || 6);
                const roll = Math.floor(Math.random() * faces) + 1;
                die.value = roll;
                const label = Array.isArray(die.customFaces) && die.customFaces[roll - 1] ? die.customFaces[roll - 1] : String(roll);
                playSound('roll');
                logEvent(`Rolled d${faces} (${die.name || 'Die'}) → ${label}`, 'DICE');
                notify();
                return { roll, label };
            },
            adjustResource(seatIndex, key, delta, exactValue) {
                const seat = state.seats.find((s) => s.seatIndex === seatIndex);
                if (!seat) return;
                pushHistory();
                if (!seat.resources) seat.resources = {};
                const prev = Number(seat.resources[key]) || 0;
                seat.resources[key] = Math.round(exactValue !== undefined ? Number(exactValue) : prev + Number(delta));
                const diff = seat.resources[key] - prev;
                if (diff !== 0) logEvent(`${seat.playerName} ${diff > 0 ? `gained ${diff}` : `paid ${Math.abs(diff)}`} ${key} (now ${seat.resources[key]}).`, 'RESOURCE');
                notify();
            },
            transferResource(fromSeatIdx, toTarget, key, amount) {
                const amt = Math.max(1, Math.round(Number(amount) || 0));
                const fromSeat = state.seats.find((s) => s.seatIndex === Number(fromSeatIdx));
                if (!fromSeat) return;
                pushHistory();
                fromSeat.resources[key] = (Number(fromSeat.resources[key]) || 0) - amt;
                if (toTarget === 'BANK') logEvent(`${fromSeat.playerName} paid ${amt} ${key} to Bank.`, 'RESOURCE');
                else {
                    const toSeat = state.seats.find((s) => s.seatIndex === Number(toTarget));
                    if (toSeat) {
                        toSeat.resources[key] = (Number(toSeat.resources[key]) || 0) + amt;
                        logEvent(`${fromSeat.playerName} transferred ${amt} ${key} to ${toSeat.playerName}.`, 'RESOURCE');
                    }
                }
                notify();
            },
            addResourceTrackKey(key, startingVal = 0) {
                const clean = String(key || '').trim().slice(0, 32);
                if (!clean) return;
                pushHistory();
                state.seats.forEach((s) => {
                    if (!s.resources) s.resources = {};
                    if (!(clean in s.resources)) s.resources[clean] = Number(startingVal) || 0;
                });
                logEvent(`Added resource tracker "${clean}".`);
                notify();
            },
            setSeatCount(count) {
                const n = Math.min(6, Math.max(1, Number(count) || 4));
                pushHistory();
                const templateRes = state.seats[0] && state.seats[0].resources ? cloneDeep(state.seats[0].resources) : { Money: 10, VP: 0 };
                while (state.seats.length < n) {
                    const idx = state.seats.length + 1;
                    state.seats.push({ seatIndex: idx, color: SEAT_COLORS[idx - 1] || '#38BDF8', assignedUserId: null, playerName: `Player ${idx}`, handCardIds: [], resources: cloneDeep(templateRes) });
                }
                if (state.seats.length > n) state.seats.length = n;
                notify();
            },
            advancePhaseOrTurn() {
                pushHistory();
                const ts = state.turnState;
                const phases = Array.isArray(ts.phases) && ts.phases.length ? ts.phases : ['Action Phase'];
                const curIdx = phases.indexOf(ts.currentPhase);
                if (curIdx >= 0 && curIdx < phases.length - 1) {
                    ts.currentPhase = phases[curIdx + 1];
                    logEvent(`Phase advanced to "${ts.currentPhase}".`);
                } else {
                    ts.currentPhase = phases[0];
                    const nextSeat = (ts.activeSeat % state.seats.length) + 1;
                    if (nextSeat === 1) ts.roundNumber = (ts.roundNumber || 1) + 1;
                    ts.activeSeat = nextSeat;
                    logEvent(`Turn passed to Player ${ts.activeSeat} (Round ${ts.roundNumber}).`);
                }
                notify();
            },
            addStickyNote(x, y, text, targetId = null) {
                const clean = String(text || '').trim();
                if (!clean) return;
                pushHistory();
                const seat = state.seats.find((s) => s.seatIndex === ui.activeViewerSeat);
                state.notes.push({ id: uid('note'), x: Math.round(x), y: Math.round(y), author: seat ? seat.playerName : (bootstrap.currentUserName || 'Designer'), text: clean.slice(0, 800), targetId: targetId || null });
                logEvent(`Added feedback note: "${clean.slice(0, 60)}"`);
                notify();
            },
            deleteStickyNote(noteId) { pushHistory(); state.notes = state.notes.filter((n) => n.id !== noteId); notify(); },
            saveScenario(name) {
                const clean = String(name || '').trim() || `Scenario ${state.scenarios.length + 1}`;
                const snap = snapshotCore();
                state.scenarios.unshift({ id: uid('scn'), name: clean.slice(0, 100), savedAt: new Date().toLocaleString(), snapshot: { turnState: snap.turnState, seats: snap.seats, boardObjects: snap.boardObjects, zones: snap.zones, notes: snap.notes } });
                logEvent(`Saved mid-game scenario "${clean}".`);
                notify();
            },
            loadScenario(scenarioId) {
                const scn = state.scenarios.find((s) => s.id === scenarioId);
                if (!scn || !scn.snapshot) return;
                pushHistory();
                applyCore(Object.assign({ mode: state.mode }, cloneDeep(scn.snapshot)));
                logEvent(`Loaded scenario "${scn.name}".`);
                notify();
            },
            async freezeStartingSnapshot() {
                const fd = new FormData();
                fd.append('action', 'save_sandbox_snapshot');
                fd.append('sandbox_id', String(bootstrap.sandboxId));
                fd.append('csrf_token', bootstrap.csrfToken);
                fd.append('state', JSON.stringify(window.SandboxSchema.serializeForSave(state)));
                const res = await fetch('api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data && data.success) { ui.hasStartingSnapshot = true; logEvent('Saved Default Starting Snapshot.'); notify(true); }
            },
            async resetToStartingSnapshot() {
                const fd = new FormData();
                fd.append('action', 'reset_sandbox_snapshot');
                fd.append('sandbox_id', String(bootstrap.sandboxId));
                fd.append('csrf_token', bootstrap.csrfToken);
                const res = await fetch('api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data && data.success && data.state) {
                    pushHistory(); applyCore(data.state); replenishMarketRivers();
                    logEvent('Reset table to Default Starting State.'); notify(true);
                }
            },
            replenishMarketRivers
        };
    }

    window.SandboxStoreFactory = { createStore };
})();
