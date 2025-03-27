-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 27, 2025 at 06:41 AM
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
  `comment_id` int(11) DEFAULT NULL,
  `filename` varchar(50) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by_user_id` int(11) DEFAULT NULL COMMENT 'Regular user who uploaded the file',
  `uploaded_by_staff_member_id` int(11) DEFAULT NULL COMMENT 'Staff member who uploaded the file',
  `uploaded_by_guest_email` varchar(255) DEFAULT NULL COMMENT 'Guest who uploaded the file',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attachments`
--

INSERT INTO `attachments` (`id`, `ticket_id`, `comment_id`, `filename`, `file_path`, `uploaded_by_user_id`, `uploaded_by_staff_member_id`, `uploaded_by_guest_email`, `created_at`) VALUES
(3, 10, NULL, '7764f55a-c55b-47c6-89e0-22cc12afca4f (1).jpg', '../../uploads/tickets/10/1741833253_7764f55a-c55b-47c6-89e0-22cc12afca4f (1).jpg', NULL, 4, NULL, '2025-03-12 18:34:13'),
(10, 15, NULL, 'banana.jpg', '../../uploads/tickets/15/1742191714_banana.jpg', NULL, 4, NULL, '2025-03-16 22:08:34'),
(14, 14, NULL, 'Screenshot (1).png', '../../uploads/tickets/14/1742534927_Screenshot (1).png', NULL, 4, NULL, '2025-03-20 21:28:47'),
(17, 14, 36, 'Screenshot 2024-12-12 030752.png', '../../uploads/tickets/14/1742538044_Screenshot 2024-12-12 030752.png', NULL, 4, NULL, '2025-03-21 06:20:44'),
(18, 16, NULL, '67e4e47bb12f6.png', '../uploads/tickets/16/67e4e47bb12f6.png', 5, NULL, NULL, '2025-03-27 05:39:07');

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
-- Table structure for table `entity_logs`
--

CREATE TABLE `entity_logs` (
  `id` int(11) NOT NULL,
  `entity_type` enum('category','priority','subcategory') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `action` enum('add','update','delete') NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `staff_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `entity_logs`
--

INSERT INTO `entity_logs` (`id`, `entity_type`, `entity_id`, `action`, `old_value`, `new_value`, `staff_id`, `created_at`) VALUES
(1, 'category', 13, 'add', NULL, 'name: testing, description: QWEQWEQWE', 4, '2025-03-21 01:24:33'),
(2, 'category', 13, 'delete', 'name: testing, description: QWEQWEQWE', NULL, 4, '2025-03-21 01:24:52'),
(3, 'priority', 3, 'update', 'name: High, level: 1', 'name: Urgent, level: 1', 4, '2025-03-21 01:29:16'),
(4, 'priority', 3, 'update', 'name: Urgent, level: 1', 'name: High, level: 1', 4, '2025-03-21 05:07:34');

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
(6, 'tenten_belando@outlook.com', '32bffa2ffe263b567194e2a95796bcdc', 'Alessandro', 'Belando', '2025-03-14 00:59:16', '2025-03-06 23:59:16'),
(7, 'tenten_belando@outlook.com', '12a8f46f77b5b1f3c6ba1d967ead980d', 'Alessandro', 'Belando', '2025-03-17 15:25:30', '2025-03-10 14:25:30'),
(8, 'tentenpollo1010@outlook.com', '521df44b3d012e344f93af584dd1fa3b', 'Alessandro', 'Belando', '2025-03-24 06:17:32', '2025-03-17 05:17:32'),
(9, 'tentenpollo1010@outlook.com', '01c282baa1e568a4c4fb5374b3ed908d', 'Alessandro', 'Belando', '2025-03-24 06:30:07', '2025-03-17 05:30:07');

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
(3, 'High', 1, '2025-03-05 06:04:31', '2025-03-21 05:07:34');

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
(4, 'Admin User', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin', '2025-03-06 00:48:56', '2025-03-27 05:41:32'),
(6, 'Zyreel Pacis', '$2y$10$kmCMk0IYabaZNzPA6Jk8TuH.0MyT37uY1n7Cf4a2646GfzPaOTGmu', 'test@outlook.com', 'master_agent', '2025-03-06 01:32:35', '2025-03-06 02:47:04'),
(8, 'Stephanie Mae', '$2y$10$hBxuiZgAFaYezKfX3IVDRu9hgUD.eQuDVWGA3bB4aJ0LuTGCyc/de', 'ackasn@gmail.comm', 'master_agent', '2025-03-12 06:37:21', '2025-03-13 14:26:45');

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
  `status` enum('seen','resolved','pending','open') NOT NULL DEFAULT 'open',
  `created_by` enum('user','guest') NOT NULL COMMENT 'Type of creator (user or guest)',
  `user_id` int(11) DEFAULT NULL COMMENT 'ID of the user who created the ticket (if created_by = "user")',
  `guest_email` varchar(255) DEFAULT NULL COMMENT 'Email of the guest who created the ticket (if created_by = "guest")',
  `category_id` int(11) DEFAULT NULL,
  `priority_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL COMMENT 'Assigned to a staff member (admin/master agent/agent)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `ref_id` varchar(50) DEFAULT NULL
) ;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `title`, `description`, `status`, `created_by`, `user_id`, `guest_email`, `category_id`, `priority_id`, `assigned_to`, `created_at`, `updated_at`, `is_archived`, `ref_id`) VALUES
(9, 'Login Issue', 'Unable to log in to my account.', 'resolved', 'guest', NULL, 'guest1@example.com', 1, 1, 8, '2025-03-05 13:46:52', '2025-03-24 23:59:45', 0, 'REF-101297924981391360'),
(10, 'Payment Failed', 'My payment was deducted, but I did not receive a confirmation.', 'open', 'guest', NULL, 'guest2@example.com', 2, 1, 8, '2025-03-05 13:46:52', '2025-03-25 00:01:22', 0, 'REF-101297924981391361'),
(11, 'Bug in Dashboard', 'The reports section is not loading properly.', 'seen', 'guest', NULL, 'guest3@example.com', 1, 2, 8, '2025-03-05 13:46:52', '2025-03-24 23:59:45', 0, 'REF-101297924981391362'),
(12, 'Account Locked', 'My account got locked after multiple failed login attempts.', 'resolved', 'guest', NULL, 'guest5@example.com', 1, 3, 4, '2025-03-05 13:46:52', '2025-03-24 23:59:45', 0, 'REF-101297924981391363'),
(14, '12312321', '123213123213', 'pending', 'guest', NULL, 'tenten_belando@outlook.com', 2, 2, 4, '2025-03-10 14:25:44', '2025-03-27 05:41:39', 0, 'REF-101297924981391364'),
(15, 'Banana', 'Sample image of a banana', 'pending', 'guest', NULL, 'tentenpollo1010@outlook.com', 3, 3, 4, '2025-03-17 05:18:59', '2025-03-24 23:59:45', 0, 'REF-101297924981391365'),
(16, 'test', '123123', '', 'user', 5, NULL, 2, NULL, NULL, '2025-03-27 05:39:07', '2025-03-27 05:39:07', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_comments`
--

CREATE TABLE `ticket_comments` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('comment','status_change','priority_change','assignment','attachment','archive') NOT NULL,
  `content` text DEFAULT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ticket_comments`
--

INSERT INTO `ticket_comments` (`id`, `ticket_id`, `user_id`, `type`, `content`, `old_value`, `new_value`, `is_internal`, `created_at`) VALUES
(1, 14, 4, 'comment', '<p>test msg</p>', NULL, NULL, 1, '2025-03-17 03:41:31'),
(2, 14, 4, 'comment', '<p>test</p>', NULL, NULL, 0, '2025-03-17 03:43:43'),
(3, 15, 4, 'comment', '<p>HI</p>', NULL, NULL, 0, '2025-03-17 05:22:32'),
(13, 15, 4, 'comment', '<p>test</p>', NULL, NULL, 1, '2025-03-17 06:08:51'),
(14, 15, 4, 'comment', '<ol><li>test</li><li>test2</li></ol>', NULL, NULL, 1, '2025-03-17 07:10:39'),
(36, 14, 4, 'comment', '<p>test</p>', NULL, NULL, 1, '2025-03-21 06:20:44');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_history`
--

CREATE TABLE `ticket_history` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `type` enum('status_change','priority_change','assignment','comment','attachment','attachment_delete','archive','deletion') NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ticket_history`
--

INSERT INTO `ticket_history` (`id`, `ticket_id`, `staff_id`, `type`, `old_value`, `new_value`, `created_at`) VALUES
(1, 14, 4, 'priority_change', 'Medium', 'Medium', '2025-03-21 00:57:08'),
(2, 14, 4, 'attachment', NULL, 'Uploaded file: Screenshot (1).png', '2025-03-21 05:20:53'),
(3, 14, 4, 'attachment', NULL, 'Uploaded file: Screenshot 2024-12-10 204411.png', '2025-03-21 05:22:12'),
(4, 14, 4, 'attachment_delete', NULL, NULL, '2025-03-21 05:28:34'),
(5, 14, 4, 'attachment_delete', NULL, NULL, '2025-03-21 05:28:41'),
(6, 14, 4, 'attachment', NULL, 'Uploaded file: Screenshot (1).png', '2025-03-21 05:28:47'),
(28, 14, 4, 'comment', NULL, '<p>test</p>', '2025-03-21 06:20:44'),
(29, 14, 4, 'status_change', 'open', 'seen', '2025-03-25 00:03:23'),
(30, 14, 4, 'status_change', 'seen', 'pending', '2025-03-25 00:03:30'),
(31, 14, 4, 'status_change', 'pending', 'resolved', '2025-03-25 00:03:35'),
(32, 14, 4, 'status_change', 'resolved', 'open', '2025-03-25 00:03:41'),
(33, 14, 4, 'status_change', 'open', 'seen', '2025-03-27 05:41:37'),
(34, 14, 4, 'status_change', 'seen', 'pending', '2025-03-27 05:41:39');

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
(5, 'abelando', 'Alessandro', 'Belando', '$2y$10$ouqGJL9aDyQXJ4gXf0WmleS6xJGlxXhcd7nWOfwb7rPXnwC1i9Rcq', 'tenten_belando@outlook.com', 0, '2025-03-06 00:29:07', '2025-03-27 05:38:33'),
(7, 'abelando1', 'Alessandro', 'Belando', '$2y$10$n2rxdK/4HDGWYXVikEAsAOXrdfbN.7Cn7KhEPXQKoF83/gnvxUPMe', 'testing123@gmail.com', 123456789, '2025-03-10 06:12:31', '2025-03-10 06:12:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `comment_id` (`comment_id`),
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
-- Indexes for table `entity_logs`
--
ALTER TABLE `entity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

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
-- Indexes for table `ticket_comments`
--
ALTER TABLE `ticket_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `ticket_history`
--
ALTER TABLE `ticket_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `staff_id` (`staff_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `entity_logs`
--
ALTER TABLE `entity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `guest_users`
--
ALTER TABLE `guest_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `priorities`
--
ALTER TABLE `priorities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_members`
--
ALTER TABLE `staff_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `subcategories`
--
ALTER TABLE `subcategories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_comments`
--
ALTER TABLE `ticket_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `ticket_history`
--
ALTER TABLE `ticket_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attachments_ibfk_2` FOREIGN KEY (`comment_id`) REFERENCES `ticket_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attachments_ibfk_3` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `attachments_ibfk_4` FOREIGN KEY (`uploaded_by_staff_member_id`) REFERENCES `staff_members` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `category_subcategories`
--
ALTER TABLE `category_subcategories`
  ADD CONSTRAINT `category_subcategories_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `category_subcategories_ibfk_2` FOREIGN KEY (`subcategory_id`) REFERENCES `subcategories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entity_logs`
--
ALTER TABLE `entity_logs`
  ADD CONSTRAINT `entity_logs_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff_members` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `ticket_comments`
--
ALTER TABLE `ticket_comments`
  ADD CONSTRAINT `ticket_comments_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
  ADD CONSTRAINT `ticket_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `staff_members` (`id`);

--
-- Constraints for table `ticket_history`
--
ALTER TABLE `ticket_history`
  ADD CONSTRAINT `ticket_history_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_history_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff_members` (`id`) ON DELETE CASCADE;

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
