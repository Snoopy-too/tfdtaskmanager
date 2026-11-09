/**
 * Sandbox Workspace Coordinator & Interactive Modals (spec §2, §5, §7, §8).
 */
(function () {
    'use strict';

    const { escapeHtml } = window.SandboxSchema;

    const modalBackdrop = document.getElementById('sbx-modal-backdrop');
    const modalContent = document.getElementById('sbx-modal-content');

    function closeModal() {
        modalBackdrop.classList.add('hidden');
        modalContent.innerHTML = '';
    }

    modalBackdrop.addEventListener('click', (e) => {
        if (e.target === modalBackdrop) closeModal();
    });

    // 1. Deck Search / Peek Top X Modal (spec §5.2)
    function openDeckSearchModal(store, deck) {
        const state = store.getState();
        store.logEvent(`is searching deck "${deck.name || 'Deck'}".`);

        function renderModalBody(filterText = '') {
            const cardIds = Array.isArray(deck.cardIds) ? deck.cardIds : [];
            const cards = cardIds.map((id, idx) => ({ idx, card: state.boardObjects[id] })).filter((x) => x.card);
            const filtered = filterText
                ? cards.filter((x) => (x.card.name || '').toLowerCase().includes(filterText.toLowerCase()))
                : cards;

            modalContent.innerHTML = `
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <div>
                        <h3 class="text-base font-extrabold text-white">Search / Peek: ${escapeHtml(deck.name || 'Deck')}</h3>
                        <p class="text-xs text-slate-400">${cardIds.length} cards in stack (Top = #1)</p>
                    </div>
                    <button type="button" id="sbx-modal-close" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">Close</button>
                </div>
                <input type="text" id="sbx-deck-search-input" value="${escapeHtml(filterText)}" placeholder="Filter cards by name..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white">
                <div class="flex-1 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 gap-2 pr-1">
                    ${filtered.map(({ idx, card }) => `
                        <div class="rounded-xl bg-slate-950 border border-slate-800 p-2.5 flex flex-col justify-between gap-2 text-xs">
                            <div>
                                <div class="flex items-center justify-between text-[10px] text-slate-500">
                                    <span>#${idx + 1} from top</span>
                                    <span>${escapeHtml(card.cardAssetId || '')}</span>
                                </div>
                                <div class="font-bold text-slate-100 mt-0.5">${escapeHtml(card.name || 'Card')}</div>
                            </div>
                            <button type="button" data-take-card="${escapeHtml(card.id)}" class="w-full py-1 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-[11px] font-bold">Take to Hand</button>
                        </div>
                    `).join('') || '<div class="col-span-3 text-xs text-slate-500 py-6 text-center">No matching cards in deck.</div>'}
                </div>
            `;

            modalContent.querySelector('#sbx-modal-close').onclick = closeModal;
            const searchInp = modalContent.querySelector('#sbx-deck-search-input');
            searchInp.oninput = () => renderModalBody(searchInp.value);
            searchInp.focus();

            modalContent.querySelectorAll('button[data-take-card]').forEach((btn) => {
                btn.onclick = () => {
                    const cid = btn.getAttribute('data-take-card');
                    deck.cardIds = (deck.cardIds || []).filter((id) => id !== cid);
                    store.returnCardToHand(cid);
                    renderModalBody(searchInp.value);
                };
            });
        }

        modalBackdrop.classList.remove('hidden');
        renderModalBody('');
    }

    // 1b. Split / Deal Top N Cards into New Deck Modal
    function openSplitDeckModal(store, deck) {
        const cardCount = Array.isArray(deck.cardIds) ? deck.cardIds.length : 0;
        const defaultCount = Math.min(30, Math.max(1, cardCount));
        const defaultName = `${deck.name || 'Deck'} (Round Deck)`;

        modalContent.innerHTML = `
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-extrabold text-white">✂️ Split / Deal Top N Cards</h3>
                    <p class="text-xs text-slate-400">Extract the top cards from <strong>${escapeHtml(deck.name || 'Deck')}</strong> (${cardCount} cards) into a separate round deck.</p>
                </div>
                <button type="button" id="sbx-modal-close" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">Cancel</button>
            </div>
            <form id="sbx-split-deck-form" class="space-y-3 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 mb-1">Number of Top Cards to Take (Max ${cardCount})</label>
                        <input type="number" id="sbx-split-count" min="1" max="${Math.max(1, cardCount)}" value="${defaultCount}" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono">
                    </div>
                    <div>
                        <label class="block text-slate-400 mb-1">New Round Deck Name</label>
                        <input type="text" id="sbx-split-name" maxlength="80" value="${escapeHtml(defaultName)}" required class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-slate-300 pt-1 cursor-pointer">
                    <input type="checkbox" id="sbx-split-shuffle" checked>
                    <span>Shuffle <strong>${escapeHtml(deck.name || 'Deck')}</strong> before dealing out the top cards</span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold">Create Round Deck</button>
                </div>
            </form>
        `;

        modalContent.querySelector('#sbx-modal-close').onclick = closeModal;
        modalContent.querySelector('#sbx-split-deck-form').onsubmit = (e) => {
            e.preventDefault();
            const cnt = Number(modalContent.querySelector('#sbx-split-count').value) || 30;
            const name = modalContent.querySelector('#sbx-split-name').value;
            const shuffleFirst = Boolean(modalContent.querySelector('#sbx-split-shuffle').checked);
            store.splitDeckTopN(deck.id, cnt, name, shuffleFirst);
            closeModal();
        };
        modalBackdrop.classList.remove('hidden');
        const countInput = modalContent.querySelector('#sbx-split-count');
        countInput.focus();
        countInput.select();
    }

    // 1c. Merge Decks Modal
    function openMergeDecksModal(store, primaryDeck) {
        const state = store.getState();
        const otherDecks = Object.values(state.boardObjects || {}).filter(
            (o) => o && o.type === 'DECK' && o.id !== primaryDeck.id
        );

        modalContent.innerHTML = `
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-extrabold text-white">🔀 Merge & Shuffle Decks Together</h3>
                    <p class="text-xs text-slate-400">Combine other deck(s) on the table into <strong>${escapeHtml(primaryDeck.name || 'Deck')}</strong> (${(primaryDeck.cardIds || []).length} cards).</p>
                </div>
                <button type="button" id="sbx-modal-close" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">Cancel</button>
            </div>
            ${otherDecks.length === 0 ? `
                <div class="text-xs text-slate-400 py-6 text-center">No other decks on the table yet. Spawn another deck from the Project drawer first, or drag one deck directly onto another!</div>
            ` : `
                <form id="sbx-merge-decks-form" class="space-y-3 text-xs">
                    <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                        <div class="text-slate-400 font-semibold mb-1">Select deck(s) to merge into "${escapeHtml(primaryDeck.name || 'Deck')}":</div>
                        ${otherDecks.map((d, idx) => `
                            <label class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950 border border-slate-800 hover:border-indigo-500/50 cursor-pointer">
                                <span class="flex items-center gap-2 font-bold text-slate-100">
                                    <input type="checkbox" name="merge_deck_id" value="${escapeHtml(d.id)}" ${idx === 0 ? 'checked' : ''}>
                                    <span>${escapeHtml(d.name || 'Deck')}</span>
                                </span>
                                <span class="px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 text-[10px] font-bold">${(d.cardIds || []).length} cards</span>
                            </label>
                        `).join('')}
                    </div>
                    <div>
                        <label class="block text-slate-400 mb-1">Combined Deck Name (leave blank to auto-name)</label>
                        <input type="text" id="sbx-merge-name" maxlength="80" placeholder="e.g. Combined Fight Deck (Blue + Red)" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                    </div>
                    <label class="flex items-center gap-2 text-slate-300 pt-1 cursor-pointer">
                        <input type="checkbox" id="sbx-merge-shuffle" checked>
                        <span>Shuffle combined deck immediately after merging</span>
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold">Merge & Shuffle</button>
                    </div>
                </form>
            `}
        `;

        modalContent.querySelector('#sbx-modal-close').onclick = closeModal;
        const form = modalContent.querySelector('#sbx-merge-decks-form');
        if (form) {
            form.onsubmit = (e) => {
                e.preventDefault();
                const checked = Array.from(form.querySelectorAll('input[name="merge_deck_id"]:checked')).map((el) => el.value);
                if (checked.length === 0) return;
                const name = modalContent.querySelector('#sbx-merge-name').value;
                const shouldShuffle = Boolean(modalContent.querySelector('#sbx-merge-shuffle').checked);
                store.mergeDecks([primaryDeck.id, ...checked], name, shouldShuffle);
                closeModal();
            };
        }
        modalBackdrop.classList.remove('hidden');
    }

    // 2. Mid-Game Scenario Save/Load Modal (spec §5.4)
    function openScenariosModal(store) {
        const state = store.getState();
        const scenarios = state.scenarios || [];

        modalContent.innerHTML = `
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-extrabold text-white">Mid-Game Scenario Snapshots</h3>
                    <p class="text-xs text-slate-400">Save any mid-game board state to reload later for balance testing.</p>
                </div>
                <button type="button" id="sbx-modal-close" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">Close</button>
            </div>
            <form id="sbx-save-scn-form" class="flex items-center gap-2">
                <input type="text" id="sbx-scn-name" required maxlength="90" placeholder="e.g. Round 6 Endgame Engine Test" class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-white">
                <button type="submit" class="px-3.5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold">Save Snapshot</button>
            </form>
            <div class="flex-1 overflow-y-auto space-y-2">
                ${scenarios.map((scn) => `
                    <div class="rounded-xl bg-slate-950 border border-slate-800 p-3 flex items-center justify-between gap-2 text-xs">
                        <div>
                            <div class="font-bold text-slate-100">${escapeHtml(scn.name)}</div>
                            <div class="text-[10px] text-slate-500">${escapeHtml(scn.savedAt || '')}</div>
                        </div>
                        <button type="button" data-load-scn="${escapeHtml(scn.id)}" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold">Load Scenario</button>
                    </div>
                `).join('') || '<div class="text-xs text-slate-500 py-6 text-center">No saved scenarios yet.</div>'}
            </div>
        `;

        modalContent.querySelector('#sbx-modal-close').onclick = closeModal;
        modalContent.querySelector('#sbx-save-scn-form').onsubmit = (e) => {
            e.preventDefault();
            const val = modalContent.querySelector('#sbx-scn-name').value;
            store.saveScenario(val);
            openScenariosModal(store);
        };
        modalContent.querySelectorAll('button[data-load-scn]').forEach((btn) => {
            btn.onclick = () => {
                store.loadScenario(btn.getAttribute('data-load-scn'));
                closeModal();
            };
        });
        modalBackdrop.classList.remove('hidden');
    }

    // 3. Sticky Note Feedback Pin Modal (spec §5.4)
    function openStickyNoteModal(store, x, y, targetId) {
        modalContent.innerHTML = `
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-base font-extrabold text-amber-300">📌 Drop Playtest Feedback Pin</h3>
                <button type="button" id="sbx-modal-close" class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 text-xs font-bold">Cancel</button>
            </div>
            <form id="sbx-note-form" class="space-y-3">
                <textarea id="sbx-note-text" rows="3" required maxlength="600" placeholder="e.g. Verify if cost reduction stacks here..." class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-xs text-white"></textarea>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold">Pin Note to Table</button>
                </div>
            </form>
        `;
        modalContent.querySelector('#sbx-modal-close').onclick = closeModal;
        modalContent.querySelector('#sbx-note-form').onsubmit = (e) => {
            e.preventDefault();
            store.addStickyNote(x, y, modalContent.querySelector('#sbx-note-text').value, targetId);
            closeModal();
        };
        modalBackdrop.classList.remove('hidden');
        modalContent.querySelector('#sbx-note-text').focus();
    }

    // 4. Add Custom Resource Key Modal (spec §5.1.4)
    function openAddResourceModal(store) {
        modalContent.innerHTML = `
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-base font-extrabold text-white">Add Custom Resource Tracker</h3>
                <button type="button" id="sbx-modal-close" class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 text-xs font-bold">Cancel</button>
            </div>
            <form id="sbx-res-form" class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-400 mb-1">Resource Name (e.g. Yen, Kaika Points, Population, Happiness)</label>
                    <input type="text" id="sbx-new-res-name" required maxlength="28" placeholder="Population" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                </div>
                <div>
                    <label class="block text-slate-400 mb-1">Starting Quantity per Seat</label>
                    <input type="number" id="sbx-new-res-val" value="0" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white font-mono">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold">Add Tracker</button>
                </div>
            </form>
        `;
        modalContent.querySelector('#sbx-modal-close').onclick = closeModal;
        modalContent.querySelector('#sbx-res-form').onsubmit = (e) => {
            e.preventDefault();
            store.addResourceTrackKey(
                modalContent.querySelector('#sbx-new-res-name').value,
                Number(modalContent.querySelector('#sbx-new-res-val').value) || 0
            );
            closeModal();
        };
        modalBackdrop.classList.remove('hidden');
        modalContent.querySelector('#sbx-new-res-name').focus();
    }

    // 5. Real-Time Multiplayer Room & Seat Claiming Modal (spec §7)
    function openMultiplayerModal(store) {
        const state = store.getState();
        const ui = store.getUI();
        const shareUrl = `${window.location.origin}${window.location.pathname}?id=${encodeURIComponent(state.sandboxId)}&host=1`;

        modalContent.innerHTML = `
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-extrabold text-white">Authoritative Multiplayer Playtest Room</h3>
                    <p class="text-xs text-slate-400">Host a live room, claim a seat (1–6), and share the room URL with playtesters.</p>
                </div>
                <button type="button" id="sbx-modal-close" class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 text-xs font-bold">Close</button>
            </div>

            <div class="rounded-xl bg-slate-950 border border-slate-800 p-3 flex items-center justify-between gap-2 text-xs">
                <div>
                    <div class="text-slate-400">Room Status: <strong class="${ui.isMultiplayerActive ? 'text-emerald-400' : 'text-amber-300'}">${ui.isMultiplayerActive ? `Active (${escapeHtml(ui.liveRoomCode || 'LIVE')})` : 'Solo Local Session'}</strong></div>
                    <div class="text-[11px] text-slate-500 mt-0.5 truncate max-w-md">${escapeHtml(shareUrl)}</div>
                </div>
                <button type="button" id="sbx-host-room-btn" class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold">
                    ${ui.isMultiplayerActive ? 'Copy Room Link' : 'Host Live Session'}
                </button>
            </div>

            <div class="space-y-1.5">
                <div class="text-xs font-bold text-slate-300">Claim a Seat (or Spectator)</div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    ${(state.seats || []).map((s) => `
                        <button type="button" data-claim-seat="${s.seatIndex}" class="p-2.5 rounded-xl border ${ui.activeViewerSeat === s.seatIndex && !ui.godMode ? 'border-sky-400 bg-sky-500/10' : 'border-slate-800 bg-slate-950 hover:bg-slate-900'} flex items-center justify-between text-xs">
                            <span class="flex items-center gap-2 font-bold text-white">
                                <span class="w-3 h-3 rounded-full" style="background:${escapeHtml(s.color)}"></span>
                                ${escapeHtml(s.playerName)}
                            </span>
                            <span class="text-[10px] text-sky-400 font-semibold">${ui.activeViewerSeat === s.seatIndex && !ui.godMode ? 'Claimed' : 'Join'}</span>
                        </button>
                    `).join('')}
                </div>
            </div>
        `;

        modalContent.querySelector('#sbx-modal-close').onclick = closeModal;
        modalContent.querySelector('#sbx-host-room-btn').onclick = async () => {
            if (!ui.isMultiplayerActive && window.SandboxMultiplayer) {
                await window.SandboxMultiplayer.hostOrJoinRoom();
            }
            if (navigator.clipboard) {
                navigator.clipboard.writeText(shareUrl).catch(() => {});
            }
            openMultiplayerModal(store);
        };
        modalContent.querySelectorAll('button[data-claim-seat]').forEach((btn) => {
            btn.onclick = () => {
                const idx = Number(btn.getAttribute('data-claim-seat'));
                if (window.SandboxMultiplayer) {
                    window.SandboxMultiplayer.claimSeat(idx);
                } else {
                    store.setViewerSeat(idx, false);
                }
                openMultiplayerModal(store);
            };
        });
        modalBackdrop.classList.remove('hidden');
    }

    window.SandboxWorkspaceModals = {
        openDeckSearchModal,
        openSplitDeckModal,
        openMergeDecksModal,
        openScenariosModal,
        openStickyNoteModal,
        openAddResourceModal,
        openMultiplayerModal
    };

    // --- Bootstrapping the Workspace ---
    document.addEventListener('DOMContentLoaded', () => {
        const bootstrap = window.sandboxBootstrap;
        if (!bootstrap) return;

        const store = window.SandboxStoreFactory.createStore(bootstrap);
        window.sandboxActiveStore = store;
        const canvasCtrl = window.SandboxCanvasController.createCanvasController(store, bootstrap);

        const topBarEl = document.getElementById('sbx-top-bar');
        const drawerEl = document.getElementById('sbx-component-drawer');
        const inspectorEl = document.getElementById('sbx-inspector-panel');
        const resourceEl = document.getElementById('sbx-resource-dashboard');
        const logEl = document.getElementById('sbx-event-log');
        const handTrayEl = document.getElementById('sbx-hand-tray');

        function renderWorkspace() {
            window.SandboxTopControlBar.renderTopControlBar(topBarEl, store, bootstrap);
            window.SandboxComponentDrawer.renderComponentDrawer(drawerEl, inspectorEl, store, bootstrap);
            window.SandboxResourceDashboard.renderResourceDashboard(resourceEl, store);
            window.SandboxEventAndChatLog.renderEventAndChatLog(logEl, store);
            window.SandboxPlayerHandTray.renderPlayerHandTray(handTrayEl, store);
            canvasCtrl.renderAll();
        }

        store.subscribe(renderWorkspace);
        window.SandboxMultiplayer.init(store, bootstrap);
        renderWorkspace();

        if (window.SandboxTemplateRenderer) {
            window.SandboxTemplateRenderer.preloadProjectTemplates(bootstrap, store);
        }
    });
})();
