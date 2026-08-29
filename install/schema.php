<?php
/**
 * 安装/升级表结构定义：全部表 DDL（MySQL 5.7 兼容，utf8mb4 + InnoDB）
 * 被安装程序与后台安全设置（按需建 password_history 表）复用
 */

/**
 * 返回建表 SQL 列表
 *
 * @param string $prefix 表前缀
 * @return array SQL 语句列表
 */
function install_schema($prefix)
{
    $tables = array();

    $tables['users'] = "CREATE TABLE IF NOT EXISTS `{$prefix}users` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(32) NOT NULL,
        `nickname` VARCHAR(64) NOT NULL DEFAULT '',
        `password` VARCHAR(255) NOT NULL,
        `email` VARCHAR(128) NULL DEFAULT NULL,
        `phone` VARCHAR(20) NOT NULL DEFAULT '',
        `avatar` VARCHAR(255) NOT NULL DEFAULT '',
        `signature` VARCHAR(255) NOT NULL DEFAULT '',
        `role` ENUM('admin','editor','user') NOT NULL DEFAULT 'user',
        `status` TINYINT NOT NULL DEFAULT 1,
        `is_banned` TINYINT NOT NULL DEFAULT 0,
        `is_deleted` TINYINT NOT NULL DEFAULT 0,
        `password_changed_at` DATETIME NULL DEFAULT NULL,
        `login_fail` INT UNSIGNED NOT NULL DEFAULT 0,
        `locked_until` DATETIME NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_username` (`username`),
        UNIQUE KEY `uk_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['posts'] = "CREATE TABLE IF NOT EXISTS `{$prefix}posts` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `author_id` INT UNSIGNED NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(255) NULL DEFAULT NULL,
        `content` MEDIUMTEXT,
        `excerpt` TEXT,
        `category_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `cover` VARCHAR(255) NOT NULL DEFAULT '',
        `status` ENUM('draft','pending','published','trash') NOT NULL DEFAULT 'draft',
        `is_page` TINYINT NOT NULL DEFAULT 0,
        `is_top` TINYINT NOT NULL DEFAULT 0,
        `views` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_slug` (`slug`),
        KEY `idx_status_created` (`status`,`created_at`),
        KEY `idx_category` (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['comments'] = "CREATE TABLE IF NOT EXISTS `{$prefix}comments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `post_id` INT UNSIGNED NOT NULL,
        `user_id` INT UNSIGNED NOT NULL,
        `parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `content` TEXT,
        `status` ENUM('pending','published','trash') NOT NULL DEFAULT 'pending',
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_post_status` (`post_id`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['categories'] = "CREATE TABLE IF NOT EXISTS `{$prefix}categories` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(64) NOT NULL,
        `slug` VARCHAR(64) NOT NULL,
        `description` VARCHAR(255) NOT NULL DEFAULT '',
        `sort` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['options'] = "CREATE TABLE IF NOT EXISTS `{$prefix}options` (
        `option_key` VARCHAR(64) NOT NULL,
        `option_value` MEDIUMTEXT,
        PRIMARY KEY (`option_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // 注意：验证码明文存储（6位数字），有 expires_at + attempts 双重保护。
    // 如需更高安全性，可改为 hash('sha256', $code) 存储。
    $tables['verify_codes'] = "CREATE TABLE IF NOT EXISTS `{$prefix}verify_codes` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `scene` ENUM('register','reset','profile') NOT NULL,
        `target` VARCHAR(128) NOT NULL,
        `code` VARCHAR(8) NOT NULL,
        `channel` ENUM('email','sms') NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `used` TINYINT NOT NULL DEFAULT 0,
        `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_scene_target` (`scene`,`target`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $tables['logs'] = "CREATE TABLE IF NOT EXISTS `{$prefix}logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `role` VARCHAR(16) NOT NULL DEFAULT 'guest',
        `category` VARCHAR(24) NOT NULL,
        `action` VARCHAR(64) NOT NULL,
        `result` ENUM('success','fail') NOT NULL,
        `detail` TEXT,
        `ip` VARCHAR(45) NOT NULL DEFAULT '',
        `ua` VARCHAR(255) NOT NULL DEFAULT '',
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_category_created` (`category`,`created_at`),
        KEY `idx_user` (`user_id`),
        KEY `idx_ip` (`ip`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    // 插件通用数据存储：global（配置）/user（用户级键值）两种作用域，
    // expires_at 非空即临时缓存（等价 transient），过期由内核每日惰性清理
    $tables['plugin_data'] = "CREATE TABLE IF NOT EXISTS `{$prefix}plugin_data` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `plugin` VARCHAR(64) NOT NULL,
        `scope` ENUM('global','user') NOT NULL DEFAULT 'global',
        `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
        `data_key` VARCHAR(191) NOT NULL,
        `data_value` MEDIUMTEXT,
        `expires_at` DATETIME NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_lookup` (`plugin`,`scope`,`user_id`,`data_key`),
        KEY `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return $tables;
}

/**
 * password_history 表 DDL（密码历史功能开启时按需建表）
 *
 * @param string $prefix 表前缀
 * @return string SQL
 */
function install_password_history_schema($prefix)
{
    return "CREATE TABLE IF NOT EXISTS `{$prefix}password_history` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}
