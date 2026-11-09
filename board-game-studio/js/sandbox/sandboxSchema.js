/**
 * Sandbox Schema, Categorical Z-Index Tiers, DOM Sanitization & WebAudio SFX (spec §3, §4).
 */
(function () {
    'use strict';

    const Z_TIERS = {
        BOARD: { min: 100, max: 199 },
        ZONE: { min: 200, max: 299 },
        DECK: { min: 300, max: 499 },
        CARD: { min: 300, max: 499 },
        TOKEN: { min: 500, max: 699 },
        SPAWNER: { min: 500, max: 699 },
        DIE: { min: 500, max: 699 },
        DRAG: { min: 900, max: 999 }
    };

    const SEAT_COLORS = ['#E53935', '#1E88E5', '#43A047', '#FDD835', '#8E24AA', '#FB8C00'];

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function clampZ(type, z) {
        const tier = Z_TIERS[type] || Z_TIERS.TOKEN;
        const n = Number.isFinite(Number(z)) ? Math.round(Number(z)) : tier.min;
        return Math.max(tier.min, Math.min(tier.max, n));
    }

    function nextZInTier(boardObjects, type) {
        const tier = Z_TIERS[type] || Z_TIERS.TOKEN;
        let maxFound = tier.min - 1;
        Object.values(boardObjects || {}).forEach((obj) => {
            if (!obj) return;
            const t = Z_TIERS[obj.type];
            if (t && t.min === tier.min && Number.isFinite(obj.zIndex)) {
                if (obj.zIndex > maxFound) maxFound = obj.zIndex;
            }
        });
        return Math.min(tier.max, Math.max(tier.min, maxFound + 1));
    }

    function uid(prefix) {
        const rand = Math.random().toString(36).slice(2, 8);
        return `${prefix}_${Date.now().toString(36).slice(-4)}${rand}`;
    }

    function cloneDeep(obj) {
        return JSON.parse(JSON.stringify(obj));
    }

    function serializeForSave(state) {
        const copy = cloneDeep(state);
        return {
            mode: copy.mode === 'PLAY' ? 'PLAY' : 'SETUP',
            turnState: copy.turnState || {
                activeSeat: 1,
                roundNumber: 1,
                currentPhase: 'Draw',
                phases: ['Draw', 'Action Phase', 'Cleanup']
            },
            seats: Array.isArray(copy.seats) ? copy.seats : [],
            boardObjects: copy.boardObjects || {},
            zones: Array.isArray(copy.zones) ? copy.zones : [],
            notes: Array.isArray(copy.notes) ? copy.notes : [],
            scenarios: Array.isArray(copy.scenarios) ? copy.scenarios.slice(0, 20) : [],
            eventLog: Array.isArray(copy.eventLog) ? copy.eventLog.slice(-150) : []
        };
    }

    // Crisp WebAudio synthesizer for tabletop cues (shuffle, flip, tap, roll, ping)
    let audioCtx = null;
    function getAudio() {
        if (!audioCtx && (window.AudioContext || window.webkitAudioContext)) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            audioCtx = new Ctx();
        }
        return audioCtx;
    }

    function playSound(kind) {
        try {
            const ctx = getAudio();
            if (!ctx) return;
            if (ctx.state === 'suspended') ctx.resume();
            const now = ctx.currentTime;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            if (kind === 'flip' || kind === 'tap') {
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(kind === 'flip' ? 420 : 310, now);
                osc.frequency.exponentialRampToValueAtTime(680, now + 0.06);
                gain.gain.setValueAtTime(0.08, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.07);
                osc.start(now);
                osc.stop(now + 0.075);
            } else if (kind === 'shuffle') {
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(220, now);
                osc.frequency.linearRampToValueAtTime(520, now + 0.14);
                gain.gain.setValueAtTime(0.07, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.16);
                osc.start(now);
                osc.stop(now + 0.17);
            } else if (kind === 'roll') {
                osc.type = 'square';
                osc.frequency.setValueAtTime(340, now);
                osc.frequency.setValueAtTime(510, now + 0.08);
                osc.frequency.setValueAtTime(640, now + 0.16);
                gain.gain.setValueAtTime(0.07, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
                osc.start(now);
                osc.stop(now + 0.26);
            } else if (kind === 'ping') {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(740, now);
                osc.frequency.exponentialRampToValueAtTime(980, now + 0.22);
                gain.gain.setValueAtTime(0.11, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
                osc.start(now);
                osc.stop(now + 0.26);
            }
        } catch (_) {
            // Audio optional
        }
    }

    window.SandboxSchema = {
        Z_TIERS,
        SEAT_COLORS,
        escapeHtml,
        clampZ,
        nextZInTier,
        uid,
        cloneDeep,
        serializeForSave,
        playSound
    };
})();
