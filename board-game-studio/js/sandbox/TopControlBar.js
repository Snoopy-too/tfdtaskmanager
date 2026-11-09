/**
 * Top Control Bar HUD (spec §5.1.5, §5.4, §7.1).
 * Mode switcher (Setup vs Play), Turn/Round/Phase tracker, 5-step Undo/Redo,
 * Save/Reset Starting Snapshot, Scenarios modal, and Live Multiplayer Room button.
 */
(function () {
    'use strict';

    const { escapeHtml } = window.SandboxSchema;

    function renderTopControlBar(barEl, store, bootstrap) {
        const state = store.getState();
        const ui = store.getUI();
        const ts = state.turnState || { activeSeat: 1, roundNumber: 1, currentPhase: 'Action Phase' };
        const activeSeatObj = state.seats.find((s) => s.seatIndex === ts.activeSeat) || state.seats[0];

        barEl.innerHTML = `
            <div class="flex items-center gap-2 min-w-0">
                <a href="sandboxes.php?project_id=${Number(bootstrap.projectId)}" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold flex items-center gap-1 transition">
                    <span>←</span><span class="hidden sm:inline">Sandboxes</span>
                </a>
                <span class="text-sm font-extrabold text-white truncate max-w-[180px]" title="${escapeHtml(state.name)}">${escapeHtml(state.name)}</span>

                <!-- Two-Mode Architecture Switcher -->
                <div class="flex items-center bg-slate-950 border border-slate-800 rounded-xl p-0.5 ml-1">
                    <button type="button" id="sbx-mode-setup" class="px-2.5 py-1 rounded-lg text-xs font-bold transition ${state.mode === 'SETUP' ? 'bg-sky-600 text-white shadow' : 'text-slate-400 hover:text-white'}">
                        🛠 Setup Mode
                    </button>
                    <button type="button" id="sbx-mode-play" class="px-2.5 py-1 rounded-lg text-xs font-bold transition ${state.mode === 'PLAY' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-white'}">
                        ▶ Play Mode
                    </button>
                </div>

                <span class="text-[11px] ${String(ui.saveStatus || '').startsWith('Save error') ? 'text-rose-400 border-rose-500/40 bg-rose-500/10 font-bold' : 'text-slate-400 bg-slate-950/90 border-slate-800'} border px-2 py-0.5 rounded-full hidden lg:inline">${escapeHtml(ui.saveStatus)}</span>
            </div>

            <!-- Turn & Phase Tracker -->
            <div class="hidden md:flex items-center gap-2 bg-slate-950 border border-slate-800 px-3 py-1 rounded-xl text-xs">
                <span class="font-bold text-slate-400">R${Number(ts.roundNumber) || 1}</span>
                <span class="text-slate-600">·</span>
                <span class="inline-flex items-center gap-1.5 font-bold text-white">
                    <span class="w-2.5 h-2.5 rounded-full" style="background:${escapeHtml(activeSeatObj ? activeSeatObj.color : '#38BDF8')}"></span>
                    ${escapeHtml(activeSeatObj ? activeSeatObj.playerName : `Player ${ts.activeSeat}`)}
                </span>
                <span class="text-slate-600">·</span>
                <span class="px-2 py-0.5 rounded bg-indigo-500/15 text-indigo-300 font-semibold">${escapeHtml(ts.currentPhase || 'Action Phase')}</span>
                <button type="button" id="sbx-next-phase" class="px-2 py-0.5 rounded bg-indigo-600 hover:bg-indigo-500 text-white font-bold transition" title="Advance Phase / Turn">Next →</button>
            </div>

            <!-- Undo / Snapshot / Scenarios / Multiplayer Actions -->
            <div class="flex items-center gap-1.5">
                <button type="button" id="sbx-btn-undo" ${store.canUndo() ? '' : 'disabled'} class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 disabled:opacity-40 text-xs font-semibold text-slate-200 transition" title="Undo (Ctrl+Z)">↶ Undo</button>
                <button type="button" id="sbx-btn-redo" ${store.canRedo() ? '' : 'disabled'} class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 disabled:opacity-40 text-xs font-semibold text-slate-200 transition" title="Redo (Ctrl+Shift+Z)">↷</button>

                <button type="button" id="sbx-btn-freeze" class="px-2.5 py-1 rounded-lg bg-sky-500/15 hover:bg-sky-500/25 border border-sky-500/30 text-sky-300 text-xs font-bold transition" title="Freeze layout as Default Starting Snapshot">
                    Freeze Start
                </button>
                <button type="button" id="sbx-btn-reset" class="px-2.5 py-1 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-amber-300 text-xs font-bold transition" title="Reset table to Starting State">
                    Reset Table
                </button>
                <button type="button" id="sbx-btn-scenarios" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition">
                    Scenarios (${(state.scenarios || []).length})
                </button>
                <button type="button" id="sbx-btn-multiplayer" class="px-2.5 py-1 rounded-lg ${ui.isMultiplayerActive ? 'bg-emerald-600 text-white' : 'bg-violet-600/80 hover:bg-violet-500 text-white'} text-xs font-bold transition">
                    ${ui.isMultiplayerActive ? `🟢 Live (${escapeHtml(ui.liveRoomCode || 'Room')})` : '📡 Live Room'}
                </button>
            </div>
        `;

        barEl.querySelector('#sbx-mode-setup').onclick = () => store.setMode('SETUP');
        barEl.querySelector('#sbx-mode-play').onclick = () => store.setMode('PLAY');
        barEl.querySelector('#sbx-btn-undo').onclick = () => store.undo();
        barEl.querySelector('#sbx-btn-redo').onclick = () => store.redo();
        barEl.querySelector('#sbx-btn-freeze').onclick = () => store.freezeStartingSnapshot();
        barEl.querySelector('#sbx-btn-reset').onclick = () => store.resetToStartingSnapshot();
        const nextBtn = barEl.querySelector('#sbx-next-phase');
        if (nextBtn) nextBtn.onclick = () => store.advancePhaseOrTurn();

        barEl.querySelector('#sbx-btn-scenarios').onclick = () => {
            if (window.SandboxWorkspaceModals) window.SandboxWorkspaceModals.openScenariosModal(store);
        };
        barEl.querySelector('#sbx-btn-multiplayer').onclick = () => {
            if (window.SandboxWorkspaceModals) window.SandboxWorkspaceModals.openMultiplayerModal(store);
        };
    }

    window.SandboxTopControlBar = { renderTopControlBar };
})();
