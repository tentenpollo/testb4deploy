-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 10, 2025 at 07:03 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ticketing_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `message_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by_user_id` int(11) DEFAULT NULL COMMENT 'Regular user who uploaded the file',
  `uploaded_by_staff_member_id` int(11) DEFAULT NULL COMMENT 'Staff member who uploaded the file',
  `uploaded_by_guest_email` varchar(255) DEFAULT NULL COMMENT 'Guest who uploaded the file',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Technical Support', 'Hardware and software issues', '2025-03-05 06:04:31', '2025-03-05 06:04:31'),
(2, 'Billing', 'Payment and subscription issues', '2025-03-05 06:04:31', '2025-03-05 06:04:31'),
(3, 'General Inquiry', 'General questions and information', '2025-03-05 06:04:31', '2025-03-05 06:04:31');

-- --------------------------------------------------------

--
-- Table structure for table `category_subcategories`
--

CREATE TABLE `category_subcategories` (
  `category_id` int(11) NOT NULL,
  `subcategory_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category_subcategories`
--

INSERT INTO `category_subcategories` (`category_id`, `subcategory_id`) VALUES
(1, 19),
(1, 20),
(1, 21),
(2, 22),
(2, 23),
(2, 24),
(3, 25),
(3, 26),
(3, 27);

-- --------------------------------------------------------

--
-- Table structure for table `guest_users`
--

CREATE TABLE `guest_users` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guest_users`
--

INSERT INTO `guest_users` (`id`, `email`, `token`, `first_name`, `last_name`, `expires_at`, `created_at`) VALUES
(1, 'tenten_belando@outlook.com', 'f8f1d858c13ceb3bb5f0053714c5382a', 'Alessandro', 'Belando', '2025-03-13 00:46:22', '2025-03-05 23:46:22'),
(4, 'tenten_belando@outlook.com', '70eac396716e876341c84f58c3ad3d5b', 'Alessandro', 'Belando', '2025-03-13 01:07:28', '2025-03-06 00:07:28'),
(5, 'tenten_belando@outlook.com', '95bfa783c6c6d5dc7c8f5c6542d2e813', 'Alessandro', 'Belando', '2025-03-13 01:12:31', '2025-03-06 00:12:31'),
(6, 'tenten_belando@outlook.com', '32bffa2ffe263b567194e2a95796bcdc', 'Alessandro', 'Belando', '2025-03-14 00:59:16', '2025-03-06 23:59:16');

-- --------------------------------------------------------

--
-- Table structure for table `priorities`
--

CREATE TABLE `priorities` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `level` int(11) NOT NULL COMMENT 'Lower number means higher priority',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `priorities`
--

INSERT INTO `priorities` (`id`, `name`, `level`, `created_at`, `updated_at`) VALUES
(1, 'Low', 3, '2025-03-05 06:04:31', '2025-03-05 06:04:31'),
(2, 'Medium', 2, '2025-03-05 06:04:31', '2025-03-05 06:04:31'),
(3, 'High', 1, '2025-03-05 06:04:31', '2025-03-05 06:04:31');

-- --------------------------------------------------------

--
-- Table structure for table `staff_members`
--

CREATE TABLE `staff_members` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('admin','master_agent','agent') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_members`
--

INSERT INTO `staff_members` (`id`, `name`, `password`, `email`, `role`, `created_at`, `updated_at`) VALUES
(4, 'Admin User', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin', '2025-03-06 00:48:56', '2025-03-10 05:14:27'),
(6, 'Zyreel Pacis', '$2y$10$kmCMk0IYabaZNzPA6Jk8TuH.0MyT37uY1n7Cf4a2646GfzPaOTGmu', 'test@outlook.com', 'master_agent', '2025-03-06 01:32:35', '2025-03-06 02:47:04');

-- --------------------------------------------------------

--
-- Table structure for table `subcategories`
--

CREATE TABLE `subcategories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subcategories`
--

INSERT INTO `subcategories` (`id`, `name`, `description`, `category_id`, `created_at`, `updated_at`) VALUES
(19, 'Website Issue', 'Issues related to website functionality', 1, '2025-03-06 00:01:01', '2025-03-06 00:01:01'),
(20, 'Login Problem', 'Problems with user authentication', 1, '2025-03-06 00:01:01', '2025-03-06 00:01:01'),
(21, 'Performance', 'System performance issues', 1, '2025-03-06 00:01:01', '2025-03-06 00:01:01'),
(22, 'Payment Issue', 'Problems with payments processing', 2, '2025-03-06 00:01:01', '2025-03-06 00:01:01'),
(23, 'Refund Request', 'Customer refund inquiries', 2, '2025-03-06 00:01:01', '2025-03-06 00:01:01'),
(24, 'Invoice Problem', 'Issues with billing documents', 2, '2025-03-06 00:01:01', '2025-03-06 00:01:01'),
(25, 'Feedback', 'Customer suggestions and feedback', 3, '2025-03-06 00:01:01', '2025-03-06 00:01:01'),
(26, 'Account Inquiry', 'Questions about user accounts', 3, '2025-03-06 00:01:01', '2025-03-06 00:01:01'),
(27, 'Other', 'Miscellaneous inquiries', 3, '2025-03-06 00:01:01', '2025-03-06 00:01:01');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('unseen','seen','resolved') NOT NULL DEFAULT 'unseen',
  `created_by` enum('user','guest') NOT NULL COMMENT 'Type of creator (user or guest)',
  `user_id` int(11) DEFAULT NULL COMMENT 'ID of the user who created the ticket (if created_by = "user")',
  `guest_email` varchar(255) DEFAULT NULL COMMENT 'Email of the guest who created the ticket (if created_by = "guest")',
  `category_id` int(11) DEFAULT NULL,
  `priority_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Assigned to a staff member (admin/master agent/agent)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `title`, `description`, `status`, `created_by`, `user_id`, `guest_email`, `category_id`, `priority_id`, `assigned_to`, `created_at`, `updated_at`) VALUES
(9, 'Login Issue', 'Unable to log in to my account.', 'unseen', 'guest', NULL, 'guest1@example.com', 2, 3, 4, '2025-03-05 13:46:52', '2025-03-07 01:37:48'),
(10, 'Payment Failed', 'My payment was deducted, but I did not receive a confirmation.', 'seen', 'guest', NULL, 'guest2@example.com', 2, 1, NULL, '2025-03-05 13:46:52', '2025-03-05 13:46:52'),
(11, 'Bug in Dashboard', 'The reports section is not loading properly.', 'unseen', 'guest', NULL, 'guest3@example.com', 1, 1, NULL, '2025-03-05 13:46:52', '2025-03-05 13:48:58'),
(12, 'Account Locked', 'My account got locked after multiple failed login attempts.', 'resolved', 'guest', NULL, 'guest5@example.com', 1, 1, NULL, '2025-03-05 13:46:52', '2025-03-05 13:46:52');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_messages`
--

CREATE TABLE `ticket_messages` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Regular user who sent the message',
  `staff_member_id` int(11) DEFAULT NULL COMMENT 'Staff member who sent the message',
  `guest_email` varchar(255) DEFAULT NULL COMMENT 'Guest who sent the message',
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `first_name`, `last_name`, `password`, `email`, `phone`, `created_at`, `updated_at`) VALUES
(5, 'abelando', 'Alessandro', 'Belando', '$2y$10$ouqGJL9aDyQXJ4gXf0WmleS6xJGlxXhcd7nWOfwb7rPXnwC1i9Rcq', 'tenten_belando@outlook.com', 0, '2025-03-06 00:29:07', '2025-03-06 00:29:07');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `message_id` (`message_id`),
  ADD KEY `uploaded_by_user_id` (`uploaded_by_user_id`),
  ADD KEY `uploaded_by_staff_member_id` (`uploaded_by_staff_member_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `category_subcategories`
--
ALTER TABLE `category_subcategories`
  ADD PRIMARY KEY (`category_id`,`subcategory_id`),
  ADD KEY `subcategory_id` (`subcategory_id`);

--
-- Indexes for table `guest_users`
--
ALTER TABLE `guest_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_unique` (`token`),
  ADD KEY `email_index` (`email`),
  ADD KEY `expires_at_index` (`expires_at`);

--
-- Indexes for table `priorities`
--
ALTER TABLE `priorities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `level` (`level`);

--
-- Indexes for table `staff_members`
--
ALTER TABLE `staff_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`name`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_category` (`name`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `priority_id` (`priority_id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `staff_member_id` (`staff_member_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `guest_users`
--
ALTER TABLE `guest_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `priorities`
--
ALTER TABLE `priorities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_members`
--
ALTER TABLE `staff_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attachments_ibfk_2` FOREIGN KEY (`message_id`) REFERENCES `ticket_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attachments_ibfk_3` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `attachments_ibfk_4` FOREIGN KEY (`uploaded_by_staff_member_id`) REFERENCES `staff_members` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `category_subcategories`
--
ALTER TABLE `category_subcategories`
  ADD CONSTRAINT `category_subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_subcategories_ibfk_2` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subcategories`
--
ALTER TABLE `subcategories`
  ADD CONSTRAINT `subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_3` FOREIGN KEY (`priority_id`) REFERENCES `priorities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_4` FOREIGN KEY (`assigned_to`) REFERENCES `staff_members` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  ADD CONSTRAINT `ticket_messages_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `ticket_messages_ibfk_3` FOREIGN KEY (`staff_member_id`) REFERENCES `staff_members` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
