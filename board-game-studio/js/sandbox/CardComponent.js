/**
 * Card Component Renderer (Tier 3: 300-499, spec §5.2).
 * Handles faceUp/faceDown state, private Peek, 90° Tap, parent-child tuck badges, and soft-lock outlines.
 */
(function () {
    'use strict';

    const { escapeHtml } = window.SandboxSchema;

    function collectDeckCardSet(boardObjects) {
        const set = new Set();
        Object.values(boardObjects || {}).forEach((o) => {
            if (o && o.type === 'DECK' && Array.isArray(o.cardIds)) {
                o.cardIds.forEach((cid) => set.add(cid));
            }
        });
        return set;
    }

    function renderCards(cardLayerEl, state, ui, currentUserId, handlers) {
        const inDeckIds = collectDeckCardSet(state.boardObjects);
        const cards = Object.values(state.boardObjects || {})
            .filter((o) => o && o.type === 'CARD' && o.ownerSeat === null && !inDeckIds.has(o.id))
            .sort((a, b) => (a.zIndex || 350) - (b.zIndex || 350));

        cards.forEach((card) => {
            const el = document.createElement('div');
            const isSelected = ui.selectedIds.includes(card.id);
            const isTuckTarget = ui.tuckHighlightParentId === card.id;
            const peekingIds = Array.isArray(card.peekingUserIds) ? card.peekingUserIds : [];
            const isPeekingSelf = peekingIds.includes(Number(currentUserId));
            const isSomeonePeeking = peekingIds.length > 0;
            const showFront = Boolean(card.faceUp || ui.godMode || isPeekingSelf);

            const lockedByOther = card.lockedByUserId && Number(card.lockedByUserId) !== Number(currentUserId);
            const rot = Number(card.rotation) || (card.isTapped ? 90 : 0);

            el.className = `sbx-obj rounded-xl border shadow-lg transition cursor-grab ${isSelected ? 'is-selected border-sky-400' : 'border-slate-600/80'} ${isTuckTarget ? 'is-tuck-target' : ''}`;
            el.dataset.objectId = card.id;
            el.style.left = `${card.x || 0}px`;
            el.style.top = `${card.y || 0}px`;
            el.style.width = `${card.width || 140}px`;
            el.style.height = `${card.height || 200}px`;
            el.style.zIndex = String(card.zIndex || 350);
            el.style.transform = `rotate(${rot}deg)`;

            if (lockedByOther) {
                el.style.outline = '2px solid #F43F5E';
                el.style.outlineOffset = '2px';
            }

            const metaEntries = Object.entries(card.metadata || {}).slice(0, 3);
            const metaPills = metaEntries.map(([k, v]) =>
                `<span class="px-1.5 py-0.5 rounded bg-slate-900/90 border border-slate-700 text-[9px] text-slate-300">${escapeHtml(k)}: <strong>${escapeHtml(v)}</strong></span>`
            ).join('');

            const tuckedCount = Array.isArray(card.tuckedChildren) ? card.tuckedChildren.length : 0;

            if (showFront) {
                el.innerHTML = `
                    <div class="w-full h-full rounded-xl bg-gradient-to-b from-slate-800 via-slate-900 to-slate-950 p-2 flex flex-col justify-between overflow-hidden relative">
                        <div class="flex items-start justify-between gap-1">
                            <span class="text-[11px] font-extrabold text-white leading-tight line-clamp-2">${escapeHtml(card.name || 'Card')}</span>
                            ${card.isTapped ? '<span class="px-1 py-0.5 rounded bg-amber-500/20 text-amber-300 text-[8px] font-bold">90°</span>' : ''}
                        </div>
                        ${card.frontImageUrl
                            ? `<div class="my-1 flex-1 rounded-lg bg-slate-950/60 bg-center bg-contain bg-no-repeat" style="background-image:url('${escapeHtml(card.frontImageUrl)}')"></div>`
                            : `<div class="my-1 flex-1 rounded-lg bg-slate-950/60 border border-slate-800/80 flex items-center justify-center text-[10px] text-slate-400 px-2 text-center">${escapeHtml(card.name || 'Component')}</div>`
                        }
                        <div class="flex flex-wrap gap-1">${metaPills}</div>
                        ${isSomeonePeeking ? '<span class="absolute top-1.5 right-1.5 px-1.5 py-0.5 rounded-full bg-amber-500/90 text-slate-950 text-[9px] font-extrabold" title="Player peeking">👁 Peek</span>' : ''}
                        ${tuckedCount > 0 ? `<span class="absolute bottom-1.5 right-1.5 px-1.5 py-0.5 rounded bg-sky-500/20 border border-sky-400/40 text-sky-300 text-[9px] font-bold">+${tuckedCount} tucked</span>` : ''}
                    </div>
                `;
            } else {
                el.innerHTML = `
                    <div class="w-full h-full rounded-xl bg-gradient-to-br from-indigo-950 via-slate-900 to-slate-950 p-2 flex flex-col items-center justify-center relative border border-indigo-500/30">
                        <div class="w-12 h-12 rounded-full border-2 border-indigo-400/40 flex items-center justify-center text-indigo-300 font-black text-xs">TFD</div>
                        <span class="text-[10px] text-slate-400 mt-2 font-semibold">Face Down</span>
                        ${isSomeonePeeking ? '<span class="absolute top-1.5 right-1.5 px-1.5 py-0.5 rounded-full bg-amber-500/90 text-slate-950 text-[9px] font-extrabold">👁 Peeking</span>' : ''}
                    </div>
                `;
            }

            el.addEventListener('pointerenter', () => handlers.onObjectHover(card));
            el.addEventListener('pointerleave', () => handlers.onObjectHover(null));
            el.addEventListener('pointerdown', (e) => handlers.onObjectPointerDown(e, card));
            el.addEventListener('dblclick', (e) => {
                e.stopPropagation();
                handlers.onCardDoubleClick(card);
            });
            el.addEventListener('contextmenu', (e) => handlers.onObjectContextMenu(e, card));

            cardLayerEl.appendChild(el);
        });
    }

    window.SandboxCardComponent = { renderCards };
})();
