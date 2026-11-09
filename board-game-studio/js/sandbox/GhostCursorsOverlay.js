/**
 * Ghost Cursors, Spatial Pings & Sticky Notes Overlay (Tier 5: 900-999, spec §3, §5.4, §7).
 */
(function () {
    'use strict';

    const { escapeHtml } = window.SandboxSchema;

    function renderNotesAndOverlays(notesLayerEl, overlayLayerEl, state, ui, currentUserId, handlers) {
        notesLayerEl.innerHTML = '';
        overlayLayerEl.innerHTML = '';

        // 1. Sticky Feedback Note Pins (spec §5.4)
        (state.notes || []).forEach((note) => {
            const nEl = document.createElement('div');
            nEl.className = 'sbx-obj w-48 rounded-xl bg-amber-300 text-slate-950 p-2.5 shadow-xl border border-amber-500';
            nEl.style.left = `${note.x || 0}px`;
            nEl.style.top = `${note.y || 0}px`;
            nEl.style.zIndex = '750';
            nEl.innerHTML = `
                <div class="flex items-center justify-between text-[10px] font-extrabold text-amber-900 border-b border-amber-400/80 pb-1 mb-1">
                    <span>📌 ${escapeHtml(note.author || 'Tester')}</span>
                    <button type="button" data-del-note="${escapeHtml(note.id)}" class="hover:text-rose-700 font-black px-1" title="Remove note">✕</button>
                </div>
                <p class="text-[11px] font-medium leading-snug break-words">${escapeHtml(note.text || '')}</p>
            `;
            const delBtn = nEl.querySelector('[data-del-note]');
            if (delBtn) {
                delBtn.addEventListener('pointerdown', (e) => e.stopPropagation());
                delBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    handlers.onDeleteNote(note.id);
                });
            }
            notesLayerEl.appendChild(nEl);
        });

        // 2. Remote Ghost Cursors
        Object.values(ui.remoteCursors || {}).forEach((cur) => {
            if (!cur || Number(cur.userId) === Number(currentUserId)) return;
            const cEl = document.createElement('div');
            cEl.className = 'absolute pointer-events-none transition-all duration-100';
            cEl.style.left = `${cur.x || 0}px`;
            cEl.style.top = `${cur.y || 0}px`;
            cEl.style.zIndex = '960';
            cEl.innerHTML = `
                <svg class="w-4 h-4 drop-shadow" viewBox="0 0 24 24" fill="${escapeHtml(cur.color || '#38BDF8')}">
                    <path d="M4 2l16 10-7 2 4 7-3 1-4-7-6 5z"/>
                </svg>
                <span class="ml-3 -mt-1 inline-block px-1.5 py-0.5 rounded text-[9px] font-bold text-white shadow" style="background:${escapeHtml(cur.color || '#38BDF8')}">
                    ${escapeHtml(cur.name || 'Player')}
                </span>
            `;
            overlayLayerEl.appendChild(cEl);
        });

        // 3. Spatial Pings (G + Click)
        (ui.remotePings || []).forEach((ping) => {
            const pEl = document.createElement('div');
            pEl.className = 'sbx-ping-ring';
            pEl.style.left = `${ping.x || 0}px`;
            pEl.style.top = `${ping.y || 0}px`;
            pEl.style.color = ping.color || '#F43F5E';
            pEl.style.zIndex = '980';
            overlayLayerEl.appendChild(pEl);
        });
    }

    window.SandboxGhostOverlay = { renderNotesAndOverlays };
})();
