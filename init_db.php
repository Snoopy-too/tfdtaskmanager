<?php
declare(strict_types=1);

// Database initialization and seeding script
$config = require __DIR__ . '/config.php';

$dbConfig = $config['db'];
$dsn = sprintf('mysql:host=%s;charset=%s', $dbConfig['host'], $dbConfig['charset']);

try {
    // Connect to MySQL server first without selecting database (database might not exist, but user said it does)
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Create database if not exists
    $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $dbConfig['dbname']));
    $pdo->exec(sprintf('USE `%s`', $dbConfig['dbname']));

    echo "Database structure setup starting...\n";

    // 1. Users table (InnoDB)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `role` ENUM('super_admin', 'member') NOT NULL DEFAULT 'member',
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(191) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
    echo "- 'users' table created.\n";

    // 2. Projects table (InnoDB)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `projects` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
    echo "- 'projects' table created.\n";

    // 3. Tasks table (InnoDB with foreign keys)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `tasks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `title` VARCHAR(150) NOT NULL,
            `details` TEXT,
            `status` ENUM('To Do', 'In Progress', 'Done') NOT NULL DEFAULT 'To Do',
            `deadline` DATE DEFAULT NULL,
            `created_by` INT NOT NULL,
            `assigned_to` INT DEFAULT NULL,
            `checked_out_at` TIMESTAMP NULL DEFAULT NULL,
            `is_bug` TINYINT(1) NOT NULL DEFAULT 0,
            `version` INT NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_tasks_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
            CONSTRAINT `fk_tasks_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB;
    ");
    echo "- 'tasks' table created.\n";

    // 4. Task History table (InnoDB with cascade delete on task)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `task_history` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `task_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `action` ENUM('created', 'checked_out', 'checked_in', 'completed', 'updated') NOT NULL,
            `note` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_history_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB;
    ");
    echo "- 'task_history' table created.\n";

    // 5. Comments table (InnoDB with cascade delete on task)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `comments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `task_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `message` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_comments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB;
    ");
    echo "- 'comments' table created.\n";

    // 6. Meetings table (InnoDB)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `meetings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(150) NOT NULL,
            `scheduled_date` DATE DEFAULT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_meetings_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");
    echo "- 'meetings' table created.\n";

    // 7. Meeting Topics table (InnoDB)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `meeting_topics` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `meeting_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_topics_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_topics_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");
    echo "- 'meeting_topics' table created.\n";

    // 8. Board Game Component Types table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `bg_component_types` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(50) NOT NULL,
            `width_mm` DECIMAL(8,2) NOT NULL,
            `height_mm` DECIMAL(8,2) NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "- 'bg_component_types' table created.\n";

    // Seed standard component types if not present
    $pdo->exec("
        INSERT IGNORE INTO `bg_component_types` (`id`, `name`, `width_mm`, `height_mm`, `description`) VALUES
        (1, 'Poker Card',  63.00,  88.00, 'Standard poker-size playing card'),
        (2, 'Tarot Card',  70.00, 120.00, 'Standard tarot-size card'),
        (3, 'Game Board (Square)', 480.00, 480.00, 'Standard folding square game board'),
        (4, 'Punchboard', 280.00, 280.00, 'Custom punchboard for tokens'),
        (5, 'Game Board (Rectangular)', 508.00, 762.00, 'Standard large rectangular game board (20x30 in)'),
        (6, 'Player Board (A4 Landscape)', 297.00, 210.00, 'Standard player mat/board (A4 Landscape)'),
        (7, 'Player Board (A5 Landscape)', 210.00, 148.00, 'Compact player board (A5 Landscape)'),
        (8, 'Game Board (Medium Square)', 300.00, 300.00, 'Medium square game board'),
        (9, 'Custom', 0.00, 0.00, 'Custom dimensions defined by user.');
    ");
    // Force reset all default types to their correct details
    $pdo->exec("UPDATE `bg_component_types` SET `name` = 'Poker Card', `width_mm` = 63.00, `height_mm` = 88.00, `description` = 'Standard poker-size playing card' WHERE `id` = 1");
    $pdo->exec("UPDATE `bg_component_types` SET `name` = 'Tarot Card', `width_mm` = 70.00, `height_mm` = 120.00, `description` = 'Standard tarot-size card' WHERE `id` = 2");
    $pdo->exec("UPDATE `bg_component_types` SET `name` = 'Game Board (Square)', `width_mm` = 480.00, `height_mm` = 480.00, `description` = 'Standard folding square game board' WHERE `id` = 3");
    $pdo->exec("UPDATE `bg_component_types` SET `name` = 'Punchboard', `width_mm` = 280.00, `height_mm` = 280.00, `description` = 'Custom punchboard for tokens' WHERE `id` = 4");
    $pdo->exec("UPDATE `bg_component_types` SET `name` = 'Game Board (Rectangular)', `width_mm` = 508.00, `height_mm` = 762.00, `description` = 'Standard large rectangular game board (20x30 in)' WHERE `id` = 5");
    $pdo->exec("UPDATE `bg_component_types` SET `name` = 'Player Board (A4 Landscape)', `width_mm` = 297.00, `height_mm` = 210.00, `description` = 'Standard player mat/board (A4 Landscape)' WHERE `id` = 6");
    $pdo->exec("UPDATE `bg_component_types` SET `name` = 'Player Board (A5 Landscape)', `width_mm` = 210.00, `height_mm` = 148.00, `description` = 'Compact player board (A5 Landscape)' WHERE `id` = 7");
    $pdo->exec("UPDATE `bg_component_types` SET `name` = 'Game Board (Medium Square)', `width_mm` = 300.00, `height_mm` = 300.00, `description` = 'Medium square game board' WHERE `id` = 8");
    $pdo->exec("UPDATE `bg_component_types` SET `name` = 'Custom', `width_mm` = 0.00, `height_mm` = 0.00, `description` = 'Custom dimensions defined by user.' WHERE `id` = 9");
    echo "- Seeded default component types.\n";

    // 9. Board Game Assets table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `bg_assets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT DEFAULT NULL,
            `original_filename` VARCHAR(255) NOT NULL,
            `stored_filename` VARCHAR(255) NOT NULL,
            `mime_type` VARCHAR(100) NOT NULL,
            `file_size_bytes` INT UNSIGNED NOT NULL,
            `tag` VARCHAR(100) DEFAULT NULL,
            `uploaded_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_bg_assets_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_bg_assets_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
            INDEX `idx_bg_assets_project` (`project_id`),
            INDEX `idx_bg_assets_tag` (`tag`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "- 'bg_assets' table created.\n";

    // 10. Board Game Datasets table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `bg_datasets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `column_map` JSON NOT NULL,
            `row_data` JSON NOT NULL,
            `created_by` INT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_bg_datasets_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_bg_datasets_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
            INDEX `idx_bg_datasets_project` (`project_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "- 'bg_datasets' table created.\n";

    // 11. Board Game Templates table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `bg_templates` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `component_type_id` INT NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `canvas_json` JSON DEFAULT NULL,
            `canvas_width_px` INT UNSIGNED NOT NULL,
            `canvas_height_px` INT UNSIGNED NOT NULL,
            `bleed_mm` DECIMAL(5,2) NOT NULL DEFAULT 3.00,
            `safe_margin_mm` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
            `dataset_id` INT DEFAULT NULL,
            `created_by` INT NOT NULL,
            `locked_by_user_id` INT DEFAULT NULL,
            `locked_at` TIMESTAMP NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_bg_templates_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_bg_templates_comp_type` FOREIGN KEY (`component_type_id`) REFERENCES `bg_component_types` (`id`),
            CONSTRAINT `fk_bg_templates_dataset` FOREIGN KEY (`dataset_id`) REFERENCES `bg_datasets` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_bg_templates_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
            CONSTRAINT `fk_bg_templates_locked_user` FOREIGN KEY (`locked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
            INDEX `idx_bg_templates_project` (`project_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "- 'bg_templates' table created.\n";

    // 12. Board Game Template Layers table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `bg_template_layers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `template_id` INT NOT NULL,
            `name` VARCHAR(100) NOT NULL DEFAULT 'Layer',
            `layer_type` ENUM('text','image','shape','dropzone') NOT NULL,
            `z_index` INT NOT NULL DEFAULT 0,
            `x_pos` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `y_pos` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `width` DECIMAL(10,2) NOT NULL,
            `height` DECIMAL(10,2) NOT NULL,
            `rotation` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            `opacity` DECIMAL(3,2) NOT NULL DEFAULT 1.00,
            `properties` JSON NOT NULL,
            `variable_binding` VARCHAR(100) DEFAULT NULL,
            `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
            `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `fk_bg_layers_template` FOREIGN KEY (`template_id`) REFERENCES `bg_templates` (`id`) ON DELETE CASCADE,
            INDEX `idx_bg_layers_template` (`template_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "- 'bg_template_layers' table created.\n";

    // Seed default Super-Admin if not exists
    $adminEmail = 'admin@tfdtaskmgr.local';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $adminEmail]);
    if (!$stmt->fetch()) {
        $adminPassword = 'AdminPass123!';
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash($adminPassword, $algo);
        
        $insert = $pdo->prepare("
            INSERT INTO users (role, name, email, password_hash) 
            VALUES ('super_admin', 'System Super-Admin', :email, :password_hash)
        ");
        $insert->execute([
            'email' => $adminEmail,
            'password_hash' => $hash,
        ]);
        echo "- Seeded Super-Admin user: $adminEmail with default credentials.\n";
    } else {
        echo "- Super-Admin user already exists. Skipping seeding.\n";
    }

    // Migration: Check if locks columns exist on bg_templates for existing DBs
    $columns = $pdo->query("SHOW COLUMNS FROM `bg_templates` LIKE 'locked_by_user_id'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE `bg_templates` ADD COLUMN `locked_by_user_id` INT DEFAULT NULL AFTER `created_by`");
        $pdo->exec("ALTER TABLE `bg_templates` ADD COLUMN `locked_at` TIMESTAMP NULL DEFAULT NULL AFTER `locked_by_user_id`");
        $pdo->exec("ALTER TABLE `bg_templates` ADD CONSTRAINT `fk_bg_templates_locked_user` FOREIGN KEY (`locked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
        echo "- Added locking columns and constraint to 'bg_templates' table via migration.\n";
    }

    echo "Database setup completed successfully!\n";

} catch (PDOException $e) {
    fwrite(STDERR, "Database error: " . $e->getMessage() . "\n");
    exit(1);
}
