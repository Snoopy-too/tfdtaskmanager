# Agent Directives: TFD Task Manager & Board Game Studio - PHP 8.5.12 Modernization & Entity Evolution

> **Target Project:** `C:\xampp\htdocs\tfdtaskmanager`  
> **Runtime Environment:** PHP 8.5.12 (ZTS Visual C++ 2022 x64) / Apache 2.4.58 (Win64) / MySQL (XAMPP Windows)  
> **Architecture Status:** Advanced Clean Architecture in `src/`; Multiple root presentation controllers  
> **Key Subsystems:** Core Task Manager, Meeting Minutes, Board Game Studio (Assets, Dielines, Datasets, Rulebooks), Single Sign-On (SSO)  
> **Lead Architect Directive:** Security-First Principal Engineer Governance  

---

## 1. Executive Summary & PHP 8.5.12 Baseline

The server's PHP runtime has been upgraded to **PHP 8.5.12** (VS17 x64 with OPcache).

`tfdtaskmanager` is an enterprise-grade platform combining task tracking, meeting logging, and a Board Game Studio. It is built upon Clean Architecture in `src/` (`Domain/Entities`, `Domain/Repositories`, `Application/Services`, `Infrastructure/Repository`, `Container/DIContainer`).

Agents assigned to this codebase are directed to modernize domain entities with PHP 8.5 features, resolve implicitly nullable parameter deprecations across all services and repositories, use `json_validate()` on Studio datasets, and complete the consolidation of root scripts into thin controllers.

---

## 2. Refactoring Directives for PHP 8.5.12

### 2.1 Domain Entities with Asymmetric Visibility & Property Hooks
Modernize the 15+ domain entities in `src/Domain/Entities/` ([`Task.php`](file:///C:/xampp/htdocs/tfdtaskmanager/src/Domain/Entities/Task.php), [`Project.php`](file:///C:/xampp/htdocs/tfdtaskmanager/src/Domain/Entities/Project.php), [`BgAsset.php`](file:///C:/xampp/htdocs/tfdtaskmanager/src/Domain/Entities/BgAsset.php), [`BgDataset.php`](file:///C:/xampp/htdocs/tfdtaskmanager/src/Domain/Entities/BgDataset.php), [`BgTemplate.php`](file:///C:/xampp/htdocs/tfdtaskmanager/src/Domain/Entities/BgTemplate.php)):

```php
<?php
declare(strict_types=1);

namespace App\Domain\Entities;

use DateTimeImmutable;
use InvalidArgumentException;

final class Task
{
    public const string STATUS_TODO = 'todo';
    public const string STATUS_IN_PROGRESS = 'in_progress';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_ARCHIVED = 'archived';

    public function __construct(
        public private(set) int $id,
        public private(set) int $projectId,
        public private(set) string $title,
        private string $status = self::STATUS_TODO,
        public private(set) ?int $assignedUserId = null,
        public private(set) DateTimeImmutable $createdAt = new DateTimeImmutable()
    ) {}

    // Property hook for status transitions
    public string $currentStatus {
        get => $this->status;
        set {
            $allowed = [self::STATUS_TODO, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_ARCHIVED];
            if (!in_array($value, $allowed, true)) {
                throw new InvalidArgumentException("Invalid task status: {$value}");
            }
            $this->status = $value;
        }
    }
}
```

### 2.2 Board Game Studio JSON Validation with `json_validate()`
In [`BgDatasetService`](file:///C:/xampp/htdocs/tfdtaskmanager/src/Application/Services/BgDatasetService.php), [`BgTemplateService`](file:///C:/xampp/htdocs/tfdtaskmanager/src/Application/Services/BgTemplateService.php), and [`BgBoxDielineService`](file:///C:/xampp/htdocs/tfdtaskmanager/src/Application/Services/BgBoxDielineService.php):
* Use PHP 8.3/8.5 native `json_validate()` before decoding uploaded dataset JSON or dieline layer definitions to prevent unhandled parsing exceptions:
  ```php
  if (!json_validate($rawJson)) {
      throw new ValidationException('Invalid JSON provided for Board Game dataset.');
  }

  $data = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
  ```

### 2.3 Strict Elimination of Implicitly Nullable Parameters
Audit all methods in `Application/Services/` and `Domain/Repositories/`. In PHP 8.5:
* **FORBIDDEN (Emits `E_DEPRECATED`):**
  ```php
  public function getAllTasks(int $userId, int $projectId = null, string $status = null): array
  ```
* **MANDATORY:**
  ```php
  public function getAllTasks(int $userId, ?int $projectId = null, ?string $status = null): array
  ```

### 2.4 Mandatory `#[Override]` on Persistence Repositories
Tag every method in `src/Infrastructure/Repository/PDOTaskRepository.php`, `PDOProjectRepository.php`, `PDOBgAssetRepository.php`, and `PDOUserRepository.php` with `#[Override]`.

### 2.5 Array Cleanliness with `array_find` & `array_any`
Replace custom search loops when filtering task tags, permissions, or meeting topics:
```php
$isAssigned = array_any($tasks, fn(Task $t) => $t->assignedUserId === $userId);
$activeMeeting = array_find($meetings, fn(Meeting $m) => $m->status === 'in_progress');
```

---

## 3. Mandatory Governance & Security Rules

1. **Strict Types:** Every file must start with `declare(strict_types=1);`.
2. **Thin Controllers:** Root scripts ([`index.php`](file:///C:/xampp/htdocs/tfdtaskmanager/index.php), [`task_detail.php`](file:///C:/xampp/htdocs/tfdtaskmanager/task_detail.php), [`meeting_detail.php`](file:///C:/xampp/htdocs/tfdtaskmanager/meeting_detail.php), [`projects.php`](file:///C:/xampp/htdocs/tfdtaskmanager/projects.php)) must exclusively delegate to services via the DI Container.
3. **CSRF Protection:** Verify CSRF tokens on all POST requests using `SecurityHelper::verifyCsrfToken()`.
4. **File Upload Hardening:** Server-side MIME type sniffing via `finfo` for all Studio assets. Reject executable uploads.
5. **DOM Sanitization:** All user-generated text and comments must be sanitized via `SecurityHelper::sanitize()` or `htmlspecialchars()` with `UTF-8`.
6. **Database Governance (MySQL InnoDB):** Ensure all 20+ tables use `ENGINE=InnoDB` with cascading or restrict foreign keys.

---

## 4. Agent Execution Checklist

1. **Syntax & Deprecation Lint:** Run `C:\xampp\php\php.exe -l` across `src/` and root files to ensure zero `E_DEPRECATED` notices.
2. **Foreign Key Integrity:** Inspect schema in [`init_db.php`](file:///C:/xampp/htdocs/tfdtaskmanager/init_db.php) and verify foreign keys exist in MySQL.
3. **Session & SSO Security:** Verify constant-time comparison (`hash_equals()`) in [`auth_callback.php`](file:///C:/xampp/htdocs/tfdtaskmanager/auth_callback.php) and `SSOHelper.php`.
