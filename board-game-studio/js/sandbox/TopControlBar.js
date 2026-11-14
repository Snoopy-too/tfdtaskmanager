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
        const inDeckIds = new Set();
        Object.values(state.boardObjects || {}).forEach((o) => {
            if (o && o.type === 'DECK' && Array.isArray(o.cardIds)) {
                o.cardIds.forEach((cid) => inDeckIds.add(cid));
            }
        });
        const visibleTableCount = Object.values(state.boardObjects || {}).filter(
            (o) => o && (o.ownerSeat === undefined || o.ownerSeat === null) && !(o.type === 'CARD' && inDeckIds.has(o.id))
        ).length;

        const isLive = Boolean(ui.isMultiplayerActive);
        const isHost = isLive && ui.hostUserId !== null && Number(ui.hostUserId) === Number(bootstrap.currentUserId);
        const memberCount = Array.isArray(ui.roomMembers) ? ui.roomMembers.length : 0;
        const roomAvailable = !isLive && Boolean(ui.roomStatus ? ui.roomStatus.active : bootstrap.activeRoomExists);
        const availableCode = (ui.roomStatus && ui.roomStatus.roomCode) || bootstrap.roomCode || 'Room';
        const roomHostName = (ui.roomStatus && ui.roomStatus.hostUserName) || bootstrap.roomHostName || 'Host';
        const hasSaveErr = String(ui.saveStatus || '').toLowerCase().includes('error');

        barEl.innerHTML = `
            <div class="flex items-center gap-2 min-w-0">
                <a href="sandboxes.php?project_id=${Number(bootstrap.projectId)}" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold flex items-center gap-1 transition">
                    <span>←</span><span class="hidden sm:inline">Sandboxes</span>
                </a>
                <span class="text-sm font-extrabold text-white truncate max-w-[180px]" title="${escapeHtml(state.name)}">${escapeHtml(state.name)}</span>

                <!-- Two-Mode Architecture Switcher -->
                <div class="flex items-center bg-slate-950 border border-slate-800 rounded-xl p-0.5 ml-1">
                    <button type="button" id="sbx-mode-setup" ${isLive ? 'disabled title="Leave Live Room to edit Setup"' : ''} class="px-2.5 py-1 rounded-lg text-xs font-bold transition ${state.mode === 'SETUP' ? 'bg-sky-600 text-white shadow' : 'text-slate-400 hover:text-white disabled:opacity-40 disabled:cursor-not-allowed'}">
                        🛠 Setup Mode
                    </button>
                    <button type="button" id="sbx-mode-play" class="px-2.5 py-1 rounded-lg text-xs font-bold transition ${state.mode === 'PLAY' ? 'bg-emerald-600 text-white shadow' : 'text-slate-400 hover:text-white'}">
                        ▶ Play Mode
                    </button>
                </div>

                <span class="text-[11px] ${hasSaveErr ? 'text-rose-400 border-rose-500/40 bg-rose-500/10 font-bold' : 'text-slate-400 bg-slate-950/90 border-slate-800'} border px-2 py-0.5 rounded-full hidden lg:inline">${escapeHtml(ui.saveStatus)} · ${visibleTableCount} on table</span>
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

                <button type="button" id="sbx-btn-freeze" ${isLive ? 'disabled title="Freeze Start is only available in Setup/Solo mode"' : 'title="Freeze layout as Default Starting Snapshot"'} class="px-2.5 py-1 rounded-lg bg-sky-500/15 hover:bg-sky-500/25 disabled:opacity-40 disabled:cursor-not-allowed border border-sky-500/30 text-sky-300 text-xs font-bold transition">
                    Freeze Start
                </button>
                <button type="button" id="sbx-btn-reset" ${(isLive && !isHost) ? 'disabled title="Only the room host can reset the live table"' : 'title="Reset table to Starting State"'} class="px-2.5 py-1 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 disabled:opacity-40 disabled:cursor-not-allowed border border-amber-500/30 text-amber-300 text-xs font-bold transition">
                    Reset Table
                </button>
                <button type="button" id="sbx-btn-scenarios" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition">
                    Scenarios (${(state.scenarios || []).length})
                </button>
                <button type="button" id="sbx-btn-multiplayer" class="px-2.5 py-1 rounded-lg ${isLive ? 'bg-emerald-600 hover:bg-emerald-500 text-white' : (roomAvailable ? 'bg-emerald-600/80 hover:bg-emerald-500 text-white' : 'bg-violet-600/80 hover:bg-violet-500 text-white')} text-xs font-bold transition">
                    ${isLive
                        ? `🟢 Live (${escapeHtml(ui.liveRoomCode || 'Room')}${memberCount > 0 ? ` · ${memberCount}` : ''})`
                        : (roomAvailable ? `🟢 Join Live (${escapeHtml(availableCode)})` : '📡 Live Room')}
                </button>
                <button type="button" id="sbx-btn-publish" class="px-2.5 py-1 rounded-lg ${bootstrap && bootstrap.publication ? 'bg-amber-600 hover:bg-amber-500 text-white' : 'bg-indigo-600 hover:bg-indigo-500 text-white'} text-xs font-bold transition" title="Publish to The Flying Dutchmen Game Lobby (Admins Only)">
                    ${bootstrap && bootstrap.publication
                        ? `🚀 Published v${Number(bootstrap.publication.publishedVersion || 1)}`
                        : '🚀 Publish to Lobby'}
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
        const pubBtn = barEl.querySelector('#sbx-btn-publish');
        if (pubBtn) {
            pubBtn.onclick = () => {
                if (window.SandboxWorkspaceModals && window.SandboxWorkspaceModals.openPublishModal) {
                    window.SandboxWorkspaceModals.openPublishModal(store, bootstrap);
                }
            };
        }

        const bannerEl = document.getElementById('sbx-room-banner');
        if (bannerEl) {
            if (!isLive && roomAvailable) {
                bannerEl.innerHTML = `
                    <div class="bg-emerald-500/10 border-b border-emerald-500/30 text-emerald-200 px-4 py-1.5 text-xs flex items-center justify-between gap-3">
                        <span><strong>Live Room Active:</strong> Code <span class="font-mono font-bold text-white">${escapeHtml(availableCode)}</span> hosted by <strong>${escapeHtml(roomHostName)}</strong>.</span>
                        <div class="flex items-center gap-3">
                            <button type="button" id="sbx-banner-join-btn" class="px-2.5 py-0.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold transition">Join Live Room</button>
                            <a href="sandboxes.php?project_id=${Number(bootstrap.projectId)}" class="underline hover:text-white">Back to Sandboxes</a>
                        </div>
                    </div>
                `;
                const joinBannerBtn = bannerEl.querySelector('#sbx-banner-join-btn');
                if (joinBannerBtn) {
                    joinBannerBtn.onclick = () => {
                        if (window.SandboxMultiplayer) window.SandboxMultiplayer.joinRoom();
                    };
                }
            } else if (!isLive && bootstrap.isLockedByOther) {
                bannerEl.innerHTML = `
                    <div class="bg-amber-500/10 border-b border-amber-500/30 text-amber-300 px-4 py-1.5 text-xs flex items-center justify-between gap-3">
                        <span><strong>Shared Session Notice:</strong> Setup is currently locked by <strong>${escapeHtml(bootstrap.lockOwnerName || 'Another user')}</strong>. Start a Live Room to playtest together in real time.</span>
                        <div class="flex items-center gap-3">
                            <button type="button" id="sbx-banner-host-btn" class="px-2.5 py-0.5 rounded-lg bg-violet-600 hover:bg-violet-500 text-white font-bold transition">Start Live Room</button>
                            <a href="sandboxes.php?project_id=${Number(bootstrap.projectId)}" class="underline hover:text-white">Back to Sandboxes</a>
                        </div>
                    </div>
                `;
                const hostBannerBtn = bannerEl.querySelector('#sbx-banner-host-btn');
                if (hostBannerBtn) {
                    hostBannerBtn.onclick = () => {
                        if (window.SandboxMultiplayer) window.SandboxMultiplayer.hostOrJoinRoom();
                    };
                }
            } else {
                bannerEl.innerHTML = '';
            }
        }
    }

    window.SandboxTopControlBar = { renderTopControlBar };
})();
