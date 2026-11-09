/**
 * Authoritative Real-Time Multiplayer Synchronization Engine (spec §7).
 *
 * Uses Dual-Transport:
 * 1. Instant cross-tab / same-origin BroadcastChannel (`tfd_sandbox_room_<id>`) for 30Hz drag,
 *    cursor streaming, and spatial pings.
 * 2. Authoritative Server Room Sync (`api.php?action=sync_sandbox_room`) with soft-locking,
 *    seat claiming, and server-side hidden-hand redaction.
 */
(function () {
    'use strict';

    let storeRef = null;
    let bootstrapRef = null;
    let bc = null;
    let pollTimer = null;
    let lockHeartbeatTimer = null;
    let lastKnownVersion = 0;
    let lastCursorSentAt = 0;
    let lastDragSentAt = 0;
    let pendingCursor = null;

    function init(store, bootstrap) {
        storeRef = store;
        bootstrapRef = bootstrap;

        if (typeof BroadcastChannel !== 'undefined') {
            bc = new BroadcastChannel(`tfd_sandbox_room_${bootstrap.sandboxId}`);
            bc.onmessage = (ev) => handleBroadcastMessage(ev.data);
        }

        // Edit-lock heartbeat every 25s
        lockHeartbeatTimer = setInterval(() => {
            const fd = new FormData();
            fd.append('action', 'heartbeat_lock_sandbox');
            fd.append('sandbox_id', String(bootstrap.sandboxId));
            fd.append('csrf_token', bootstrap.csrfToken);
            fetch('api.php', { method: 'POST', body: fd }).catch(() => {});
        }, 25000);

        window.addEventListener('beforeunload', () => {
            if (navigator.sendBeacon) {
                const fd = new FormData();
                fd.append('action', 'release_lock_sandbox');
                fd.append('sandbox_id', String(bootstrap.sandboxId));
                fd.append('csrf_token', bootstrap.csrfToken);
                navigator.sendBeacon('api.php', fd);
            }
        });

        if (bootstrap.autoHost || store.getUI().isMultiplayerActive) {
            startLivePolling();
        }
    }

    function handleBroadcastMessage(msg) {
        if (!msg || !storeRef || Number(msg.senderId) === Number(bootstrapRef.currentUserId)) return;
        const state = storeRef.getState();
        const ui = storeRef.getUI();

        if (msg.type === 'CURSOR') {
            ui.remoteCursors[String(msg.senderId)] = msg.cursor;
            storeRef.notify(true);
        } else if (msg.type === 'PING') {
            ui.remotePings.push(msg.ping);
            window.SandboxSchema.playSound('ping');
            storeRef.notify(true);
            setTimeout(() => {
                ui.remotePings = ui.remotePings.filter((p) => p.id !== msg.ping.id);
                storeRef.notify(true);
            }, 1600);
        } else if (msg.type === 'OBJECT_DRAG_MOVE') {
            const o = state.boardObjects[msg.objectId];
            if (o) {
                o.x = msg.x;
                o.y = msg.y;
                o.lockedByUserId = msg.senderId;
                if (o.type === 'CARD') {
                    window.SandboxTuckingEngine.syncChildrenPositions(state.boardObjects, o.id);
                }
                storeRef.notify(true);
            }
        } else if (msg.type === 'OBJECT_DROP') {
            const o = state.boardObjects[msg.objectId];
            if (o) {
                o.x = msg.x;
                o.y = msg.y;
                o.lockedByUserId = null;
                if (o.type === 'CARD') {
                    window.SandboxTuckingEngine.syncChildrenPositions(state.boardObjects, o.id);
                }
                storeRef.notify(true);
            }
        } else if (msg.type === 'FULL_STATE' && msg.state) {
            applyRemoteState(msg.state);
        }
    }

    function applyRemoteState(remoteState) {
        if (!storeRef || !remoteState) return;
        const state = storeRef.getState();
        state.mode = remoteState.mode || state.mode;
        state.turnState = remoteState.turnState || state.turnState;
        state.seats = remoteState.seats || state.seats;
        state.boardObjects = remoteState.boardObjects || state.boardObjects;
        state.zones = remoteState.zones || state.zones;
        state.notes = remoteState.notes || state.notes;
        if (Array.isArray(remoteState.eventLog)) state.eventLog = remoteState.eventLog;
        storeRef.notify(true);
    }

    async function hostOrJoinRoom() {
        if (!storeRef || !bootstrapRef) return null;
        const fd = new FormData();
        fd.append('action', 'host_sandbox_room');
        fd.append('sandbox_id', String(bootstrapRef.sandboxId));
        fd.append('csrf_token', bootstrapRef.csrfToken);
        const res = await fetch('api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data && data.success) {
            const ui = storeRef.getUI();
            ui.isMultiplayerActive = true;
            ui.liveRoomCode = data.roomCode || 'LIVE';
            lastKnownVersion = Number(data.version) || 1;
            startLivePolling();
            storeRef.logEvent(`Live Playtest Room active (Code: ${ui.liveRoomCode}).`);
            return data;
        }
        return null;
    }

    function startLivePolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(() => {
            syncWithServer({ subAction: 'POLL' });
        }, 1200);
    }

    async function syncWithServer(extraPayload) {
        if (!storeRef || !bootstrapRef || !storeRef.getUI().isMultiplayerActive) return;
        const ui = storeRef.getUI();
        const payload = Object.assign({
            subAction: 'POLL',
            viewerSeat: ui.activeViewerSeat,
            godMode: ui.godMode,
            cursor: pendingCursor
        }, extraPayload || {});
        pendingCursor = null;

        try {
            const fd = new FormData();
            fd.append('action', 'sync_sandbox_room');
            fd.append('sandbox_id', String(bootstrapRef.sandboxId));
            fd.append('csrf_token', bootstrapRef.csrfToken);
            fd.append('payload', JSON.stringify(payload));
            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data && data.success) {
                ui.liveRoomCode = data.roomCode || ui.liveRoomCode;
                if (data.presence) {
                    ui.remoteCursors = data.presence.cursors || {};
                }
                if (Number(data.version) > lastKnownVersion && data.state && payload.subAction === 'POLL') {
                    lastKnownVersion = Number(data.version);
                    applyRemoteState(data.state);
                } else if (Number(data.version) > lastKnownVersion) {
                    lastKnownVersion = Number(data.version);
                }
                storeRef.notify(true);
            }
        } catch (_) {
            // Transient network error
        }
    }

    function sendLockRequest(objectId) {
        syncWithServer({ subAction: 'LOCK_REQUEST', objectId });
    }

    function throttledSendDrag(objectId, x, y) {
        const now = performance.now();
        if (now - lastDragSentAt < 33) return; // ~30Hz throttle
        lastDragSentAt = now;
        if (bc) {
            bc.postMessage({
                type: 'OBJECT_DRAG_MOVE',
                senderId: bootstrapRef.currentUserId,
                objectId,
                x,
                y
            });
        }
        syncWithServer({ subAction: 'OBJECT_DRAG_MOVE', objectId, x, y });
    }

    function sendObjectDrop(objectId, x, y) {
        if (bc) {
            bc.postMessage({
                type: 'OBJECT_DROP',
                senderId: bootstrapRef.currentUserId,
                objectId,
                x,
                y
            });
        }
        syncWithServer({ subAction: 'OBJECT_DROP', objectId, x, y });
    }

    function throttledSendCursor(x, y) {
        if (!storeRef || !bootstrapRef) return;
        const now = performance.now();
        if (now - lastCursorSentAt < 50) return;
        lastCursorSentAt = now;
        const state = storeRef.getState();
        const ui = storeRef.getUI();
        const seat = state.seats.find((s) => s.seatIndex === ui.activeViewerSeat) || state.seats[0];
        const cursor = {
            userId: bootstrapRef.currentUserId,
            name: bootstrapRef.currentUserName,
            color: seat ? seat.color : '#38BDF8',
            x: Math.round(x),
            y: Math.round(y)
        };
        pendingCursor = cursor;
        if (bc) {
            bc.postMessage({ type: 'CURSOR', senderId: bootstrapRef.currentUserId, cursor });
        }
    }

    function sendPing(ping) {
        if (bc) {
            bc.postMessage({ type: 'PING', senderId: bootstrapRef.currentUserId, ping });
        }
        syncWithServer({ subAction: 'PING', ping });
    }

    function claimSeat(seatIndex) {
        syncWithServer({ subAction: 'CLAIM_SEAT', seatIndex });
        storeRef.setViewerSeat(seatIndex, false);
    }

    function broadcastFullState(serializedState) {
        if (bc) {
            bc.postMessage({
                type: 'FULL_STATE',
                senderId: bootstrapRef.currentUserId,
                state: serializedState
            });
        }
        syncWithServer({ subAction: 'COMMIT_STATE', state: JSON.stringify(serializedState) });
    }

    window.SandboxMultiplayer = {
        init,
        hostOrJoinRoom,
        sendLockRequest,
        throttledSendDrag,
        sendObjectDrop,
        throttledSendCursor,
        sendPing,
        claimSeat,
        broadcastFullState
    };
})();
