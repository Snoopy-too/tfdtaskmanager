/**
 * Authoritative Real-Time Multiplayer Synchronization Engine (spec §7).
 *
 * Uses Dual-Transport:
 * 1. Instant cross-tab / same-origin BroadcastChannel (`tfd_sandbox_room_<id>`) for 30Hz drag,
 *    cursor streaming, and spatial pings.
 * 2. Authoritative Server Room Sync (`api.php?action=sync_sandbox_room`) with host/join separation,
 *    seat ownership, and server-side hidden-hand preservation.
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
    let lastBcDragSentAt = 0;
    let lastHttpDragSentAt = 0;
    let dragHttpInFlight = false;
    let pendingCursor = null;

    function init(store, bootstrap) {
        storeRef = store;
        bootstrapRef = bootstrap;
        lastKnownVersion = Number(bootstrap.roomVersion) || 0;

        if (typeof BroadcastChannel !== 'undefined') {
            bc = new BroadcastChannel(`tfd_sandbox_room_${bootstrap.sandboxId}`);
            bc.onmessage = (ev) => handleBroadcastMessage(ev.data);
        }

        // Setup edit-lock heartbeat only while editing Setup/Solo (not in a Live Room)
        lockHeartbeatTimer = setInterval(() => {
            if (!storeRef || storeRef.getUI().isMultiplayerActive) return;
            const fd = new FormData();
            fd.append('action', 'heartbeat_lock_sandbox');
            fd.append('sandbox_id', String(bootstrap.sandboxId));
            fd.append('csrf_token', bootstrap.csrfToken);
            fetch('api.php', { method: 'POST', body: fd }).catch(() => {});
        }, 25000);

        window.addEventListener('beforeunload', () => {
            if (storeRef && storeRef.getUI().isMultiplayerActive) return;
            if (navigator.sendBeacon) {
                const fd = new FormData();
                fd.append('action', 'release_lock_sandbox');
                fd.append('sandbox_id', String(bootstrap.sandboxId));
                fd.append('csrf_token', bootstrap.csrfToken);
                navigator.sendBeacon('api.php', fd);
            }
        });

        if (store.getUI().isMultiplayerActive) {
            startLivePolling();
            syncWithServer({ subAction: 'POLL' });
        }
    }

    function releaseSetupLock() {
        if (!bootstrapRef) return;
        const fd = new FormData();
        fd.append('action', 'release_lock_sandbox');
        fd.append('sandbox_id', String(bootstrapRef.sandboxId));
        fd.append('csrf_token', bootstrapRef.csrfToken);
        fetch('api.php', { method: 'POST', body: fd }).catch(() => {});
    }

    function handleBroadcastMessage(msg) {
        if (!msg || !storeRef || Number(msg.senderId) === Number(bootstrapRef.currentUserId)) return;
        const state = storeRef.getState();
        const ui = storeRef.getUI();
        if (!ui.isMultiplayerActive) return;

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
            storeRef.applyRemoteCore(msg.state);
        }
    }

    function activateJoinedRoom(data) {
        if (!storeRef || !data) return;
        const ui = storeRef.getUI();
        storeRef.clearPendingSaveTimer();
        releaseSetupLock();

        ui.isMultiplayerActive = true;
        ui.liveRoomCode = data.roomCode || ui.liveRoomCode || 'LIVE';
        ui.hostUserId = data.hostUserId ? Number(data.hostUserId) : ui.hostUserId;
        ui.saveStatus = 'Synced to room';
        ui.roomMembers = data.presence && data.presence.members ? Object.values(data.presence.members) : [];
        ui.remoteCursors = data.presence && data.presence.cursors ? data.presence.cursors : {};
        lastKnownVersion = Number(data.version) || 1;

        if (data.assignedSeat) {
            storeRef.setViewerSeat(Number(data.assignedSeat), false);
        }
        if (data.state) {
            storeRef.applyRemoteCore(data.state);
        } else {
            storeRef.notify(true);
        }
        startLivePolling();
    }

    async function fetchRoomStatus() {
        if (!storeRef || !bootstrapRef) return null;
        try {
            const fd = new FormData();
            fd.append('action', 'get_sandbox_room_status');
            fd.append('sandbox_id', String(bootstrapRef.sandboxId));
            fd.append('csrf_token', bootstrapRef.csrfToken);
            const res = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data && data.success) {
                const ui = storeRef.getUI();
                ui.roomStatus = data;
                if (data.active) {
                    ui.liveRoomCode = data.roomCode || ui.liveRoomCode;
                    ui.hostUserId = data.hostUserId ? Number(data.hostUserId) : ui.hostUserId;
                    ui.roomMembers = Array.isArray(data.members) ? data.members : [];
                }
                storeRef.notify(true);
                return data;
            }
        } catch (_) {
            // Ignore transient status fetch failure
        }
        return null;
    }

    async function hostRoom(forceReset = false) {
        if (!storeRef || !bootstrapRef) return null;
        const fd = new FormData();
        fd.append('action', 'host_sandbox_room');
        fd.append('sandbox_id', String(bootstrapRef.sandboxId));
        fd.append('csrf_token', bootstrapRef.csrfToken);
        fd.append('reset', forceReset ? '1' : '0');
        const res = await fetch('api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data && data.success) {
            activateJoinedRoom(data);
            return data;
        }
        return null;
    }

    async function joinRoom() {
        if (!storeRef || !bootstrapRef) return null;
        const fd = new FormData();
        fd.append('action', 'join_sandbox_room');
        fd.append('sandbox_id', String(bootstrapRef.sandboxId));
        fd.append('csrf_token', bootstrapRef.csrfToken);
        const res = await fetch('api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data && data.success) {
            activateJoinedRoom(data);
            return data;
        }
        return data;
    }

    async function hostOrJoinRoom() {
        const status = await fetchRoomStatus();
        if (status && status.active) {
            return joinRoom();
        }
        return hostRoom(false);
    }

    async function leaveRoom() {
        if (!storeRef || !bootstrapRef) return;
        stopLivePolling();
        try {
            const fd = new FormData();
            fd.append('action', 'sync_sandbox_room');
            fd.append('sandbox_id', String(bootstrapRef.sandboxId));
            fd.append('csrf_token', bootstrapRef.csrfToken);
            fd.append('payload', JSON.stringify({ subAction: 'LEAVE_ROOM' }));
            await fetch('api.php', { method: 'POST', body: fd });
        } catch (_) {}
        const ui = storeRef.getUI();
        ui.isMultiplayerActive = false;
        ui.remoteCursors = {};
        ui.remotePings = [];
        ui.roomMembers = [];
        ui.saveStatus = 'Saved';
        storeRef.notify(true);
    }

    async function endRoom() {
        if (!storeRef || !bootstrapRef) return { success: false };
        const fd = new FormData();
        fd.append('action', 'end_sandbox_room');
        fd.append('sandbox_id', String(bootstrapRef.sandboxId));
        fd.append('csrf_token', bootstrapRef.csrfToken);
        const res = await fetch('api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data && data.success) {
            stopLivePolling();
            const ui = storeRef.getUI();
            ui.isMultiplayerActive = false;
            ui.liveRoomCode = null;
            ui.hostUserId = null;
            ui.roomMembers = [];
            ui.remoteCursors = {};
            ui.remotePings = [];
            ui.roomStatus = { active: false };
            ui.saveStatus = 'Saved';
            storeRef.notify(true);
        }
        return data;
    }

    function startLivePolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(() => {
            syncWithServer({ subAction: 'POLL' });
        }, 1200);
    }

    function stopLivePolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    async function syncWithServer(extraPayload, expectedSeq = null) {
        if (!storeRef || !bootstrapRef || !storeRef.getUI().isMultiplayerActive) return { success: false };
        const ui = storeRef.getUI();
        const seqAtStart = expectedSeq !== null ? expectedSeq : storeRef.getLocalMutationSeq();
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

            if (data && data.roomEnded) {
                stopLivePolling();
                ui.isMultiplayerActive = false;
                ui.liveRoomCode = null;
                ui.hostUserId = null;
                ui.roomMembers = [];
                ui.remoteCursors = {};
                ui.roomStatus = { active: false };
                ui.saveStatus = 'Room ended';
                storeRef.notify(true);
                return data;
            }

            if (data && data.success) {
                ui.liveRoomCode = data.roomCode || ui.liveRoomCode;
                if (data.hostUserId) ui.hostUserId = Number(data.hostUserId);
                if (data.presence) {
                    ui.remoteCursors = data.presence.cursors || {};
                    ui.roomMembers = data.presence.members ? Object.values(data.presence.members) : [];
                }

                const remoteVersion = Number(data.version) || 0;
                const noNewLocalEdits = seqAtStart === storeRef.getLocalMutationSeq() && !storeRef.hasPendingLocalSave();

                if (payload.subAction === 'POLL') {
                    if (remoteVersion > lastKnownVersion && data.state && noNewLocalEdits) {
                        lastKnownVersion = remoteVersion;
                        storeRef.applyRemoteCore(data.state);
                    } else if (remoteVersion > lastKnownVersion && !data.state) {
                        lastKnownVersion = remoteVersion;
                    }
                } else {
                    if (remoteVersion > lastKnownVersion) {
                        lastKnownVersion = remoteVersion;
                    }
                    const shouldApplyAckState = (payload.subAction === 'COMMIT_STATE' || payload.subAction === 'CLAIM_SEAT' || payload.subAction === 'RELEASE_SEAT')
                        && data.state
                        && noNewLocalEdits;
                    if (shouldApplyAckState) {
                        storeRef.applyRemoteCore(data.state);
                    } else {
                        storeRef.notify(true);
                    }
                }
                return data;
            }
            return data || { success: false };
        } catch (_) {
            return { success: false };
        }
    }

    function sendLockRequest(objectId) {
        syncWithServer({ subAction: 'LOCK_REQUEST', objectId });
    }

    function throttledSendDrag(objectId, x, y) {
        const now = performance.now();
        if (bc && now - lastBcDragSentAt >= 33) {
            lastBcDragSentAt = now;
            bc.postMessage({
                type: 'OBJECT_DRAG_MOVE',
                senderId: bootstrapRef.currentUserId,
                objectId,
                x,
                y
            });
        }
        if (!dragHttpInFlight && now - lastHttpDragSentAt >= 180) {
            lastHttpDragSentAt = now;
            dragHttpInFlight = true;
            syncWithServer({ subAction: 'OBJECT_DRAG_MOVE', objectId, x, y }).finally(() => {
                dragHttpInFlight = false;
            });
        }
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
        if (!storeRef || !bootstrapRef || !storeRef.getUI().isMultiplayerActive) return;
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

    async function claimSeat(seatIndex) {
        const res = await syncWithServer({ subAction: 'CLAIM_SEAT', seatIndex });
        if (res && res.success) {
            if (seatIndex !== null && seatIndex !== undefined) {
                storeRef.setViewerSeat(seatIndex, false);
            }
        }
        return res;
    }

    async function releaseSeat(seatIndex) {
        return syncWithServer({ subAction: 'RELEASE_SEAT', seatIndex });
    }

    async function broadcastFullState(serializedState, seqAtStart) {
        if (bc) {
            bc.postMessage({
                type: 'FULL_STATE',
                senderId: bootstrapRef.currentUserId,
                state: serializedState
            });
        }
        const res = await syncWithServer({ subAction: 'COMMIT_STATE', state: JSON.stringify(serializedState) }, seqAtStart);
        return Boolean(res && res.success);
    }

    window.SandboxMultiplayer = {
        init,
        fetchRoomStatus,
        hostRoom,
        joinRoom,
        hostOrJoinRoom,
        leaveRoom,
        endRoom,
        sendLockRequest,
        throttledSendDrag,
        sendObjectDrop,
        throttledSendCursor,
        sendPing,
        claimSeat,
        releaseSeat,
        broadcastFullState
    };
})();
