# Product Requirements Document (PRD)
## App Name: Board Game Design Studio (Module of task manager Suite)
**Version:** 1.1

---

## 1. Product Overview
**Objective:** Provide a web-based, highly iterative design environment where board game creators can layout physical components (cards, boards, punchboards), manage global graphic assets, and generate print-ready or digital-playtest files.
**Context:** This application operates as a sub-module within a larger task manager suite. It acts as a specialized tool accessed directly from the parent application's interface.
**Target Audience:** Indie board game designers, hobbyists, and small-scale publishers currently utilizing the task manager suite.

## 2. System Architecture & Integration
Because this app is part of a larger ecosystem, its architecture is tightly coupled with the parent task manager app.

### 2.1 Routing & Deployment
*   **Subdirectory Hosting:** The app will reside in a subdirectory of the parent app (e.g., `https://[parent-app-domain].com/board-game-studio/`).
*   **Web Server Configuration:** The Nginx/Apache configuration must route requests for this subdirectory to the specific PHP/JS logic of the board game module while maintaining the root domain's SSL and security policies.

### 2.2 Authentication & Authorization (SSO)
*   **Shared Session State:** Users will not log in directly to the board game app. Authentication is handled entirely by the parent task manager.
*   **Access Flow:** A user logged into the task manager clicks a "Board Game Studio" button. The app verifies the active PHP session or auth token (e.g., JWT) established by the parent app. If valid, access is granted.
*   **Permissioning:** The app will respect any Role-Based Access Control (RBAC) established in the parent app (e.g., 'View Only' vs. 'Editor' rights on a project).

### 2.3 Shared Database Infrastructure
*   **Unified Database:** This module will utilize the *existing* MySQL or PostgreSQL database of the task manager app.
*   **Foreign Key Dependencies:** The board game module's tables will map directly to the parent app's existing `users` and `projects` tables.
*   **Encoding:** The database must ensure `utf8mb4` character encoding is maintained to support multi-byte characters, ensuring flawless localization for text layers (e.g., if you or a user needs to translate card text into Japanese).

---

## 3. Core Features (MVP)

### 3.1 Project & Asset Management
*   **Parent Project Linkage:** Workspaces are defined by the parent task manager. Opening the Board Game Studio from a specific project automatically loads that project's context.
*   **Project Asset Library:** A centralized repository within the project where users upload images (PNG, JPG, SVG) and custom fonts (TTF, OTF).
*   **Tagging System:** Assets can be tagged (e.g., `[icon_health]`) for dynamic reference in text boxes.

### 3.2 The Visual Canvas (JavaScript Editor)
*   **Component Types:** Templates for Poker Cards, Tarot Cards, Boards, and Custom Punchboards.
*   **Layers:** A Photoshop-style layer system (Text, Image, Shape, Drop Zone).
*   **Drop Zones / Grids:** Designers can define snap-to grids on boards.
*   **Bleed & Safe Zones:** Toggleable visual guides showing the physical cut line, safe text zone, and bleed edge for print manufacturing.

### 3.3 Data-Driven Templating Engine
*   **Dataset Import:** Users can upload a CSV file or paste tabular data directly.
*   **Variable Binding:** Text boxes on the canvas accept variables like `{{Attack}}`. The app parses these and binds them to the dataset.
*   **Live Preview:** Real-time canvas updates as the user pages through rows in their dataset.

### 3.4 Export Engine
*   **Print-and-Play (PDF):** A backend process generating A4/Letter pages with auto-generated crop marks, utilizing the HTML5 canvas data.
*   **Digital Playtest:** Export a single sprite sheet and the associated JSON file required for environments like Tabletop Simulator.

---

## 4. Database Architecture Addendum

Since the app shares a database with the task manager, new tables will be prefixed (e.g., `bg_`) to prevent collisions, while relying on the existing `users` and `projects` tables.

### Proposed Table Structure (Integration)
*   **`users`** *(Existing table from parent app)*
*   **`projects`** *(Existing table from parent app)*
*   **`bg_assets`**: `id`, `project_id` (FK to projects), `file_url`, `tag`
*   **`bg_datasets`**: `id`, `project_id` (FK to projects), `csv_data` (JSON format recommended for dynamic card rows)
*   **`bg_templates`**: `id`, `project_id` (FK to projects), `type_id` (e.g., card, board), `name`
*   **`bg_template_layers`**: `id`, `template_id` (FK to bg_templates), `z_index`, `x_pos`, `y_pos`, `width`, `height`, `content_type`

---

## 5. Out of Scope for MVP (V2 Roadmap)
*   3D rendering of custom dice faces.
*   Multi-page rulebook layout editor.

