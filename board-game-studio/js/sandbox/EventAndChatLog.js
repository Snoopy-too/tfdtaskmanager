/**
 * Authoritative Action Feed, Dice Outcomes & Playtester Chat Log (spec §5.2, §5.3, §8).
 */
(function () {
    'use strict';

    const { escapeHtml } = window.SandboxSchema;

    function renderEventAndChatLog(logContainerEl, store) {
        const state = store.getState();
        const events = Array.isArray(state.eventLog) ? state.eventLog : [];

        // Preserve input draft if re-rendering
        const existingInput = logContainerEl.querySelector('#sbx-chat-input');
        const draft = existingInput ? existingInput.value : '';

        logContainerEl.innerHTML = `
            <div class="px-3 py-2 border-b border-slate-800 flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-300">Event & Playtest Log</span>
                <span class="text-[10px] text-slate-500">${events.length} entries</span>
            </div>

            <div class="flex-1 overflow-y-auto p-3 space-y-1.5 text-[11px]" id="sbx-log-feed">
                ${events.length === 0
                    ? '<div class="text-slate-500 italic">Table events, shuffles, dice outcomes, and resource transactions appear here.</div>'
                    : events.slice(-60).map((e) => {
                        const badge = e.type === 'DICE' ? '🎲' : (e.type === 'RESOURCE' ? '💰' : (e.type === 'CHAT' ? '💬' : '•'));
                        return `
                            <div class="leading-snug flex items-start gap-1.5">
                                <span class="text-[9px] text-slate-500 font-mono flex-shrink-0 mt-0.5">${escapeHtml(e.ts || '')}</span>
                                <span class="flex-shrink-0">${badge}</span>
                                <div class="min-w-0 break-words">
                                    <strong style="color:${escapeHtml(e.seatColor || '#38BDF8')}">${escapeHtml(e.author || 'Player')}:</strong>
                                    <span class="text-slate-300"> ${escapeHtml(e.text || '')}</span>
                                </div>
                            </div>
                        `;
                    }).join('')
                }
            </div>

            <form id="sbx-chat-form" class="p-2 border-t border-slate-800 flex items-center gap-1.5">
                <input type="text" id="sbx-chat-input" maxlength="240" placeholder="Send chat or playtest note..." class="flex-1 bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1.5 text-xs text-slate-100 focus:outline-none focus:border-sky-500">
                <button type="submit" class="px-2.5 py-1.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold">Send</button>
            </form>
        `;

        const feedEl = logContainerEl.querySelector('#sbx-log-feed');
        if (feedEl) feedEl.scrollTop = feedEl.scrollHeight;

        const inputEl = logContainerEl.querySelector('#sbx-chat-input');
        if (inputEl && draft) inputEl.value = draft;

        const formEl = logContainerEl.querySelector('#sbx-chat-form');
        formEl.onsubmit = (e) => {
            e.preventDefault();
            const msg = (inputEl.value || '').trim();
            if (!msg) return;
            inputEl.value = '';
            store.logEvent(msg, 'CHAT');
        };
    }

    window.SandboxEventAndChatLog = { renderEventAndChatLog };
})();
