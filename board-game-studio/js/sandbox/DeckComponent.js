/**
 * Deck Component Renderer (Tier 3: 300-499, spec §5.1.3, §5.2).
 * Renders deck stacks with card count badge, Draw 1, Shuffle, and Search/Peek modal trigger.
 */
(function () {
    'use strict';

    const { escapeHtml } = window.SandboxSchema;

    function renderDecks(cardLayerEl, state, ui, handlers) {
        const decks = Object.values(state.boardObjects || {})
            .filter((o) => o && o.type === 'DECK')
            .sort((a, b) => (a.zIndex || 310) - (b.zIndex || 310));

        decks.forEach((deck) => {
            const count = Array.isArray(deck.cardIds) ? deck.cardIds.length : 0;
            const isSelected = ui.selectedIds.includes(deck.id);
            const el = document.createElement('div');
            el.className = `sbx-obj rounded-xl border-2 transition cursor-grab ${isSelected ? 'is-selected border-sky-400' : 'border-indigo-400/60'}`;
            el.dataset.objectId = deck.id;
            el.style.left = `${deck.x || 0}px`;
            el.style.top = `${deck.y || 0}px`;
            el.style.width = `${deck.width || 140}px`;
            el.style.height = `${deck.height || 200}px`;
            el.style.zIndex = String(deck.zIndex || 310);
            el.style.transform = `rotate(${deck.rotation || 0}deg)`;

            // 3D stack shadow effect
            el.style.boxShadow = '4px 4px 0 #1e293b, 8px 8px 0 #0f172a, 0 14px 28px rgba(0,0,0,0.6)';

            const topCardId = count > 0 ? deck.cardIds[0] : null;
            const topCard = topCardId ? state.boardObjects[topCardId] : null;
            const topLabel = deck.faceUp && topCard ? (topCard.name || 'Top Card') : 'Face-Down Stack';

            el.innerHTML = `
                <div class="w-full h-full rounded-xl bg-gradient-to-br from-indigo-900/90 via-slate-900 to-slate-950 p-2.5 flex flex-col justify-between relative">
                    <div class="flex items-start justify-between gap-1">
                        <span class="text-[11px] font-extrabold text-indigo-200 leading-tight line-clamp-2">${escapeHtml(deck.name || 'Deck')}</span>
                        <span class="px-2 py-0.5 rounded-full bg-indigo-500 text-white text-[10px] font-black shadow">${count}</span>
                    </div>

                    <div class="my-1 flex-1 rounded-lg border border-dashed border-indigo-400/30 bg-slate-950/50 flex flex-col items-center justify-center p-2 text-center">
                        <span class="text-[10px] font-bold text-slate-300">${escapeHtml(topLabel)}</span>
                        <span class="text-[9px] text-slate-500 mt-1">Hover + [1-9] Draw · [S] Shuffle</span>
                    </div>

                    <div class="grid grid-cols-3 gap-1">
                        <button type="button" data-deck-act="draw" class="px-1.5 py-1 rounded bg-sky-600/80 hover:bg-sky-500 text-white text-[9px] font-bold transition" ${count === 0 ? 'disabled' : ''}>Draw 1</button>
                        <button type="button" data-deck-act="shuffle" class="px-1.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-200 text-[9px] font-bold transition">Shuffle</button>
                        <button type="button" data-deck-act="search" class="px-1.5 py-1 rounded bg-slate-800 hover:bg-slate-700 text-amber-300 text-[9px] font-bold transition">Search</button>
                    </div>
                </div>
            `;

            el.querySelectorAll('button[data-deck-act]').forEach((btn) => {
                btn.addEventListener('pointerdown', (e) => e.stopPropagation());
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const act = btn.getAttribute('data-deck-act');
                    if (act === 'draw') handlers.onDeckDraw(deck.id, 1);
                    if (act === 'shuffle') handlers.onDeckShuffle(deck.id);
                    if (act === 'search') handlers.onDeckSearch(deck);
                });
            });

            el.addEventListener('pointerenter', () => handlers.onObjectHover(deck));
            el.addEventListener('pointerleave', () => handlers.onObjectHover(null));
            el.addEventListener('pointerdown', (e) => handlers.onObjectPointerDown(e, deck));
            el.addEventListener('contextmenu', (e) => handlers.onObjectContextMenu(e, deck));

            cardLayerEl.appendChild(el);
        });
    }

    window.SandboxDeckComponent = { renderDecks };
})();
