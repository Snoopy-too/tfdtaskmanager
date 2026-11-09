/**
 * Player Resource HUD Dashboard (spec §1, §5.1.4, §5.3).
 * Public trackers (+1, -1, +5, -5 & numeric input), custom resource seeding, and Bank/Player transfers.
 */
(function () {
    'use strict';

    const { escapeHtml } = window.SandboxSchema;

    function renderResourceDashboard(resEl, store) {
        const state = store.getState();
        const ui = store.getUI();
        const seats = state.seats || [];
        const activeSeat = seats.find((s) => s.seatIndex === ui.activeViewerSeat) || seats[0];
        if (!activeSeat) {
            resEl.innerHTML = '';
            return;
        }
        const resources = activeSeat.resources || { Money: 10, VP: 0 };
        const resKeys = Object.keys(resources);

        resEl.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full" style="background:${escapeHtml(activeSeat.color)}"></span>
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-200">${escapeHtml(activeSeat.playerName)} Resources</span>
                </div>
                <button type="button" id="sbx-add-res-key" class="text-[10px] font-bold text-sky-400 hover:text-sky-300">+ Track Stat</button>
            </div>

            <div class="space-y-1.5" id="sbx-res-rows">
                ${resKeys.map((key) => {
                    const val = Number(resources[key]) || 0;
                    return `
                        <div class="bg-slate-950 border border-slate-800/90 rounded-xl px-2 py-1.5 flex items-center justify-between gap-1 text-xs">
                            <span class="font-bold text-slate-300 truncate max-w-[85px]" title="${escapeHtml(key)}">${escapeHtml(key)}</span>
                            <div class="flex items-center gap-1">
                                <button type="button" data-res-key="${escapeHtml(key)}" data-res-delta="-5" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-rose-500/30 text-rose-300 text-[10px] font-bold">-5</button>
                                <button type="button" data-res-key="${escapeHtml(key)}" data-res-delta="-1" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-rose-500/30 text-rose-300 text-[10px] font-bold">-1</button>
                                <input type="number" data-res-exact="${escapeHtml(key)}" value="${val}" class="w-12 bg-slate-900 border border-slate-700 rounded text-center text-white font-mono text-xs py-0.5">
                                <button type="button" data-res-key="${escapeHtml(key)}" data-res-delta="1" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-emerald-500/30 text-emerald-300 text-[10px] font-bold">+1</button>
                                <button type="button" data-res-key="${escapeHtml(key)}" data-res-delta="5" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-emerald-500/30 text-emerald-300 text-[10px] font-bold">+5</button>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>

            <!-- Quick Transaction Menu: Pay to Bank or Transfer to Seat -->
            <div class="pt-1 border-t border-slate-800/80 flex items-center gap-1 text-[11px]">
                <input type="number" id="sbx-tx-amount" value="3" min="1" class="w-12 bg-slate-950 border border-slate-800 rounded-lg px-1.5 py-1 text-center font-mono text-white">
                <select id="sbx-tx-key" class="flex-1 bg-slate-950 border border-slate-800 rounded-lg px-1.5 py-1 text-slate-200">
                    ${resKeys.map((k) => `<option value="${escapeHtml(k)}">${escapeHtml(k)}</option>`).join('')}
                </select>
                <span class="text-slate-500">→</span>
                <select id="sbx-tx-target" class="bg-slate-950 border border-slate-800 rounded-lg px-1.5 py-1 text-slate-200">
                    <option value="BANK">Bank</option>
                    ${seats.filter((s) => s.seatIndex !== activeSeat.seatIndex).map((s) => `<option value="${s.seatIndex}">${escapeHtml(s.playerName)}</option>`).join('')}
                </select>
                <button type="button" id="sbx-tx-send" class="px-2 py-1 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold">Pay</button>
            </div>
        `;

        resEl.querySelectorAll('button[data-res-delta]').forEach((btn) => {
            btn.onclick = () => store.adjustResource(
                activeSeat.seatIndex,
                btn.getAttribute('data-res-key'),
                Number(btn.getAttribute('data-res-delta'))
            );
        });

        resEl.querySelectorAll('input[data-res-exact]').forEach((inp) => {
            inp.onchange = () => store.adjustResource(
                activeSeat.seatIndex,
                inp.getAttribute('data-res-exact'),
                0,
                Number(inp.value)
            );
        });

        resEl.querySelector('#sbx-tx-send').onclick = () => {
            const amt = Number(resEl.querySelector('#sbx-tx-amount').value) || 1;
            const key = resEl.querySelector('#sbx-tx-key').value;
            const target = resEl.querySelector('#sbx-tx-target').value;
            if (key) store.transferResource(activeSeat.seatIndex, target, key, amt);
        };

        resEl.querySelector('#sbx-add-res-key').onclick = () => {
            if (window.SandboxWorkspaceModals) {
                window.SandboxWorkspaceModals.openAddResourceModal(store);
            }
        };
    }

    window.SandboxResourceDashboard = { renderResourceDashboard };
})();
