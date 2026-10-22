# Agent Directives: TFD Task Manager & Board Game Studio - Clean Architecture Governance & PHP 8.2.35 Compliance

> **Target Project:** `C:\xampp\htdocs\tfdtaskmanager`  
> **Runtime Environment:** PHP 8.2.35 (ZTS Visual C++ 2019 x64) / Apache 2.4 / MySQL (XAMPP Windows)  
> **Architecture Status:** Advanced Clean Architecture in `src/`; Multiple root presentation controllers  
> **Key Subsystems:** Core Task Manager, Meeting Minutes, Board Game Studio (Assets, Dielines, Datasets, Rulebooks), Single Sign-On (SSO)  
> **Lead Architect Directive:** Security-First Principal Engineer Governance  

---

## 1. Executive Summary & Current State Audit

The server's PHP installation in XAMPP has been updated to **PHP 8.2.35**.

`tfdtaskmanager` is a mission-critical platform combining task management, meeting logging, and a full Board Game Design Studio. It features a robust Clean (Hexagonal) Architecture in `src/`:
* `src/Domain/Entities/`: 15+ rich domain models (`Task`, `Project`, `User`, `Meeting`, `BgAsset`, `BgDataset`, `BgRulebook`, `BgTemplate`, etc.).
* `src/Domain/Repositories/`: Repository interfaces defining domain persistence contracts.
* `src/Application/Services/`: Comprehensive application services (`TaskService`, `ProjectService`, `UserService`, `MeetingService`, `BgAssetService`, `BgExportService`, etc.).
* `src/Infrastructure/`: `PDO*Repository` classes, `Connection`, `SecurityHelper`, `SSOHelper`.
* `src/Container/`: Full Dependency Injection container (`DIContainer`).

While the backend architecture is enterprise-grade, root entry scripts ([index.php](file:///C:/xampp/htdocs/tfdtaskmanager/index.php), [task_detail.php](file:///C:/xampp/htdocs/tfdtaskmanager/task_detail.php), [meeting_detail.php](file:///C:/xampp/htdocs/tfdtaskmanager/meeting_detail.php), [projects.php](file:///C:/xampp/htdocs/tfdtaskmanager/projects.php), [users.php](file:///C:/xampp/htdocs/tfdtaskmanager/users.php)) mix controller routing with presentation rendering. 

Agents assigned to this codebase must **preserve architectural discipline**, **eliminate remaining procedural script leaks**, **harden file uploads**, and **audit database integrity**.

---

## 2. Layer Boundaries & Architectural Governance

Agents must adhere strictly to the established Clean Architecture layers:

```
src/
├── Domain/
│   ├── Entities/             # Pure entities with encapsulated state and validation invariants
│   └── Repositories/         # Repository interfaces (No PDO/SQL dependencies here)
│
├── Application/
│   ├── Exceptions/           # ValidationException, AuthorizationException, NotFoundException
│   └── Services/             # Application workflows (TaskService, BgAssetService, BgExportService)
│
├── Container/
│   └── DIContainer.php       # Service container managing constructor injections
│
└── Infrastructure/
    ├── Database/             # Connection.php (PDO wrapper, utf8mb4, emulated prepares disabled)
    ├── Repository/           # PDO implementations of all Domain Repository Interfaces
    └── Security/             # SecurityHelper (CSRF, XSS sanitization, password hashing), SSOHelper
```

---

## 3. Mandatory Governance & Security Rules

### 3.1 Strict Typing
Every single PHP file (`.php`), without exception, must begin with:
```php
<?php
declare(strict_types=1);
```
Ensure all method signatures across `Application\Services` and `Infrastructure\Repository` have complete type coverage (return types, argument types, and nullable annotations).

### 3.2 Dependency Injection via Container
* Root controller scripts must NEVER instantiate repositories or database connections directly with `new`.
* All services must be obtained through the DI Container:
  ```php
  $container = require_once __DIR__ . '/src/bootstrap.php';
  $taskService = $container->get(TaskService::class);
  ```

### 3.3 Strict CSRF & Session Security
* In every form (tasks, projects, meetings, asset uploads), emit and verify a cryptographic CSRF token:
  ```php
  // Form generation:
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(SecurityHelper::generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

  // POST processing:
  if (!SecurityHelper::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
      http_response_code(403);
      die('CSRF token validation failed.');
  }
  ```
* Enforce session regeneration upon privilege escalation and login.

### 3.4 Board Game Studio File Upload Hardening
For [BgAssetService](file:///C:/xampp/htdocs/tfdtaskmanager/src/Application/Services/BgAssetService.php) and asset upload handlers:
* Never trust client-provided `$_FILES['name']` or MIME types.
* Perform server-side MIME type sniffing via `finfo_file(finfo_open(FILEINFO_MIME_TYPE), $tempPath)`:
  * Only allow `image/png`, `image/jpeg`, `image/webp`, `image/svg+xml`, `application/pdf`, `text/csv`.
* Store uploaded files with randomly generated UUID filenames outside web root or with `.htaccess` disabling script execution (`php_flag engine off`).

### 3.5 DOM Output Sanitization (XSS Defense)
* All task titles, markdown descriptions, comments, meeting notes, and user profile data MUST be sanitized before rendering into the DOM:
  ```php
  // Raw text:
  <?= htmlspecialchars((string)$task->getTitle(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>

  // Markdown rendered HTML:
  // Must pass through a trusted sanitizer / whitelist (strip script, iframe, on* attributes)
  ```

---

## 4. Database Governance (MySQL InnoDB)

* Ensure all tables in `init_db.php` use **InnoDB** with strict Foreign Keys:
  * `tasks.project_id` -> `FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE`
  * `tasks.assigned_user_id` -> `FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL`
  * `comments.task_id` -> `FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE`
  * `meeting_topics.meeting_id` -> `FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE`
  * `bg_template_layers.template_id` -> `FOREIGN KEY (template_id) REFERENCES bg_templates(id) ON DELETE CASCADE`
* State transitions (e.g. task assignment changes with history logging, asset uploads with dataset attachment) must execute inside database transactions.

---

## 5. Agent Action Items & Verification Protocol

1. **Strict Types Verification:** Confirm `declare(strict_types=1);` is present on every file in `src/` and project root.
2. **PHP 8.2.35 Syntax Audit:** Run `C:\xampp\php\php.exe -l` on all PHP files in the project to guarantee zero syntax or deprecation errors.
3. **Database Constraints Audit:** Verify existing database schema via [init_db.php](file:///C:/xampp/htdocs/tfdtaskmanager/init_db.php) to ensure all foreign keys are actively enforced.
4. **SSO & Auth Audit:** Verify constant-time comparison (`hash_equals()`) in [auth_callback.php](file:///C:/xampp/htdocs/tfdtaskmanager/auth_callback.php) and `SSOHelper.php`.
5. **CSRF Audit:** Verify that all POST routes across [task_detail.php](file:///C:/xampp/htdocs/tfdtaskmanager/task_detail.php), [meeting_detail.php](file:///C:/xampp/htdocs/tfdtaskmanager/meeting_detail.php), and [add_task.php](file:///C:/xampp/htdocs/tfdtaskmanager/add_task.php) mandate valid CSRF tokens.
