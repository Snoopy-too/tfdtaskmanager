# Board Game Studio: 2D Playtest Sandbox Specification & Implementation Plan

## 1. Executive Summary & Architectural Philosophy
This document defines the architecture, data models, user workflows, and multiplayer synchronization protocol for the **Playtest Sandbox** module inside **Board Game Studio**.

### Core Design Tenets
* **Zero-Physics 2D Canvas:** No 3D physics engines or rigid-body collisions. All components operate on a deterministic 2D coordinate plane (`x, y, zIndex, rotation`) with crisp state transitions and audio feedback.
* **Two-Mode Architecture:** Strict separation between **Setup Mode (Staging)**—where the designer imports project assets, configures decks, places snap points, and saves the initial table state—and **Play Mode (Testing)**—where solo or multiplayer testers manipulate components under tabletop rules.
* **HUD-Driven Resource Management:** Physical tokens and meeples are used strictly for spatial board presence. Currency, Victory Points, and trackable stats use an integrated **Player Resource HUD** with instant bank/player transfers and automated logging.
* **Authoritative Real-Time Multiplayer:** Supports 1–5 simultaneous logged-in users (plus spectators) via WebSocket action dispatching, soft-locking dragged objects, server-side hand redaction, and ghost cursors.
* **Modular Code Governance:** Keep individual source files modular and strictly under 500 lines per file to ensure clean agentic maintenance.

---

## 2. Navigation & Entry Flow
1. **Project Context:** User opens **Board Game Studio** and selects an existing Game Project.
2. **Left Sidebar Navigation:** User clicks **"Sandbox"** in the project sidebar menu.
3. **Sandbox Manager View:**
   * Displays a list of existing sandboxes for the project (showing Name, Last Modified, Player Count, and an option to **Edit Setup** or **Host Live Session**).
   * Clicking **"+ New Sandbox"** opens a modal to input the `sandboxName` and `defaultPlayerCount` (1–6), then clicks **"Create"**.
4. **Canvas Initialization:** Enters the newly created Sandbox in **Setup Mode** with an empty 2D infinite pan/zoom viewport and the **Project Asset Importer** accessible in the sidebar.

---

## 3. Categorical Z-Index Layering System
To guarantee tokens never slip under game boards and dragged items always render cleanly on top, enforce strict Z-Index buckets:

| Tier | Z-Index Range | Component Types | Behavior |
| :--- | :--- | :--- | :--- |
| **Tier 0: Table Surface** | `0` | Background Canvas Grid | Pan and zoom base layer |
| **Tier 1: Boards & Mats** | `100 - 199` | Main Gameboards, Player Mats | Usually locked (`isLocked: true`) during Play Mode |
| **Tier 2: Zones & Trays** | `200 - 299` | Market Rivers, Discard Trays, Snap Zones | Interactive drop targets |
| **Tier 3: Cards & Decks** | `300 - 499` | Decks, Loose Cards, Tucked Card Stacks | Dynamic stacking order within range |
| **Tier 4: Tokens & Meeples**| `500 - 699` | Meeples, Chits, Spawner Bags, Dice | Always sit visually on top of cards and boards |
| **Tier 5: Active Drag / UI**| `900 - 999` | Currently Dragged Component, Ghost Cursors, Pings | Highest priority on canvas |

---

## 4. Data Schemas (JSON Specifications)

### 4.1 Project Asset Import Schema
The Sandbox ingests existing assets directly from the parent Board Game Studio project:

```json
{
  "projectId": "proj_001",
  "importedAssets": {
    "boards": [
      { "assetId": "b_01", "name": "Main Map", "imageUrl": "/assets/main_map.webp", "width": 1600, "height": 1200 }
    ],
    "cardBatches": [
      {
        "batchId": "cb_01",
        "name": "Main Building Deck",
        "backImageUrl": "/assets/card_back.webp",
        "width": 140,
        "height": 200,
        "cards": [
          { "cardAssetId": "c_001", "name": "Telegraph Office", "frontImageUrl": "/assets/c_001.webp", "metadata": { "type": "civic", "cost": 3 } }
        ]
      }
    ],
    "tokens": [
      { "assetId": "t_01", "name": "Worker Meeple", "imageUrl": "/assets/meeple_red.webp", "width": 48, "height": 48 }
    ]
  }
}
```

### 4.2 Sandbox Table State & Snapshot Schema
This state represents both the **Default Starting Snapshot** and any saved mid-game **Scenario Snapshot**:

```json
{
  "sandboxId": "sbx_901",
  "projectId": "proj_001",
  "name": "Standard 4-Player Balance Test",
  "mode": "SETUP",
  "turnState": {
    "activeSeat": 1,
    "roundNumber": 1,
    "currentPhase": "Action Phase",
    "phases": ["Draw", "Action Phase", "Market Buy", "Cleanup"]
  },
  "seats": [
    {
      "seatIndex": 1,
      "color": "#E53935",
      "assignedUserId": "user_abc",
      "playerName": "Player 1",
      "handCardIds": ["inst_card_104", "inst_card_109"],
      "resources": { "Money": 10, "VP": 0, "Population": 2, "Happiness": 1 }
    }
  ],
  "boardObjects": {
    "inst_board_1": {
      "id": "inst_board_1",
      "type": "BOARD",
      "assetId": "b_01",
      "x": 0,
      "y": 0,
      "zIndex": 100,
      "rotation": 0,
      "isLocked": true,
      "snapGrid": { "enabled": true, "type": "CARTESIAN", "sizeX": 80, "sizeY": 80 },
      "snapPoints": [
        { "id": "sp_1", "x": 240, "y": 320, "radius": 40, "acceptedTypes": ["CARD", "TOKEN"] }
      ]
    },
    "inst_deck_1": {
      "id": "inst_deck_1",
      "type": "DECK",
      "name": "Civic Deck",
      "x": 450,
      "y": 120,
      "zIndex": 310,
      "isLocked": false,
      "faceUp": false,
      "backImageUrl": "/assets/card_back.webp",
      "cardIds": ["inst_card_101", "inst_card_102", "inst_card_103"],
      "autoShuffleOnReset": true,
      "linkedDiscardId": "inst_discard_1"
    },
    "inst_card_100": {
      "id": "inst_card_100",
      "type": "CARD",
      "cardAssetId": "c_001",
      "x": 650,
      "y": 400,
      "zIndex": 350,
      "rotation": 0,
      "isTapped": false,
      "faceUp": true,
      "ownerSeat": null,
      "parentId": null,
      "tuckedChildren": [
        { "cardId": "inst_card_105", "offsetX": 0, "offsetY": -35, "zIndexOffset": -1 }
      ],
      "lockedByUserId": null
    },
    "inst_spawner_1": {
      "id": "inst_spawner_1",
      "type": "SPAWNER",
      "assetId": "t_01",
      "name": "Worker Supply",
      "x": -200,
      "y": 100,
      "zIndex": 500,
      "infinite": true
    }
  },
  "zones": [
    {
      "id": "zone_river_1",
      "type": "MARKET_RIVER",
      "x": 600,
      "y": 120,
      "width": 640,
      "height": 220,
      "sourceDeckId": "inst_deck_1",
      "slots": 4,
      "autoReplenish": true
    }
  ],
  "notes": [
    { "id": "note_1", "x": 660, "y": 390, "author": "Player 1", "text": "Verify if cost reduction stacks here." }
  ]
}
```

---

## 5. Core Functional Modules & Mechanics

### 5.1 Setup Mode (Staging Workflow)
1. **Component Drawer:** Collapsible bottom/right panel showing imported project assets grouped into *Boards*, *Cards/Decks*, *Tokens/Meeples*, and *Utility Zones*.
2. **Board Anchoring & Snap Editor:**
   * Dragging a board onto the canvas allows scaling and positioning.
   * Pressing `L` or clicking **Lock** pins the board so it ignores drag events during play.
   * **Snap Point Tool:** Allows clicking anywhere on a locked board to drop magnetic snap nodes (with configurable snap radius and accepted component types).
3. **Deck Assembly & Market Linking:**
   * Marquee-selecting loose cards -> Right-Click -> **"Form Deck"**.
   * Inspector allows setting the card back, initial shuffle rule, and linking a **Discard Tray** or **Market River Zone** (which automatically slides a replacement card from the deck when a slot empties).
4. **Seating & Resource Seeding:**
   * Configure 1–6 player seats and define custom resource keys (e.g., `Yen`, `Kaika Points`, `Population`, `Happiness`) with starting quantities.
5. **Freeze Starting Snapshot:**
   * Clicking **"Save as Default Starting State"** serializes the entire layout into `startingSnapshotJSON`. Clicking **"Reset to Start"** at any point during testing restores this exact state.

### 5.2 Card, Deck, and Tucking Mechanics
* **Deck Actions:**
  * *Draw $N$:* Click or use hotkeys `1`–`9` while hovering over a deck to draw $N$ cards directly to the active player's hand.
  * *Shuffle:* Fisher-Yates shuffle with audio cue and event log broadcast.
  * *Search / Peek:* View top $X$ cards or open a searchable modal of the deck (logs `"Player X is searching Deck Y"` to the table).
* **Card Manipulation:**
  * *Flip (`F`):* Toggles `faceUp` state for all players.
  * *Peek (`Alt + Hover` or Right-Click -> Peek):* Privately reveals a face-down table card to the inspecting player only; renders a "Peeking" eye icon on the card for other players.
  * *Tap / Exhaust (`T` or Double-Click):* Rotates card $90^\circ$ clockwise (`isTapped: true`). Includes a **"Ready All My Cards"** button on the player HUD.
* **Parent-Child Card Tucking & Stacking:**
  * Dragging Card B onto the top/bottom edge of Card A displays a highlight drop-zone: **"Tuck / Attach"**.
  * Dropping binds `CardB.parentId = CardA.id` and appends Card B to `CardA.tuckedChildren` with a configurable pixel offset (e.g., `offsetY: 35px` so the header or bottom icon of the tucked card remains visible).
  * Moving the parent card transforms all attached child cards in unison. Dragging a child card out detaches it automatically.

### 5.3 Tokens, Dice, and Virtual Player HUD
* **Infinite Spawners:** Token supplies behave as infinite bags by default; dragging out spawns a new token instance, and dragging a token back onto the bag deletes it.
* **Instant 2D Dice Roller:** Clicking a die (d4, d6, d8, d10, d12, d20, or custom-face die) plays a crisp roll sound effect, displays a brief 300ms shake animation, updates the top face, and prints the authoritative roll to the Event Log.
* **Player Resource HUD:**
  * Docked bar showing all players' public trackers (`+1`, `-1`, `+5`, `-5` buttons and direct numeric input).
  * **Quick Transaction Menu:** Pay $X$ resource to Bank or Transfer $X$ to another seat, automatically appending `"Player 1 paid 4 Yen to Bank"` to the log.

### 5.4 Designer & Playtesting Meta-Tools
* **Solo Seat-Switcher:** In single-player testing, a top toolbar lets the designer switch between `Seat 1`, `Seat 2`, `Seat 3`, `Seat 4`, or `Omniscient God Mode` in a single click, dynamically swapping the bottom Hand Tray.
* **5-Step Undo / Redo Stack:** Stores the last 5 state mutations prior to each action so misclicks or accidental deck drops can be reversed immediately.
* **Sticky Note Pins:** Right-clicking any card or board coordinate drops a yellow playtest feedback pin that saves straight to the project's feedback log.
* **Mid-Game Scenario Save/Load:** Export any mid-game board state to a named scenario slot (e.g., *"Round 6 Endgame Engine Test"*) to reload later.

---

## 6. Keyboard & Mouse UX Map

| Input / Shortcut | Context | Action |
| :--- | :--- | :--- |
| **Middle-Click Drag / `Space` + Drag** | Canvas | Pan camera viewport |
| **Mouse Wheel** | Canvas | Zoom camera towards cursor |
| **Left-Click Drag** | Unlocked Component | Move component (acquires multiplayer soft-lock) |
| **Left-Click Drag** | Empty Canvas | Box / Marquee multi-select |
| **`F`** | Hovered / Selected Card(s) | Flip face-up / face-down |
| **`T` or Double-Click** | Hovered / Selected Card(s) | Tap / Untap ($90^\circ$ toggle) |
| **`R` / `Shift + R`** | Hovered Component | Rotate $45^\circ$ or $90^\circ$ clockwise / counter-clockwise |
| **`S`** | Hovered Deck | Shuffle deck |
| **`1` – `9`** | Hovered Deck | Draw $1$ to $9$ cards into active player's hand |
| **`G` (Hold) + Left-Click** | Anywhere on Canvas | Trigger animated colored Ping circle + sound for all players |
| **`L`** | Selected Object (Setup Mode) | Toggle Lock/Unlock component to table |
| **`Alt` (Hold)** | Hovered Card | High-resolution zoom preview overlay (and private peek if permitted) |
| **`Ctrl + Z`** | Global | Undo last state action |

---

## 7. Real-Time Multiplayer Architecture (4–5+ Players)

### 7.1 Session Lifecycle
1. **Host Live Session:** Designer clicks **"Host Playtest"** on a saved Sandbox. Server creates an active room instance in memory initialized from the Sandbox's `startingSnapshotJSON`.
2. **Lobby & Seat Claiming:** Logged-in Board Game Studio users join via Room URL or active project room list and select a Seat (`Seat 1`–`Seat 5`) or `Spectator`.
3. **State Authority:** The server (or Host node via WebSocket/Supabase Realtime broadcast + authoritative room state) processes discrete action messages and broadcasts deltas.

### 7.2 Soft-Locking & Concurrency Control
* On `pointerdown` over a card or token, client sends `LOCK_REQUEST { objectId, userId }`.
* Server sets `lockedByUserId = userId` and broadcasts lock. Other clients render a colored outline matching that player's seat color and block local drag interaction on that item.
* Position coordinates stream via `OBJECT_DRAG_MOVE` (throttled to ~30Hz with client-side linear interpolation).
* On `pointerup`, client sends `OBJECT_DROP { objectId, x, y, parentId, zoneId }`, releasing the lock and committing the state.

### 7.3 Server-Side Hidden Information Filtering
* Cards residing inside `seat.handCardIds` or face-down inside a `DECK` have their `frontImageUrl`, `name`, and `metadata` stripped from network payloads sent to non-owning clients:
  ```json
  { "id": "inst_card_104", "zone": "HAND", "ownerSeat": 1, "faceUp": false, "frontImageUrl": null }
  ```
* Full card face data is transmitted to a client **only** when:
  1. The card moves into that client's own hand.
  2. The card is flipped `faceUp: true` on the public board.
  3. The client triggers an explicit `PEEK_CARD` action (which logs `"Player 2 peeked at a card"` in the public event log).

---

## 8. Recommended Module Directory Structure
Ensure every file remains modular, focused, and under the 500-line threshold:

```text
src/modules/sandbox/
├── SandboxManagerView.jsx       # Project sidebar entry: list, create, or join sandboxes
├── SandboxWorkspace.jsx         # Top-level container managing Setup vs Play mode & WebSocket provider
├── canvas/
│   ├── SandboxCanvas.jsx        # 2D pan/zoom viewport, coordinate transform, and marquee selection
│   ├── BoardLayer.jsx           # Renders locked boards, snap grids, and visual snap node overlays
│   ├── CardComponent.jsx        # Card rendering, flip/tap animations, and parent-child tuck slots
│   ├── DeckComponent.jsx        # Stack rendering, count badge, draw/shuffle context actions
│   ├── TokenSpawner.jsx         # Meeples, infinite supply bags, and 2D instant dice rollers
│   └── GhostCursorsOverlay.jsx  # Remote player cursors and animated spatial pings
├── hud/
│   ├── TopControlBar.jsx        # Mode switcher, Turn/Phase tracker, Undo, Save/Reset Snapshot
│   ├── ComponentDrawer.jsx      # Setup mode drawer for dragging imported project assets to table
│   ├── PlayerHandTray.jsx       # Bottom private hand dock + Solo Seat Switcher
│   ├── ResourceDashboard.jsx    # Virtual currency/VP spinners and Bank/Player transfer controls
│   └── EventAndChatLog.jsx      # Authoritative action feed, dice outcomes, and playtester chat
├── state/
│   ├── sandboxStore.js          # Client state store (Zustand/Redux) + 5-step undo history ring
│   ├── tuckingEngine.js         # Coordinate math for parent-child card attachments and offsets
│   ├── snapEngine.js            # Radial and Cartesian grid snapping calculations
│   └── multiplayerSync.js       # WebSocket action dispatcher, soft-lock manager, and state reconciliation
└── schemas/
    └── sandboxSchema.js         # JSON validation and snapshot serializer/deserializer
```

---

## 9. Phased Implementation Plan for Antigravity Agent

### Phase 1: Sandbox Manager & 2D Viewport Foundation
* Add the `"Sandbox"` tab to the Board Game Studio project navigation.
* Build `SandboxManagerView` to create, list, and open project sandboxes.
* Implement `SandboxCanvas` with smooth 2D pan, cursor-centered zoom, categorical Z-Index sorting, and component locking (`L` key).

### Phase 2: Project Asset Importer & Setup Mode Staging
* Connect `ComponentDrawer` to fetch existing Boards, Card Batches, and Tokens from the active Board Game Studio project.
* Implement drag-to-spawn onto the canvas, snap grid toggles, custom radial snap points, and the **"Save as Default Starting State"** / **"Reset to Start"** snapshot serialization.

### Phase 3: Card, Deck, and Parent-Child Tucking Mechanics
* Implement `DeckComponent` (Fisher-Yates shuffle, Draw $1$–$9$, Search modal, Discard tray linking, and Auto-Replenish Market River zones).
* Implement `CardComponent` (`F` to flip, `T` to tap, `Alt` to zoom preview, and `tuckingEngine.js` for parent-child card attachments).

### Phase 4: Player Hand Trays, Resource HUD, and Meta Utilities
* Build `PlayerHandTray` with private card masking and the **Solo Seat-Switcher** (`Seat 1–5` + `God Mode`).
* Build `ResourceDashboard` for non-spatial counters (Money, VP, custom stats) and instant 2D Dice Rollers connected to `EventAndChatLog`.
* Add the 5-step Undo stack, on-canvas Sticky Note pins, and mid-game Scenario Save/Load.

### Phase 5: Real-Time Multiplayer Synchronization
* Integrate `multiplayerSync.js` for live room hosting, seat claiming, soft-locking dragged components, server-side hand redaction, remote ghost cursors, and spatial `G`-key pings.