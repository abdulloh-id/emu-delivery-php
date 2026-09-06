CREATE TABLE IF NOT EXISTS `emu_region_list` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `emu_town_list` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `region_code` INT UNSIGNED NOT NULL,
    `region_name` VARCHAR(255) NOT NULL,
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    INDEX `idx_region_code` (`region_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `emu_pvz_list` (
    `id` INT UNSIGNED NOT NULL PRIMARY KEY,
    `client_code` VARCHAR(100) DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `parent_code` INT UNSIGNED DEFAULT NULL,
    `parent_name` VARCHAR(255) DEFAULT NULL,
    `town` VARCHAR(255) NOT NULL,
    `town_code` VARCHAR(100) NOT NULL,
    `town_region_code` INT UNSIGNED NOT NULL,
    `town_region_name` VARCHAR(255) NOT NULL,
    `address` TEXT DEFAULT NULL,
    `phone` VARCHAR(100) DEFAULT NULL,
    `comment` TEXT DEFAULT NULL,
    `work_time` TEXT DEFAULT NULL,
    `travel_description` TEXT DEFAULT NULL,
    `max_weight` INT UNSIGNED DEFAULT 0,
    `accept_cash` TINYINT(1) DEFAULT 0,
    `accept_card` TINYINT(1) DEFAULT 0,
    `accept_fitting` TINYINT(1) DEFAULT 0,
    `accept_individuals` TINYINT(1) DEFAULT 0,
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    INDEX `idx_town_code` (`town_code`),
    INDEX `idx_town_region_code` (`town_region_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;