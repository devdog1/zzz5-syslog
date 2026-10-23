-- Syslog Viewer Installation Schema
-- Note: {prefix} placeholder is replaced with plug_syslog_viewer_ automatically

CREATE TABLE IF NOT EXISTS `{prefix}search_bookmarks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `bookmark_name` VARCHAR(100) NOT NULL,
  `query_params` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{prefix}search_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `search_file` VARCHAR(255) NOT NULL,
  `host_filter` VARCHAR(100) DEFAULT NULL,
  `keyword` VARCHAR(255) DEFAULT NULL,
  `results_count` INT NOT NULL DEFAULT 0,
  `searched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`searched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
