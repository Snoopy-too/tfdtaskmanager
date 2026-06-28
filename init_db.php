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
            `action` ENUM('created', 'checked_out', 'checked_in', 'completed') NOT NULL,
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

    // Seed default Super-Admin if not exists
    $adminEmail = 'admin@tfdtaskmgr.local';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $adminEmail]);
    if (!$stmt->fetch()) {
        $adminPassword = 'AdminPass123!';
        $hash = password_hash($adminPassword, PASSWORD_BCRYPT);
        
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

    echo "Database setup completed successfully!\n";

} catch (PDOException $e) {
    fwrite(STDERR, "Database error: " . $e->getMessage() . "\n");
    exit(1);
}
