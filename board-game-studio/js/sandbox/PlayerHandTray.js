/**
 * Bottom Private Player Hand Tray + Solo Seat-Switcher & Omniscient God Mode (spec §5.2, §5.4).
 */
(function () {
    'use strict';

    const { escapeHtml } = window.SandboxSchema;

    function renderPlayerHandTray(trayEl, store) {
        const state = store.getState();
        const ui = store.getUI();
        const seats = state.seats || [];
        const activeSeat = seats.find((s) => s.seatIndex === ui.activeViewerSeat) || seats[0];
        const handIds = activeSeat && Array.isArray(activeSeat.handCardIds) ? activeSeat.handCardIds : [];

        trayEl.innerHTML = `
            <div class="h-8 px-3 border-b border-slate-800/80 flex items-center justify-between gap-2 bg-slate-950/60">
                <div class="flex items-center gap-1 overflow-x-auto">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mr-1">Seat Switcher:</span>
                    ${seats.map((s) => {
                        const isCur = !ui.godMode && s.seatIndex === ui.activeViewerSeat;
                        return `
                            <button type="button" data-switch-seat="${s.seatIndex}" class="px-2.5 py-0.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition ${isCur ? 'bg-slate-800 text-white border border-slate-600' : 'text-slate-400 hover:text-white'}">
                                <span class="w-2 h-2 rounded-full" style="background:${escapeHtml(s.color)}"></span>
                                <span>${escapeHtml(s.playerName)}</span>
                                <span class="text-[10px] px-1 rounded bg-slate-900 text-slate-300">${(s.handCardIds || []).length}</span>
                            </button>
                        `;
                    }).join('')}
                    <button type="button" id="sbx-btn-godmode" class="px-2.5 py-0.5 rounded-lg text-xs font-bold transition ${ui.godMode ? 'bg-amber-500 text-slate-950' : 'text-amber-400 hover:bg-amber-500/10'}">
                        👁 Omniscient God Mode
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" id="sbx-btn-ready-all" class="px-2.5 py-0.5 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-emerald-300 text-[11px] font-bold transition">
                        ↻ Ready All My Cards
                    </button>
                </div>
            </div>

            <div class="flex-1 px-4 py-2 flex items-center gap-2.5 overflow-x-auto" id="sbx-hand-cards-row">
                ${handIds.length === 0
                    ? `<div class="text-xs text-slate-500 italic">No cards in ${escapeHtml(activeSeat ? activeSeat.playerName : 'Player')}'s hand. Hover a deck and press [1–9] or click "Draw 1" to draw cards.</div>`
                    : ''
                }
            </div>
        `;

        trayEl.querySelectorAll('button[data-switch-seat]').forEach((btn) => {
            btn.onclick = () => store.setViewerSeat(Number(btn.getAttribute('data-switch-seat')), false);
        });
        trayEl.querySelector('#sbx-btn-godmode').onclick = () => store.setViewerSeat(ui.activeViewerSeat, !ui.godMode);
        trayEl.querySelector('#sbx-btn-ready-all').onclick = () => store.readyAllPlayerCards(ui.activeViewerSeat);

        const rowEl = trayEl.querySelector('#sbx-hand-cards-row');
        handIds.forEach((cid) => {
            const card = state.boardObjects[cid];
            if (!card) return;
            const cEl = document.createElement('div');
            cEl.draggable = true;
            cEl.className = 'w-28 h-24 flex-shrink-0 rounded-xl bg-gradient-to-b from-slate-800 to-slate-950 border border-slate-700 hover:border-sky-400 p-2 flex flex-col justify-between cursor-grab transition hover:-translate-y-1 shadow-md';
            const metaLine = Object.entries(card.metadata || {}).slice(0, 2).map(([k, v]) => `${escapeHtml(k)}: ${escapeHtml(v)}`).join(' · ');
            cEl.innerHTML = `
                <div class="text-[11px] font-extrabold text-white leading-tight line-clamp-2">${escapeHtml(card.name || 'Card')}</div>
                <div class="text-[9px] text-slate-400 truncate">${metaLine || 'Drag to table'}</div>
                <div class="flex items-center justify-between gap-1 pt-1 border-t border-slate-800">
                    <button type="button" data-play-up="${escapeHtml(card.id)}" class="flex-1 py-0.5 rounded bg-sky-600/80 hover:bg-sky-500 text-white text-[9px] font-bold">Play</button>
                    <button type="button" data-play-down="${escapeHtml(card.id)}" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-[9px] font-bold" title="Play Face-Down">FD</button>
                </div>
            `;

            cEl.ondragstart = (e) => {
                e.dataTransfer.setData('application/x-sandbox-item', JSON.stringify({
                    dragKind: 'HAND_CARD',
                    cardId: card.id
                }));
            };
            cEl.querySelector('[data-play-up]').onclick = () => store.playCardFromHand(card.id, 120 + Math.random() * 140, 120, true);
            cEl.querySelector('[data-play-down]').onclick = () => store.playCardFromHand(card.id, 120 + Math.random() * 140, 120, false);

            rowEl.appendChild(cEl);
        });
    }

    window.SandboxPlayerHandTray = { renderPlayerHandTray };
})();
