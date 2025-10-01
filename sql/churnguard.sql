-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 21, 2025 at 04:45 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `churnguard`
--

-- --------------------------------------------------------

--
-- Table structure for table `auth_log`
--

CREATE TABLE `auth_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `event_time` datetime NOT NULL,
  `location` varchar(191) DEFAULT NULL,
  `device` varchar(191) DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `churn_data`
--

CREATE TABLE `churn_data` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `receipt_count` int(11) NOT NULL DEFAULT 0,
  `sales_volume` decimal(12,2) NOT NULL DEFAULT 0.00,
  `customer_traffic` int(11) NOT NULL DEFAULT 0,
  `morning_receipt_count` int(11) NOT NULL DEFAULT 0,
  `swing_receipt_count` int(11) NOT NULL DEFAULT 0,
  `graveyard_receipt_count` int(11) NOT NULL DEFAULT 0,
  `morning_sales_volume` decimal(12,2) NOT NULL DEFAULT 0.00,
  `swing_sales_volume` decimal(12,2) NOT NULL DEFAULT 0.00,
  `graveyard_sales_volume` decimal(12,2) NOT NULL DEFAULT 0.00,
  `previous_day_receipt_count` int(11) NOT NULL DEFAULT 0,
  `previous_day_sales_volume` decimal(12,2) NOT NULL DEFAULT 0.00,
  `weekly_average_receipts` decimal(10,2) NOT NULL DEFAULT 0.00,
  `weekly_average_sales` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transaction_drop_percentage` decimal(6,2) NOT NULL DEFAULT 0.00,
  `sales_drop_percentage` decimal(6,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `churn_data`
--

INSERT INTO `churn_data` (`id`, `user_id`, `date`, `receipt_count`, `sales_volume`, `customer_traffic`, `morning_receipt_count`, `swing_receipt_count`, `graveyard_receipt_count`, `morning_sales_volume`, `swing_sales_volume`, `graveyard_sales_volume`, `previous_day_receipt_count`, `previous_day_sales_volume`, `weekly_average_receipts`, `weekly_average_sales`, `transaction_drop_percentage`, `sales_drop_percentage`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-08-21', 12, 3.00, 544, 56, 456, 4, 564.00, 56.00, 4.00, 564, 654.00, 564.00, 564.00, 56.00, 456.00, '2025-08-21 07:38:59', '2025-08-21 14:10:53');

-- --------------------------------------------------------

--
-- Table structure for table `churn_predictions`
--

CREATE TABLE `churn_predictions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `risk_score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `risk_level` enum('Low','Medium','High') NOT NULL DEFAULT 'Low',
  `factors` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`factors`)),
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `level` varchar(16) NOT NULL DEFAULT 'Low',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `churn_predictions`
--

INSERT INTO `churn_predictions` (`id`, `user_id`, `date`, `risk_score`, `risk_level`, `factors`, `description`, `created_at`, `level`, `updated_at`) VALUES
(1, 1, '2025-08-21', 100.00, '', '[\"Decreased visit frequency\"]', NULL, '2025-08-21 07:39:00', 'Medium Risk', '2025-08-21 09:06:45');

-- --------------------------------------------------------

--
-- Stand-in structure for view `login_history`
-- (See below for the actual view)
--
CREATE TABLE `login_history` (
`id` bigint(20) unsigned
,`user_id` int(10) unsigned
,`datetime` varchar(24)
,`location` varchar(191)
,`device` varchar(191)
,`ip` varchar(64)
,`status` varchar(7)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `profile`
-- (See below for the actual view)
--
CREATE TABLE `profile` (
`user_id` int(10) unsigned
,`username` varchar(64)
,`email` varchar(191)
,`firstname` varchar(100)
,`lastname` varchar(100)
,`company` varchar(191)
,`role` varchar(100)
,`avatar_url` varchar(255)
,`two_factor_enabled` int(4)
,`address` varchar(255)
,`phone` varchar(32)
);

-- --------------------------------------------------------

--
-- Table structure for table `revenue_categories`
--

CREATE TABLE `revenue_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `revenue` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `revenue_category`
-- (See below for the actual view)
--
CREATE TABLE `revenue_category` (
`id` bigint(20) unsigned
,`user_id` int(10) unsigned
,`category_name` varchar(100)
,`revenue` decimal(12,2)
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shift_performance`
--

CREATE TABLE `shift_performance` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `shift` enum('morning','swing','graveyard') NOT NULL DEFAULT 'morning',
  `shift_name` enum('morning','swing','graveyard') NOT NULL,
  `receipt_count` int(11) NOT NULL DEFAULT 0,
  `sales_volume` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shift_performance`
--

INSERT INTO `shift_performance` (`id`, `user_id`, `date`, `shift`, `shift_name`, `receipt_count`, `sales_volume`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-08-21', 'morning', 'morning', 456, 456.00, '2025-08-21 08:56:24', '2025-08-21 09:29:02');

-- --------------------------------------------------------

--
-- Table structure for table `traffic_hourly`
--

CREATE TABLE `traffic_hourly` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `hour` tinyint(3) UNSIGNED NOT NULL COMMENT '0..23',
  `visitors` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `firstname` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `company` varchar(191) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `isActive` tinyint(1) NOT NULL DEFAULT 1,
  `isVerified` tinyint(1) NOT NULL DEFAULT 0,
  `otp_code` varchar(12) DEFAULT NULL,
  `otp_purpose` varchar(32) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `otp_last_sent_at` datetime DEFAULT NULL,
  `otp_attempts` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `otp_created_at` datetime DEFAULT NULL,
  `otp_is_used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password_hash`, `password`, `firstname`, `lastname`, `company`, `address`, `phone`, `role`, `avatar_url`, `icon`, `two_factor_enabled`, `isActive`, `isVerified`, `otp_code`, `otp_purpose`, `otp_expires_at`, `otp_last_sent_at`, `otp_attempts`, `created_at`, `updated_at`, `otp_created_at`, `otp_is_used`) VALUES
(1, 'ysl.aether.bank@gmail.com', 'ysl.aether.bank@gmail.com', NULL, '$2y$10$x6.XNrLE3UPS01GqNdnq8uBRLGGAq5UbLUF4H3x27A00ZTrDNB0Wq', 'Brian', 'Deric', NULL, '1908', '09120092365', NULL, NULL, NULL, 0, 1, 1, NULL, NULL, NULL, NULL, 0, '2025-08-21 07:33:48', '2025-08-21 13:12:08', '2025-08-21 15:33:48', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `refresh_interval` int(11) NOT NULL DEFAULT 6,
  `dark_mode` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure for view `login_history`
--
DROP TABLE IF EXISTS `login_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `login_history`  AS SELECT `auth_log`.`id` AS `id`, `auth_log`.`user_id` AS `user_id`, date_format(`auth_log`.`event_time`,'%Y-%m-%d %H:%i:%s') AS `datetime`, coalesce(`auth_log`.`location`,'') AS `location`, coalesce(`auth_log`.`device`,'') AS `device`, coalesce(`auth_log`.`ip_address`,'') AS `ip`, CASE WHEN `auth_log`.`status` = 1 THEN 'Success' ELSE 'Failed' END AS `status` FROM `auth_log` ;

-- --------------------------------------------------------

--
-- Structure for view `profile`
--
DROP TABLE IF EXISTS `profile`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `profile`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`username` AS `username`, `u`.`email` AS `email`, coalesce(`u`.`firstname`,'') AS `firstname`, coalesce(`u`.`lastname`,'') AS `lastname`, coalesce(`u`.`company`,'') AS `company`, coalesce(`u`.`role`,'') AS `role`, coalesce(`u`.`avatar_url`,'') AS `avatar_url`, coalesce(`u`.`two_factor_enabled`,0) AS `two_factor_enabled`, coalesce(`u`.`address`,'') AS `address`, coalesce(`u`.`phone`,'') AS `phone` FROM `users` AS `u` ;

-- --------------------------------------------------------

--
-- Structure for view `revenue_category`
--
DROP TABLE IF EXISTS `revenue_category`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `revenue_category`  AS SELECT `revenue_categories`.`id` AS `id`, `revenue_categories`.`user_id` AS `user_id`, `revenue_categories`.`category_name` AS `category_name`, `revenue_categories`.`revenue` AS `revenue`, `revenue_categories`.`updated_at` AS `updated_at` FROM `revenue_categories` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `auth_log`
--
ALTER TABLE `auth_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auth_user_time` (`user_id`,`event_time`);

--
-- Indexes for table `churn_data`
--
ALTER TABLE `churn_data`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_churn_user_date` (`user_id`,`date`),
  ADD KEY `idx_churn_user_date` (`user_id`,`date`);

--
-- Indexes for table `churn_predictions`
--
ALTER TABLE `churn_predictions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pred_user_date` (`user_id`,`date`),
  ADD UNIQUE KEY `uniq_user_date` (`user_id`,`date`),
  ADD KEY `idx_pred_user_date` (`user_id`,`date`),
  ADD KEY `idx_user_date` (`user_id`,`date`);

--
-- Indexes for table `revenue_categories`
--
ALTER TABLE `revenue_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rev_user_date_cat` (`user_id`,`date`,`category_name`),
  ADD KEY `idx_rev_user_date` (`user_id`,`date`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_settings_name` (`name`);

--
-- Indexes for table `shift_performance`
--
ALTER TABLE `shift_performance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_shift_user_date_name` (`user_id`,`date`,`shift_name`),
  ADD UNIQUE KEY `uniq_user_date_shift` (`user_id`,`date`,`shift`);

--
-- Indexes for table `traffic_hourly`
--
ALTER TABLE `traffic_hourly`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_traffic_user_date_hour` (`user_id`,`date`,`hour`),
  ADD KEY `idx_traffic_user_date` (`user_id`,`date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uniq_users_username` (`username`),
  ADD UNIQUE KEY `uniq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_sessions_user` (`user_id`,`is_active`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `auth_log`
--
ALTER TABLE `auth_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `churn_data`
--
ALTER TABLE `churn_data`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `churn_predictions`
--
ALTER TABLE `churn_predictions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `revenue_categories`
--
ALTER TABLE `revenue_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shift_performance`
--
ALTER TABLE `shift_performance`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `traffic_hourly`
--
ALTER TABLE `traffic_hourly`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auth_log`
--
ALTER TABLE `auth_log`
  ADD CONSTRAINT `fk_auth_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `churn_data`
--
ALTER TABLE `churn_data`
  ADD CONSTRAINT `fk_churndata_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `churn_predictions`
--
ALTER TABLE `churn_predictions`
  ADD CONSTRAINT `fk_pred_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `revenue_categories`
--
ALTER TABLE `revenue_categories`
  ADD CONSTRAINT `fk_revcats_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `shift_performance`
--
ALTER TABLE `shift_performance`
  ADD CONSTRAINT `fk_shift_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `traffic_hourly`
--
ALTER TABLE `traffic_hourly`
  ADD CONSTRAINT `fk_traffic_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_usersettings_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
