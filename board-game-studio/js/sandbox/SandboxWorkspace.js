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
    function openMultiplayerModal(store, skipStatusFetch = false, statusMessage = '') {
        const state = store.getState();
        const ui = store.getUI();
        const currentUserId = window.sandboxBootstrap ? Number(window.sandboxBootstrap.currentUserId) : 0;
        const isLive = Boolean(ui.isMultiplayerActive);
        const roomStatus = ui.roomStatus;
        const remoteActive = !isLive && Boolean((roomStatus && roomStatus.active) || (window.sandboxBootstrap && window.sandboxBootstrap.activeRoomExists && (!roomStatus || roomStatus.active !== false)));
        const roomCode = ui.liveRoomCode || (roomStatus && roomStatus.roomCode) || (window.sandboxBootstrap && window.sandboxBootstrap.roomCode) || '';
        const hostId = ui.hostUserId || (roomStatus && roomStatus.hostUserId) || (window.sandboxBootstrap && window.sandboxBootstrap.hostUserId) || null;
        const isHost = isLive && hostId !== null && Number(hostId) === currentUserId;
        const inviteUrl = `${window.location.origin}${window.location.pathname}?id=${encodeURIComponent(state.sandboxId)}&live=1`;

        const members = isLive
            ? (Array.isArray(ui.roomMembers) ? ui.roomMembers : [])
            : (roomStatus && Array.isArray(roomStatus.members) ? roomStatus.members : []);
        const displaySeats = isLive
            ? (state.seats || [])
            : (remoteActive && roomStatus && Array.isArray(roomStatus.seats) ? roomStatus.seats : (state.seats || []));

        let statusBadge = '<strong class="text-amber-300">Solo Local Session</strong>';
        if (isLive) {
            statusBadge = `<strong class="text-emerald-400">Active · Code: <span class="font-mono">${escapeHtml(roomCode || 'LIVE')}</span></strong>`;
        } else if (remoteActive) {
            statusBadge = `<strong class="text-emerald-300">Room Open · Code: <span class="font-mono">${escapeHtml(roomCode || 'LIVE')}</span></strong>`;
        }

        modalContent.innerHTML = `
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-extrabold text-white">Authoritative Multiplayer Playtest Room</h3>
                    <p class="text-xs text-slate-400">Host or join the live room, claim your seat (1–6), and share the invite link with other admins.</p>
                </div>
                <button type="button" id="sbx-modal-close" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">Close</button>
            </div>

            ${statusMessage ? `<div class="rounded-xl bg-sky-500/10 border border-sky-500/30 text-sky-200 px-3 py-2 text-xs">${escapeHtml(statusMessage)}</div>` : ''}

            <div class="rounded-xl bg-slate-950 border border-slate-800 p-3 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="min-w-0 flex-1">
                    <div class="text-slate-400">Room Status: ${statusBadge}</div>
                    ${isLive || remoteActive
                        ? `<div class="text-[11px] text-slate-500 mt-0.5 truncate max-w-md">${escapeHtml(inviteUrl)}</div>`
                        : '<div class="text-[11px] text-slate-500 mt-0.5">Starts from your frozen Starting Snapshot (or current table).</div>'}
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    ${isLive ? `
                        <button type="button" id="sbx-copy-room-link-btn" class="px-3 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold">Copy Invite Link</button>
                        <button type="button" id="sbx-leave-room-btn" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold">Leave Room</button>
                        ${isHost ? `
                            <button type="button" id="sbx-reset-room-btn" class="px-3 py-2 rounded-xl bg-amber-600/80 hover:bg-amber-500 text-white font-bold" title="Reset room table back to Starting Snapshot">Reset Room</button>
                            <button type="button" id="sbx-end-room-btn" class="px-3 py-2 rounded-xl bg-rose-600/80 hover:bg-rose-500 text-white font-bold">End Room</button>
                        ` : ''}
                    ` : (remoteActive ? `
                        <button type="button" id="sbx-join-room-btn" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold">Join Live Room</button>
                    ` : `
                        <button type="button" id="sbx-host-room-btn" class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white font-bold">Start Live Room</button>
                    `)}
                </div>
            </div>

            ${(isLive || remoteActive) ? `
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-slate-400 font-semibold mr-1">Connected (${members.length}):</span>
                    ${members.length > 0
                        ? members.map((m) => `
                            <span class="px-2 py-0.5 rounded-full bg-slate-950 border ${Number(m.userId) === currentUserId ? 'border-emerald-500/40 text-emerald-300' : 'border-slate-800 text-slate-300'} text-[11px] font-semibold">
                                ${escapeHtml(m.name || ('User #' + m.userId))}${Number(m.userId) === Number(hostId) ? ' ★' : ''}${Number(m.userId) === currentUserId ? ' (You)' : ''}
                            </span>
                        `).join('')
                        : '<span class="text-slate-500 text-[11px]">Waiting for players to sync…</span>'}
                </div>
            ` : ''}

            <div class="space-y-1.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-300">${isLive ? 'Seats (Claim yours or switch to Spectator)' : 'Table Seats'}</span>
                    ${isLive ? '<button type="button" id="sbx-vacate-seat-btn" class="text-[11px] text-slate-400 hover:text-white underline">Spectate (Leave Seat)</button>' : ''}
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    ${displaySeats.map((s) => {
                        const holderId = s.assignedUserId ? Number(s.assignedUserId) : null;
                        const isMine = isLive ? holderId === currentUserId : (ui.activeViewerSeat === s.seatIndex && !ui.godMode);
                        const claimedByOther = isLive && holderId !== null && holderId !== currentUserId;
                        const statusLabel = isMine ? 'You' : (claimedByOther ? 'Claimed' : (isLive ? 'Join' : 'Open'));
                        return `
                            <div class="p-2.5 rounded-xl border ${isMine ? 'border-sky-400 bg-sky-500/10' : (claimedByOther ? 'border-slate-800 bg-slate-950/60 opacity-80' : 'border-slate-800 bg-slate-950')} flex items-center justify-between gap-2 text-xs">
                                <button type="button" data-claim-seat="${s.seatIndex}" ${(!isLive || claimedByOther) ? 'disabled' : ''} class="flex-1 flex items-center justify-between text-left min-w-0 ${(!isLive || claimedByOther) ? 'cursor-default' : 'hover:text-sky-300 cursor-pointer'}">
                                    <span class="flex items-center gap-2 font-bold text-white truncate">
                                        <span class="w-3 h-3 rounded-full flex-shrink-0" style="background:${escapeHtml(s.color)}"></span>
                                        <span class="truncate">${escapeHtml(s.playerName)}</span>
                                    </span>
                                    <span class="text-[10px] ${isMine ? 'text-emerald-400' : (claimedByOther ? 'text-slate-500' : 'text-sky-400')} font-semibold ml-1">${statusLabel}</span>
                                </button>
                                ${isHost && claimedByOther ? `<button type="button" data-release-seat="${s.seatIndex}" class="text-[10px] text-rose-400 hover:text-rose-300 underline flex-shrink-0" title="Free this seat">Free</button>` : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;

        modalContent.querySelector('#sbx-modal-close').onclick = closeModal;

        const hostBtn = modalContent.querySelector('#sbx-host-room-btn');
        if (hostBtn) {
            hostBtn.onclick = async () => {
                if (window.SandboxMultiplayer) {
                    const res = await window.SandboxMultiplayer.hostRoom(false);
                    openMultiplayerModal(store, true, res ? `Live room started (Code: ${res.roomCode}).` : 'Could not start live room.');
                }
            };
        }

        const joinBtn = modalContent.querySelector('#sbx-join-room-btn');
        if (joinBtn) {
            joinBtn.onclick = async () => {
                if (window.SandboxMultiplayer) {
                    const res = await window.SandboxMultiplayer.joinRoom();
                    if (res && res.success) {
                        openMultiplayerModal(store, true, `Joined live room (${res.roomCode}).`);
                    } else {
                        openMultiplayerModal(store, true, (res && res.error) || 'Could not join live room.');
                    }
                }
            };
        }

        const copyBtn = modalContent.querySelector('#sbx-copy-room-link-btn');
        if (copyBtn) {
            copyBtn.onclick = () => {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(inviteUrl).catch(() => {});
                }
                openMultiplayerModal(store, true, 'Invite link copied to clipboard.');
            };
        }

        const leaveBtn = modalContent.querySelector('#sbx-leave-room-btn');
        if (leaveBtn) {
            leaveBtn.onclick = async () => {
                if (window.SandboxMultiplayer) {
                    await window.SandboxMultiplayer.leaveRoom();
                    await window.SandboxMultiplayer.fetchRoomStatus();
                }
                openMultiplayerModal(store, true, 'You left the live room.');
            };
        }

        const resetBtn = modalContent.querySelector('#sbx-reset-room-btn');
        if (resetBtn) {
            resetBtn.onclick = async () => {
                if (!window.confirm('Reset the live room table and hands back to the Starting State for all players?')) return;
                if (window.SandboxMultiplayer) {
                    await window.SandboxMultiplayer.hostRoom(true);
                }
                openMultiplayerModal(store, true, 'Live room reset to Starting State.');
            };
        }

        const endBtn = modalContent.querySelector('#sbx-end-room-btn');
        if (endBtn) {
            endBtn.onclick = async () => {
                if (!window.confirm('End this live room for all connected players?')) return;
                if (window.SandboxMultiplayer) {
                    const res = await window.SandboxMultiplayer.endRoom();
                    if (res && res.success) {
                        openMultiplayerModal(store, true, 'Live room ended.');
                    } else {
                        openMultiplayerModal(store, true, (res && res.error) || 'Could not end live room.');
                    }
                }
            };
        }

        const vacateBtn = modalContent.querySelector('#sbx-vacate-seat-btn');
        if (vacateBtn) {
            vacateBtn.onclick = async () => {
                if (window.SandboxMultiplayer) {
                    await window.SandboxMultiplayer.claimSeat(null);
                    openMultiplayerModal(store, true, 'You are now spectating.');
                }
            };
        }

        modalContent.querySelectorAll('button[data-claim-seat]').forEach((btn) => {
            btn.onclick = async () => {
                if (!isLive || btn.disabled) return;
                const idx = Number(btn.getAttribute('data-claim-seat'));
                if (window.SandboxMultiplayer) {
                    const res = await window.SandboxMultiplayer.claimSeat(idx);
                    if (res && res.success) {
                        openMultiplayerModal(store, true);
                    } else {
                        openMultiplayerModal(store, true, (res && res.error) || 'Seat is already claimed.');
                    }
                }
            };
        });

        modalContent.querySelectorAll('button[data-release-seat]').forEach((btn) => {
            btn.onclick = async () => {
                const idx = Number(btn.getAttribute('data-release-seat'));
                if (window.SandboxMultiplayer) {
                    await window.SandboxMultiplayer.releaseSeat(idx);
                    openMultiplayerModal(store, true);
                }
            };
        });

        modalBackdrop.classList.remove('hidden');

        if (!skipStatusFetch && !isLive && window.SandboxMultiplayer) {
            window.SandboxMultiplayer.fetchRoomStatus().then(() => {
                if (!modalBackdrop.classList.contains('hidden')) {
                    openMultiplayerModal(store, true, statusMessage);
                }
            });
        }
    }

    // 8. Publish to TFD Game Lobby (Admin-Only Game)
    function inferImageMimeFromUrl(url) {
        const clean = String(url || '').split('?')[0].split('#')[0].toLowerCase();
        if (clean.endsWith('.svg')) return 'image/svg+xml';
        if (clean.endsWith('.png')) return 'image/png';
        if (clean.endsWith('.webp')) return 'image/webp';
        if (clean.endsWith('.jpg') || clean.endsWith('.jpeg')) return 'image/jpeg';
        return 'image/png';
    }

    function blobToDataUrl(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
            reader.onerror = () => reject(new Error('Failed to read image data'));
            reader.readAsDataURL(blob);
        });
    }

    async function renderObjectAssets(obj, bootstrap) {
        const renderer = window.SandboxTemplateRenderer;
        const imported = (bootstrap && bootstrap.projectAssets && bootstrap.projectAssets.importedAssets) || {};
        if (!obj || typeof obj !== 'object') return;

        if (obj.type === 'BOARD' && (!obj.imageUrl || String(obj.imageUrl).startsWith('tpl_'))) {
            const boards = imported.boards || [];
            const def = boards.find(
                (b) =>
                    (obj.assetId && b.assetId === obj.assetId) ||
                    (obj.templateId && b.templateId === obj.templateId) ||
                    (obj.name && b.name === obj.name && b.canvasJson)
            );
            const tplId = obj.templateId || (def && def.templateId) ||
                (typeof obj.assetId === 'string' && obj.assetId.startsWith('tpl_board_') ? Number(obj.assetId.replace('tpl_board_', '')) : null);
            if (def && def.imageUrl && !def.imageUrl.startsWith('tpl_')) {
                obj.imageUrl = def.imageUrl;
            } else if (renderer && tplId && (obj.canvasJson || (def && def.canvasJson))) {
                const canvasJson = obj.canvasJson || def.canvasJson;
                const nativeW = obj.canvasWidth || (def && def.canvasWidth) || 1200;
                const nativeH = obj.canvasHeight || (def && def.canvasHeight) || 800;
                const rendered = await renderer.renderTemplateAsync(tplId, canvasJson, null, nativeW, nativeH, `tpl_${tplId}_base`);
                if (rendered) obj.imageUrl = rendered;
            }
        }

        if (obj.type === 'CARD' && (!obj.frontImageUrl || String(obj.frontImageUrl).startsWith('tpl_'))) {
            const batches = imported.cardBatches || [];
            const prefixMatch = typeof obj.cardAssetId === 'string' ? obj.cardAssetId.match(/^c_(\d+)_\d+$/) : null;
            const inferredTplId = prefixMatch ? Number(prefixMatch[1]) : null;
            let matchedBatch = null;
            let matchedCardDef = null;

            for (let i = 0; i < batches.length; i++) {
                const b = batches[i];
                if ((obj.templateId && b.templateId === obj.templateId) || (inferredTplId && b.templateId === inferredTplId)) {
                    matchedBatch = b;
                }
                const cDef = (b.cards || []).find((c) => c.cardAssetId === obj.cardAssetId);
                if (cDef) {
                    matchedBatch = b;
                    matchedCardDef = cDef;
                    break;
                }
            }

            if (matchedCardDef && matchedCardDef.frontImageUrl && !matchedCardDef.frontImageUrl.startsWith('tpl_')) {
                obj.frontImageUrl = matchedCardDef.frontImageUrl;
            } else {
                const tplId = obj.templateId || (matchedBatch && matchedBatch.templateId);
                const canvasJson = obj.canvasJson || (matchedBatch && matchedBatch.canvasJson);
                if (renderer && tplId && canvasJson) {
                    const rowData = obj.rowData || (matchedCardDef && matchedCardDef.rowData) || obj.metadata || null;
                    const nativeW = (matchedBatch && matchedBatch.canvasWidth) || 744;
                    const nativeH = (matchedBatch && matchedBatch.canvasHeight) || 1039;
                    const rendered = await renderer.renderTemplateAsync(tplId, canvasJson, rowData, nativeW, nativeH, `card_${obj.cardAssetId || tplId}`);
                    if (rendered) obj.frontImageUrl = rendered;
                }
            }
        }
    }

    async function bakeSnapshotForLobby(rawSnapshot, bootstrap, onProgress) {
        const draft = window.SandboxSchema.cloneDeep(rawSnapshot || {});
        const objects = Object.values(draft.boardObjects || {});
        const assetList = [];
        const keyBySource = new Map();
        let assetCounter = 0;

        async function registerImageValue(val) {
            if (!val || typeof val !== 'string') return val;
            const trimmed = val.trim();
            if (!trimmed || trimmed.startsWith('assetref:') || trimmed.startsWith('/published-sandbox-assets/')) {
                return trimmed;
            }
            if (keyBySource.has(trimmed)) {
                return `assetref:${keyBySource.get(trimmed)}`;
            }

            let dataUrl = null;
            if (trimmed.startsWith('data:image/')) {
                dataUrl = trimmed;
            } else if (!/^https?:\/\//i.test(trimmed) || trimmed.startsWith(window.location.origin)) {
                try {
                    const res = await fetch(trimmed, { credentials: 'same-origin' });
                    if (res.ok) {
                        const blob = await res.blob();
                        const converted = await blobToDataUrl(blob);
                        if (converted.startsWith('data:')) {
                            let mime = (blob.type || '').split(';')[0].trim().toLowerCase();
                            if (!mime.startsWith('image/')) {
                                mime = inferImageMimeFromUrl(trimmed);
                            }
                            const b64Part = converted.split(',')[1] || '';
                            if (b64Part) {
                                dataUrl = `data:${mime};base64,${b64Part}`;
                            }
                        }
                    }
                } catch (_) {
                    return trimmed;
                }
            } else {
                return trimmed;
            }

            if (!dataUrl) return trimmed;
            const match = /^data:(image\/[a-zA-Z0-9+.-]+);base64,(.+)$/.exec(dataUrl);
            if (!match) return trimmed;

            const mimeType = match[1].toLowerCase() === 'image/jpg' ? 'image/jpeg' : match[1].toLowerCase();
            const key = `asset_${++assetCounter}`;
            keyBySource.set(trimmed, key);
            assetList.push({
                key,
                mimeType,
                dataBase64: match[2]
            });
            return `assetref:${key}`;
        }

        for (let i = 0; i < objects.length; i++) {
            const obj = objects[i];
            if (!obj || typeof obj !== 'object') continue;
            if (typeof onProgress === 'function') {
                onProgress(`Baking component artwork (${i + 1}/${objects.length})...`);
            }
            await renderObjectAssets(obj, bootstrap);
            if (obj.imageUrl) obj.imageUrl = await registerImageValue(obj.imageUrl);
            if (obj.frontImageUrl) obj.frontImageUrl = await registerImageValue(obj.frontImageUrl);
            if (obj.backImageUrl) obj.backImageUrl = await registerImageValue(obj.backImageUrl);
        }

        const snapshot = window.SandboxSchema.serializeForSave(draft);
        return { snapshot, assets: assetList };
    }

    function openPublishModal(store, bootstrap) {
        const boot = bootstrap || window.sandboxBootstrap || {};
        const state = store.getState();
        const existingPub = boot.publication || null;
        const hasStarting = Boolean(boot.startingSnapshot || (store.ui && store.ui.hasStartingSnapshot));
        const defaultName = (state && state.name) || boot.sandboxName || 'Published Sandbox';
        const defaultMin = existingPub ? Number(existingPub.minPlayers || 1) : 1;
        const defaultMax = existingPub ? Number(existingPub.maxPlayers || state.seats.length || 4) : Math.max(1, Math.min(6, (state.seats && state.seats.length) || boot.defaultPlayerCount || 4));
        const defaultDesc = existingPub && existingPub.description ? existingPub.description : '';
        const lobbyUrl = boot.lobbyPublicUrl || 'https://lobby.theflyingdutchmen.games';

        const playerOpts = (selected) => [1, 2, 3, 4, 5, 6].map((n) => `<option value="${n}" ${n === selected ? 'selected' : ''}>${n} Player${n === 1 ? '' : 's'}</option>`).join('');

        modalContent.innerHTML = `
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-extrabold text-white">Publish to TFD Game Lobby</h3>
                    <p class="text-xs text-slate-400">Publishes an <span class="text-amber-300 font-semibold">Admin-Only</span> playable build to the lobby without restarting the server.</p>
                </div>
                <button type="button" id="sbx-modal-close" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">Close</button>
            </div>

            ${existingPub ? `
                <div class="rounded-xl bg-amber-500/10 border border-amber-500/30 p-3 text-xs text-amber-200 flex items-center justify-between gap-2">
                    <div>
                        <span class="font-bold">Currently Live (Admin Only):</span> Version v${Number(existingPub.publishedVersion || 1)}
                        <span class="text-amber-300/80 ml-1">· ID ${escapeHtml(existingPub.lobbyGameId || ('sandbox-' + boot.sandboxId))}</span>
                    </div>
                    <a href="${escapeHtml(lobbyUrl)}" target="_blank" rel="noopener" class="px-2.5 py-1 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 font-bold whitespace-nowrap">Open Lobby ↗</a>
                </div>
            ` : ''}

            <div class="space-y-3 text-xs">
                <div>
                    <label for="sbx-pub-name" class="block font-bold text-slate-300 mb-1">Lobby Game Title</label>
                    <input id="sbx-pub-name" type="text" maxlength="150" value="${escapeHtml(defaultName)}" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">
                </div>
                <div>
                    <label for="sbx-pub-desc" class="block font-bold text-slate-300 mb-1">Description (shown on Lobby game card)</label>
                    <textarea id="sbx-pub-desc" rows="2" maxlength="2000" placeholder="Rules overview or playtest focus for fellow admins..." class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">${escapeHtml(defaultDesc)}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="sbx-pub-min" class="block font-bold text-slate-300 mb-1">Min Players</label>
                        <select id="sbx-pub-min" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">${playerOpts(defaultMin)}</select>
                    </div>
                    <div>
                        <label for="sbx-pub-max" class="block font-bold text-slate-300 mb-1">Max Players</label>
                        <select id="sbx-pub-max" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white">${playerOpts(defaultMax)}</select>
                    </div>
                </div>
                <div class="rounded-xl bg-slate-950 border border-slate-800 p-3 space-y-1.5">
                    <div class="font-bold text-slate-300">Table Layout Snapshot to Publish</div>
                    <label class="flex items-center gap-2 cursor-pointer text-slate-300">
                        <input type="radio" name="sbx-pub-source" value="table" checked>
                        <span>Current Table Layout <span class="text-slate-500">(publishes what you see now)</span></span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer ${hasStarting ? 'text-slate-300' : 'text-slate-600 cursor-not-allowed'}">
                        <input type="radio" name="sbx-pub-source" value="setup" ${hasStarting ? '' : 'disabled'}>
                        <span>Frozen Starting Setup <span class="text-slate-500">${hasStarting ? '(uses saved starting snapshot)' : '(freeze setup first to enable)'}</span></span>
                    </label>
                </div>
                <div id="sbx-pub-status" class="hidden rounded-xl px-3 py-2 text-xs"></div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-800">
                <div>
                    ${existingPub ? `<button type="button" id="sbx-pub-unpublish" class="px-3 py-2 rounded-xl bg-rose-600/20 hover:bg-rose-600/30 border border-rose-500/30 text-rose-300 text-xs font-bold">Unpublish from Lobby</button>` : ''}
                </div>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" id="sbx-pub-cancel" class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold">Cancel</button>
                    <button type="button" id="sbx-pub-submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold">${existingPub ? 'Update Published Build' : 'Publish to Lobby'}</button>
                </div>
            </div>
        `;

        modalContent.querySelector('#sbx-modal-close').onclick = closeModal;
        modalContent.querySelector('#sbx-pub-cancel').onclick = closeModal;

        const statusEl = modalContent.querySelector('#sbx-pub-status');
        const submitBtn = modalContent.querySelector('#sbx-pub-submit');
        const unpubBtn = modalContent.querySelector('#sbx-pub-unpublish');
        const minSel = modalContent.querySelector('#sbx-pub-min');
        const maxSel = modalContent.querySelector('#sbx-pub-max');

        const setStatus = (msg, kind = 'info') => {
            statusEl.className = `rounded-xl px-3 py-2 text-xs border ${
                kind === 'error'
                    ? 'bg-rose-500/10 border-rose-500/30 text-rose-300'
                    : (kind === 'success'
                        ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300'
                        : 'bg-sky-500/10 border-sky-500/30 text-sky-300')
            }`;
            statusEl.textContent = msg;
            statusEl.classList.remove('hidden');
        };

        minSel.onchange = () => {
            if (Number(maxSel.value) < Number(minSel.value)) {
                maxSel.value = minSel.value;
            }
        };

        submitBtn.onclick = async () => {
            const title = (modalContent.querySelector('#sbx-pub-name').value || '').trim();
            if (!title) {
                setStatus('Please enter a Lobby Game Title.', 'error');
                return;
            }
            const description = (modalContent.querySelector('#sbx-pub-desc').value || '').trim();
            const minPlayers = Math.max(1, Math.min(6, Number(minSel.value) || 1));
            const maxPlayers = Math.max(minPlayers, Math.min(6, Number(maxSel.value) || minPlayers));
            const sourceRadio = modalContent.querySelector('input[name="sbx-pub-source"]:checked');
            const snapshotSource = sourceRadio ? sourceRadio.value : 'table';

            submitBtn.disabled = true;
            if (unpubBtn) unpubBtn.disabled = true;

            try {
                const rawSnap = snapshotSource === 'setup' && boot.startingSnapshot
                    ? boot.startingSnapshot
                    : store.getState();

                setStatus('Baking template artwork & icons for portable lobby bundle...');
                const baked = await bakeSnapshotForLobby(rawSnap, boot, (stepMsg) => setStatus(stepMsg));

                setStatus('Uploading bundle to The Flying Dutchmen Game Lobby...');
                const fd = new FormData();
                fd.append('action', 'publish_sandbox_to_lobby');
                fd.append('sandbox_id', String(boot.sandboxId));
                fd.append('csrf_token', boot.csrfToken);
                fd.append('payload', JSON.stringify({
                    name: title,
                    description,
                    minPlayers,
                    maxPlayers,
                    snapshotSource,
                    snapshot: baked.snapshot,
                    assets: baked.assets
                }));

                const res = await fetch('api.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error((data && data.error) || `HTTP ${res.status}`);
                }

                boot.publication = data.publication;
                if (data.lobbyPublicUrl) boot.lobbyPublicUrl = data.lobbyPublicUrl;
                store.logEvent(`Published v${data.publication.publishedVersion} to Lobby (Admin Only).`);
                store.notify(true);
                setStatus(`Published v${data.publication.publishedVersion} to the Lobby! Admins can now see and play it.`, 'success');
                setTimeout(() => {
                    if (!modalBackdrop.classList.contains('hidden')) {
                        openPublishModal(store, boot);
                    }
                }, 900);
            } catch (err) {
                setStatus('Publish failed: ' + err.message, 'error');
                submitBtn.disabled = false;
                if (unpubBtn) unpubBtn.disabled = false;
            }
        };

        if (unpubBtn) {
            unpubBtn.onclick = async () => {
                unpubBtn.disabled = true;
                submitBtn.disabled = true;
                setStatus('Removing game from Lobby...');
                try {
                    const fd = new FormData();
                    fd.append('action', 'unpublish_sandbox_from_lobby');
                    fd.append('sandbox_id', String(boot.sandboxId));
                    fd.append('csrf_token', boot.csrfToken);

                    const res = await fetch('api.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error((data && data.error) || `HTTP ${res.status}`);
                    }
                    boot.publication = null;
                    store.logEvent('Unpublished sandbox from Lobby.');
                    store.notify(true);
                    closeModal();
                } catch (err) {
                    setStatus('Unpublish failed: ' + err.message, 'error');
                    unpubBtn.disabled = false;
                    submitBtn.disabled = false;
                }
            };
        }

        modalBackdrop.classList.remove('hidden');
    }

    window.SandboxWorkspaceModals = {
        openDeckSearchModal,
        openSplitDeckModal,
        openMergeDecksModal,
        openScenariosModal,
        openStickyNoteModal,
        openAddResourceModal,
        openMultiplayerModal,
        openPublishModal
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
