/**
 * Token, Infinite Spawner Bag & 2D Instant Dice Roller Renderer (Tier 4: 500-699, spec §5.3).
 */
(function () {
    'use strict';

    const { escapeHtml } = window.SandboxSchema;

    function renderTokensAndDice(tokenLayerEl, state, ui, handlers) {
        tokenLayerEl.innerHTML = '';

        const items = Object.values(state.boardObjects || {})
            .filter((o) => o && (o.type === 'TOKEN' || o.type === 'SPAWNER' || o.type === 'DIE'))
            .sort((a, b) => (a.zIndex || 500) - (b.zIndex || 500));

        items.forEach((item) => {
            const isSelected = ui.selectedIds.includes(item.id);
            const el = document.createElement('div');
            el.dataset.objectId = item.id;
            el.style.left = `${item.x || 0}px`;
            el.style.top = `${item.y || 0}px`;
            el.style.width = `${item.width || 56}px`;
            el.style.height = `${item.height || 56}px`;
            el.style.zIndex = String(item.zIndex || 500);
            el.style.transform = `rotate(${item.rotation || 0}deg)`;

            if (item.type === 'SPAWNER') {
                el.className = `sbx-obj rounded-2xl border-2 border-dashed ${isSelected ? 'is-selected border-sky-400' : 'border-emerald-400/70'} bg-emerald-950/70 flex flex-col items-center justify-center p-1 cursor-grab shadow-lg`;
                el.innerHTML = `
                    <span class="text-[10px] font-black text-emerald-300 uppercase tracking-tighter text-center leading-tight">${escapeHtml(item.name || 'Supply')}</span>
                    <span class="text-[8px] font-bold text-emerald-400/80 mt-0.5">∞ Drag Out</span>
                `;
            } else if (item.type === 'DIE') {
                const faces = Number(item.faces) || 6;
                const val = item.value || 1;
                const displayFace = Array.isArray(item.customFaces) && item.customFaces[val - 1]
                    ? item.customFaces[val - 1]
                    : String(val);
                el.className = `sbx-obj rounded-xl border-2 ${isSelected ? 'is-selected border-sky-400' : 'border-amber-400/80'} bg-gradient-to-br from-amber-500/25 via-slate-900 to-slate-950 flex flex-col items-center justify-center cursor-pointer shadow-lg`;
                el.innerHTML = `
                    <span class="text-base font-black text-amber-300 leading-none">${escapeHtml(displayFace)}</span>
                    <span class="text-[8px] font-bold text-slate-400 mt-0.5">d${faces} · Click</span>
                `;
                el.addEventListener('click', (e) => {
                    if (el.dataset.wasDragged === '1') {
                        el.dataset.wasDragged = '0';
                        return;
                    }
                    e.stopPropagation();
                    el.classList.remove('sbx-die-rolling');
                    void el.offsetWidth;
                    el.classList.add('sbx-die-rolling');
                    setTimeout(() => handlers.onDieRoll(item.id), 160);
                });
            } else {
                // TOKEN / Meeple
                const bg = item.color || '#E53935';
                el.className = `sbx-obj rounded-full border-2 ${isSelected ? 'is-selected border-sky-400' : 'border-white/70'} flex items-center justify-center cursor-grab shadow-md`;
                el.style.backgroundColor = bg;
                if (item.imageUrl) {
                    el.style.backgroundImage = `url("${escapeHtml(item.imageUrl)}")`;
                    el.style.backgroundSize = 'contain';
                    el.style.backgroundRepeat = 'no-repeat';
                    el.style.backgroundPosition = 'center';
                } else {
                    el.innerHTML = `<span class="text-[10px] font-extrabold text-white drop-shadow px-1 truncate">${escapeHtml((item.name || 'T').slice(0, 4))}</span>`;
                }
            }

            el.addEventListener('pointerenter', () => handlers.onObjectHover(item));
            el.addEventListener('pointerleave', () => handlers.onObjectHover(null));
            el.addEventListener('pointerdown', (e) => handlers.onObjectPointerDown(e, item));
            el.addEventListener('contextmenu', (e) => handlers.onObjectContextMenu(e, item));

            tokenLayerEl.appendChild(el);
        });
    }

    window.SandboxTokenSpawner = { renderTokensAndDice };
})();
